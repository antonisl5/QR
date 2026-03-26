<?php
/**
 * install.php
 *
 * One-time database seeder and initial setup script.
 * CAUTION: This file must be deleted immediately after running it successfully.
 */

// Basic error reporting for setup
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Basic security: if we are already installed (detect an admin user), block it
$isInstalled = false;

try {
    require_once 'includes/db.php';
    $pdo = Database::getInstance()->getConnection();

    // Check if the users table exists and has an admin
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        if ($stmt->fetch()) {
            $isInstalled = true;
        }
    }
} catch (Exception $e) {
    // If DB connection fails, we assume it's not fully installed yet or DB is down
    $dbError = $e->getMessage();
}

// Handle Form Submission
$message = '';
$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    if ($isInstalled) {
        $message = "System is already installed!";
        $status = "error";
    } else {
        try {
            $pdo = Database::getInstance()->getConnection();


            // DevOps Fresh Start: Clear uploads directory
            $uploadDirs = [__DIR__ . '/uploads/logos/', __DIR__ . '/uploads/campaigns/'];
            foreach ($uploadDirs as $dir) {
                if (is_dir($dir)) {
                    $files = glob($dir . '*');
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            unlink($file);
                        }
                    }
                }
            }

            // 1. Load and execute the schema
            $schemaFile = __DIR__ . '/database/schema.sql';
            if (!file_exists($schemaFile)) {
                throw new Exception("Schema file not found at $schemaFile");
            }

            $sql = file_get_contents($schemaFile);

            // Execute multiple queries
            $pdo->exec($sql);

            // 2. Create the Super Admin account
            $adminEmail = 'admin@example.com';
            $adminPassword = 'password123';
            $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
            $stmt->execute(['admin', $adminEmail, $hashedPassword]);

            $status = "success";
            $message = "Installation successful! The database schema has been created.";
            $isInstalled = true;

        } catch (Exception $e) {
            $status = "error";
            $message = "Installation failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Coupon SaaS - Installer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .install-card { background: white; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); padding: 2.5rem; max-width: 500px; width: 100%; text-align: center; }
        .btn-install { background: #3b82f6; color: white; border: none; padding: 0.75rem 2rem; font-weight: 600; border-radius: 8px; font-size: 1.1rem; transition: background 0.2s; }
        .btn-install:hover { background: #2563eb; }
    </style>
</head>
<body>

<div class="install-card">
    <h2 class="mb-2 fw-bold text-primary">System Installer</h2>
    <p class="text-muted mb-4">Initialize the database and create the super admin account.</p>

    <?php if (isset($dbError)): ?>
        <div class="alert alert-danger text-start">
            <strong>Database Error:</strong><br>
            <?= htmlspecialchars($dbError) ?><br><br>
            Please run <code>setup.sh</code> first or ensure your <code>.env</code> file is properly configured.
        </div>
    <?php endif; ?>

    <?php if ($isInstalled && $status !== 'success'): ?>
        <div class="alert alert-info">
            The system appears to be already installed.
        </div>
        <button class="btn btn-warning w-100 mt-3" onclick="deleteSelf()">Delete Installer Files (Recommended)</button>
        <a href="public/login.php" class="btn btn-primary w-100 mt-2">Go to Login</a>
    <?php elseif ($status === 'success'): ?>
        <div class="alert alert-success text-start">
            <h5>✅ Success!</h5>
            <p>The database has been seeded.</p>
            <hr>
            <strong>Default Admin Credentials:</strong><br>
            Email: <code>admin@example.com</code><br>
            Password: <code>password123</code>
        </div>

        <div class="alert alert-danger mt-4">
            <strong>CRITICAL:</strong> You MUST delete <code>setup.sh</code> and <code>install.php</code> immediately.
        </div>

        <button class="btn btn-danger w-100 mt-2" onclick="deleteSelf()">Delete Installer Scripts & Go to Login</button>

    <?php else: ?>
        <form method="POST">
            <button type="submit" name="install" class="btn btn-install w-100" <?= isset($dbError) ? 'disabled' : '' ?>>
                Run Installation
            </button>
        </form>
    <?php endif; ?>

    <?php if ($status === 'error'): ?>
        <div class="alert alert-danger mt-4 text-start">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script>
    function deleteSelf() {
        Swal.fire({
            title: 'Delete Installer Scripts?',
            text: "This will attempt to delete setup.sh and install.php. Continue?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete them!'
        }).then((result) => {
            if (result.isConfirmed) {
                // In a real scenario, this would call an API endpoint to delete the files via unlink()
                // For safety in this demo, we'll just instruct the user.
                Swal.fire(
                    'Manual Deletion Required',
                    'For security reasons, please run: <br><code>rm setup.sh install.php</code><br> in your terminal.',
                    'info'
                ).then(() => {
                    window.location.href = 'public/login.php';
                });
            }
        });
    }
</script>
</body>
</html>