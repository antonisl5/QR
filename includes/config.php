<?php
/**
 * includes/config.php
 *
 * Central configuration file for the QR Coupon Platform.
 * Settings here control system-wide behaviors without requiring core logic edits.
 */

// Define the number of hours after a coupon is 'confirmed' before it automatically resets to 'idle'
// This allows the coupon to be reused the next day.
// The default is 24 hours. Change this value to adjust the reset window.

/**
 * Simple .env parser to support local development and manual deployments
 * without requiring Apache/Nginx environment variable injection.
 */
function loadEnv($path)
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Load the .env file if it exists at the root level
loadEnv(__DIR__ . '/../.env');

// We use the environment variable if available, otherwise default to 24
$resetHours = getenv('COUPON_RESET_HOURS') ?: 24;
define('COUPON_RESET_HOURS', (int)$resetHours);

// Optional: Define other system-wide settings here in the future
// define('SYSTEM_TIMEZONE', 'Europe/Athens');
// date_default_timezone_set(SYSTEM_TIMEZONE);
