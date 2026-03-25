<?php
/**
 * api/get_analytics.php
 *
 * Fetches aggregated data, time-series data for charts, and recent activity logs
 * for the Admin Dashboard.
 *
 * Returns: JSON with 'metrics', 'chart_data', and 'recent_activity'.
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

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
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

try {
    // ---------------------------------------------------------
    // 3. AGGREGATE METRICS
    // ---------------------------------------------------------
    // For a real app, this might be filtered by campaign_id or date range
    $stmtMetrics = $pdo->query("
        SELECT
            COUNT(CASE WHEN event_type = 'activated' THEN 1 END) as total_activations,
            COUNT(CASE WHEN event_type = 'confirmed' THEN 1 END) as total_confirmations
        FROM coupon_events
    ");
    $metrics = $stmtMetrics->fetch();

    $totalActivations = (int)$metrics['total_activations'];
    $totalConfirmations = (int)$metrics['total_confirmations'];

    $conversionRate = 0;
    if ($totalActivations > 0) {
        $conversionRate = round(($totalConfirmations / $totalActivations) * 100, 2);
    }

    // ---------------------------------------------------------
    // 4. TIME-SERIES DATA FOR CHART (Last 7 Days)
    // ---------------------------------------------------------
    // Generates the last 7 dates and LEFT JOINs with events to ensure zeroes are returned
    $chartQuery = "
        SELECT
            dates.date,
            COALESCE(SUM(CASE WHEN ce.event_type = 'activated' THEN 1 ELSE 0 END), 0) as activations,
            COALESCE(SUM(CASE WHEN ce.event_type = 'confirmed' THEN 1 ELSE 0 END), 0) as confirmations
        FROM (
            SELECT DATE(DATE_SUB(NOW(), INTERVAL n DAY)) as date
            FROM (
                SELECT 0 as n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3
                UNION SELECT 4 UNION SELECT 5 UNION SELECT 6
            ) days
        ) dates
        LEFT JOIN coupon_events ce ON DATE(ce.created_at) = dates.date
        GROUP BY dates.date
        ORDER BY dates.date ASC
    ";

    $stmtChart = $pdo->query($chartQuery);
    $chartDataRows = $stmtChart->fetchAll();

    $chartData = [
        'labels' => [],
        'activations' => [],
        'confirmations' => []
    ];

    foreach ($chartDataRows as $row) {
        // Format date for display (e.g., "15/05")
        $dateStr = date('d/m', strtotime($row['date']));
        $chartData['labels'][] = $dateStr;
        $chartData['activations'][] = (int)$row['activations'];
        $chartData['confirmations'][] = (int)$row['confirmations'];
    }

    // ---------------------------------------------------------
    // 5. RECENT ACTIVITY (Last 50 events)
    // ---------------------------------------------------------
    $stmtRecent = $pdo->query("
        SELECT
            ce.event_type,
            ce.created_at,
            ce.ip_address,
            c.uuid,
            cam.title as campaign_title,
            s.name as store_name
        FROM coupon_events ce
        JOIN coupons c ON ce.coupon_id = c.id
        JOIN campaigns cam ON c.campaign_id = cam.id
        LEFT JOIN stores s ON ce.store_id = s.id
        ORDER BY ce.created_at DESC
        LIMIT 50
    ");

    $recentActivity = $stmtRecent->fetchAll();

    // ---------------------------------------------------------
    // 6. BUILD AND SEND RESPONSE
    // ---------------------------------------------------------
    sendResponse(true, 200, 'Data fetched successfully', [
        'metrics' => [
            'total_activations' => $totalActivations,
            'total_confirmations' => $totalConfirmations,
            'conversion_rate' => $conversionRate
        ],
        'chart_data' => $chartData,
        'recent_activity' => $recentActivity
    ]);

} catch (Exception $e) {
    // In production, log $e->getMessage()
    sendResponse(false, 500, 'Προέκυψε σφάλμα κατά την ανάκτηση των στατιστικών. ' . $e->getMessage());
}
