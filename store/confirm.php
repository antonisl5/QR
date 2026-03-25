<?php
/**
 * store/confirm.php
 *
 * The Store Confirmation Interface.
 * Used by store staff to redeem activated coupons via scanning or manual entry.
 */

// ---------------------------------------------------------
// 1. MOCK SESSION & AUTHENTICATION
// ---------------------------------------------------------
// TODO: Replace with real JWT/Session Auth
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['store_id'] = 1;
$_SESSION['role'] = 'store_staff';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'store_staff'])) {
    // In production, redirect to login
    die("Μη εξουσιοδοτημένη πρόσβαση.");
}

$pageTitle = 'Εξαργύρωση Κουπονιού';
?>
<!DOCTYPE html>
<html lang="el" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($pageTitle) ?> | QR Coupon Store</title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Remix Icons -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">

    <style>
        :root {
            --bg-main: #f8f9fa;
            --glass-bg: rgba(255, 255, 255, 0.9);
            --border-color: #dee2e6;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-main);
            color: #212529;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Glassmorphism Navbar */
        .store-navbar {
            background-color: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem;
        }

        .main-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .scanner-card {
            width: 100%;
            max-width: 500px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.05);
        }

        /* Scanner Camera Placeholder Area */
        .camera-viewport {
            height: 250px;
            background-color: #1e293b;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            overflow: hidden;
        }

        .camera-target-box {
            width: 200px;
            height: 200px;
            border: 2px dashed rgba(255,255,255,0.5);
            border-radius: 12px;
            position: absolute;
        }

        /* Scanning laser animation */
        .laser-line {
            position: absolute;
            top: 0;
            left: 10%;
            width: 80%;
            height: 2px;
            background-color: #10b981;
            box-shadow: 0 0 10px #10b981, 0 0 20px #10b981;
            animation: scanLaser 2.5s infinite linear;
        }

        @keyframes scanLaser {
            0% { top: 10%; opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { top: 90%; opacity: 0; }
        }

        .manual-entry-divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 1.5rem 0;
            color: #6c757d;
        }

        .manual-entry-divider::before,
        .manual-entry-divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #dee2e6;
        }

        .manual-entry-divider:not(:empty)::before { margin-right: .5em; }
        .manual-entry-divider:not(:empty)::after { margin-left: .5em; }

        .uuid-input {
            text-align: center;
            font-family: monospace;
            letter-spacing: 1px;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <header class="store-navbar fixed-top d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <i class="ri-store-2-line fs-4 text-primary"></i>
            <span class="fw-bold fs-5">Κατάστημα #1</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-light text-dark border"><i class="ri-user-line me-1"></i> Ταμείο</span>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-container mt-5">

        <div class="scanner-card">

            <!-- Camera Scanner Area (Visual Placeholder for Future JS Integration) -->
            <div class="camera-viewport" id="scannerViewport">
                <div class="camera-target-box">
                    <div class="laser-line"></div>
                </div>
                <div class="text-center mt-3 z-1">
                    <i class="ri-camera-lens-line fs-1 text-white opacity-75 mb-2"></i>
                    <p class="mb-0 fw-medium">Κάμερα Ανενεργή</p>
                    <small class="text-white-50">Εκκρεμεί ενσωμάτωση JS Scanner</small>
                </div>
            </div>

            <div class="p-4">
                <div class="text-center mb-4">
                    <h5 class="fw-bold">Εξαργύρωση Κουπονιού</h5>
                    <p class="text-muted small">Σαρώστε το ενεργοποιημένο QR code του πελάτη ή εισάγετε τον κωδικό χειροκίνητα.</p>
                </div>

                <div class="d-grid gap-2">
                    <button class="btn btn-primary btn-lg rounded-pill fw-bold" id="startScannerBtn">
                        <i class="ri-qr-scan-2-line me-2"></i> Εκκίνηση Κάμερας
                    </button>
                </div>

                <div class="manual-entry-divider text-uppercase small fw-bold">ή χειροκινητα</div>

                <!-- Manual UUID Form -->
                <form id="confirmForm">
                    <div class="mb-3">
                        <input type="text" class="form-control form-control-lg uuid-input bg-light" id="couponUuid" placeholder="π.χ. 123e4567-e89b-12d3-a456-426614174000" required autocomplete="off">
                    </div>
                    <button type="submit" class="btn btn-dark btn-lg w-100 rounded-pill fw-bold" id="submitConfirmBtn">
                        <i class="ri-check-double-line me-2"></i> Επιβεβαίωση Κωδικού
                    </button>
                </form>

            </div>
        </div>

    </main>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script src="../assets/js/store_confirm.js"></script>
</body>
</html>
