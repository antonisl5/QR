<?php
/**
 * includes/db.php
 *
 * Global Database Wrapper using the PDO Singleton pattern.
 * Ensures only a single database connection is instantiated per request lifecycle.
 * Sets charset to utf8mb4 and enables strict error mode.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

class Database {

    // Hold the class instance
    private static $instance = null;

    // Hold the PDO connection
    private $pdo;

    /**
     * Private constructor to prevent multiple instances
     */
    private function __construct() {

        $dbHost = getenv('DB_HOST') ?: '127.0.0.1';
        $dbName = getenv('DB_NAME') ?: 'qr_coupons';
        $dbUser = getenv('DB_USER') ?: 'root';
        $dbPass = getenv('DB_PASS') ?: '';

        $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";

        try {
            $this->pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Disable emulated prepared statements to ensure true database-level preparation
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Log the error securely and display a generic message
            error_log("Database Connection Failed: " . $e->getMessage());
            die("Σφάλμα σύνδεσης με τη βάση δεδομένων. Παρακαλώ δοκιμάστε αργότερα.");
        }
    }

    /**
     * Prevent object cloning
     */
    private function __clone() {}

    /**
     * Prevent un-serializing
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize a singleton.");
    }

    /**
     * The Singleton method
     *
     * @return Database The single instance of this class
     */
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    /**
     * Returns the active PDO connection
     *
     * @return PDO
     */
    public function getConnection(): PDO {
        return $this->pdo;
    }
}

/**
 * Helper function for legacy procedural code to easily grab the connection
 *
 * @return PDO
 */
function get_db_connection(): PDO {
    return Database::getInstance()->getConnection();
}
