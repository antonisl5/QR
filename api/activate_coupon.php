<?php
/**
 * api/activate_coupon.php
 *
 * Endpoint to handle the activation of a QR coupon by a customer.
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

/**
 * Helper function to send JSON responses and exit.
 */
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

// Retrieve JSON payload (assuming modern fetch/axios POST)
$rawInput = file_get_contents('php://input');
$postData = json_decode($rawInput, true);

// Fallback to standard $_POST if not sent as application/json
$uuid = $postData['uuid'] ?? $_POST['uuid'] ?? null;

// Validate the UUID format (strict 36 characters, standard format)
if (empty($uuid) || !is_string($uuid) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
    sendResponse(false, 400, 'Μη έγκυρη μορφή κωδικού (UUID).');
}

// Initialize Database Connection (Stubbed logic based on standard PDO setup)
try {
    // Assuming a configuration helper or standard .env loader is available in production
    $dbHost = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $dbName = $_ENV['DB_NAME'] ?? 'qr_coupons';
    $dbUser = $_ENV['DB_USER'] ?? 'root';
    $dbPass = $_ENV['DB_PASS'] ?? '';

    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // Enforce real prepared statements
    ]);
} catch (PDOException $e) {
    // In production, log $e->getMessage() to a secure log file, never expose to frontend
    sendResponse(false, 500, 'Σφάλμα σύνδεσης με τη βάση δεδομένων.');
}

// Start Transaction to handle race conditions and ensure data integrity
try {
    $pdo->beginTransaction();

    // Fetch the coupon and lock the row using FOR UPDATE
    // This prevents concurrent requests from activating the same idle coupon simultaneously
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
    if ($coupon['status'] === 'activated') {
        $pdo->rollBack();
        sendResponse(false, 400, 'Το κουπόνι είναι ήδη ενεργοποιημένο και εκκρεμεί η εξαργύρωσή του.');
    }

    if ($coupon['status'] === 'confirmed') {
        $pdo->rollBack();
        sendResponse(false, 403, 'Το κουπόνι έχει ήδη εξαργυρωθεί και δεν είναι πλέον έγκυρο.');
    }

    // If status is not 'idle' by this point, something is wrong with the state machine
    if ($coupon['status'] !== 'idle') {
        $pdo->rollBack();
        sendResponse(false, 500, 'Άγνωστη κατάσταση κουπονιού.');
    }

    // Status is 'idle'. Proceed to activate.
    $updateStmt = $pdo->prepare("
        UPDATE coupons
        SET status = 'activated', activated_at = NOW()
        WHERE id = :coupon_id
    ");
    $updateStmt->execute([':coupon_id' => $coupon['coupon_id']]);

    // Insert Audit Log into coupon_events
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $logStmt = $pdo->prepare("
        INSERT INTO coupon_events (coupon_id, event_type, ip_address, user_agent)
        VALUES (:coupon_id, 'activated', :ip_address, :user_agent)
    ");
    $logStmt->execute([
        ':coupon_id' => $coupon['coupon_id'],
        ':ip_address' => $ipAddress,
        ':user_agent' => $userAgent
    ]);

    // Commit the transaction
    $pdo->commit();

    // Success Response
    sendResponse(true, 200, 'Το κουπόνι ενεργοποιήθηκε επιτυχώς! Μπορείτε πλέον να το δείξετε στο ταμείο.', [
        'uuid' => $uuid,
        'new_status' => 'activated',
        'activated_at' => date('c') // ISO 8601 string
    ]);

} catch (Exception $e) {
    // Rollback any changes if an error occurred during the transaction
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // In production, log the exception message securely
    sendResponse(false, 500, 'Προέκυψε ένα αναπάντεχο σφάλμα κατά την ενεργοποίηση του κουπονιού. Παρακαλώ δοκιμάστε ξανά.');
}
