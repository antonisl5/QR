<?php
/**
 * admin/users.php
 * UI for User & Role Management.
 * Features a DataTable, Add/Edit Modal, and dynamic Store selection based on Role.
 */

session_start();
// MOCK AUTHENTICATION - Require Admin
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Διαχείριση Χρηστών & Ρόλων | QR Coupon Platform</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Remix Icons -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <!-- Toastr CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">

    <style>
        /* Glassmorphism & Core Styles */
        :root {
            --glass-bg-light: rgba(255, 255, 255, 0.85);
            --glass-border-light: rgba(255, 255, 255, 0.4);
            --glass-bg-dark: rgba(30, 30, 30, 0.85);
            --glass-border-dark: rgba(255, 255, 255, 0.1);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            transition: background 0.3s, color 0.3s;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body.dark-mode {
            background: linear-gradient(135deg, #232526 0%, #414345 100%);
            color: #f8f9fa;
        }

        .glass-panel {
            background: var(--glass-bg-light);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border-light);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1);
            transition: all 0.3s ease;
        }

        body.dark-mode .glass-panel {
            background: var(--glass-bg-dark);
            border-color: var(--glass-border-dark);
            color: #fff;
        }

        .navbar-glass {
            background: var(--glass-bg-light);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--glass-border-light);
        }

        body.dark-mode .navbar-glass {
            background: var(--glass-bg-dark);
            border-bottom-color: var(--glass-border-dark);
        }

        .btn-theme-toggle {
            cursor: pointer;
            font-size: 1.2rem;
            color: inherit;
            border: none;
            background: transparent;
        }

        /* Modal styling for dark mode */
        body.dark-mode .modal-content {
            background-color: #2b2b2b;
            color: #fff;
            border-color: #444;
        }
        body.dark-mode .modal-header,
        body.dark-mode .modal-footer {
            border-color: #444;
        }
        body.dark-mode .form-control,
        body.dark-mode .form-select {
            background-color: #333;
            border-color: #555;
            color: #fff;
        }
        body.dark-mode .form-control:focus,
        body.dark-mode .form-select:focus {
            background-color: #444;
            color: #fff;
            border-color: #0d6efd;
        }

        /* Dynamic Store Dropdown Wrapper */
        #storeSelectWrapper {
            display: none; /* Hidden by default until Store Staff role is selected */
        }

        /* Status Badges */
        .badge-role-admin { background-color: #dc3545; }
        .badge-role-campaign { background-color: #0d6efd; }
        .badge-role-staff { background-color: #198754; }
        .badge-status-active { background-color: #198754; }
        .badge-status-inactive { background-color: #6c757d; }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-glass sticky-top mb-4">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
                <i class="ri-user-settings-line me-2 text-primary"></i>
                <strong>Χρήστες & Ρόλοι</strong>
            </a>
            <div class="d-flex align-items-center">
                <button class="btn-theme-toggle" id="themeToggle" aria-label="Toggle Dark Mode">
                    <i class="ri-moon-line"></i>
                </button>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container pb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Διαχείριση Χρηστών</h2>
            <button class="btn btn-primary rounded-pill px-4 shadow-sm" onclick="openUserModal()">
                <i class="ri-user-add-line me-2"></i>Νέος Χρήστης
            </button>
        </div>

        <div class="glass-panel">
            <div class="table-responsive">
                <table id="usersTable" class="table table-hover align-middle w-100">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Όνομα Χρήστη</th>
                            <th>Email</th>
                            <th>Ρόλος</th>
                            <th>Κατάστημα (Αν Υπάρχει)</th>
                            <th>Κατάσταση</th>
                            <th class="text-end">Ενέργειες</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Populated via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- User Modal (Add/Edit) -->
    <div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-panel">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title" id="userModalLabel">Προσθήκη Χρήστη</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="userForm">
                        <input type="hidden" id="userId" name="id">

                        <div class="mb-3">
                            <label for="username" class="form-label">Όνομα Χρήστη <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" required autocomplete="off">
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required autocomplete="off">
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label" id="passwordLabel">Κωδικός Πρόσβασης <span class="text-danger" id="passwordRequiredAsterisk">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" minlength="8" autocomplete="new-password">
                            <small class="text-muted" id="passwordHint">Απαιτούνται τουλάχιστον 8 χαρακτήρες.</small>
                        </div>

                        <div class="mb-3">
                            <label for="role" class="form-label">Ρόλος <span class="text-danger">*</span></label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="" disabled selected>Επιλέξτε Ρόλο</option>
                                <option value="admin">System Admin</option>
                                <option value="campaign_owner">Campaign Owner</option>
                                <option value="store_staff">Store Staff</option>
                            </select>
                        </div>

                        <div class="mb-3" id="storeSelectWrapper">
                            <label for="store_id" class="form-label">Συνδεδεμένο Κατάστημα <span class="text-danger">*</span></label>
                            <select class="form-select" id="store_id" name="store_id">
                                <!-- Populated dynamically from API -->
                                <option value="" disabled selected>Επιλέξτε Κατάστημα</option>
                            </select>
                            <small class="text-muted">Απαραίτητο μόνο για το προσωπικό καταστήματος.</small>
                        </div>

                        <!-- Status toggle visible only on edit -->
                        <div class="mb-3" id="statusWrapper" style="display:none;">
                            <label class="form-label d-block">Κατάσταση Λογαριασμού</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="isActive" name="is_active" value="1" checked>
                                <label class="form-check-label" for="isActive">Ενεργός</label>
                            </div>
                        </div>

                    </form>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Ακύρωση</button>
                    <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" id="saveUserBtn">
                        <i class="ri-save-3-line me-1"></i> Αποθήκευση
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>

    <!-- Custom Users Module Logic -->
    <script src="../assets/js/users.js"></script>

</body>
</html>
