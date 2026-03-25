<?php

// Custom pure-PHP JWT Implementation & Auth Guard
// STRICTLY NO FRAMEWORKS

require_once __DIR__ . '/config.php';

/**
 * Generate a JWT using HMAC-SHA256
 */
function generate_jwt($payload) {
    // Secret key for JWT signing - in a real app this should come from an environment variable
    // For this example, we'll use a hardcoded fallback if env is missing
    $secret = getenv('JWT_SECRET') ?: 'SuperSecretKey_CHANGE_ME_IN_PRODUCTION!';

    // Add Issued At (iat) and Expiration Time (exp) to payload
    $now = time();
    $payload['iat'] = $now;
    $payload['exp'] = $now + (8 * 60 * 60); // 8 hours expiration

    // Header
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);

    // Base64Url Encode
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));

    // Create Signature Hash
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

/**
 * Verify and decode a JWT. Returns the payload if valid, false otherwise.
 */
function verify_jwt($jwt) {
    $secret = getenv('JWT_SECRET') ?: 'SuperSecretKey_CHANGE_ME_IN_PRODUCTION!';

    // Split the token
    $tokenParts = explode('.', $jwt);
    if (count($tokenParts) != 3) {
        return false;
    }

    $header = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[0]));
    $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1]));
    $signature_provided = $tokenParts[2];

    // Check the expiration time
    $payload_data = json_decode($payload, true);
    if (!isset($payload_data['exp']) || $payload_data['exp'] < time()) {
        return false; // Token has expired
    }

    // Build a signature based on the header and payload using the secret
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

    // Verify it matches the signature provided in the token
    if (hash_equals($base64UrlSignature, $signature_provided)) {
        return $payload_data;
    }

    return false;
}

/**
 * Get JWT from HttpOnly cookie or Authorization header
 */
function get_jwt_from_request() {
    // 1. Check cookies (preferred, more secure for web)
    if (isset($_COOKIE['auth_token'])) {
        return $_COOKIE['auth_token'];
    }

    // 2. Check Authorization header (for API clients)
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
    }

    return null;
}

/**
 * Middleware: Require Authentication
 * Returns the user payload if authenticated, otherwise sends 401 and exits.
 */
function require_auth() {
    $jwt = get_jwt_from_request();

    if (!$jwt) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized: No token provided']);
        exit;
    }

    $payload = verify_jwt($jwt);

    if (!$payload) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized: Invalid or expired token']);
        exit;
    }

    return $payload;
}

/**
 * Middleware: Require Role
 * Checks if the authenticated user has one of the allowed roles.
 */
function require_role($allowed_roles) {
    $user = require_auth();

    if (!isset($user['role']) || !in_array($user['role'], $allowed_roles)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden: Insufficient permissions']);
        exit;
    }

    return $user;
}

/**
 * Set the JWT as an HttpOnly cookie
 */
function set_auth_cookie($jwt) {
    // 8 hours expiration
    $expires = time() + (8 * 60 * 60);
    // secure flag should be true in production with HTTPS
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

    setcookie('auth_token', $jwt, [
        'expires' => $expires,
        'path' => '/',
        'domain' => '', // Current domain
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict' // Protect against CSRF
    ]);
}

/**
 * Clear the auth cookie (logout)
 */
function clear_auth_cookie() {
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    setcookie('auth_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}
