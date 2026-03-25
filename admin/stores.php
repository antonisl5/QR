<?php
/**
 * admin/stores.php
 *
 * Admin interface for managing stores.
 * Features a DataTable and a Bootstrap modal for CRUD operations.
 */

// ---------------------------------------------------------
// MOCK SESSION & AUTHENTICATION
// ---------------------------------------------------------
// TODO: Replace with real JWT/Session Auth
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Μη εξουσιοδοτημένη πρόσβαση. Απαιτούνται δικαιώματα διαχειριστή.");
}

$pageTitle = 'Διαχείριση Καταστημάτων';
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

    <!-- Toastr CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">

    <style>
        :root {
            --bg-main: #f4f6f9;
            --text-primary: #1e293b;
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.4);
            --card-shadow: 0 4px 20px rgba(0,0,0,0.05);
            --primary-color: #3b82f6;
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
        }

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 1rem;
            box-shadow: var(--card-shadow);
        }

        .action-btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 0.25rem;
            transition: all 0.2s;
        }

        .action-btn:hover {
            transform: translateY(-2px);
        }

        /* Table responsive layout */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            color: #64748b;
        }
    </style>
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg glass-navbar fixed-top">
        <div class="container-fluid px-4">
            <a class="navbar-brand d-flex align-items-center gap-2" href="dashboard.php">
                <i class="ri-store-2-line fs-4 text-primary"></i>
                <span class="fw-bold">Καταστήματα</span>
            </a>
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-icon" id="themeToggleBtn" title="Εναλλαγή θέματος">
                    <i class="ri-moon-line fs-5"></i>
                </button>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-fluid px-4 py-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0 fw-bold">Διαχείριση Καταστημάτων</h4>
            <button class="btn btn-primary d-flex align-items-center gap-2 shadow-sm rounded-pill px-4" id="addStoreBtn">
                <i class="ri-add-line"></i> <span>Νέο Κατάστημα</span>
            </button>
        </div>

        <div class="glass-card p-4">
            <div class="table-responsive">
                <table id="storesTable" class="table table-hover align-middle w-100">
                    <thead>
                        <tr>
                            <th>Όνομα</th>
                            <th>Τοποθεσία</th>
                            <th>Ημ. Δημιουργίας</th>
                            <th class="text-end">Ενέργειες</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Populated via JS -->
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Store Modal (Add/Edit) -->
    <div class="modal fade" id="storeModal" tabindex="-1" aria-labelledby="storeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold" id="storeModalLabel">Προσθήκη Καταστήματος</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="storeForm">
                        <input type="hidden" id="storeId" name="id">

                        <div class="mb-3">
                            <label for="storeName" class="form-label fw-medium">Όνομα Καταστήματος <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="storeName" name="name" required placeholder="π.χ. Υποκατάστημα Αθήνας">
                        </div>

                        <div class="mb-3">
                            <label for="storeLocation" class="form-label fw-medium">Τοποθεσία (Προαιρετικό)</label>
                            <input type="text" class="form-control" id="storeLocation" name="location" placeholder="π.χ. Ερμού 10">
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Ακύρωση</button>
                    <button type="button" class="btn btn-primary rounded-pill px-4" id="saveStoreBtn">
                        <i class="ri-save-line me-1"></i> Αποθήκευση
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <!-- SweetAlert2 & Toastr -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <!-- Custom Stores JS -->
    <script src="../assets/js/stores.js"></script>

</body>
</html>
