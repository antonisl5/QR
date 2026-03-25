<?php
/**
 * admin/dashboard.php
 *
 * Admin Dashboard UI.
 * Displays key metrics, an analytics chart, and a table of recent activity.
 */

require_once '../includes/auth_guard.php';
$user = require_role(['admin', 'campaign_owner']);
$pageTitle = 'Πίνακας Ελέγχου';
require_once '../includes/header.php';
require_once 'sidebar.php';
?>



    <!-- Main Content -->
    <div class="container-fluid">

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


<?php
$extraScripts = '<script src="../assets/js/dashboard.js"></script>';
require_once '../includes/footer.php';
?>