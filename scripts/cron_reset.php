<?php

/**
 * scripts/cron_reset.php
 *
 * Scheduled task (CRON) to automatically reset 'confirmed' coupons
 * back to 'idle' state after a configured number of hours, allowing reuse.
 *
 * Recommended CRON schedule: Run every hour (0 * * * *)
 */

declare(strict_types=1);

// This script is meant to be run via CLI. Prevent execution from the web browser if necessary.
// We check against a specific secret value to prevent unauthorized web execution.
$cronSecret = getenv('CRON_SECRET') ?: 'ChangeMeInProduction123!';
if (php_sapi_name() !== 'cli' && (!isset($_GET['force_run_secret']) || $_GET['force_run_secret'] !== $cronSecret)) {
    http_response_code(403);
    die("This script can only be run from the command line or with a valid secret.");
}

// Ensure error reporting is visible for cron logs
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Include central configuration to grab dynamic values like COUPON_RESET_HOURS
require_once __DIR__ . '/../includes/config.php';

// Log startup
$logFile = __DIR__ . '/../cron.log';
function logMessage($msg) {
    global $logFile;
    $date = date('Y-m-d H:i:s');
    $formattedMsg = "[$date] [RESET_JOB] $msg" . PHP_EOL;
    file_put_contents($logFile, $formattedMsg, FILE_APPEND);
    echo $formattedMsg; // Output to stdout as well
}

logMessage("Starting scheduled reset job. Configured hours threshold: " . COUPON_RESET_HOURS);

try {
    // Database connection using environment variables or fallbacks
    require_once __DIR__ . '/../includes/db.php';
    $pdo = Database::getInstance()->getConnection();

    // Define the chunk size to avoid memory exhaustion and long table locks
    $chunkSize = 1000;
    $totalReset = 0;

    // We will loop until no more eligible coupons are found
    while (true) {

        $pdo->beginTransaction();

        try {
            // Find IDs of coupons that are confirmed AND older than the configured threshold.
            // Using a subquery limit ensures we only lock/process a chunk at a time.
            // NOTE: MySQL doesn't directly support LIMIT in an IN subquery targeting the same table for UPDATE
            // So we first SELECT the IDs, then UPDATE them.

            $selectSql = "SELECT id, uuid FROM coupons
                          WHERE status = 'confirmed'
                          AND confirmed_at <= DATE_SUB(NOW(), INTERVAL ? HOUR)
                          LIMIT ?";

            $stmt = $pdo->prepare($selectSql);
            // Bind parameters explicitly since we mix types (int/string logic)
            $stmt->bindValue(1, COUPON_RESET_HOURS, PDO::PARAM_INT);
            $stmt->bindValue(2, $chunkSize, PDO::PARAM_INT);
            $stmt->execute();

            $couponsToReset = $stmt->fetchAll();

            if (empty($couponsToReset)) {
                // Nothing left to process
                $pdo->rollBack(); // Safe rollback
                break;
            }

            // Extract IDs for the UPDATE query and Audit Logging
            $ids = array_column($couponsToReset, 'id');

            $count = count($ids);

            // Prepare the IN clause for the UPDATE query
            $placeholders = implode(',', array_fill(0, $count, '?'));

            // Update status back to 'idle' and clear the confirmed_at timestamp
            $updateSql = "UPDATE coupons
                          SET status = 'idle', confirmed_at = NULL
                          WHERE id IN ($placeholders)";

            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute($ids);

            // Audit Trail Insertion
            // Instead of looping, we bulk insert the events
            // The event_type enum allows 'activated', 'confirmed', 'reset'
            $eventPlaceholders = implode(',', array_fill(0, $count, '(?, ?, NULL, NULL, ?, ?)'));
            $insertSql = "INSERT INTO coupon_events (coupon_id, event_type, store_id, user_id, ip_address, user_agent) VALUES $eventPlaceholders";

            $eventValues = [];
            foreach ($ids as $id) {
                $eventValues[] = $id;
                $eventValues[] = 'reset'; // The correct enum value
                $eventValues[] = '127.0.0.1'; // System CRON IP
                $eventValues[] = 'System_Cron'; // System User Agent
            }

            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute($eventValues);

            $pdo->commit();

            $totalReset += $count;
            logMessage("Processed chunk of $count coupons.");

            // If we processed less than chunk size, it means we hit the end of the data
            if ($count < $chunkSize) {
                break;
            }

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e; // Re-throw to be caught by outer catch block
        }
    }

    logMessage("Job finished successfully. Total coupons reset: $totalReset");

} catch (PDOException $e) {
    logMessage("CRITICAL DATABASE ERROR: " . $e->getMessage());
    exit(1);
} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage());
    exit(1);
}
