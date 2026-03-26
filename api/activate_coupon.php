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

require_once __DIR__ . '/../includes/db.php';

/**
 * Helper function to send JSON responses and exit.
 */
function sendResponse(bool $success, int $statusCode, string $message, array $data = []) {
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

// We now expect coupon_id instead of uuid
$coupon_id = $postData['coupon_id'] ?? $_POST['coupon_id'] ?? null;

if (empty($coupon_id) || !is_numeric($coupon_id)) {
    sendResponse(false, 400, 'Μη έγκυρο ID κουπονιού.');
}

try {
    $pdo = Database::getInstance()->getConnection();

    // Begin a transaction to prevent race conditions
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT c.id as coupon_id, c.status, c.uuid, cam.is_active, cam.start_date, cam.end_date
        FROM coupons c
        JOIN campaigns cam ON c.campaign_id = cam.id
        WHERE c.id = :id
        FOR UPDATE
    ");
    $stmt->execute([':id' => $coupon_id]);
    $coupon = $stmt->fetch();

    if (!$coupon) {
        $pdo->rollBack();
        sendResponse(false, 404, 'Το κουπόνι δεν βρέθηκε.');
    }

    // 2. Validate Campaign Date and Status
    $currentTimestamp = date('Y-m-d H:i:s');
    if ((int)$coupon['is_active'] === 0 || $currentTimestamp < $coupon['start_date'] || $currentTimestamp > $coupon['end_date']) {
        $pdo->rollBack();
        sendResponse(false, 403, 'Η προσφορά έχει λήξει ή δεν είναι ενεργή.');
    }

    // 3. State Machine Check: Only allow transition from 'idle' to 'activated'
    if ($coupon['status'] === 'activated') {
        $pdo->rollBack();
        sendResponse(true, 200, 'Το κουπόνι είναι ήδη ενεργοποιημένο.');
    }

    if ($coupon['status'] === 'confirmed') {
        $pdo->rollBack();
        sendResponse(false, 403, 'Το κουπόνι έχει ήδη εξαργυρωθεί.');
    }

    if ($coupon['status'] !== 'idle') {
        $pdo->rollBack();
        sendResponse(false, 400, 'Μη έγκυρη κατάσταση κουπονιού.');
    }

    $updateStmt = $pdo->prepare("UPDATE coupons SET status = 'activated', activated_at = CURRENT_TIMESTAMP WHERE id = :id");
    $updateStmt->execute([':id' => $coupon_id]);

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

    $eventStmt = $pdo->prepare("INSERT INTO coupon_events (coupon_id, event_type, ip_address, user_agent) VALUES (:coupon_id, 'activated', :ip_address, :user_agent)");
    $eventStmt->execute([
        ':coupon_id'  => $coupon_id,
        ':ip_address' => $ip_address,
        ':user_agent' => $user_agent
    ]);

    $pdo->commit();

    sendResponse(true, 200, 'Το κουπόνι ενεργοποιήθηκε επιτυχώς! Δείξτε την οθόνη σας στο ταμείο.', [
        'coupon_id' => $coupon_id,
        'uuid' => $coupon['uuid']
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database Error in activate_coupon.php: " . $e->getMessage());
    sendResponse(false, 500, 'Παρουσιάστηκε σφάλμα κατά την ενεργοποίηση.');
}
    // In production, log the exception message securely
    sendResponse(false, 500, 'Προέκυψε ένα αναπάντεχο σφάλμα κατά την ενεργοποίηση του κουπονιού. Παρακαλώ δοκιμάστε ξανά.');
}
