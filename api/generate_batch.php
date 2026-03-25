<?php

/**
 * api/generate_batch.php
 *
 * Endpoint to generate a batch of unique QR coupons for a specific campaign.
 *
 * Expected POST data:
 * - campaign_id: The ID of the campaign.
 * - quantity: The number of coupons to generate (e.g., 100).
 *
 * Returns:
 * JSON response with success status and generated count.
 */

declare(strict_types=1);

require_once '../includes/auth_guard.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Require authentication and specific roles (Admin or Campaign Owner - though here we'll assume admins for now based on context)
// If you have a specific 'campaign_owner' role, add it to the array.
$user = require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Μη επιτρεπτή μέθοδος (Method Not Allowed).']);
    exit;
}

$raw_data = file_get_contents("php://input");
$data = json_decode($raw_data, true);

$campaign_id = $data['campaign_id'] ?? null;
$quantity = $data['quantity'] ?? null;

// Validation
if (empty($campaign_id) || !is_numeric($campaign_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Μη έγκυρο ID καμπάνιας.']);
    exit;
}

if (empty($quantity) || !is_numeric($quantity) || (int)$quantity <= 0 || (int)$quantity > 5000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Η ποσότητα πρέπει να είναι μεταξύ 1 και 5000.']);
    exit;
}

$quantity = (int)$quantity;
$campaign_id = (int)$campaign_id;

/**
 * Generates a UUID v4
 */
function generate_uuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
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
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Verify campaign exists
    $stmt = $pdo->prepare("SELECT id FROM campaigns WHERE id = ? LIMIT 1");
    $stmt->execute([$campaign_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Η καμπάνια δεν βρέθηκε.']);
        exit;
    }

    // Begin Transaction for bulk insert
    $pdo->beginTransaction();

    // Prepare the bulk insert query
    // We will chunk the inserts if the quantity is very large to avoid hitting MySQL limits (e.g. max_allowed_packet)
    $chunk_size = 500;
    $total_inserted = 0;

    while ($total_inserted < $quantity) {
        $current_chunk = min($chunk_size, $quantity - $total_inserted);

        $placeholders = [];
        $values = [];

        for ($i = 0; $i < $current_chunk; $i++) {
            $uuid = generate_uuid();
            $placeholders[] = '(?, ?, ?)';
            $values[] = $uuid;
            $values[] = $campaign_id;
            $values[] = 'idle'; // Initial state
        }

        $sql = "INSERT INTO coupons (uuid, campaign_id, status) VALUES " . implode(', ', $placeholders);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        $total_inserted += $current_chunk;
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Επιτυχής δημιουργία $total_inserted κουπονιών.",
        'quantity' => $total_inserted
    ]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Check for duplicate entry error (1062) - extremely rare with UUID v4, but possible
    if ($e->errorInfo[1] === 1062) {
         http_response_code(500);
         echo json_encode(['success' => false, 'error' => 'Υπήρξε σύγκρουση κωδικών. Δοκιμάστε ξανά.']);
    } else {
        error_log('Database error in generate_batch: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Σφάλμα κατά τη δημιουργία των κουπονιών.']);
    }
}
