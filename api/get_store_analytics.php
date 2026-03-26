<?php
/**
 * api/get_store_analytics.php
 *
 * Secure API endpoint for Store Staff to view their redemption statistics.
 * Filters data strictly based on the staff member's store_id from the JWT.
 */

require_once '../includes/auth_guard.php';
require_once '../includes/db.php';

// Require store_staff role
$user = require_role(['store_staff']);
$storeId = $user['store_id'] ?? null;

header('Content-Type: application/json');

if (!$storeId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Ο λογαριασμός σας δεν είναι συνδεδεμένος με κάποιο κατάστημα.']);
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();

    // 1. Total Redeemed Today
    $stmtToday = $pdo->prepare("
        SELECT COUNT(id) as total_today
        FROM coupon_events
        WHERE store_id = ?
        AND event_type = 'confirmed'
        AND DATE(created_at) = CURDATE()
    ");
    $stmtToday->execute([$storeId]);
    $totalToday = $stmtToday->fetchColumn() ?: 0;

    // 2. Total Redeemed Lifetime
    $stmtLifetime = $pdo->prepare("
        SELECT COUNT(id) as total_lifetime
        FROM coupon_events
        WHERE store_id = ?
        AND event_type = 'confirmed'
    ");
    $stmtLifetime->execute([$storeId]);
    $totalLifetime = $stmtLifetime->fetchColumn() ?: 0;

    // 3. Last 50 Redemptions
    $stmtRecent = $pdo->prepare("
        SELECT ce.created_at, c.uuid, cmp.title as campaign_title
        FROM coupon_events ce
        JOIN coupons c ON ce.coupon_id = c.id
        JOIN campaigns cmp ON c.campaign_id = cmp.id
        WHERE ce.store_id = ? AND ce.event_type = 'confirmed'
        ORDER BY ce.created_at DESC
        LIMIT 50
    ");
    $stmtRecent->execute([$storeId]);
    $recentActivity = $stmtRecent->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => [
            'total_today' => $totalToday,
            'total_lifetime' => $totalLifetime,
            'recent_activity' => $recentActivity
        ]
    ]);

} catch (PDOException $e) {
    error_log("DB Error in get_store_analytics: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Σφάλμα ανάκτησης στατιστικών.']);
}
