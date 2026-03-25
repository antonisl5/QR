<?php
/**
 * api/manage_users.php
 * Secure RESTful CRUD endpoint for User & Role Management.
 * Implements strict JSON requests, PDO statements, password hashing,
 * soft deactivation via is_active, and store_id association logic.
 */

// Define strict JSON headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Helper function for sending JSON responses and exiting
function sendResponse($statusCode, $success, $message, $data = null) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'status_code' => $statusCode,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

require_once '../includes/auth_guard.php';
$user = require_role(['admin']);


// ----------------------------------------------------------------------------
// DATABASE CONNECTION (PDO)
// ----------------------------------------------------------------------------
try {
    require_once '../includes/db.php';
    $pdo = Database::getInstance()->getConnection();
} catch (PDOException $e) {
    // In production, log $e->getMessage() securely.
    sendResponse(500, false, 'Database connection failed.');
}

// ----------------------------------------------------------------------------
// REQUEST ROUTING
// ----------------------------------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'];

// Parse incoming JSON payloads (Strictly requires JSON from frontend)
$rawInput = file_get_contents("php://input");
$payload = json_decode($rawInput, true) ?: [];

switch ($method) {
    case 'GET':
        handleGetUsers($pdo);
        break;
    case 'POST':
        handleCreateUser($pdo, $payload);
        break;
    case 'PUT':
        handleUpdateUser($pdo, $payload);
        break;
    case 'DELETE':
        handleDeactivateUser($pdo, $payload);
        break;
    default:
        sendResponse(405, false, 'Method Not Allowed.');
}

// ----------------------------------------------------------------------------
// CONTROLLERS
// ----------------------------------------------------------------------------

/**
 * Handle GET Request - List all users with their linked store name.
 */
function handleGetUsers(PDO $pdo) {
    try {
        // We use LEFT JOIN so we still retrieve admins/campaign_owners who lack a store
        $stmt = $pdo->prepare("
            SELECT
                u.id,
                u.username,
                u.email,
                u.role,
                u.store_id,
                u.is_active,
                s.name as store_name,
                u.created_at
            FROM users u
            LEFT JOIN stores s ON u.store_id = s.id
            ORDER BY u.created_at DESC
        ");
        $stmt->execute();
        $users = $stmt->fetchAll();

        sendResponse(200, true, 'Users retrieved successfully.', $users);
    } catch (PDOException $e) {
        sendResponse(500, false, 'Failed to retrieve users: ' . $e->getMessage());
    }
}

/**
 * Handle POST Request - Create a new user.
 */
function handleCreateUser(PDO $pdo, array $payload) {
    // 1. Validation
    if (empty($payload['username']) || empty($payload['email']) || empty($payload['password']) || empty($payload['role'])) {
        sendResponse(400, false, 'Username, Email, Password, and Role are required.');
    }

    $username = trim($payload['username']);
    $email = trim($payload['email']);
    $password = $payload['password'];
    $role = $payload['role'];
    $store_id = !empty($payload['store_id']) ? (int)$payload['store_id'] : null;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(400, false, 'Invalid email format.');
    }

    if (strlen($password) < 8) {
        sendResponse(400, false, 'Password must be at least 8 characters long.');
    }

    $validRoles = ['admin', 'campaign_owner', 'store_staff'];
    if (!in_array($role, $validRoles)) {
        sendResponse(400, false, 'Invalid role selected.');
    }

    // Role-Store constraint check
    if ($role === 'store_staff' && empty($store_id)) {
        sendResponse(400, false, 'A linked Store is strictly required for Store Staff.');
    }
    if ($role !== 'store_staff') {
        $store_id = null; // Enforce: Admins and Owners don\'t have a store
    }

    try {
        $pdo->beginTransaction();

        // 2. Prevent duplicates (Username & Email)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            sendResponse(409, false, 'Username or Email already exists.');
        }

        // 3. Hash Password securely using BCRYPT
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        // 4. Insert User
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password_hash, role, store_id, is_active)
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$username, $email, $passwordHash, $role, $store_id]);

        $newUserId = $pdo->lastInsertId();

        $pdo->commit();
        sendResponse(201, true, 'User created successfully.', ['id' => $newUserId]);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendResponse(500, false, 'Failed to create user. Please check database logs.');
    }
}

/**
 * Handle PUT Request - Update user info (or reset password).
 */
function handleUpdateUser(PDO $pdo, array $payload) {
    if (empty($payload['id'])) {
        sendResponse(400, false, 'User ID is required for updating.');
    }

    $id = (int)$payload['id'];
    $username = isset($payload['username']) ? trim($payload['username']) : null;
    $email = isset($payload['email']) ? trim($payload['email']) : null;
    $role = isset($payload['role']) ? $payload['role'] : null;
    $password = !empty($payload['password']) ? $payload['password'] : null;
    $is_active = isset($payload['is_active']) ? (int)$payload['is_active'] : null;

    // Store ID parsing
    $store_id = null;
    if (isset($payload['store_id']) && $payload['store_id'] !== '') {
        $store_id = (int)$payload['store_id'];
    }

    // Role constraint verification
    if ($role === 'store_staff' && empty($store_id)) {
        sendResponse(400, false, 'A linked Store is required when role is Store Staff.');
    }
    if ($role !== 'store_staff' && $role !== null) {
        $store_id = null;
    }

    try {
        $pdo->beginTransaction();

        // Ensure user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
             $pdo->rollBack();
             sendResponse(404, false, 'User not found.');
        }

        // Duplicate checks for email/username (excluding current user ID)
        if ($username || $email) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ? LIMIT 1");
            $stmt->execute([$username, $email, $id]);
            if ($stmt->fetch()) {
                $pdo->rollBack();
                sendResponse(409, false, 'The requested Username or Email is already taken by another account.');
            }
        }

        // Build dynamic update query
        $fields = [];
        $params = [];

        if ($username !== null) { $fields[] = "username = ?"; $params[] = $username; }
        if ($email !== null) { $fields[] = "email = ?"; $params[] = $email; }
        if ($role !== null) { $fields[] = "role = ?"; $params[] = $role; }
        $fields[] = "store_id = ?"; $params[] = $store_id; // Always update store_id based on rules
        if ($is_active !== null) { $fields[] = "is_active = ?"; $params[] = $is_active; }

        if ($password !== null) {
            if (strlen($password) < 8) {
                $pdo->rollBack();
                sendResponse(400, false, 'Password must be at least 8 characters long.');
            }
            $fields[] = "password_hash = ?";
            $params[] = password_hash($password, PASSWORD_BCRYPT);
        }

        if (empty($fields)) {
            $pdo->rollBack();
            sendResponse(400, false, 'No data provided to update.');
        }

        $params[] = $id; // For WHERE clause
        $sql = "UPDATE users SET " . implode(", ", $fields) . ", updated_at = NOW() WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $pdo->commit();
        sendResponse(200, true, 'User updated successfully.');

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendResponse(500, false, 'Failed to update user: ' . $e->getMessage());
    }
}

/**
 * Handle DELETE Request - Soft-deactivate a user.
 */
function handleDeactivateUser(PDO $pdo, array $payload) {
    if (empty($payload['id'])) {
        sendResponse(400, false, 'User ID is required for deactivation.');
    }

    $id = (int)$payload['id'];

    // Safety check to prevent deactivating oneself
    if ($id === $user['user_id']) {
        sendResponse(403, false, 'You cannot deactivate your own administrative account.');
    }

    try {
        // Soft delete via is_active = 0
        $stmt = $pdo->prepare("UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            sendResponse(404, false, 'User not found or already deactivated.');
        }

        sendResponse(200, true, 'User account securely deactivated.');
    } catch (PDOException $e) {
        sendResponse(500, false, 'Failed to deactivate user.');
    }
}
