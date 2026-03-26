/**
 * assets/js/stores.js
 *
 * Logic for the Admin Stores Management module.
 * Fetches, creates, updates, and soft-deletes stores via AJAX.
 */

$(document).ready(function() {

    let table;
    const modalElement = document.getElementById('storeModal');
    const storeModal = new bootstrap.Modal(modalElement);
    const form = $('#storeForm');

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
        table = $('#storesTable').DataTable({
            ajax: {
                url: '../api/manage_stores.php',
                type: 'GET',
                dataSrc: 'data' // Tells DataTables where the array is in the JSON response
            },
            columns: [
                { data: 'name', render: $.fn.dataTable.render.text() },
                { data: 'location', render: $.fn.dataTable.render.text() },
                {
                    data: 'created_at',
                    render: function(data) {
                        return formatDate(data);
                    }
                },
                {
                    data: null,
                    className: 'text-end',
                    orderable: false,
                    render: function(data, type, row) {
                        return `
                            <button class="btn btn-sm btn-outline-primary action-btn me-1 edit-btn" data-id="${row.id}">
                                <i class="ri-edit-line"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger action-btn delete-btn" data-id="${row.id}">
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

    // Open Add Modal
    $('#addStoreBtn').on('click', function() {
        $('#storeModalLabel').text('Προσθήκη Καταστήματος');
        form[0].reset();
        $('#storeId').val(''); // Clear ID
        storeModal.show();
    });

    // Open Edit Modal
    $('#storesTable').on('click', '.edit-btn', function() {
        const tr = $(this).closest('tr');
        const rowData = table.row(tr).data();

        $('#storeModalLabel').text('Επεξεργασία Καταστήματος');
        $('#storeId').val(rowData.id);
        $('#storeName').val(rowData.name);
        $('#storeLocation').val(rowData.location);

        storeModal.show();
    });

    // Save Store (Create or Update)
    $('#saveStoreBtn').on('click', function() {
        // Validate form
        if (!$('#storeName').val()) {
            toastr.error('Το όνομα του καταστήματος είναι υποχρεωτικό.');
            return;
        }

        const id = $('#storeId').val();
        const method = id ? 'PUT' : 'POST';

        const formData = new FormData(document.getElementById('storeForm'));
        if (id) formData.append('id', id);
        formData.append('_method', method);

        const btn = $(this);
        const originalText = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Αποθήκευση...');

        $.ajax({
            url: '../api/manage_stores.php',
            type: 'POST',
            processData: false,
            contentType: false,
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    storeModal.hide();
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

    // Delete Store
    $('#storesTable').on('click', '.delete-btn', function() {
        const id = $(this).data('id');

        Swal.fire({
            title: 'Είστε σίγουρος;',
            text: "Το κατάστημα θα διαγραφεί (Soft Delete). Οι λογαριασμοί προσωπικού που συνδέονται με αυτό θα πρέπει να εκχωρηθούν ξανά σε άλλο κατάστημα.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ναι, διαγραφή!',
            cancelButtonText: 'Ακύρωση'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '../api/manage_stores.php',
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