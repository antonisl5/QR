
let storesLoaded = false;
function loadStores(selectedStoreId = null) {
    if (storesLoaded && !selectedStoreId) return;

    $.ajax({
        url: '../api/manage_stores.php',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                const select = $('#store_id');
                select.empty();
                select.append('<option value="">Χωρίς Σύνδεση (Όλα τα καταστήματα)</option>');

                response.data.forEach(store => {
                    const isSelected = selectedStoreId == store.id ? 'selected' : '';
                    select.append(`<option value="${store.id}" ${isSelected}>${store.name}</option>`);
                });
                storesLoaded = true;
            }
        }
    });
}

// Call on load to cache them
$(document).ready(function() {
    loadStores();
});

/**
 * assets/js/campaigns.js
 *
 * Logic for the Admin Campaigns Management module.
 * Fetches, creates, updates, and soft-deletes campaigns via AJAX.
 */

$(document).ready(function() {

    let table;
    const modalElement = document.getElementById('campaignModal');
    const campaignModal = new bootstrap.Modal(modalElement);
    const form = $('#campaignForm');

    // Toastr Configuration
    toastr.options = {
        "closeButton": true,
        "progressBar": true,
        "positionClass": "toast-top-right",
        "timeOut": "3000",
    };

    // Theme Toggle Logic
    $('#themeToggleBtn').on('click', function() {
        const html = $('html');
        const isDark = html.attr('data-theme') === 'dark';
        html.attr('data-theme', isDark ? 'light' : 'dark');

        const icon = isDark ? '<i class="ri-moon-line fs-5"></i>' : '<i class="ri-sun-line fs-5"></i>';
        $(this).html(icon);
    });

    // Initialize DataTable
    function initTable() {
        table = $('#campaignsTable').DataTable({
            ajax: {
                url: '../api/manage_campaigns.php',
                type: 'GET',
                dataSrc: 'data' // Tells DataTables where the array is in the JSON response
            },
            columns: [
                { data: 'title', render: $.fn.dataTable.render.text() },
                { data: 'description', render: $.fn.dataTable.render.text() },
                {
                    data: 'start_date',
                    render: function(data) {
                        return formatDate(data);
                    }
                },
                {
                    data: 'end_date',
                    render: function(data) {
                        return formatDate(data);
                    }
                },
                {
                    data: null,
                    render: function(data, type, row) {
                        return getStatusBadge(row.is_active, row.end_date);
                    }
                },
                {
                    data: null,
                    className: 'text-end',
                    orderable: false,
                    render: function(data, type, row) {
                        return `
                            <button class="btn btn-sm btn-outline-success action-btn me-1 btn-generate-batch" data-id="${row.id}" data-title="${row.title}" title="Δημιουργία & Εκτύπωση">
                                <i class="ri-printer-line"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-primary action-btn me-1 edit-btn" data-id="${row.id}" title="Επεξεργασία">
                                <i class="ri-edit-line"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger action-btn delete-btn" data-id="${row.id}" title="Διαγραφή">
                                <i class="ri-delete-bin-line"></i>
                            </button>
                        `;
                    }
                }
            ],
            language: {
                "sProcessing":   "Επεξεργασία...",
                "sLengthMenu":   "Δείξε _MENU_ εγγραφές",
                "sZeroRecords":  "Δεν βρέθηκαν εγγραφές",
                "sInfo":         "Δείχνοντας _START_ εως _END_ από _TOTAL_ εγγραφές",
                "sInfoEmpty":    "Δείχνοντας 0 εως 0 από 0 εγγραφές",
                "sInfoFiltered": "(φιλτραρισμένες από _MAX_ συνολικά εγγραφές)",
                "sSearch":       "Αναζήτηση:",
                "oPaginate": {
                    "sFirst":    "Πρώτη",
                    "sPrevious": "Προηγούμενη",
                    "sNext":     "Επόμενη",
                    "sLast":     "Τελευταία"
                }
            }
        });
    }

    // Helper: Format Date to DD/MM/YYYY HH:MM
    function formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('el-GR') + ' ' + date.toLocaleTimeString('el-GR', {hour: '2-digit', minute:'2-digit'});
    }

    // Helper: Generate Status Badge
    function getStatusBadge(isActive, endDateStr) {
        const now = new Date();
        const endDate = new Date(endDateStr);

        if (now > endDate) {
            return '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle rounded-pill px-3"><i class="ri-time-line me-1"></i>Έληξε</span>';
        }

        if (isActive == 1) {
            return '<span class="badge bg-success bg-opacity-10 text-success border border-success-subtle rounded-pill px-3"><i class="ri-checkbox-circle-line me-1"></i>Ενεργή</span>';
        } else {
            return '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning-subtle rounded-pill px-3"><i class="ri-pause-circle-line me-1"></i>Ανενεργή</span>';
        }
    }

    // Open Add Modal
    $('#addCampaignBtn').on('click', function() {
        $('#campaignModalLabel').text('Προσθήκη Καμπάνιας');
        form[0].reset();
        $('#campaignId').val(''); // Clear ID
        campaignModal.show();
    });

    // Open Edit Modal
    $('#campaignsTable').on('click', '.edit-btn', function() {
        const tr = $(this).closest('tr');
        const rowData = table.row(tr).data();

        $('#campaignModalLabel').text('Επεξεργασία Καμπάνιας');
        $('#campaignId').val(rowData.id);
        $('#campaignTitle').val(rowData.title);
        $('#campaignDescription').val(rowData.description);

        // Format datetime for datetime-local input (YYYY-MM-DDThh:mm)
        $('#campaignStartDate').val(rowData.start_date.replace(' ', 'T').substring(0, 16));
        $('#campaignEndDate').val(rowData.end_date.replace(' ', 'T').substring(0, 16));

        $('#campaignIsActive').prop('checked', rowData.is_active == 1);

        campaignModal.show();
    });

    // Save Campaign (Create or Update)
    $('#saveCampaignBtn').on('click', function() {
        // Validate form
        if (!$('#campaignTitle').val() || !$('#campaignStartDate').val() || !$('#campaignEndDate').val()) {
            toastr.error('Παρακαλώ συμπληρώστε όλα τα υποχρεωτικά πεδία.');
            return;
        }

        const id = $('#campaignId').val();
        const method = id ? 'PUT' : 'POST';

        const formData = new FormData(document.getElementById('campaignForm'));
        if (id) formData.append('id', id);
        formData.append('_method', method);

        // Ensure checkbox is handled properly
        formData.set('is_active', $('#campaignIsActive').is(':checked') ? 1 : 0);

        const btn = $(this);
        const originalText = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Αποθήκευση...');

        $.ajax({
            url: '../api/manage_campaigns.php',
            type: 'POST',
            processData: false,
            contentType: false,
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    campaignModal.hide();
                    table.ajax.reload(null, false); // Reload table keeping current pagination
                } else {
                    toastr.error(response.message);
                }
            },
            error: function(xhr) {
                let msg = 'Σφάλμα κατά την αποθήκευση.';
                if(xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                toastr.error(msg);
            },
            complete: function() {
                btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Delete Campaign
    $('#campaignsTable').on('click', '.delete-btn', function() {
        const id = $(this).data('id');

        Swal.fire({
            title: 'Είστε σίγουρος;',
            text: "Η καμπάνια θα διαγραφεί (Soft Delete). Δεν θα επηρεαστούν τα στατιστικά των ήδη εξαργυρωμένων κουπονιών.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ναι, διαγραφή!',
            cancelButtonText: 'Ακύρωση'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '../api/manage_campaigns.php',
                    type: 'DELETE',
                    contentType: 'application/json',
                    data: JSON.stringify({ id: id }),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            table.ajax.reload(null, false);
                        } else {
                            Swal.fire('Σφάλμα', response.message, 'error');
                        }
                    },
                    error: function(xhr) {
                        let msg = 'Σφάλμα κατά τη διαγραφή.';
                        if(xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        Swal.fire('Σφάλμα', msg, 'error');
                    }
                });
            }
        });
    });

    // Init
    initTable();
});