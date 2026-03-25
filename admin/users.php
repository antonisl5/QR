<?php
/**
 * admin/users.php
 * UI for User & Role Management.
 * Features a DataTable, Add/Edit Modal, and dynamic Store selection based on Role.
 */

require_once '../includes/auth_guard.php';
$user = require_role(['admin']);
$pageTitle = 'Χρήστες';
require_once '../includes/header.php';
require_once 'sidebar.php';
?>
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


<?php
$extraScripts = '<script src="../assets/js/users.js"></script>';
require_once '../includes/footer.php';
?>