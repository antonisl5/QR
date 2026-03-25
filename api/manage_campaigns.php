<?php
/**
 * api/manage_campaigns.php
 *
 * CRUD API for managing campaigns in the QR Coupon Platform.
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
// 1. MOCK SESSION & AUTHENTICATION
// ---------------------------------------------------------
// TODO: Replace with real JWT/Session Auth
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

$activeUserId = $_SESSION['user_id'] ?? null;
$activeRole = $_SESSION['role'] ?? null;

if (!$activeUserId || !in_array($activeRole, ['admin', 'campaign_owner'])) {
    sendResponse(false, 401, 'Μη εξουσιοδοτημένη πρόσβαση.');
}

// ---------------------------------------------------------
// 2. DATABASE CONNECTION
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

// Determine request method
$method = $_SERVER['REQUEST_METHOD'];

// Helper to get raw JSON payload
function getJsonPayload(): array {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    return is_array($data) ? $data : [];
}

try {
    switch ($method) {
        // ---------------------------------------------------------
        // GET: FETCH CAMPAIGNS
        // ---------------------------------------------------------
        case 'GET':
            $stmt = $pdo->prepare("
                SELECT id, title, description, start_date, end_date, is_active, created_at
                FROM campaigns
                WHERE deleted_at IS NULL AND user_id = :user_id
                ORDER BY created_at DESC
            ");
            // If the user is an admin, they might see all campaigns, but for now we scope to the user
            // To see all, remove "AND user_id = :user_id" if role is admin.
            $stmt->execute([':user_id' => $activeUserId]);
            $campaigns = $stmt->fetchAll();
            sendResponse(true, 200, 'Καμπάνιες ανακτήθηκαν επιτυχώς.', $campaigns);
            break;

        // ---------------------------------------------------------
        // POST: CREATE CAMPAIGN
        // ---------------------------------------------------------
        case 'POST':
            $data = getJsonPayload();

            $title = trim($data['title'] ?? '');
            $description = trim($data['description'] ?? '');
            $start_date = trim($data['start_date'] ?? '');
            $end_date = trim($data['end_date'] ?? '');
            $is_active = isset($data['is_active']) ? (int)$data['is_active'] : 1;

            if (empty($title) || empty($start_date) || empty($end_date)) {
                sendResponse(false, 400, 'Ο τίτλος και οι ημερομηνίες είναι υποχρεωτικά πεδία.');
            }

            if (strtotime($end_date) <= strtotime($start_date)) {
                sendResponse(false, 400, 'Η ημερομηνία λήξης πρέπει να είναι μεταγενέστερη της έναρξης.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO campaigns (user_id, title, description, start_date, end_date, is_active)
                VALUES (:user_id, :title, :description, :start_date, :end_date, :is_active)
            ");

            $stmt->execute([
                ':user_id' => $activeUserId,
                ':title' => $title,
                ':description' => $description,
                ':start_date' => $start_date,
                ':end_date' => $end_date,
                ':is_active' => $is_active
            ]);

            sendResponse(true, 201, 'Η καμπάνια δημιουργήθηκε επιτυχώς.', ['id' => $pdo->lastInsertId()]);
            break;

        // ---------------------------------------------------------
        // PUT: UPDATE CAMPAIGN
        // ---------------------------------------------------------
        case 'PUT':
            $data = getJsonPayload();

            $id = (int)($data['id'] ?? 0);
            $title = trim($data['title'] ?? '');
            $description = trim($data['description'] ?? '');
            $start_date = trim($data['start_date'] ?? '');
            $end_date = trim($data['end_date'] ?? '');
            $is_active = isset($data['is_active']) ? (int)$data['is_active'] : 1;

            if ($id <= 0 || empty($title) || empty($start_date) || empty($end_date)) {
                sendResponse(false, 400, 'Λείπουν υποχρεωτικά πεδία.');
            }

            if (strtotime($end_date) <= strtotime($start_date)) {
                sendResponse(false, 400, 'Η ημερομηνία λήξης πρέπει να είναι μεταγενέστερη της έναρξης.');
            }

            // Ensure ownership before updating
            $checkStmt = $pdo->prepare("SELECT id FROM campaigns WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL");
            $checkStmt->execute([':id' => $id, ':user_id' => $activeUserId]);
            if (!$checkStmt->fetch()) {
                sendResponse(false, 404, 'Η καμπάνια δεν βρέθηκε ή δεν έχετε δικαίωμα επεξεργασίας.');
            }

            $stmt = $pdo->prepare("
                UPDATE campaigns
                SET title = :title, description = :description, start_date = :start_date,
                    end_date = :end_date, is_active = :is_active
                WHERE id = :id
            ");

            $stmt->execute([
                ':title' => $title,
                ':description' => $description,
                ':start_date' => $start_date,
                ':end_date' => $end_date,
                ':is_active' => $is_active,
                ':id' => $id
            ]);

            sendResponse(true, 200, 'Η καμπάνια ενημερώθηκε επιτυχώς.');
            break;

        // ---------------------------------------------------------
        // DELETE: SOFT DELETE CAMPAIGN
        // ---------------------------------------------------------
        case 'DELETE':
            $data = getJsonPayload();
            $id = (int)($data['id'] ?? 0);

            if ($id <= 0) {
                sendResponse(false, 400, 'Μη έγκυρο ID καμπάνιας.');
            }

            // Ensure ownership before deleting
            $checkStmt = $pdo->prepare("SELECT id FROM campaigns WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL");
            $checkStmt->execute([':id' => $id, ':user_id' => $activeUserId]);
            if (!$checkStmt->fetch()) {
                sendResponse(false, 404, 'Η καμπάνια δεν βρέθηκε ή δεν έχετε δικαίωμα διαγραφής.');
            }

            // Soft delete by setting deleted_at
            $stmt = $pdo->prepare("UPDATE campaigns SET deleted_at = NOW(), is_active = 0 WHERE id = :id");
            $stmt->execute([':id' => $id]);

            sendResponse(true, 200, 'Η καμπάνια διαγράφηκε επιτυχώς.');
            break;

        default:
            sendResponse(false, 405, 'Η μέθοδος αιτήματος δεν υποστηρίζεται.');
            break;
    }

} catch (Exception $e) {
    // In production, log the exception securely
    sendResponse(false, 500, 'Προέκυψε ένα αναπάντεχο σφάλμα. ' . $e->getMessage());
}
