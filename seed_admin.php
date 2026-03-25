<?php
require_once 'includes/db.php';
$pdo = Database::getInstance()->getConnection();

$adminEmail = 'admin@example.com';
$adminPassword = 'password123';
$hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'admin') ON DUPLICATE KEY UPDATE password_hash=?");
$stmt->execute(['admin', $adminEmail, $hashedPassword, $hashedPassword]);
echo "Seeded admin.\n";
