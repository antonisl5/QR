<?php
/**
 * api/manage_stores.php
 *
 * CRUD API for managing stores in the QR Coupon Platform.
 * Supports GET (list), POST (create), PUT (update), and DELETE (soft delete).
 * Handles JSON payloads for consistent RESTful operations.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

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

// ---------------------------------------------------------
require_once '../includes/auth_guard.php';
$user = require_role(['admin']);
$activeUserId = $user['user_id'] ?? $user['id'];
$activeRole = $user['role'];


if (!$activeUserId || $activeRole !== 'admin') {
    sendResponse(false, 401, 'Μη εξουσιοδοτημένη πρόσβαση. Απαιτούνται δικαιώματα διαχειριστή.');
}

// ---------------------------------------------------------
// 2. DATABASE CONNECTION
// ---------------------------------------------------------
try {
    require_once '../includes/db.php';
require_once '../includes/file_uploader.php';
    $pdo = Database::getInstance()->getConnection();
} catch (PDOException $e) {
    sendResponse(false, 500, 'Σφάλμα σύνδεσης με τη βάση δεδομένων.');
}

// Determine request method
$method = $_SERVER['REQUEST_METHOD'];

// Add file uploader
require_once '../includes/file_uploader.php';

// Helper to get raw JSON payload
function getJsonPayload(): array {
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'POST') {
        if (isset($_POST['_method'])) {
            $override = strtoupper($_POST['_method']);
            if ($override === 'PUT' || $override === 'DELETE') {
                $_SERVER['REQUEST_METHOD'] = $override;
            }
        }
        return $_POST;
    } else if ($method === 'PUT' || $method === 'DELETE') {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        return is_array($data) ? $data : [];
    }
    return [];
}

// Ensure method override is applied to global $method variable
$payload = getJsonPayload();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        // ---------------------------------------------------------
        // GET: FETCH STORES
        // ---------------------------------------------------------
        case 'GET':
            $stmt = $pdo->prepare("
                SELECT id, name, location, logo_path, created_at
                FROM stores
                WHERE deleted_at IS NULL
                ORDER BY created_at DESC
            ");
            $stmt->execute();
            $stores = $stmt->fetchAll();
            sendResponse(true, 200, 'Καταστήματα ανακτήθηκαν επιτυχώς.', $stores);
            break;

        // ---------------------------------------------------------
        // POST: CREATE STORE
        // ---------------------------------------------------------
        case 'POST':
            $data = getJsonPayload();
            $name = trim($data['name'] ?? '');
            $location = trim($data['location'] ?? '');
            $logo_path = null;

            if (empty($name)) {
                sendResponse(false, 400, 'Το όνομα του καταστήματος είναι υποχρεωτικό.');
            }

            if (isset($_FILES['store_logo']) && $_FILES['store_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploader = new FileUploader();
                $uploadResult = $uploader->upload($_FILES['store_logo'], 'logos');
                if (!$uploadResult['success']) {
                    sendResponse(false, 400, $uploadResult['error']);
                }
                $logo_path = $uploadResult['path'];
            }

            $stmt = $pdo->prepare("
                INSERT INTO stores (name, location, logo_path)
                VALUES (:name, :location, :logo_path)
            ");

            $stmt->execute([
                ':name' => $name,
                ':location' => $location,
                ':logo_path' => $logo_path
            ]);

            sendResponse(true, 201, 'Το κατάστημα δημιουργήθηκε επιτυχώς.', ['id' => $pdo->lastInsertId()]);
            break;

        // ---------------------------------------------------------
        // PUT: UPDATE STORE
        // ---------------------------------------------------------
        case 'PUT':
            $data = getJsonPayload();

            $id = (int)($data['id'] ?? 0);
            $name = trim($data['name'] ?? '');
            $location = trim($data['location'] ?? '');

            if ($id <= 0 || empty($name)) {
                sendResponse(false, 400, 'Το ID και το όνομα του καταστήματος είναι υποχρεωτικά.');
            }

            $checkStmt = $pdo->prepare("SELECT id FROM stores WHERE id = :id AND deleted_at IS NULL");
            $checkStmt->execute([':id' => $id]);
            if (!$checkStmt->fetch()) {
                sendResponse(false, 404, 'Το κατάστημα δεν βρέθηκε.');
            }

            $stmt = $pdo->prepare("
                UPDATE stores
                SET name = :name, location = :location
                WHERE id = :id
            ");

            $stmt->execute([
                ':name' => $name,
                ':location' => $location,
                ':id' => $id
            ]);

            sendResponse(true, 200, 'Το κατάστημα ενημερώθηκε επιτυχώς.');
            break;

        // ---------------------------------------------------------
        // DELETE: SOFT DELETE STORE
        // ---------------------------------------------------------
        case 'DELETE':
            $data = getJsonPayload();
            $id = (int)($data['id'] ?? 0);

            if ($id <= 0) {
                sendResponse(false, 400, 'Μη έγκυρο ID καταστήματος.');
            }

            $checkStmt = $pdo->prepare("SELECT id, logo_path FROM stores WHERE id = :id AND deleted_at IS NULL");
            $checkStmt->execute([':id' => $id]);
            $store = $checkStmt->fetch();
            if (!$store) {
                sendResponse(false, 404, 'Το κατάστημα δεν βρέθηκε.');
            }

            if ($store['logo_path']) {
                $uploader = new FileUploader();
                $uploader->deleteFile($store['logo_path']);
            }

            // Soft delete by setting deleted_at
            $stmt = $pdo->prepare("UPDATE stores SET deleted_at = NOW(), logo_path = NULL WHERE id = :id");
            $stmt->execute([':id' => $id]);

            sendResponse(true, 200, 'Το κατάστημα διαγράφηκε επιτυχώς.');
            break;

        default:
            sendResponse(false, 405, 'Η μέθοδος αιτήματος δεν υποστηρίζεται.');
            break;
    }

} catch (Exception $e) {
    // In production, log the exception securely
    sendResponse(false, 500, 'Προέκυψε ένα αναπάντεχο σφάλμα. ' . $e->getMessage());
}
