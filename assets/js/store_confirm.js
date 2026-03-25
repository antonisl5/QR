/**
 * assets/js/store_confirm.js
 *
 * Logic for the Store Confirmation flow.
 * Manages manual UUID submission to api/confirm_coupon.php and handles the
 * visual feedback (Success/Error) using SweetAlert2.
 * Includes a stub for future camera scanner integration.
 */

$(document).ready(function() {

    // Elements
    const form = $('#confirmForm');
    const input = $('#couponUuid');
    const submitBtn = $('#submitConfirmBtn');
    const scannerBtn = $('#startScannerBtn');

    // Focus input on load for faster entry
    input.focus();

    // Prevent default form submission and handle via AJAX
    form.on('submit', function(e) {
        e.preventDefault();
        const uuid = input.val().trim();

        if (uuid === '') {
            Swal.fire('Σφάλμα', 'Παρακαλώ εισάγετε έναν κωδικό.', 'warning');
            return;
        }

        confirmCoupon(uuid);
    });

    // Handle AJAX Confirmation
    function confirmCoupon(uuid) {
        // Disable UI
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true);
        submitBtn.html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Επεξεργασία...');
        input.prop('disabled', true);

        $.ajax({
            url: '../api/confirm_coupon.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ uuid: uuid }),
            dataType: 'json',
            success: function(response) {
                // Success Modal
                Swal.fire({
                    title: 'Επιτυχής Εξαργύρωση!',
                    text: response.message,
                    icon: 'success',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    didClose: () => {
                        // Reset Form
                        input.val('');
                        input.prop('disabled', false).focus();
                        submitBtn.prop('disabled', false).html(originalText);
                    }
                });
            },
            error: function(xhr) {
                // Determine Error Message
                let errorMsg = 'Προέκυψε ένα σφάλμα. Ελέγξτε τη σύνδεση σας.';
                let iconType = 'error';

                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;

                    // If it's a 400 or 403, it's likely a business logic issue, use warning icon
                    if(xhr.status === 400 || xhr.status === 403) {
                        iconType = 'warning';
                    }
                }

                // Error Modal
                Swal.fire({
                    title: 'Αποτυχία',
                    text: errorMsg,
                    icon: iconType,
                    confirmButtonColor: '#212529',
                    confirmButtonText: 'Εντάξει, κλείσιμο'
                }).then(() => {
                    // Reset Form
                    input.val('');
                    input.prop('disabled', false).focus();
                    submitBtn.prop('disabled', false).html(originalText);
                });
            }
        });
    }

    // Camera Scanner Stub
    scannerBtn.on('click', function() {
        Swal.fire({
            title: 'Κάμερα',
            text: 'Η ενσωμάτωση του σαρωτή (JS QR Scanner) θα προστεθεί στην επόμενη φάση.',
            icon: 'info',
            confirmButtonText: 'Εντάξει'
        });
    });

});