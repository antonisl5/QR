<?php

require_once '../includes/auth_guard.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Get JSON POST body
$raw_data = file_get_contents("php://input");
$data = json_decode($raw_data, true);

// Validate input
if (!isset($data['email']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Email and password are required']);
    exit;
}

$email = trim($data['email']);
$password = $data['password'];

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Email and password cannot be empty']);
    exit;
}

try {
    // Initialize Database Connection
    $dbHost = getenv('DB_HOST') ?: '127.0.0.1';
    $dbName = getenv('DB_NAME') ?: 'qr_coupons';
    $dbUser = getenv('DB_USER') ?: 'root';
    $dbPass = getenv('DB_PASS') ?: '';

    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // Enforce real prepared statements
    ]);

    // Find user by email, ensure they are active
    $stmt = $pdo->prepare('SELECT id, password_hash, role, store_id FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Use generic error message to prevent email enumeration
        http_response_code(401);
        echo json_encode(['error' => 'Invalid email or password']);
        exit;
    }

    // Verify password
    if (password_verify($password, $user['password_hash'])) {
        // Password is correct, generate JWT
        $payload = [
            'user_id' => $user['id'],
            'role' => $user['role'],
            'store_id' => $user['store_id'],
            'email' => $email
        ];

        $jwt = generate_jwt($payload);

        // Set the JWT in an HttpOnly cookie for security
        set_auth_cookie($jwt);

        // Return success response with user info and redirect path based on role
        $redirect_url = '/admin/dashboard.php';
        if ($user['role'] === 'store_staff') {
            $redirect_url = '/store/confirm.php';
        }

        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'email' => $email,
                'role' => $user['role'],
                'store_id' => $user['store_id']
            ],
            'redirect' => $redirect_url,
            // We can optionally return the token, but frontend shouldn't store it in localStorage
            // The cookie handles the session automatically.
            'token' => $jwt
        ]);

    } else {
        // Invalid password
        http_response_code(401);
        echo json_encode(['error' => 'Invalid email or password']);
    }

} catch (PDOException $e) {
    error_log('Database error in login: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An internal server error occurred']);
}
