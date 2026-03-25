<?php
/**
 * includes/footer.php
 *
 * Global UI Shell Footer for the Admin Panel.
 * Closes out the main layout divs and injects all global JS libraries.
 */
?>

    <!-- Scripts -->
    <!-- jQuery (required by DataTables & Toastr) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- Bootstrap 5 Bundle (includes Popper for tooltips/dropdowns) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables JS (Global inclusion for admin tables) -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <!-- SweetAlert2 (Global Alerts & Modals) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>

    <!-- Toastr (Global Notifications) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <!-- Global Helper Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile Sidebar Toggle Logic
            const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
            const sidebar = document.getElementById('sidebar');

            if (sidebarToggleBtn && sidebar) {
                sidebarToggleBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
            }

            // Theme Toggle Logic
            const themeToggleBtn = document.getElementById('themeToggleBtn');
            if (themeToggleBtn) {
                themeToggleBtn.addEventListener('click', function() {
                    const html = document.documentElement;
                    const isDark = html.getAttribute('data-theme') === 'dark';
                    html.setAttribute('data-theme', isDark ? 'light' : 'dark');

                    const icon = isDark ? '<i class="ri-moon-line fs-5"></i>' : '<i class="ri-sun-line fs-5"></i>';
                    this.innerHTML = icon;

                    // Optional: Save preference to localStorage
                    localStorage.setItem('theme', isDark ? 'light' : 'dark');
                });

                // Apply saved theme on load
                const savedTheme = localStorage.getItem('theme');
                if (savedTheme) {
                    document.documentElement.setAttribute('data-theme', savedTheme);
                    themeToggleBtn.innerHTML = savedTheme === 'dark' ? '<i class="ri-sun-line fs-5"></i>' : '<i class="ri-moon-line fs-5"></i>';
                }
            }

            // Global Logout Function
            window.globalLogout = function() {
                Swal.fire({
                    title: 'Αποσύνδεση',
                    text: 'Είστε σίγουροι ότι θέλετε να αποσυνδεθείτε;',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ναι',
                    cancelButtonText: 'Ακύρωση'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Clear the HttpOnly auth_token by calling a logout endpoint or clearing via JS if allowed path
                        // For maximum security, we clear the path "/"
                        document.cookie = "auth_token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
                        window.location.href = '/public/login.php';
                    }
                });
            }
        });
    </script>

    <?php if (isset($extraScripts)) echo $extraScripts; ?>
</div>
</body>
</html>