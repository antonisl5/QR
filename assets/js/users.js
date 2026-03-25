/**
 * assets/js/users.js
 * JavaScript logic for User & Role Management CRUD operations.
 * Handles DataTables initialization, dynamic modal fields, AJAX requests,
 * and SweetAlert2 confirmations.
 */

$(document).ready(function () {
    // ------------------------------------------------------------------------
    // INIT DATATABLES
    // ------------------------------------------------------------------------
    var usersTable = $('#usersTable').DataTable({
        "ajax": {
            "url": "../api/manage_users.php",
            "type": "GET",
            "dataSrc": function (json) {
                if (!json.success) {
                    toastr.error(json.message || "Failed to load users");
                    return [];
                }
                return json.data;
            }
        },
        "columns": [
            { "data": "id" },
            {
                "data": "username",
                "render": $.fn.dataTable.render.text() // Prevent XSS
            },
            {
                "data": "email",
                "render": $.fn.dataTable.render.text() // Prevent XSS
            },
            {
                "data": "role",
                "render": function(data) {
                    let text = data === 'admin' ? 'Admin' : (data === 'campaign_owner' ? 'Campaign Owner' : 'Store Staff');
                    let badgeClass = data === 'admin' ? 'badge-role-admin' : (data === 'campaign_owner' ? 'badge-role-campaign' : 'badge-role-staff');
                    return `<span class="badge ${badgeClass} rounded-pill px-3 py-2">${text}</span>`;
                }
            },
            {
                "data": "store_name",
                "render": function(data, type, row) {
                    if (row.role === 'store_staff') {
                        return data ? `<i class="ri-store-2-line me-1 text-secondary"></i> ${$.fn.dataTable.render.text().display(data)}` : '<span class="text-danger">Ορφανό Κατάστημα</span>';
                    }
                    return '<span class="text-muted">-</span>';
                }
            },
            {
                "data": "is_active",
                "render": function(data) {
                    let text = data == 1 ? 'Ενεργός' : 'Ανενεργός';
                    let badgeClass = data == 1 ? 'badge-status-active' : 'badge-status-inactive';
                    return `<span class="badge ${badgeClass}">${text}</span>`;
                }
            },
            {
                "data": null,
                "orderable": false,
                "className": "text-end",
                "render": function (data, type, row) {
                    let actions = `
                        <button class="btn btn-sm btn-outline-primary rounded-circle edit-btn" title="Επεξεργασία" data-id="${row.id}" data-username="${row.username}" data-email="${row.email}" data-role="${row.role}" data-storeid="${row.store_id}" data-isactive="${row.is_active}">
                            <i class="ri-edit-2-line"></i>
                        </button>
                    `;

                    // Don't show delete button for active user if they are admin ID 1 (safety in UI)
                    if (row.id != 1) {
                         actions += `
                            <button class="btn btn-sm btn-outline-danger rounded-circle ms-1 delete-btn" title="${row.is_active == 1 ? 'Απενεργοποίηση' : 'Ανενεργός'}" data-id="${row.id}" ${row.is_active == 0 ? 'disabled' : ''}>
                                <i class="ri-user-unfollow-line"></i>
                            </button>
                         `;
                    }
                    return actions;
                }
            }
        ],
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/el.json"
        },
        "responsive": true,
        "order": [[ 0, "desc" ]] // Sort by newest by default
    });

    // ------------------------------------------------------------------------
    // PRELOAD STORES FOR DROPDOWN
    // ------------------------------------------------------------------------
    function loadStores() {
        $.ajax({
            url: '../api/manage_stores.php', // Existing API
            type: 'GET',
            success: function(response) {
                if(response.success && response.data) {
                    let options = '<option value="" disabled selected>Επιλέξτε Κατάστημα</option>';
                    response.data.forEach(function(store) {
                         options += `<option value="${store.id}">${$('<div>').text(store.name).html()}</option>`;
                    });
                    $('#store_id').html(options);
                }
            },
            error: function() {
                toastr.error('Αποτυχία φόρτωσης λίστας καταστημάτων.');
            }
        });
    }

    loadStores();

    // ------------------------------------------------------------------------
    // DYNAMIC UI: ROLE -> STORE TOGGLE
    // ------------------------------------------------------------------------
    $('#role').on('change', function() {
        let role = $(this).val();
        if (role === 'store_staff') {
            $('#storeSelectWrapper').slideDown(300);
            $('#store_id').attr('required', true);
        } else {
            $('#storeSelectWrapper').slideUp(300);
            $('#store_id').removeAttr('required').val('');
        }
    });

    // ------------------------------------------------------------------------
    // ADD / EDIT MODAL LOGIC
    // ------------------------------------------------------------------------
    window.openUserModal = function () {
        $('#userForm')[0].reset();
        $('#userId').val('');
        $('#userModalLabel').text('Προσθήκη Χρήστη');

        // Reset dynamic elements
        $('#storeSelectWrapper').hide();
        $('#store_id').removeAttr('required').val('');

        // Password logic for New User
        $('#password').attr('required', true).attr('placeholder', 'Εισάγετε ισχυρό κωδικό');
        $('#passwordRequiredAsterisk').show();
        $('#passwordHint').text('Απαιτούνται τουλάχιστον 8 χαρακτήρες.');
        $('#statusWrapper').hide(); // Hide status toggle on add

        var myModal = new bootstrap.Modal(document.getElementById('userModal'));
        myModal.show();
    };

    // Edit Button Click
    $('#usersTable tbody').on('click', '.edit-btn', function () {
        let id = $(this).data('id');
        let username = $(this).data('username');
        let email = $(this).data('email');
        let role = $(this).data('role');
        let storeId = $(this).data('storeid');
        let isActive = $(this).data('isactive');

        $('#userId').val(id);
        $('#username').val(username);
        $('#email').val(email);
        $('#role').val(role).trigger('change'); // trigger to handle store select visibility

        // Password logic for Edit (Optional)
        $('#password').removeAttr('required').val('').attr('placeholder', 'Αφήστε κενό για διατήρηση');
        $('#passwordRequiredAsterisk').hide();
        $('#passwordHint').text('Προαιρετικό. Απαιτούνται τουλάχιστον 8 χαρακτήρες αν αλλαχθεί.');

        // Status toggle
        $('#statusWrapper').show();
        $('#isActive').prop('checked', isActive == 1);

        // Pre-select store if store_staff
        if(role === 'store_staff' && storeId) {
            $('#store_id').val(storeId);
        }

        $('#userModalLabel').text('Επεξεργασία Χρήστη');
        var myModal = new bootstrap.Modal(document.getElementById('userModal'));
        myModal.show();
    });

    // Save Button Click (Create/Update)
    $('#saveUserBtn').on('click', function () {
        // Basic Client-Side Validation
        if (!$('#userForm')[0].checkValidity()) {
            $('#userForm')[0].reportValidity();
            return;
        }

        let userId = $('#userId').val();
        let isEdit = userId !== '';
        let method = isEdit ? 'PUT' : 'POST';

        // Build JSON Payload manually due to PUT requirement and checkbox logic
        let payload = {
            username: $('#username').val(),
            email: $('#email').val(),
            role: $('#role').val(),
            password: $('#password').val() // Might be empty on edit
        };

        if ($('#role').val() === 'store_staff') {
            payload.store_id = $('#store_id').val();
        }

        if (isEdit) {
            payload.id = userId;
            payload.is_active = $('#isActive').is(':checked') ? 1 : 0;
            // Remove password from payload if empty during edit
            if (!payload.password) {
                delete payload.password;
            }
        }

        $.ajax({
            url: '../api/manage_users.php',
            type: method,
            contentType: 'application/json',
            data: JSON.stringify(payload),
            success: function (response) {
                if (response.success) {
                    toastr.success(response.message);
                    let modal = bootstrap.Modal.getInstance(document.getElementById('userModal'));
                    modal.hide();
                    usersTable.ajax.reload(null, false); // Reload without resetting pagination
                } else {
                    toastr.error(response.message || 'Σφάλμα κατά την αποθήκευση.');
                }
            },
            error: function (xhr) {
                let res = xhr.responseJSON;
                toastr.error((res && res.message) ? res.message : 'Αποτυχία επικοινωνίας με τον διακομιστή.');
            }
        });
    });

    // ------------------------------------------------------------------------
    // DEACTIVATE BUTTON (SOFT DELETE)
    // ------------------------------------------------------------------------
    $('#usersTable tbody').on('click', '.delete-btn', function () {
        let userId = $(this).data('id');

        Swal.fire({
            title: 'Απενεργοποίηση Χρήστη;',
            text: "Ο χρήστης δεν θα διαγραφεί από τη βάση (για λόγους ιστορικού), αλλά δεν θα μπορεί πλέον να συνδεθεί στο σύστημα.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ναι, Απενεργοποίηση',
            cancelButtonText: 'Ακύρωση'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '../api/manage_users.php',
                    type: 'DELETE',
                    contentType: 'application/json',
                    data: JSON.stringify({ id: userId }),
                    success: function (response) {
                        if (response.success) {
                            Swal.fire(
                                'Απενεργοποιήθηκε!',
                                response.message,
                                'success'
                            );
                            usersTable.ajax.reload(null, false);
                        } else {
                            Swal.fire('Σφάλμα', response.message, 'error');
                        }
                    },
                    error: function (xhr) {
                        let res = xhr.responseJSON;
                        Swal.fire('Αποτυχία', (res && res.message) ? res.message : 'Παρουσιάστηκε σφάλμα κατά την απενεργοποίηση.', 'error');
                    }
                });
            }
        });
    });

    // ------------------------------------------------------------------------
    // THEME TOGGLE (Dark/Light Mode)
    // ------------------------------------------------------------------------
    const themeToggle = document.getElementById('themeToggle');
    const body = document.body;

    // Check localStorage for saved theme
    if (localStorage.getItem('theme') === 'dark') {
        body.classList.add('dark-mode');
        themeToggle.innerHTML = '<i class="ri-sun-line"></i>';
    }

    themeToggle.addEventListener('click', () => {
        body.classList.toggle('dark-mode');
        if (body.classList.contains('dark-mode')) {
            localStorage.setItem('theme', 'dark');
            themeToggle.innerHTML = '<i class="ri-sun-line"></i>';
        } else {
            localStorage.setItem('theme', 'light');
            themeToggle.innerHTML = '<i class="ri-moon-line"></i>';
        }
    });
});
