<?php
/**
 * store/dashboard.php
 *
 * Secure Dashboard for Store Staff.
 * Displays total redemptions today, lifetime redemptions, and recent activity.
 * Uses a clean Glassmorphism UI and strictly filters via JWT store_id.
 */

require_once '../includes/auth_guard.php';

// Ensure the user is Store Staff
$user = require_role(['store_staff']);
if (!$user['store_id']) {
    die("Ο λογαριασμός σας δεν έχει συνδεθεί με κάποιο κατάστημα. Απευθυνθείτε στον διαχειριστή.");
}

$pageTitle = 'Dashboard Καταστήματος';
require_once '../includes/header.php';

// Quick hack: we'll render a simple top navbar here since store staff doesn't use the admin sidebar
?>

<nav class="navbar navbar-expand-lg glass-navbar fixed-top">
    <div class="container px-4">
        <a class="navbar-brand d-flex align-items-center gap-2" href="#">
            <i class="ri-store-2-line fs-4 text-primary"></i>
            <span class="fw-bold">Staff Dashboard</span>
        </a>
        <div class="d-flex align-items-center gap-3">
            <a href="confirm.php" class="btn btn-outline-primary btn-sm"><i class="ri-qr-scan-2-line"></i> Σαρωτής</a>
            <button class="btn btn-icon" id="themeToggleBtn" title="Εναλλαγή θέματος">
                <i class="ri-moon-line fs-5"></i>
            </button>
            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="ri-user-smile-line me-1"></i> Προσωπικό
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                    <li><button class="dropdown-item text-danger" onclick="window.globalLogout()"><i class="ri-logout-box-r-line me-2"></i>Αποσύνδεση</button></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<div class="container py-5 mt-5">

    <!-- Top Row: Stat Cards -->
    <div class="row g-4 mb-5">
        <!-- Today's Redemptions -->
        <div class="col-12 col-md-6">
            <div class="glass-card p-4 h-100 d-flex align-items-center">
                <div class="stat-icon bg-success-light me-4">
                    <i class="ri-calendar-check-line"></i>
                </div>
                <div>
                    <p class="text-muted mb-1 text-uppercase fw-semibold" style="font-size: 0.8rem;">Εξαργυρώσεις Σήμερα</p>
                    <h2 class="fw-bold mb-0" id="statToday">...</h2>
                </div>
            </div>
        </div>
        <!-- Lifetime Redemptions -->
        <div class="col-12 col-md-6">
            <div class="glass-card p-4 h-100 d-flex align-items-center">
                <div class="stat-icon bg-primary-light me-4">
                    <i class="ri-history-line"></i>
                </div>
                <div>
                    <p class="text-muted mb-1 text-uppercase fw-semibold" style="font-size: 0.8rem;">Συνολικές Εξαργυρώσεις (Ιστορικό)</p>
                    <h2 class="fw-bold mb-0" id="statLifetime">...</h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Row: DataTables -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0">Πρόσφατες Εξαργυρώσεις (Τελευταίες 50)</h5>
                    <button class="btn btn-sm btn-outline-secondary" onclick="loadAnalytics()"><i class="ri-refresh-line"></i> Ανανέωση</button>
                </div>
                <div class="table-responsive">
                    <table id="activityTable" class="table table-hover align-middle w-100">
                        <thead>
                            <tr>
                                <th>Ημερομηνία/Ώρα</th>
                                <th>Καμπάνια</th>
                                <th>Κωδικός Κουπονιού (UUID)</th>
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

<?php
$extraScripts = '
<script>
    let activityTable;

    $(document).ready(function() {
        activityTable = $("#activityTable").DataTable({
            language: { url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/el.json" },
            order: [[0, "desc"]],
            pageLength: 10,
            searching: true
        });

        loadAnalytics();
    });

    function loadAnalytics() {
        $.ajax({
            url: "../api/get_store_analytics.php",
            method: "GET",
            dataType: "json",
            success: function(response) {
                if (response.success && response.data) {
                    $("#statToday").text(response.data.total_today);
                    $("#statLifetime").text(response.data.total_lifetime);

                    activityTable.clear();

                    response.data.recent_activity.forEach(item => {
                        activityTable.row.add([
                            new Date(item.created_at).toLocaleString("el-GR"),
                            item.campaign_title,
                            `<span class="badge bg-light text-dark font-monospace">${item.uuid}</span>`
                        ]);
                    });

                    activityTable.draw();
                } else {
                    toastr.error(response.error || "Σφάλμα ανάκτησης δεδομένων.");
                }
            },
            error: function() {
                toastr.error("Αποτυχία σύνδεσης με τον διακομιστή.");
            }
        });
    }
</script>
';
require_once '../includes/footer.php';
?>
