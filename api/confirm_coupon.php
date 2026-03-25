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

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';

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
// 1. AUTHENTICATION (JWT)
// ---------------------------------------------------------
// Require valid JWT and explicitly allow store_staff and admin
$user = require_role(['store_staff', 'admin']);

$activeUserId = $user['user_id'] ?? null;
$activeStoreId = $user['store_id'] ?? null;
$activeRole = $user['role'] ?? null;

// Admins don't have a store_id, so we only strictly require it for store_staff
if (!$activeUserId || ($activeRole === 'store_staff' && !$activeStoreId)) {
    sendResponse(false, 401, 'Μη έγκυρα στοιχεία χρήστη ή καταστήματος.');
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

// Initialize Database Connection using the Singleton pattern
$pdo = Database::getInstance()->getConnection();

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
            cam.end_date
        FROM coupons c
        JOIN campaigns cam ON c.campaign_id = cam.id
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
