<?php
/**
 * store/confirm.php
 *
 * The Store Staff interface for confirming activated QR coupons.
 * Features a real-time JS Camera QR Scanner and a manual fallback input.
 */

declare(strict_types=1);

require_once '../includes/auth_guard.php';

// Require Store Staff role
$user = require_role(['store_staff']);

$pageTitle = 'Επιβεβαίωση Κουπονιού';
?>
<!DOCTYPE html>
<html lang="el" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($pageTitle) ?> | QR Coupon Platform</title>

    <!-- Google Fonts -->
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
            --bg-main: #f8fafc;
            --text-primary: #1e293b;
            --glass-bg: rgba(255, 255, 255, 0.9);
            --glass-border: rgba(255, 255, 255, 0.5);
            --card-shadow: 0 10px 25px rgba(0,0,0,0.05);
            --primary-color: #3b82f6;
            --scanner-border: #10b981;
        }

        [data-theme="dark"] {
            --bg-main: #0f172a;
            --text-primary: #f8fafc;
            --glass-bg: rgba(30, 41, 59, 0.85);
            --glass-border: rgba(255, 255, 255, 0.1);
            --card-shadow: 0 10px 25px rgba(0,0,0,0.4);
            --scanner-border: #34d399;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-main);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .glass-navbar {
            background-color: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--glass-border);
        }

        .main-container {
            flex-grow: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .scanner-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 1.5rem;
            box-shadow: var(--card-shadow);
            width: 100%;
            max-width: 500px;
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .scanner-header {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            color: white;
            padding: 1.5rem;
            text-align: center;
        }

        .scanner-header h4 {
            margin: 0;
            font-weight: 700;
        }

        /* Camera Viewport Styling */
        #qr-reader {
            width: 100%;
            min-height: 300px;
            background-color: #000;
            position: relative;
        }

        /* Overrides for html5-qrcode built-in UI to look more modern */
        #qr-reader__scan_region {
            background-color: #000;
        }

        #qr-reader__dashboard {
            padding: 1rem !important;
            background-color: var(--glass-bg);
        }

        #qr-reader__dashboard_section_csr span,
        #qr-reader__dashboard_section_swaplink {
            color: var(--text-primary) !important;
            text-decoration: none !important;
        }

        #qr-reader button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            margin: 5px;
            font-weight: 500;
        }

        #qr-reader button:hover {
            opacity: 0.9;
        }

        .manual-entry {
            padding: 1.5rem;
            border-top: 1px solid var(--glass-border);
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 1rem 0;
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #cbd5e1;
        }

        .divider:not(:empty)::before {
            margin-right: .5em;
        }

        .divider:not(:empty)::after {
            margin-left: .5em;
        }

        [data-theme="dark"] .divider::before,
        [data-theme="dark"] .divider::after {
            border-bottom: 1px solid #334155;
        }

    </style>
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg glass-navbar sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="#">
                <i class="ri-store-2-line fs-4 text-primary"></i>
                <span class="fw-bold">Κατάστημα</span>
            </a>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle rounded-pill px-3 py-2">
                    <i class="ri-user-line me-1"></i> Υπάλληλος
                </span>
                <!-- Logout Button clears the cookie via a simple script or dedicated endpoint, for now we just link to login -->
                <button onclick="logout()" class="btn btn-sm btn-outline-danger rounded-pill px-3" title="Αποσύνδεση">
                    <i class="ri-logout-circle-r-line"></i>
                </button>
            </div>
        </div>
    </nav>

    <!-- Main Scanner Interface -->
    <div class="main-container">
        <div class="scanner-card">

            <div class="scanner-header">
                <i class="ri-qr-scan-2-line fs-1 mb-2 d-block"></i>
                <h4>Εξαργύρωση Κουπονιού</h4>
                <p class="mb-0 text-white-50 small">Σαρώστε το ενεργοποιημένο QR του πελάτη</p>
            </div>

            <!-- The Camera Viewport -->
            <div id="qr-reader"></div>

            <!-- Manual Fallback -->
            <div class="manual-entry">
                <div class="divider">Ή χειροκίνητη εισαγωγή</div>

                <form id="manualConfirmForm">
                    <div class="input-group mb-3">
                        <span class="input-group-text bg-transparent border-end-0"><i class="ri-barcode-line"></i></span>
                        <input type="text" class="form-control border-start-0 ps-0" id="manualUuid" placeholder="Κωδικός UUID π.χ. 123e4567..." required autocomplete="off">
                        <button class="btn btn-primary px-4" type="submit" id="confirmBtn">
                            Επιβεβαίωση
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    <!-- Scripts -->
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>

    <!-- html5-qrcode CDN (The Camera Library) -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <!-- Scanner Logic -->
    <script src="/assets/js/scanner.js"></script>

    <script>
        // Simple logout handler
        function logout() {
            // Delete the auth_token cookie by setting expiry to past
            document.cookie = "auth_token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
            window.location.href = '/public/login.php';
        }
    </script>
</body>
</html>