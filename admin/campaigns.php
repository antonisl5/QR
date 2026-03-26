<?php
/**
 * admin/campaigns.php
 *
 * Admin interface for managing campaigns.
 * Features a DataTable and a Bootstrap modal for CRUD operations.
 */

require_once '../includes/auth_guard.php';
$user = require_role(['admin', 'campaign_owner']);
$pageTitle = 'Καμπάνιες';
require_once '../includes/header.php';
require_once 'sidebar.php';
?>
<div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0 fw-bold">Διαχείριση Καμπανιών</h4>
            <button class="btn btn-primary d-flex align-items-center gap-2 shadow-sm rounded-pill px-4" id="addCampaignBtn">
                <i class="ri-add-line"></i> <span>Νέα Καμπάνια</span>
            </button>
        </div>

        <div class="glass-card p-4">
            <div class="table-responsive">
                <table id="campaignsTable" class="table table-hover align-middle w-100">
                    <thead>
                        <tr>
                            <th>Τίτλος</th>
                            <th>Περιγραφή</th>
                            <th>Ημ. Έναρξης</th>
                            <th>Ημ. Λήξης</th>
                            <th>Κατάσταση</th>
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

    <!-- Campaign Modal (Add/Edit) -->
    <div class="modal fade" id="campaignModal" tabindex="-1" aria-labelledby="campaignModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold" id="campaignModalLabel">Προσθήκη Καμπάνιας</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="campaignForm" enctype="multipart/form-data">
                        <input type="hidden" id="campaignId" name="id">

                        <div class="mb-3">
                            <label for="campaignTitle" class="form-label fw-medium">Τίτλος <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="campaignTitle" name="title" required placeholder="π.χ. Έκπτωση -20% Χριστούγεννα">
                        </div>

                        <div class="mb-3">
                            <label for="campaignDescription" class="form-label fw-medium">Περιγραφή</label>
                            <textarea class="form-control" id="campaignDescription" name="description" rows="3" placeholder="Λεπτομέρειες για την καμπάνια..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="store_id" class="form-label fw-medium">Συνδεδεμένο Κατάστημα (Προαιρετικό)</label>
                            <select class="form-select" id="store_id" name="store_id">
                                <option value="">Χωρίς Σύνδεση (Όλα τα καταστήματα)</option>
                                <!-- Populated via JS -->
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="campaign_image" class="form-label fw-medium">Κεντρική Εικόνα (Hero Image)</label>
                            <input type="file" class="form-control" id="campaign_image" name="campaign_image" accept="image/jpeg, image/png, image/webp">
                            <div class="form-text">Επιτρεπτά αρχεία: JPG, PNG, WebP. Μέγιστο μέγεθος: 2MB.</div>
                            <div id="imagePreviewContainer" class="mt-2 d-none">
                                <p class="mb-1 text-muted small">Τρέχουσα Εικόνα:</p>
                                <img id="imagePreview" src="" alt="Preview" class="img-thumbnail" style="max-height: 120px;">
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <label for="campaignStartDate" class="form-label fw-medium">Ημ. Έναρξης <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" id="campaignStartDate" name="start_date" required>
                            </div>
                            <div class="col-sm-6">
                                <label for="campaignEndDate" class="form-label fw-medium">Ημ. Λήξης <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" id="campaignEndDate" name="end_date" required>
                            </div>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="campaignIsActive" name="is_active" checked>
                            <label class="form-check-label fw-medium" for="campaignIsActive">Ενεργή</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Ακύρωση</button>
                    <button type="button" class="btn btn-primary rounded-pill px-4" id="saveCampaignBtn">
                        <i class="ri-save-line me-1"></i> Αποθήκευση
                    </button>
                </div>
            </div>
        </div>
    </div>



<?php
$extraScripts = '<script src="../assets/js/campaigns.js"></script>
<script src="../assets/js/campaign_export.js"></script>';
require_once '../includes/footer.php';
?>