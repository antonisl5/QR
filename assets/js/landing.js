/**
 * assets/js/landing.js
 *
 * Handles the AJAX logic for activating a coupon from the customer landing page.
 * Provides SweetAlert2 interactions and UI state transitions without reloading.
 */

$(document).ready(function() {

    $('#activateBtn').on('click', function(e) {
        e.preventDefault();
        const btn = $(this);
        const uuid = btn.data('uuid');

        if (!uuid) {
            Swal.fire({
                title: 'Σφάλμα',
                text: 'Λείπει ο κωδικός του κουπονιού.',
                icon: 'error',
                confirmButtonText: 'ΟΚ'
            });
            return;
        }

        // 1. Initial Confirmation Prompt
        Swal.fire({
            title: 'Είστε σίγουρος;',
            html: 'Πατήστε <b>Ενεργοποίηση</b> μόνο όταν βρίσκεστε μπροστά στο ταμείο.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ενεργοποίηση!',
            cancelButtonText: 'Ακύρωση'
        }).then((result) => {
            if (result.isConfirmed) {
                // 2. Perform the AJAX request
                activateCoupon(uuid, btn);
            }
        });
    });

    function activateCoupon(uuid, btn) {
        // Show loading state on button
        const originalContent = btn.html();
        btn.prop('disabled', true);
        btn.html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Επεξεργασία...');

        // Perform AJAX Request
        $.ajax({
            url: '../api/activate_coupon.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ uuid: uuid }),
            dataType: 'json',
            success: function(response) {
                // Remove Idle state UI
                $('#idleState').addClass('d-none');

                // Show Dynamic Activated state UI
                if(response.data && response.data.activated_at) {
                    const date = new Date(response.data.activated_at);
                    const formattedDate = date.toLocaleDateString('el-GR') + ' ' +
                                        date.toLocaleTimeString('el-GR', {hour: '2-digit', minute:'2-digit'});
                    $('#dynamicActivationTime').text(formattedDate);
                }
                $('#dynamicActivatedState').removeClass('d-none').addClass('fade-in');

                Swal.fire({
                    title: 'Επιτυχία!',
                    text: response.message,
                    icon: 'success',
                    confirmButtonColor: '#10b981',
                    confirmButtonText: 'Εντάξει'
                });
            },
            error: function(xhr) {
                // Restore Button State
                btn.prop('disabled', false).html(originalContent);

                let errorMsg = 'Προέκυψε ένα σφάλμα. Παρακαλώ δοκιμάστε ξανά.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }

                Swal.fire({
                    title: 'Αποτυχία',
                    text: errorMsg,
                    icon: 'error',
                    confirmButtonColor: '#ef4444',
                    confirmButtonText: 'Κλείσιμο'
                });
            }
        });
    }

});