<?php
// vehicle_entries_admin.php - Admin Dashboard for managing vehicle data entries
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: adminlogin.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Entries Admin - Agni Car Rental</title>
    <link rel="icon" type="image/png" href="images/pnglogoagni.png">
    
    <!-- Design System Links (matching original Dashboard design) -->
    <link rel="stylesheet" type="text/css" href="css/Dashboard_styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        :root {
            --primary-amber: #FFB300;
            --accent-yellow: #FFD54F;
            --dark-charcoal: #121212;
            --surface-dark: #1E1E1E;
            --text-light: #F5F5F5;
            --text-grey: #B0B0B0;
        }

        body {
            background-color: var(--dark-charcoal);
            color: var(--text-light);
            font-family: 'Outfit', 'Poppins', sans-serif;
        }

        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 70px);
        }

        .content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }

        .stat-card {
            background: rgba(30, 30, 30, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 179, 0, 0.15);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            margin-bottom: 30px;
            max-width: 300px;
        }

        .stat-card h3 {
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-grey);
            margin-bottom: 10px;
            font-weight: 600;
        }

        .stat-card p {
            font-size: 36px;
            font-weight: 800;
            color: var(--primary-amber);
            margin: 0;
        }

        .filter-panel {
            background: rgba(30, 30, 30, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .filter-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--primary-amber);
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control, .form-select {
            background-color: rgba(18, 18, 18, 0.8) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: white !important;
            border-radius: 10px;
            padding: 10px 15px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-amber) !important;
            box-shadow: 0 0 10px rgba(255, 179, 0, 0.15) !important;
        }

        .btn-amber {
            background-color: var(--primary-amber);
            color: var(--dark-charcoal);
            font-weight: 700;
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            transition: all 0.3s ease;
        }

        .btn-amber:hover {
            background-color: var(--accent-yellow);
            box-shadow: 0 4px 12px rgba(255, 179, 0, 0.2);
            color: var(--dark-charcoal);
        }

        .btn-outline-light {
            border-radius: 10px;
            padding: 10px 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-grey);
            font-weight: 600;
        }

        .btn-outline-light:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-color: rgba(255, 255, 255, 0.2);
        }

        .table-container {
            background: rgba(30, 30, 30, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
        }

        .table {
            color: var(--text-light) !important;
            margin-bottom: 0;
        }

        .table th {
            border-bottom: 2px solid rgba(255, 179, 0, 0.3) !important;
            color: var(--primary-amber) !important;
            text-transform: uppercase;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
            padding: 15px 10px;
        }

        .table td {
            padding: 15px 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
            font-size: 14px;
            vertical-align: middle;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(255, 255, 255, 0.02);
        }

        .action-btn {
            padding: 6px 12px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 13px;
            font-weight: 600;
        }

        .btn-edit {
            background: rgba(255, 179, 0, 0.15);
            color: var(--primary-amber);
            border: 1px solid rgba(255, 179, 0, 0.3);
        }

        .btn-edit:hover {
            background: var(--primary-amber);
            color: var(--dark-charcoal);
        }

        .btn-delete {
            background: rgba(239, 83, 80, 0.15);
            color: #EF5350;
            border: 1px solid rgba(239, 83, 80, 0.3);
        }

        .btn-delete:hover {
            background: #EF5350;
            color: white;
        }

        /* Modal Customization */
        .modal-content {
            background-color: var(--surface-dark);
            border: 1px solid rgba(255, 179, 0, 0.2);
            border-radius: 20px;
            color: white;
        }

        .modal-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .modal-title {
            color: var(--primary-amber);
            font-weight: 700;
        }

        .modal-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }

        .btn-close {
            filter: invert(1);
        }
    </style>
</head>
<body>

    <!-- Top Navigation Bar -->
    <nav class="top-nav">
        <div class="logo-container">
            <img src="images/logo_rentox.png" alt="Company Logo" class="logo">
        </div>
        <h1 class="dashboard-heading">Vehicle Entries Admin</h1>
        <div class="center-nav">
            <a href="dashboard.php" class="home-btn"><i class="fas fa-home me-2"></i> Home</a>
        </div>
        <div class="right-nav">
            <form action="logout.php" method="POST" class="logout-form">
                <button type="submit" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</button>
            </form>
        </div>
    </nav>

    <div class="container-fluid separator"></div>

    <div class="dashboard-container">
        <!-- Sidebar Navigation -->
        <nav class="sidebar" id="sidebar">
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-user me-2"></i> Driver</a></li>
                <li><a href="dashboard.php?tab=cab"><i class="fas fa-taxi me-2"></i> Cab</a></li>
                <li><a href="dashboard.php?tab=booking"><i class="fas fa-calendar me-2"></i> Booking</a></li>
                <li><a href="dashboard.php?tab=completed"><i class="fas fa-calendar-check me-2"></i> Completed</a></li>
                <li><a href="dashboard.php?tab=newuser"><i class="fa-solid fa-users me-2"></i> Customers</a></li>
                <li><a href="vendor_settlements.php"><i class="fas fa-wallet me-2"></i> Settlements</a></li>
                <li class="active"><a href="vehicle_entries_admin.php"><i class="fas fa-file-invoice me-2"></i> Vehicle Entries</a></li>
                <li><a href="city_boundaries_management.php"><i class="fas fa-map-marked-alt me-2"></i> City Boundaries</a></li>
            </ul>
        </nav>

        <!-- Main Dashboard View -->
        <main class="content">
            <!-- Summary Statistic Cards -->
            <div class="stat-card">
                <h3>Total Submissions</h3>
                <p id="totalSubmissions">0</p>
            </div>

            <!-- Filters Panel -->
            <div class="filter-panel">
                <div class="filter-title"><i class="fa-solid fa-filter me-2"></i> Search & Filters</div>
                <div class="row g-3">
                    <div class="col-md-3">
                        <input type="text" id="searchCarNo" class="form-control" placeholder="Search by Car No...">
                    </div>
                    <div class="col-md-3">
                        <select id="filterCarType" class="form-select">
                            <option value="">All Car Types</option>
                            <option value="Sedan">Sedan</option>
                            <option value="SUV">SUV</option>
                            <option value="Hatchback">Hatchback</option>
                            <option value="Truck">Truck</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="text" id="searchLocation" class="form-control" placeholder="Filter by Location...">
                    </div>
                    <div class="col-md-3">
                        <select id="sortDate" class="form-select">
                            <option value="desc">Newest First</option>
                            <option value="asc">Oldest First</option>
                        </select>
                    </div>
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-12 d-flex justify-content-between align-items-center">
                        <div class="d-flex gap-2">
                            <button id="btnExport" class="btn btn-outline-light"><i class="fa-solid fa-file-csv me-2"></i> Export to CSV</button>
                            <button id="btnShareForm" class="btn btn-outline-light"><i class="fa-solid fa-share-nodes me-2"></i> Share Form Link</button>
                        </div>
                        <button id="btnRefresh" class="btn btn-amber"><i class="fa-solid fa-rotate me-2"></i> Refresh Data</button>
                    </div>
                </div>
            </div>

            <!-- Table Panel -->
            <div class="table-container">
                <table class="table table-striped table-hover text-center align-middle">
                    <thead>
                        <tr>
                            <th>Car No</th>
                            <th>Car Type</th>
                            <th>Fuel Type</th>
                            <th>Owner</th>
                            <th>Owner Mobile</th>
                            <th>Driver</th>
                            <th>Driver Mobile</th>
                            <th>Location</th>
                            <th>Submitted Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="entriesTableBody">
                        <tr>
                            <td colspan="10" class="text-muted py-4">Loading vehicle entries...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Edit Record Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="editForm">
                    <input type="hidden" id="editId" name="id">
                    <div class="modal-header">
                         <h5 class="modal-title"><i class="fa-solid fa-pen-to-square me-2"></i> Edit Vehicle Record</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Car No -->
                        <div class="mb-3">
                            <label class="form-label text-warning uppercase">Car No</label>
                            <input type="text" class="form-control" id="editCarNo" name="car_no" required>
                        </div>
                        <!-- Car Type -->
                        <div class="mb-3">
                            <label class="form-label text-warning uppercase">Car Type</label>
                            <input type="text" class="form-control" id="editCarType" name="car_type" required placeholder="e.g. sedan, hatchback, innova crista">
                        </div>
                        <!-- Fuel Type -->
                        <div class="mb-3">
                            <label class="form-label text-warning uppercase">Fuel Type</label>
                            <select class="form-select" id="editFuelType" name="fuel_type" required>
                                <option value="Petrol">Petrol</option>
                                <option value="Diesel">Diesel</option>
                                <option value="CNG">CNG</option>
                                <option value="Electric">Electric (EV)</option>
                                <option value="Hybrid">Hybrid</option>
                            </select>
                        </div>
                        <!-- Owner -->
                        <div class="mb-3">
                            <label class="form-label text-warning uppercase">Owner</label>
                            <input type="text" class="form-control" id="editOwner" name="owner" required>
                        </div>
                        <!-- Owner Mobile -->
                        <div class="mb-3">
                            <label class="form-label text-warning uppercase">Owner Mobile</label>
                            <input type="tel" class="form-control" id="editOwnerMobile" name="owner_mobile" required pattern="[6-9][0-9]{9}">
                        </div>
                        <!-- Driver -->
                        <div class="mb-3">
                            <label class="form-label text-warning uppercase">Driver</label>
                            <input type="text" class="form-control" id="editDriver" name="driver" required>
                        </div>
                        <!-- Driver Mobile -->
                        <div class="mb-3">
                            <label class="form-label text-warning uppercase">Driver Mobile</label>
                            <input type="tel" class="form-control" id="editDriverMobile" name="driver_mobile" required pattern="[6-9][0-9]{9}">
                        </div>
                        <!-- Location -->
                        <div class="mb-3">
                            <label class="form-label text-warning uppercase">Location</label>
                            <input type="text" class="form-control" id="editLocation" name="location" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-amber">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Confirm Delete Modal -->
    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content" style="background: var(--surface-dark); border: 1px solid rgba(239, 83, 80, 0.3);">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i> Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-3">
                    <p class="mb-0 text-light">Are you sure you want to delete this vehicle record? This action cannot be undone.</p>
                </div>
                <div class="modal-footer border-0 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="btnConfirmDelete">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JS Logic -->
    <script>
        // Fetch and render data
        function loadData() {
            const search = $('#searchCarNo').val();
            const type = $('#filterCarType').val();
            const locationVal = $('#searchLocation').val();
            const sort = $('#sortDate').val();

            $.getJSON('api_vehicle_entry.php', {
                action: 'list',
                search: search,
                type: type,
                location: locationVal,
                sort: sort
            }, function(res) {
                if (res.success) {
                    $('#totalSubmissions').text(res.total_submissions);
                    renderTable(res.data);
                } else {
                    $('#entriesTableBody').html(`<tr><td colspan="9" class="text-danger py-4">${res.message}</td></tr>`);
                }
            }).fail(function() {
                $('#entriesTableBody').html(`<tr><td colspan="9" class="text-danger py-4">Failed to load entries from server.</td></tr>`);
            });
        }

        function renderTable(data) {
            let html = '';
            if (data.length === 0) {
                html = `<tr><td colspan="10" class="text-muted py-4">No matching records found.</td></tr>`;
            } else {
                data.forEach(item => {
                    const formattedDate = new Date(item.created_at).toLocaleString('en-IN', {
                        dateStyle: 'medium',
                        timeStyle: 'short'
                    });

                    html += `
                        <tr id="row-${item.id}">
                            <td><strong>${item.car_no}</strong></td>
                            <td><span class="badge bg-secondary">${item.car_type}</span></td>
                            <td><span class="badge bg-info text-dark">${item.fuel_type || 'N/A'}</span></td>
                            <td>${item.owner}</td>
                            <td>${item.owner_mobile}</td>
                            <td>${item.driver}</td>
                            <td>${item.driver_mobile}</td>
                            <td>${item.location}</td>
                            <td>${formattedDate}</td>
                            <td>
                                <button class="action-btn btn-edit me-1" onclick="openEditModal(${JSON.stringify(item).replace(/"/g, '&quot;')})">
                                    <i class="fa-solid fa-pen-to-square"></i> Edit
                                </button>
                                <button class="action-btn btn-delete" onclick="deleteRecord(${item.id})">
                                    <i class="fa-solid fa-trash-can"></i> Delete
                                </button>
                            </td>
                        </tr>
                    `;
                });
            }
            $('#entriesTableBody').html(html);
        }

        // Search and Filter Listeners
        $('#searchCarNo, #searchLocation').on('input', loadData);
        $('#filterCarType, #sortDate').on('change', loadData);
        $('#btnRefresh').on('click', loadData);

        // Export to CSV
        $('#btnExport').on('click', function() {
            window.location.href = 'api_vehicle_entry.php?action=export';
        });

        // Copy Shareable Form Link
        $('#btnShareForm').on('click', function() {
            const link = "https://agnicarrental.com/admin2025/vehicle_onboard.php";
            navigator.clipboard.writeText(link).then(function () {
                alert("Shareable onboarding form link copied to clipboard!");
            }).catch(function (err) {
                console.error("Could not copy link: ", err);
            });
        });

        // Edit Modal Opening
        function openEditModal(item) {
            $('#editId').val(item.id);
            $('#editCarNo').val(item.car_no);
            $('#editCarType').val(item.car_type);
            $('#editFuelType').val(item.fuel_type || 'Petrol');
            $('#editOwner').val(item.owner);
            $('#editOwnerMobile').val(item.owner_mobile);
            $('#editDriver').val(item.driver);
            $('#editDriverMobile').val(item.driver_mobile);
            $('#editLocation').val(item.location);
            
            const editModal = new bootstrap.Modal(document.getElementById('editModal'));
            editModal.show();
        }

        // Edit Submission
        $('#editForm').on('submit', function(e) {
            e.preventDefault();
            const data = $(this).serialize() + '&action=edit';

            $.post('api_vehicle_entry.php', data, function(res) {
                if (res.success) {
                    bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
                    alert(res.message);
                    loadData();
                } else {
                    alert('Error: ' + res.message);
                }
            }, 'json').fail(function() {
                alert('Connection error. Failed to edit record.');
            });
        });

        // Delete record confirmation
        let recordToDelete = null;
        function deleteRecord(id) {
            recordToDelete = id;
            const confirmModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
            confirmModal.show();
        }

        $('#btnConfirmDelete').on('click', function() {
            if (!recordToDelete) return;
            const btn = $(this);
            btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-2"></i> Deleting...');

            $.post('api_vehicle_entry.php', {
                action: 'delete',
                id: recordToDelete
            }, function(res) {
                btn.prop('disabled', false).text('Delete');
                bootstrap.Modal.getInstance(document.getElementById('confirmDeleteModal')).hide();
                
                if (res.success) {
                    $(`#row-${recordToDelete}`).fadeOut(300, function() {
                        $(this).remove();
                        // Update count manually
                        const current = parseInt($('#totalSubmissions').text());
                        if (current > 0) $('#totalSubmissions').text(current - 1);
                    });
                    recordToDelete = null;
                } else {
                    alert('Error: ' + res.message);
                }
            }, 'json').fail(function() {
                btn.prop('disabled', false).text('Delete');
                bootstrap.Modal.getInstance(document.getElementById('confirmDeleteModal')).hide();
                alert('Connection error. Failed to delete record.');
            });
        });

        // Initial Load
        $(document).ready(loadData);
    </script>
</body>
</html>
