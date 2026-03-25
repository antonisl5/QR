<?php
/**
 * api/confirm_coupon.php
 *
 * Endpoint to handle the confirmation (redemption) of an activated coupon by store staff.
 *
 * Expected POST data:
 * - uuid: The 36-character UUID of the coupon.
 *
 * Returns:
 * JSON response with status, message, and optional data.
 */

declare(strict_types=1);

// Enforce strict JSON response format
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Helper function to send JSON responses and exit.
function sendResponse(bool $success, int $statusCode, string $message, array $data = []): void {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'status_code' => $statusCode,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 405, 'Η μέθοδος αιτήματος δεν επιτρέπεται.');
}

// ---------------------------------------------------------
// 1. MOCK SESSION & AUTHENTICATION
// ---------------------------------------------------------
// TODO: Replace with real JWT/Session Auth verification logic
$_SESSION = [
    'user_id' => 1,          // Mock Staff ID
    'store_id' => 1,         // Mock Store ID where staff works
    'role' => 'store_staff'  // Mock Role
];

$activeUserId = $_SESSION['user_id'] ?? null;
$activeStoreId = $_SESSION['store_id'] ?? null;
$activeRole = $_SESSION['role'] ?? null;

if (!$activeUserId || !$activeStoreId || !in_array($activeRole, ['admin', 'store_staff'])) {
    sendResponse(false, 401, 'Μη εξουσιοδοτημένη πρόσβαση.');
}

// ---------------------------------------------------------
// 2. INPUT VALIDATION
// ---------------------------------------------------------

// Retrieve JSON payload
$rawInput = file_get_contents('php://input');
$postData = json_decode($rawInput, true);

// Fallback to standard $_POST if not sent as application/json
$uuid = $postData['uuid'] ?? $_POST['uuid'] ?? null;

// Validate the UUID format (strict 36 characters, standard format)
if (empty($uuid) || !is_string($uuid) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
    sendResponse(false, 400, 'Μη έγκυρη μορφή κωδικού (UUID).');
}

// ---------------------------------------------------------
// 3. DATABASE CONNECTION
// ---------------------------------------------------------

try {
    $dbHost = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $dbName = $_ENV['DB_NAME'] ?? 'qr_coupons';
    $dbUser = $_ENV['DB_USER'] ?? 'root';
    $dbPass = $_ENV['DB_PASS'] ?? '';

    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    sendResponse(false, 500, 'Σφάλμα σύνδεσης με τη βάση δεδομένων.');
}

// ---------------------------------------------------------
// 4. TRANSACTION & STATE MACHINE LOGIC
// ---------------------------------------------------------

try {
    $pdo->beginTransaction();

    // Fetch the coupon and lock the row using FOR UPDATE
    // This prevents double redemptions if the request is sent twice instantly
    $stmt = $pdo->prepare("
        SELECT
            c.id AS coupon_id,
            c.status,
            cam.id AS campaign_id,
            cam.is_active,
            cam.start_date,
            cam.end_date,
            u.store_id AS campaign_store_id
        FROM coupons c
        JOIN campaigns cam ON c.campaign_id = cam.id
        LEFT JOIN users u ON cam.user_id = u.id
        WHERE c.uuid = :uuid
        FOR UPDATE
    ");
    $stmt->execute([':uuid' => $uuid]);
    $coupon = $stmt->fetch();

    // Check if the coupon exists
    if (!$coupon) {
        $pdo->rollBack();
        sendResponse(false, 404, 'Το κουπόνι δεν βρέθηκε.');
    }

    // Security Check: Does this coupon belong to the staff member's store?
    // (Assuming the campaign owner is linked to the same store_id as the staff)
    // If the role is admin, bypass this check.
    if ($activeRole !== 'admin' && (int)$coupon['campaign_store_id'] !== (int)$activeStoreId) {
        $pdo->rollBack();
        sendResponse(false, 403, 'Δεν έχετε δικαίωμα να εξαργυρώσετε κουπόνια από άλλο κατάστημα.');
    }

    // Check Campaign Validity
    $currentTimestamp = date('Y-m-d H:i:s');
    if (
        (int)$coupon['is_active'] === 0 ||
        $currentTimestamp < $coupon['start_date'] ||
        $currentTimestamp > $coupon['end_date']
    ) {
        $pdo->rollBack();
        sendResponse(false, 403, 'Η καμπάνια για αυτό το κουπόνι δεν είναι ενεργή ή έχει λήξει.');
    }

    // Evaluate Coupon State Machine
    if ($coupon['status'] === 'idle') {
        $pdo->rollBack();
        sendResponse(false, 400, 'Το κουπόνι δεν έχει ενεργοποιηθεί από τον πελάτη ακόμη.');
    }

    if ($coupon['status'] === 'confirmed') {
        $pdo->rollBack();
        sendResponse(false, 403, 'Το κουπόνι έχει ήδη εξαργυρωθεί.');
    }

    if ($coupon['status'] !== 'activated') {
        $pdo->rollBack();
        sendResponse(false, 500, 'Άγνωστη κατάσταση κουπονιού.');
    }

    // Status is strictly 'activated'. Proceed to confirm/redeem.
    $updateStmt = $pdo->prepare("
        UPDATE coupons
        SET status = 'confirmed', confirmed_at = NOW()
        WHERE id = :coupon_id
    ");
    $updateStmt->execute([':coupon_id' => $coupon['coupon_id']]);

    // Insert Audit Log into coupon_events
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $logStmt = $pdo->prepare("
        INSERT INTO coupon_events (coupon_id, store_id, user_id, event_type, ip_address, user_agent)
        VALUES (:coupon_id, :store_id, :user_id, 'confirmed', :ip_address, :user_agent)
    ");
    $logStmt->execute([
        ':coupon_id' => $coupon['coupon_id'],
        ':store_id'  => $activeStoreId,
        ':user_id'   => $activeUserId,
        ':ip_address'=> $ipAddress,
        ':user_agent'=> $userAgent
    ]);

    // Commit the transaction
    $pdo->commit();

    // Success Response
    sendResponse(true, 200, 'Επιτυχής εξαργύρωση! Το κουπόνι καταχωρήθηκε.', [
        'uuid' => $uuid,
        'new_status' => 'confirmed',
        'confirmed_at' => date('c') // ISO 8601 string
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // In production, log the exception securely
    sendResponse(false, 500, 'Προέκυψε ένα αναπάντεχο σφάλμα κατά την εξαργύρωση του κουπονιού. Παρακαλώ δοκιμάστε ξανά. Error: ' . $e->getMessage());
}
