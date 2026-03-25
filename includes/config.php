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
define('COUPON_RESET_HOURS', 24);

// Optional: Define other system-wide settings here in the future
// define('SYSTEM_TIMEZONE', 'Europe/Athens');
// date_default_timezone_set(SYSTEM_TIMEZONE);
