/**
 * assets/js/dashboard.js
 *
 * Logic for the Admin Dashboard.
 * Fetches data from api/get_analytics.php, initializes Chart.js, and populates DataTables.
 */

$(document).ready(function() {

    const themeToggleBtn = $('#themeToggleBtn');
    const loadingOverlay = $('#loadingOverlay');
    let activityChartInstance = null;
    let activityTableInstance = null;

    // 1. Theming Logic
    themeToggleBtn.on('click', function() {
        const html = $('html');
        const isDark = html.attr('data-theme') === 'dark';
        html.attr('data-theme', isDark ? 'light' : 'dark');

        const icon = isDark ? '<i class="ri-moon-line fs-5"></i>' : '<i class="ri-sun-line fs-5"></i>';
        $(this).html(icon);

        // Re-render chart to match theme colors if initialized
        if(activityChartInstance) {
            updateChartTheme(isDark ? 'light' : 'dark');
        }
    });

    // 2. Fetch Data and Initialize
    function loadDashboardData() {
        $.ajax({
            url: '../api/get_analytics.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    populateStatCards(response.data.metrics);
                    initChart(response.data.chart_data);
                    initTable(response.data.recent_activity);
                } else {
                    showError(response.message);
                }
            },
            error: function(xhr) {
                let msg = 'Σφάλμα φόρτωσης δεδομένων.';
                if(xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                showError(msg);
            },
            complete: function() {
                // Fade out loading screen
                loadingOverlay.fadeOut('slow', function() {
                    $(this).remove();
                });
            }
        });
    }

    // 3. Populate Stat Cards
    function populateStatCards(metrics) {
        $('#statActivations').text(metrics.total_activations.toLocaleString('el-GR'));
        $('#statConfirmations').text(metrics.total_confirmations.toLocaleString('el-GR'));
        $('#statConversion').text(metrics.conversion_rate + '%');
    }

    // 4. Initialize Chart.js
    function initChart(chartData) {
        const ctx = document.getElementById('activityChart').getContext('2d');

        const isDark = $('html').attr('data-theme') === 'dark';
        const textColor = isDark ? '#f8fafc' : '#1e293b';
        const gridColor = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)';

        const config = {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [
                    {
                        label: 'Ενεργοποιήσεις',
                        data: chartData.activations,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#3b82f6',
                        pointRadius: 4
                    },
                    {
                        label: 'Εξαργυρώσεις',
                        data: chartData.confirmations,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#10b981',
                        pointRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { color: textColor, font: { family: "'Inter', sans-serif" } }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        titleFont: { family: "'Inter', sans-serif" },
                        bodyFont: { family: "'Inter', sans-serif" }
                    }
                },
                scales: {
                    x: {
                        grid: { color: gridColor, drawBorder: false },
                        ticks: { color: textColor, font: { family: "'Inter', sans-serif" } }
                    },
                    y: {
                        grid: { color: gridColor, drawBorder: false },
                        ticks: { color: textColor, font: { family: "'Inter', sans-serif" }, precision: 0 },
                        beginAtZero: true
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        };

        activityChartInstance = new Chart(ctx, config);
    }

    // Update Chart colors on theme toggle
    function updateChartTheme(theme) {
        if(!activityChartInstance) return;
        const textColor = theme === 'dark' ? '#f8fafc' : '#1e293b';
        const gridColor = theme === 'dark' ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)';

        activityChartInstance.options.plugins.legend.labels.color = textColor;
        activityChartInstance.options.scales.x.ticks.color = textColor;
        activityChartInstance.options.scales.x.grid.color = gridColor;
        activityChartInstance.options.scales.y.ticks.color = textColor;
        activityChartInstance.options.scales.y.grid.color = gridColor;

        activityChartInstance.update();
    }

    // 5. Initialize DataTables
    function initTable(activityData) {
        // Transform data for DataTables
        const tableData = activityData.map(item => {

            // Format Date
            const date = new Date(item.created_at);
            const dateStr = date.toLocaleDateString('el-GR') + ' ' + date.toLocaleTimeString('el-GR', {hour: '2-digit', minute:'2-digit'});

            // Format Badge for action
            let actionBadge = '';
            if (item.event_type === 'activated') {
                actionBadge = '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle rounded-pill px-3"><i class="ri-flashlight-line me-1"></i>Ενεργοποίηση</span>';
            } else if (item.event_type === 'confirmed') {
                actionBadge = '<span class="badge bg-success bg-opacity-10 text-success border border-success-subtle rounded-pill px-3"><i class="ri-check-double-line me-1"></i>Εξαργύρωση</span>';
            } else {
                actionBadge = `<span class="badge bg-secondary rounded-pill px-3">${item.event_type}</span>`;
            }

            // Truncate UUID for display
            const shortUuid = item.uuid.substring(0, 8) + '...';

            return [
                dateStr,
                actionBadge,
                item.campaign_title || '-',
                item.store_name || '-',
                `<span class="font-monospace text-muted" title="${item.uuid}">${shortUuid}</span>`
            ];
        });

        activityTableInstance = $('#activityTable').DataTable({
            data: tableData,
            order: [[0, "desc"]], // Sort by date descending
            columnDefs: [
                { targets: 2, render: $.fn.dataTable.render.text() },
                { targets: 3, render: $.fn.dataTable.render.text() }
            ],
            pageLength: 10,
            language: {
                "sProcessing":   "Επεξεργασία...",
                "sLengthMenu":   "Δείξε _MENU_ εγγραφές",
                "sZeroRecords":  "Δεν βρέθηκαν εγγραφές",
                "sInfo":         "Δείχνοντας _START_ εως _END_ από _TOTAL_ εγγραφές",
                "sInfoEmpty":    "Δείχνοντας 0 εως 0 από 0 εγγραφές",
                "sInfoFiltered": "(φιλτραρισμένες από _MAX_ συνολικά εγγραφές)",
                "sInfoPostFix":  "",
                "sSearch":       "Αναζήτηση:",
                "sUrl":          "",
                "oPaginate": {
                    "sFirst":    "Πρώτη",
                    "sPrevious": "Προηγούμενη",
                    "sNext":     "Επόμενη",
                    "sLast":     "Τελευταία"
                }
            },
            dom: '<"row align-items-center mb-3"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                 '<"row"<"col-sm-12"tr>>' +
                 '<"row mt-3"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        });
    }

    // Helper: Error SweetAlert
    function showError(msg) {
        Swal.fire({
            title: 'Σφάλμα',
            text: msg,
            icon: 'error',
            confirmButtonColor: '#ef4444'
        });
    }

    // Start the process
    loadDashboardData();

});