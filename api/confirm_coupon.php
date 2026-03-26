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

$action = $postData['action'] ?? 'confirm';
$uuid = $postData['uuid'] ?? $_POST['uuid'] ?? null;
$coupon_id = $postData['coupon_id'] ?? $_POST['coupon_id'] ?? null;

$pdo = Database::getInstance()->getConnection();

try {
    if ($action === 'fetch') {
        if (empty($uuid)) {
            sendResponse(false, 400, 'Το UUID είναι υποχρεωτικό για αναζήτηση.');
        }

        $storeFilter = "";
        $params = [':uuid' => $uuid];
        if ($activeRole !== 'admin' && $activeStoreId) {
            $storeFilter = " AND (cam.store_id = :store_id OR cam.store_id IS NULL) ";
            $params[':store_id'] = $activeStoreId;
        }

        $stmt = $pdo->prepare("
            SELECT
                c.id as coupon_id, c.uuid, c.status, c.activated_at, c.confirmed_at,
                cam.title as campaign_title
            FROM coupons c
            JOIN campaigns cam ON c.campaign_id = cam.id
            WHERE c.uuid = :uuid AND cam.deleted_at IS NULL $storeFilter
            ORDER BY c.status ASC
        ");
        $stmt->execute($params);
        $couponsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$couponsList) {
            sendResponse(false, 404, 'Δεν βρέθηκαν προσφορές για αυτόν τον κωδικό που να αφορούν το κατάστημά σας.');
        }

        sendResponse(true, 200, 'Βρέθηκαν προσφορές.', ['coupons' => $couponsList]);
    }

    // Default action: 'confirm'
    if (!$coupon_id) {
        sendResponse(false, 400, 'Το ID κουπονιού είναι υποχρεωτικό.');
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT c.id as coupon_id, c.status, c.uuid, cam.is_active, cam.start_date, cam.end_date, cam.store_id
        FROM coupons c
        JOIN campaigns cam ON c.campaign_id = cam.id
        WHERE c.id = :coupon_id AND cam.deleted_at IS NULL
        FOR UPDATE
    ");
    $stmt->execute([':coupon_id' => $coupon_id]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        $pdo->rollBack();
        sendResponse(false, 404, 'Το κουπόνι δεν βρέθηκε.');
    }

    if ($activeRole !== 'admin' && $activeStoreId) {
        if ($coupon['store_id'] !== null && (int)$coupon['store_id'] !== (int)$activeStoreId) {
            $pdo->rollBack();
            sendResponse(false, 403, 'Δεν έχετε δικαίωμα εξαργύρωσης για αυτό το κουπόνι.');
        }
    }

    $currentTimestamp = date('Y-m-d H:i:s');
    if ((int)$coupon['is_active'] === 0 || $currentTimestamp < $coupon['start_date'] || $currentTimestamp > $coupon['end_date']) {
        $pdo->rollBack();
        sendResponse(false, 403, 'Η καμπάνια για αυτό το κουπόνι δεν είναι ενεργή ή έχει λήξει.');
    }

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

    $updateStmt = $pdo->prepare("UPDATE coupons SET status = 'confirmed', confirmed_at = NOW() WHERE id = :coupon_id");
    $updateStmt->execute([':coupon_id' => $coupon_id]);

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $logStmt = $pdo->prepare("INSERT INTO coupon_events (coupon_id, store_id, user_id, event_type, ip_address, user_agent) VALUES (:coupon_id, :store_id, :user_id, 'confirmed', :ip_address, :user_agent)");
    $logStmt->execute([
        ':coupon_id' => $coupon_id,
        ':store_id'  => $activeStoreId,
        ':user_id'   => $activeUserId,
        ':ip_address'=> $ipAddress,
        ':user_agent'=> $userAgent
    ]);

    $pdo->commit();

    sendResponse(true, 200, 'Επιτυχής εξαργύρωση! Το κουπόνι καταχωρήθηκε.', [
        'coupon_id' => $coupon_id,
        'new_status' => 'confirmed',
        'confirmed_at' => date('c')
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // In production, log the exception securely
    sendResponse(false, 500, 'Προέκυψε ένα αναπάντεχο σφάλμα κατά την εξαργύρωση του κουπονιού. Παρακαλώ δοκιμάστε ξανά. Error: ' . $e->getMessage());
}
