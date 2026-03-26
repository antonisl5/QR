/**
 * assets/js/campaign_export.js
 *
 * Logic to trigger the batch generation of coupons and redirect to the Print View.
 * Attaches to "Generate & Print" buttons in the Campaigns DataTables view.
 */

document.addEventListener('DOMContentLoaded', function() {

    // Check if SweetAlert2 is loaded
    if (typeof Swal === 'undefined') {
        console.warn('SweetAlert2 is not loaded. Generating batches will fail.');
        return;
    }

    // Since DataTables often destroys and re-creates DOM elements on pagination/search,
    // we use Event Delegation on the document body or table container.
    document.body.addEventListener('click', function(e) {

        // Find if the clicked element (or its closest parent) is the generate button
        const btn = e.target.closest('.btn-generate-batch');
        if (!btn) return;

        e.preventDefault();

        const campaignId = btn.getAttribute('data-id');
        const campaignTitle = btn.getAttribute('data-title') || 'Αυτή την Καμπάνια';

        if (!campaignId) {
            Swal.fire({
                icon: 'error',
                title: 'Σφάλμα',
                text: 'Το ID της καμπάνιας δεν βρέθηκε.'
            });
            return;
        }

        // Fire SweetAlert2 prompt asking for quantity
        Swal.fire({
            title: 'Δημιουργία Παρτίδας (Batch)',
            html: `Πόσα μοναδικά κουπόνια θέλετε να δημιουργήσετε για την καμπάνια:<br><strong>${campaignTitle}</strong>;`,
            icon: 'question',
            input: 'number',
            inputAttributes: {
                min: 1,
                max: 5000,
                step: 1
            },
            inputValue: 100, // Default value
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-gear"></i> Δημιουργία',
            cancelButtonText: 'Ακύρωση',
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
            showLoaderOnConfirm: true,
            inputValidator: (value) => {
                if (!value || isNaN(value)) {
                    return 'Παρακαλώ εισάγετε έναν αριθμό.';
                }
                const num = parseInt(value, 10);
                if (num < 1 || num > 5000) {
                    return 'Η ποσότητα πρέπει να είναι μεταξύ 1 και 5000.';
                }
            },
            preConfirm: (quantity) => {
                // Return a fetch promise so SweetAlert shows a loading spinner
                return fetch('/api/generate_batch.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        // Optional: Include JWT token if authorization via Header is strictly required by auth_guard
                        // 'Authorization': 'Bearer ' + getCookie('auth_token')
                    },
                    body: JSON.stringify({
                        campaign_id: campaignId,
                        quantity: quantity
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(errData => {
                            throw new Error(errData.error || 'Σφάλμα επικοινωνίας με τον διακομιστή.');
                        });
                    }
                    return response.json();
                })
                .catch(error => {
                    Swal.showValidationMessage(`Σφάλμα: ${error.message}`);
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            // Result comes from the preConfirm fetch promise
            if (result.isConfirmed && result.value && result.value.success) {

                // Show Success Message with an action to Print
                Swal.fire({
                    icon: 'success',
                    title: 'Επιτυχία!',
                    text: result.value.message,
                    confirmButtonText: '<i class="bi bi-printer"></i> Άνοιγμα Εκτύπωσης',
                    confirmButtonColor: '#28a745',
                    showCancelButton: true,
                    cancelButtonText: 'Κλείσιμο'
                }).then((printResult) => {
                    if (printResult.isConfirmed) {
                        // Open the Print Export View in a new tab
                        const printUrl = `/admin/print_export.php?campaign_id=${campaignId}`;
                        window.open(printUrl, '_blank');
                    }

                    // Optional: If DataTable exists, reload it to reflect new coupon counts
                    if (typeof window.campaignsTable !== 'undefined') {
                        window.campaignsTable.ajax.reload(null, false);
                    } else if (typeof $ !== 'undefined' && $.fn.DataTable) {
                         // Attempt generic reload if the global object isn't explicitly named
                         $('.dataTable').DataTable().ajax.reload(null, false);
                    } else {
                         // Fallback: reload the whole page
                         // window.location.reload();
                    }
                });

            }
        });

    });

});

    // Handle Multi-Generate Button
    const generateMultiBtn = document.getElementById('generateMultiBtn');
    if (generateMultiBtn) {
        generateMultiBtn.addEventListener('click', function(e) {
            e.preventDefault();

            const selectedBoxes = document.querySelectorAll('.campaign-select-cb:checked');
            const campaignIds = Array.from(selectedBoxes).map(cb => cb.value);

            if (campaignIds.length === 0) {
                Swal.fire('Σφάλμα', 'Παρακαλώ επιλέξτε τουλάχιστον μία καμπάνια.', 'error');
                return;
            }

            Swal.fire({
                title: 'Δημιουργία Multi-QR',
                html: `Επιλέξατε ${campaignIds.length} καμπάνιες.<br>Πόσα <strong>κοινά QR codes</strong> θέλετε να τυπώσετε;`,
                icon: 'question',
                input: 'number',
                inputAttributes: { min: 1, max: 5000, step: 1 },
                inputValue: 100,
                showCancelButton: true,
                confirmButtonText: 'Δημιουργία',
                cancelButtonText: 'Ακύρωση',
                showLoaderOnConfirm: true,
                inputValidator: (value) => {
                    if (!value || isNaN(value) || value < 1) return 'Εισάγετε έγκυρο αριθμό.';
                },
                preConfirm: (quantity) => {
                    return fetch('/api/generate_batch.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({
                            campaign_ids: campaignIds,
                            quantity: quantity
                        })
                    })
                    .then(response => {
                        if (!response.ok) return response.json().then(err => { throw new Error(err.error); });
                        return response.json();
                    })
                    .catch(error => Swal.showValidationMessage(error.message));
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed && result.value && result.value.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Επιτυχία!',
                        text: result.value.message,
                        confirmButtonText: 'Εκτύπωση',
                        showCancelButton: true
                    }).then((printResult) => {
                        if (printResult.isConfirmed) {
                            const idsParam = campaignIds.join(',');
                            window.open(`/admin/print_export.php?campaign_ids=${idsParam}`, '_blank');
                        }
                        if (typeof window.campaignsTable !== 'undefined') window.campaignsTable.ajax.reload(null, false);
                    });
                }
            });
        });
    }
