<?php
/**
 * admin/stores.php
 *
 * Admin interface for managing stores.
 * Features a DataTable and a Bootstrap modal for CRUD operations.
 */

require_once '../includes/auth_guard.php';
$user = require_role(['admin']);
$pageTitle = 'Καταστήματα';
require_once '../includes/header.php';
require_once 'sidebar.php';
?>
<div class="container-fluid">

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
                    <form id="storeForm" enctype="multipart/form-data">
                        <input type="hidden" id="storeId" name="id">

                        <div class="mb-3">
                            <label for="storeName" class="form-label fw-medium">Όνομα Καταστήματος <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="storeName" name="name" required placeholder="π.χ. Υποκατάστημα Αθήνας">
                        </div>

                        <div class="mb-3">
                            <label for="storeLocation" class="form-label fw-medium">Τοποθεσία (Προαιρετικό)</label>
                            <input type="text" class="form-control" id="storeLocation" name="location" placeholder="π.χ. Ερμού 10">
                        </div>
                        <div class="mb-3">
                            <label for="store_logo" class="form-label fw-medium">Λογότυπο Καταστήματος (Προαιρετικό)</label>
                            <input type="file" class="form-control" id="store_logo" name="store_logo" accept="image/jpeg, image/png, image/webp">
                            <div class="form-text">Επιτρεπτά αρχεία: JPG, PNG, WebP. Μέγιστο μέγεθος: 2MB.</div>
                            <div id="logoPreviewContainer" class="mt-2 d-none">
                                <p class="mb-1 text-muted small">Τρέχον Λογότυπο:</p>
                                <img id="logoPreview" src="" alt="Preview" class="img-thumbnail" style="max-height: 80px;">
                            </div>
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



<?php
$extraScripts = '<script src="../assets/js/stores.js"></script>';
require_once '../includes/footer.php';
?>