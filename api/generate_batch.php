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

// Support both single campaign_id and an array of campaign_ids
$campaign_ids = [];
if (!empty($data['campaign_ids']) && is_array($data['campaign_ids'])) {
    $campaign_ids = array_map('intval', $data['campaign_ids']);
} elseif (!empty($data['campaign_id'])) {
    $campaign_ids = [(int)$data['campaign_id']];
}

$quantity = $data['quantity'] ?? null;

// Validation
if (empty($campaign_ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Παρακαλώ επιλέξτε τουλάχιστον μία καμπάνια.']);
    die();
}

if (empty($quantity) || !is_numeric($quantity) || $quantity < 1 || $quantity > 5000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Η ποσότητα πρέπει να είναι ένας αριθμός μεταξύ 1 και 5000.']);
    die();
}

$quantity = (int)$quantity;

try {
    require_once '../includes/db.php';
    $pdo = Database::getInstance()->getConnection();

    $pdo->beginTransaction();

    $total_generated = 0;
    while ($quantity > 0) {
        $currentBatchSize = min($quantity, 1000);
        $placeholdersArr = [];
        $values = [];

        for ($i = 0; $i < $currentBatchSize; $i++) {
            $uuid = bin2hex(random_bytes(16));
            foreach ($campaign_ids as $c_id) {
                $placeholdersArr[] = '(?, ?, ?)';
                $values[] = $uuid;
                $values[] = $c_id;
                $values[] = 'idle';
            }
        }

        $sql = "INSERT INTO coupons (uuid, campaign_id, status) VALUES " . implode(', ', $placeholdersArr);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        $total_generated += $currentBatchSize;
        $quantity -= $currentBatchSize;
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Δημιουργήθηκαν $total_generated κοινά QR Codes με επιτυχία.",
        'generated_count' => $total_generated
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
