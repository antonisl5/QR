<?php
/**
 * admin/dashboard.php
 *
 * Admin Dashboard UI.
 * Displays key metrics, an analytics chart, and a table of recent activity.
 */

// ---------------------------------------------------------
// MOCK SESSION & AUTHENTICATION
// ---------------------------------------------------------
// TODO: Replace with real JWT/Session Auth
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Μη εξουσιοδοτημένη πρόσβαση.");
}

$pageTitle = 'Πίνακας Ελέγχου';
?>
<!DOCTYPE html>
<html lang="el" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | QR Coupon Platform</title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Remix Icons -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">

    <style>
        :root {
            --bg-main: #f4f6f9;
            --text-primary: #1e293b;
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.4);
            --card-shadow: 0 4px 20px rgba(0,0,0,0.05);
            --primary-color: #3b82f6;
            --success-color: #10b981;
            --accent-color: #8b5cf6;
        }

        [data-theme="dark"] {
            --bg-main: #0f172a;
            --text-primary: #f8fafc;
            --glass-bg: rgba(30, 41, 59, 0.85);
            --glass-border: rgba(255, 255, 255, 0.1);
            --card-shadow: 0 4px 20px rgba(0,0,0,0.4);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-main);
            color: var(--text-primary);
            padding-top: 70px;
        }

        .glass-navbar {
            background-color: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--glass-border);
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        }

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 1rem;
            box-shadow: var(--card-shadow);
            transition: transform 0.2s;
        }

        .glass-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .bg-primary-light { background-color: rgba(59, 130, 246, 0.1); color: var(--primary-color); }
        .bg-success-light { background-color: rgba(16, 185, 129, 0.1); color: var(--success-color); }
        .bg-accent-light { background-color: rgba(139, 92, 246, 0.1); color: var(--accent-color); }

        .chart-container {
            position: relative;
            height: 350px;
            width: 100%;
        }

        /* Loading Overlay */
        #loadingOverlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: var(--bg-main);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>

    <!-- Loading Overlay -->
    <div id="loadingOverlay">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Φόρτωση...</span>
        </div>
        <h5 class="mt-3 text-muted">Ανάκτηση δεδομένων...</h5>
    </div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg glass-navbar fixed-top">
        <div class="container-fluid px-4">
            <a class="navbar-brand d-flex align-items-center gap-2" href="#">
                <i class="ri-dashboard-line fs-4 text-primary"></i>
                <span class="fw-bold">Admin Dashboard</span>
            </a>
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-icon" id="themeToggleBtn" title="Εναλλαγή θέματος">
                    <i class="ri-moon-line fs-5"></i>
                </button>
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="ri-user-settings-line me-1"></i> Διαχειριστής
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                        <li><a class="dropdown-item" href="coupon_builder.php"><i class="ri-brush-line me-2"></i>Coupon Builder</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="#"><i class="ri-logout-box-r-line me-2"></i>Αποσύνδεση</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-fluid px-4 py-4">

        <!-- Top Row: Stat Cards -->
        <div class="row g-4 mb-4">
            <!-- Activations -->
            <div class="col-12 col-md-4">
                <div class="glass-card p-4 h-100 d-flex align-items-center">
                    <div class="stat-icon bg-primary-light me-4">
                        <i class="ri-smartphone-line"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-1 text-uppercase fw-semibold" style="font-size: 0.8rem;">Συνολικές Ενεργοποιήσεις</p>
                        <h2 class="fw-bold mb-0" id="statActivations">0</h2>
                    </div>
                </div>
            </div>
            <!-- Confirmations -->
            <div class="col-12 col-md-4">
                <div class="glass-card p-4 h-100 d-flex align-items-center">
                    <div class="stat-icon bg-success-light me-4">
                        <i class="ri-check-double-line"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-1 text-uppercase fw-semibold" style="font-size: 0.8rem;">Συνολικές Εξαργυρώσεις</p>
                        <h2 class="fw-bold mb-0" id="statConfirmations">0</h2>
                    </div>
                </div>
            </div>
            <!-- Conversion Rate -->
            <div class="col-12 col-md-4">
                <div class="glass-card p-4 h-100 d-flex align-items-center">
                    <div class="stat-icon bg-accent-light me-4">
                        <i class="ri-pie-chart-line"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-1 text-uppercase fw-semibold" style="font-size: 0.8rem;">Ποσοστό Μετατροπής</p>
                        <h2 class="fw-bold mb-0" id="statConversion">0%</h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Middle Row: Chart -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="glass-card p-4">
                    <h5 class="fw-bold mb-4">Δραστηριότητα Τελευταίων 7 Ημερών</h5>
                    <div class="chart-container">
                        <canvas id="activityChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Row: DataTables -->
        <div class="row">
            <div class="col-12">
                <div class="glass-card p-4">
                    <h5 class="fw-bold mb-4">Πρόσφατη Δραστηριότητα</h5>
                    <div class="table-responsive">
                        <table id="activityTable" class="table table-hover align-middle w-100">
                            <thead>
                                <tr>
                                    <th>Ημερομηνία/Ώρα</th>
                                    <th>Ενέργεια</th>
                                    <th>Καμπάνια</th>
                                    <th>Κατάστημα</th>
                                    <th>Κωδικός (UUID)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Populated via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>

    <!-- Custom Dashboard JS -->
    <script src="../assets/js/dashboard.js"></script>

</body>
</html>
