<?php
require_once 'includes/db.php';
$pdo = Database::getInstance()->getConnection();

// Create a dummy campaign
$pdo->exec("INSERT INTO campaigns (id, title, description, start_date, end_date, user_id) VALUES (1, 'Test Campaign', 'Test', '2024-01-01', '2025-12-31', 1) ON DUPLICATE KEY UPDATE title='Test Campaign'");

// Generate 4 coupons for campaign 1
$pdo->exec("INSERT INTO coupons (campaign_id, uuid, status) VALUES (1, 'c1', 'idle') ON DUPLICATE KEY UPDATE status='idle'");
$pdo->exec("INSERT INTO coupons (campaign_id, uuid, status) VALUES (1, 'c2', 'idle') ON DUPLICATE KEY UPDATE status='idle'");
$pdo->exec("INSERT INTO coupons (campaign_id, uuid, status) VALUES (1, 'c3', 'idle') ON DUPLICATE KEY UPDATE status='idle'");
$pdo->exec("INSERT INTO coupons (campaign_id, uuid, status) VALUES (1, 'c4', 'idle') ON DUPLICATE KEY UPDATE status='idle'");
echo "Seeded coupons.\n";
