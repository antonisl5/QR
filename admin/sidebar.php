<?php
/**
 * admin/sidebar.php
 *
 * Global UI Shell Sidebar Navigation Menu.
 * Contains dynamic logic to highlight the currently active page.
 */

// Determine the current page for active class logic
$currentPage = basename($_SERVER['PHP_SELF']);

// User Data from the global $user variable (set by auth_guard)
$userEmail = $user['email'] ?? 'Admin User';
$userRole = $user['role'] ?? 'admin';
?>

<!-- Sidebar -->
<nav id="sidebar">
    <div class="sidebar-header">
        <i class="ri-rocket-line fs-3 text-primary"></i>
        <span>SaaS Admin</span>
    </div>

    <div class="d-flex flex-column h-100">
        <div class="nav flex-column mt-3 mb-auto">

            <a href="/admin/dashboard.php" class="nav-link <?= ($currentPage === 'dashboard.php') ? 'active' : '' ?>">
                <i class="ri-dashboard-line"></i> Dashboard
            </a>

            <a href="/admin/campaigns.php" class="nav-link <?= ($currentPage === 'campaigns.php') ? 'active' : '' ?>">
                <i class="ri-megaphone-line"></i> Καμπάνιες
            </a>

            <a href="/admin/stores.php" class="nav-link <?= ($currentPage === 'stores.php') ? 'active' : '' ?>">
                <i class="ri-store-2-line"></i> Καταστήματα
            </a>

            <a href="/admin/print_export.php" class="nav-link <?= ($currentPage === 'print_export.php') ? 'active' : '' ?>">
                <i class="ri-printer-line"></i> Εκτύπωση
            </a>

            <?php if ($userRole === 'admin'): ?>
            <a href="/admin/users.php" class="nav-link <?= ($currentPage === 'users.php') ? 'active' : '' ?>">
                <i class="ri-group-line"></i> Χρήστες
            </a>
            <?php endif; ?>

        </div>

        <div class="mt-auto border-top" style="border-color: var(--glass-border) !important;">
            <div class="p-3 d-flex flex-column gap-2">
                <div class="d-flex align-items-center gap-2 mb-2 text-muted small px-2">
                    <i class="ri-user-smile-line fs-5"></i>
                    <span class="text-truncate"><?= htmlspecialchars($userEmail) ?></span>
                </div>

                <!-- Global Top Bar Toggles (Moved here for clean UI) -->
                <button class="btn btn-outline-secondary btn-sm w-100 d-flex align-items-center justify-content-center gap-2" id="themeToggleBtn" title="Εναλλαγή θέματος">
                    <i class="ri-moon-line"></i> Σκοτεινό/Φωτεινό
                </button>

                <button onclick="window.globalLogout()" class="btn btn-danger btn-sm w-100 d-flex align-items-center justify-content-center gap-2">
                    <i class="ri-logout-circle-r-line"></i> Αποσύνδεση
                </button>
            </div>
        </div>
    </div>
</nav>

<!-- Main Content Wrapper Start -->
<div id="content-wrapper">
    <!-- Top Bar for Mobile Menu Toggle -->
    <div class="top-bar d-md-none mb-3">
        <button id="sidebarToggleBtn" class="btn btn-primary d-md-none rounded-circle shadow-sm" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
            <i class="ri-menu-line"></i>
        </button>
        <span class="fw-bold text-primary">SaaS Admin</span>
    </div>

    <!-- Breadcrumb or Context Header can go here per page -->
