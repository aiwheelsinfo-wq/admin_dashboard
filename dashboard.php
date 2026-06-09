<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: adminlogin.php");
    exit();
}
require_once __DIR__ . '/db_connect.php';

// Fetch active partners count
$partner_res = mysqli_query($conn, "SELECT COUNT(*) AS count FROM partners WHERE status = 'active'");
$active_partners_count = mysqli_fetch_assoc($partner_res)['count'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Agni Car Rental</title>
    <link rel="icon" type="image/png" href="images/pnglogoagni.png">
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom Stylesheet -->
    <link rel="stylesheet" type="text/css" href="css/Dashboard_styles.css?v=2.0">
    
    <!-- Core JS dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Maps API -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBz4vqQWuT-s_3UEWk6pnSMxSIt7QOZEqk&libraries=places"></script>
</head>
<body>

    <!-- Sticky Compact Header -->
    <header class="top-header">
        <div class="header-left">
            <button id="sidebarToggle" class="btn-sidebar-toggle" aria-label="Toggle sidebar">
                <i class="fas fa-bars"></i>
            </button>
            <div class="header-brand">
                <img src="images/logo.png" alt="Company Logo" class="brand-logo">
            </div>
            
            <!-- Global Search in Header -->
            <div class="header-search d-none d-md-flex">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="globalSearchInput" class="form-control" placeholder="Search everywhere...">
            </div>
        </div>
        
        <div class="header-right">
            <!-- Search Toggle for mobile screens -->
            <button class="btn btn-icon d-md-none" id="mobileSearchToggle" aria-label="Search">
                <i class="fas fa-search"></i>
            </button>

            <!-- Notification Bell -->
            <div class="dropdown">
                <button class="btn btn-icon position-relative" id="notificationBell" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="far fa-bell"></i>
                    <span class="notification-dot" id="headerNotificationDot" style="display: none;"></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationBell">
                    <li><h6 class="dropdown-header">Notifications</h6></li>
                    <li><hr class="dropdown-divider"></li>
                    <div id="notificationList">
                        <li><a class="dropdown-item text-muted text-center" href="#">No new notifications</a></li>
                    </div>
                </ul>
            </div>
            
            <!-- Admin Profile -->
            <div class="admin-profile">
                <div class="admin-info d-none d-sm-block">
                    <div class="admin-name">Agni Admin</div>
                    <div class="admin-role">Super Administrator</div>
                </div>
                <img src="https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=80&h=80&q=80" alt="Admin Avatar" class="admin-avatar">
            </div>
        </div>
    </header>

    <!-- Mobile Search overlay -->
    <div class="mobile-search-bar d-none" id="mobileSearchBar">
        <input type="text" id="globalSearchInputMobile" class="form-control" placeholder="Search everywhere...">
        <button class="btn btn-close-search" id="closeMobileSearch"><i class="fas fa-times"></i></button>
    </div>

    <!-- Dashboard Main Layout -->
    <div class="dashboard-wrapper">
        
        <!-- Scrollable Collapsible Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-menu-container">
                <ul class="sidebar-menu">
                    <li>
                        <a href="dashboard.php" class="menu-item active" id="btnDashboard" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                            <i class="fas fa-th-large"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="menu-item" id="driver" data-bs-toggle="tooltip" data-bs-placement="right" title="Drivers">
                            <i class="fas fa-users-cog"></i>
                            <span>Drivers</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="menu-item" id="cab" data-bs-toggle="tooltip" data-bs-placement="right" title="Cars">
                            <i class="fas fa-car"></i>
                            <span>Cars</span>
                        </a>
                    </li>
                    <li>
                        <a href="https://agnicarrental.com/admin2025/dashboard.php" class="menu-item" id="booking" data-bs-toggle="tooltip" data-bs-placement="right" title="Bookings">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Bookings</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="menu-item" id="Complete" data-bs-toggle="tooltip" data-bs-placement="right" title="Completed Trips">
                            <i class="fas fa-calendar-check"></i>
                            <span>Completed Trips</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="menu-item" id="newuser" data-bs-toggle="tooltip" data-bs-placement="right" title="Customers">
                            <i class="fas fa-users"></i>
                            <span>Customers</span>
                            <span id="newuserCount" class="badge bg-danger ms-auto" style="display: none;">0</span>
                        </a>
                    </li>
                    <li>
                        <a href="https://agnicarrental.com/admin2025/bookacall/admin-bookings.php" class="menu-item" id="bookacall" data-bs-toggle="tooltip" data-bs-placement="right" title="Book A Call">
                            <i class="fas fa-phone-alt"></i>
                            <span>Book A Call</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="menu-item" id="Blocked_Customer" data-bs-toggle="tooltip" data-bs-placement="right" title="Blocked Customers">
                            <i class="fas fa-user-slash"></i>
                            <span>Blocked Customers</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="menu-item" id="Extract_Data" data-bs-toggle="tooltip" data-bs-placement="right" title="Extract Data">
                            <i class="fas fa-file-excel"></i>
                            <span>Extract Data</span>
                        </a>
                    </li>
                    <li>
                        <a href="partner/index.php" class="menu-item" id="partner_api" data-bs-toggle="tooltip" data-bs-placement="right" title="Partner API">
                            <i class="fas fa-handshake"></i>
                            <span>Partner API</span>
                        </a>
                    </li>
                    <li>
                        <a href="partner/monitor.php" class="menu-item" id="partner_monitor" data-bs-toggle="tooltip" data-bs-placement="right" title="Partner Monitor">
                            <i class="fas fa-desktop"></i>
                            <span>Partner Monitor</span>
                        </a>
                    </li>
                    <li>
                        <a href="car_categories.php" class="menu-item" id="car_categories_menu" data-bs-toggle="tooltip" data-bs-placement="right" title="Car Categories">
                            <i class="fas fa-tags"></i>
                            <span>Car Categories</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="menu-item" id="menuSettings" data-bs-toggle="tooltip" data-bs-placement="right" title="Settings">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="menu-item" id="menuReports" data-bs-toggle="tooltip" data-bs-placement="right" title="Reports">
                            <i class="fas fa-chart-pie"></i>
                            <span>Reports</span>
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Fixed Bottom Sidebar Logout -->
            <div class="sidebar-footer">
                <a href="logout.php" class="menu-item logout-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Logout">
                    <i class="fas fa-sign-out-alt text-danger"></i>
                    <span>Logout</span>
                </a>
            </div>
        </nav>

        <!-- Main Workspace Area -->
        <main class="content">
            
            <!-- Modern Gradient KPI Grid -->
            <div class="kpi-grid">
                
                <!-- Card 1: Total Drivers -->
                <div class="kpi-card purple" id="driverCard">
                    <div class="kpi-card-content">
                        <span class="kpi-title">Total Drivers</span>
                        <h2 class="kpi-value" id="driverCount">0</h2>
                    </div>
                    <div class="kpi-icon"><i class="fas fa-users-cog"></i></div>
                </div>
                
                <!-- Card 2: Total Cars -->
                <div class="kpi-card blue" id="carCard">
                    <div class="kpi-card-content">
                        <span class="kpi-title">Total Cars</span>
                        <h2 class="kpi-value" id="carCount">0</h2>
                    </div>
                    <div class="kpi-icon"><i class="fas fa-car"></i></div>
                </div>
                
                <!-- Card 3: Bookings Today -->
                <div class="kpi-card green" id="latestBookingCard">
                    <div class="kpi-card-content">
                        <span class="kpi-title">Bookings Today</span>
                        <h2 class="kpi-value" id="bookingsTodayCount">0</h2>
                    </div>
                    <div class="kpi-icon"><i class="fas fa-calendar-day"></i></div>
                </div>
                
                <!-- Card 4: Active Trips -->
                <div class="kpi-card orange" id="onrideCard">
                    <div class="kpi-card-content">
                        <span class="kpi-title">Active Trips</span>
                        <h2 class="kpi-value" id="onRideCount">0</h2>
                    </div>
                    <div class="kpi-icon"><i class="fas fa-route"></i></div>
                </div>
                
                <!-- Card 5: Pending Approval -->
                <div class="kpi-card pink" id="waitingforapprovalCard">
                    <div class="kpi-card-content">
                        <span class="kpi-title">Pending Approval</span>
                        <h2 class="kpi-value" id="waitingforapprovalCount">0</h2>
                    </div>
                    <div class="kpi-icon"><i class="fas fa-hourglass-half"></i></div>
                </div>
                
                <!-- Card 6: Revenue Today -->
                <div class="kpi-card emerald" id="cardRevenueToday">
                    <div class="kpi-card-content">
                        <span class="kpi-title">Revenue Today</span>
                        <h2 class="kpi-value" id="revenueTodayCount">₹0</h2>
                    </div>
                    <div class="kpi-icon"><i class="fas fa-rupee-sign"></i></div>
                </div>
                
                <!-- Card 7: Revenue This Month -->
                <div class="kpi-card indigo" id="cardRevenueMonth">
                    <div class="kpi-card-content">
                        <span class="kpi-title">Revenue This Month</span>
                        <h2 class="kpi-value" id="revenueMonthCount">₹0</h2>
                    </div>
                    <div class="kpi-icon"><i class="fas fa-wallet"></i></div>
                </div>
                
                <!-- Card 8: Partner Companies -->
                <div class="kpi-card slate" id="partnerCompaniesCard" onclick="window.location.href='partner/monitor.php'">
                    <div class="kpi-card-content">
                        <span class="kpi-title">Partner Companies</span>
                        <h2 class="kpi-value" id="partnerCompaniesCount"><?= $active_partners_count ?></h2>
                    </div>
                    <div class="kpi-icon"><i class="fas fa-handshake"></i></div>
                </div>
                
            </div>

            <!-- Booking List Container -->
            <div class="table-container shadow-sm border-0" id="bookingTableContainer">
                <div class="table-container-header">
                    <div class="d-flex align-items-center gap-3">
                        <button id="refreshBookings" class="btn btn-icon-sm" title="Refresh Bookings">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                        <h4 class="mb-0 table-title" id="bookingHeading">Booking List</h4>
                    </div>
                    
                    <!-- Trip Type Filters (Tabs) -->
                    <ul class="nav nav-tabs-modern" id="bookingTabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-filter="all" type="button">All</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="oneway-tab" data-bs-toggle="tab" data-filter="One-way" type="button">One Way</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="Round-Trip-tab" data-bs-toggle="tab" data-filter="Round-Trip" type="button">Round Trip</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="localtaxi-tab" data-bs-toggle="tab" data-filter="Local-taxi" type="button">Local Taxi</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="localduty-tab" data-bs-toggle="tab" data-filter="Local-Duty" type="button">Local Duty</button>
                        </li>
                    </ul>
                </div>
                
                <!-- Advanced Filtering Toolbar -->
                <div class="filter-toolbar">
                    <div class="search-box-wrapper">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="bookingSearchInput" class="form-control" placeholder="Search bookings...">
                    </div>
                    
                    <div class="filter-inputs">
                        <div class="input-group-wrapper">
                            <i class="far fa-calendar-alt input-icon"></i>
                            <input type="date" id="bookingFilterDate" class="form-control" title="Filter by date">
                        </div>
                        <div class="input-group-wrapper">
                            <i class="fas fa-info-circle input-icon"></i>
                            <select id="bookingFilterStatus" class="form-select" title="Filter by status">
                                <option value="">All Statuses</option>
                                <option value="Pending">Pending</option>
                                <option value="Accepted">Accepted</option>
                                <option value="Started">Started</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                                <option value="Not Confirmed">Not Confirmed</option>
                            </select>
                        </div>
                        <button id="btnExportBookings" class="btn btn-export-excel">
                            <i class="fas fa-file-excel"></i> <span>Export CSV</span>
                        </button>
                    </div>
                </div>
                
                <!-- Sticky Header Table -->
                <div class="table-responsive-wrapper">
                    <table class="table-modern text-center">
                        <thead>
                            <tr>
                                <th>Sr.No</th>
                                <th>Booking Id</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Route</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Customer</th>
                                <th>Mobile</th>
                                <th>Status</th>
                                <th>Confirmation</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody id="bookingTable">
                            <!-- Injected dynamically -->
                        </tbody>
                    </table>
                </div>
                <div id="bookingTablePagination" class="pagination-wrapper mt-3"></div>
            </div>

            <!-- Driver List Container -->
            <div class="table-container hidden shadow-sm border-0" id="driverTableContainer">
                <div class="table-container-header">
                    <div class="d-flex align-items-center gap-3">
                        <button id="refreshDriver" class="btn btn-icon-sm" title="Refresh Driver">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                        <h4 class="mb-0 table-title">Driver List</h4>
                    </div>
                    <button onclick="window.open('show_map.php', '_blank')" class="btn btn-modern-primary btn-sm"><i class="fas fa-map-marked-alt"></i> Show Map</button>
                </div>
                
                <div class="filter-toolbar">
                    <div class="search-box-wrapper w-100" style="max-width: 320px;">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="driverSearchInput" class="form-control" placeholder="Search drivers...">
                    </div>
                </div>
                
                <div class="table-responsive-wrapper">
                    <table class="table-modern text-center">
                        <thead>
                            <tr>
                                <th>Driver ID</th>
                                <th>Phone Number</th>
                                <th>Full Name</th>
                                <th>Date of Install</th>
                                <th>Driver City</th>
                                <th>Licence Doe</th>
                                <th>Car details</th>
                                <th>Action</th>
                                <th>Status</th>
                                <th>Confirmation</th>
                            </tr>
                        </thead>
                        <tbody id="driverTable"></tbody>
                    </table>
                </div>
                <div id="driverTablePagination" class="pagination-wrapper mt-3"></div>
            </div>

            <!-- Car List Container -->
            <div class="table-container hidden shadow-sm border-0" id="carTableContainer">
                <div class="table-container-header">
                    <div class="d-flex align-items-center gap-3">
                        <button id="refreshCar" class="btn btn-icon-sm" title="Refresh Car">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                        <h4 class="mb-0 table-title">Car List</h4>
                    </div>
                </div>
                
                <div class="filter-toolbar">
                    <div class="search-box-wrapper w-100" style="max-width: 320px;">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="carSearchInput" class="form-control" placeholder="Search car...">
                    </div>
                </div>
                
                <div class="table-responsive-wrapper">
                    <table class="table-modern text-center">
                        <thead>
                            <tr>
                                <th>Car Id</th>
                                <th>Vehicle Number</th>
                                <th>Vehicle Type</th>
                                <th>Vehicle Name</th>
                                <th>Fuel Type</th>
                                <th>Owner Number</th>
                                <th>City</th>
                                <th>Action</th>
                                <th>Status</th>
                                <th>Confirmation</th>
                            </tr>
                        </thead>
                        <tbody id="carTable"></tbody>
                    </table>
                </div>
                <div id="carTablePagination" class="pagination-wrapper mt-3"></div>
            </div>

            <!-- Waiting for Approval Container -->
            <div class="table-container hidden shadow-sm border-0" id="waitingforapprovalTableContainer">
                <div class="table-container-header">
                    <div class="d-flex align-items-center gap-3">
                        <button id="refreshwaitingforapproval" class="btn btn-icon-sm" title="Refresh Documents">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                        <h4 class="mb-0 table-title">Document List</h4>
                    </div>
                </div>
                
                <div class="filter-toolbar">
                    <div class="search-box-wrapper w-100" style="max-width: 320px;">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="waitingforapprovalSearchInput" class="form-control" placeholder="Search Document...">
                    </div>
                </div>
                
                <div class="table-responsive-wrapper">
                    <table class="table-modern text-center">
                        <thead>
                            <tr>
                                <th>Id</th>
                                <th>Vehicle Number</th>
                                <th>Owner Number</th>
                                <th>Insurance</th>
                                <th>Puc</th>
                                <th>Taxi Permit</th>
                                <th>Fitness Certificate</th>
                                <th>Status</th>
                                <th>Confirmation</th>
                            </tr>
                        </thead>
                        <tbody id="waitingforapprovalTable"></tbody>
                    </table>
                </div>
                <div id="waitingforapprovalTablePagination" class="pagination-wrapper mt-3"></div>
            </div>
            
            <!-- On Ride Table Container -->
            <div class="table-container hidden shadow-sm border-0" id="onrideTableContainer">
                <div class="table-container-header">
                    <div class="d-flex align-items-center gap-3">
                        <button id="refreshonRide" class="btn btn-icon-sm" title="Refresh onRide">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                        <h4 class="mb-0 table-title">On Ride Vehicle</h4>
                    </div>
                </div>
                
                <div class="filter-toolbar">
                    <div class="search-box-wrapper w-100" style="max-width: 320px;">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="onrideSearchInput" class="form-control" placeholder="Search drivers...">
                    </div>
                </div>
                
                <div class="table-responsive-wrapper">
                    <table class="table-modern text-center">
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Driver Name</th>
                                <th>Driver Number</th>
                                <th>Customer Name</th>
                                <th>Customer Number</th>
                                <th>Map</th>
                                <th>Status</th>
                                <th>All Details</th>
                            </tr>
                        </thead>
                        <tbody id="onrideTable"></tbody>
                    </table>
                </div>
                <div id="onrideTablePagination" class="pagination-wrapper mt-3"></div>
            </div>
            
            <!-- Customers Container -->
            <div class="table-container hidden shadow-sm border-0" id="newuserTableContainer">
                <div class="table-container-header">
                    <div class="d-flex align-items-center gap-3">
                        <button id="refreshnewUser" class="btn btn-icon-sm" title="Refresh newUser">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                        <h4 class="mb-0 table-title">User List</h4>
                    </div>
                </div>
                
                <div class="filter-toolbar">
                    <div class="search-box-wrapper w-100" style="max-width: 320px;">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="newuserSearchInput" class="form-control" placeholder="Search Users...">
                    </div>
                </div>
                
                <div class="table-responsive-wrapper">
                    <table class="table-modern text-center">
                        <thead>
                            <tr>
                                <th>Sr.No</th>
                                <th>User ID</th>
                                <th>Phone Number</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>City</th>
                                <th>Pincode</th>
                                <th>Created_at</th>
                                <th>Account Type</th>
                                <th>Agency Name</th>
                            </tr>
                        </thead>
                        <tbody id="newuserTable"></tbody>
                    </table>
                </div>
                <div id="newuserTablePagination" class="pagination-wrapper mt-3"></div>
            </div>
            
            <!-- Blocked Customers Container -->
            <div class="table-container hidden shadow-sm border-0" id="Blocked_CustomerTableContainer">
                <div class="table-container-header">
                    <div class="d-flex align-items-center gap-3">
                        <button id="refreshBlocked_Customer" class="btn btn-icon-sm" title="Refresh Blocked_Customer">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                        <h4 class="mb-0 table-title">Blocked Customer</h4>
                    </div>
                </div>
                
                <div class="filter-toolbar">
                    <div class="search-box-wrapper w-100" style="max-width: 320px;">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="Blocked_CustomerSearchInput" class="form-control" placeholder="Search Users...">
                    </div>
                </div>
                
                <div class="table-responsive-wrapper">
                    <table class="table-modern text-center">
                        <thead>
                            <tr>
                                <th>Sr.No</th>
                                <th>User ID</th>
                                <th>Phone Number</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>City</th>
                                <th>Pincode</th>
                                <th>Created_at</th>
                                <th>Account Type</th>
                                <th>Agency Name</th>
                            </tr>
                        </thead>
                        <tbody id="Blocked_CustomerTable"></tbody>
                    </table>
                </div>
                <div id="Blocked_CustomerTablePagination" class="pagination-wrapper mt-3"></div>
            </div>
        </main>

        <!-- Booking details Modal -->
        <div id="bookingModal" class="modal">
            <div class="modal-content shadow-lg border-0 rounded-4">
                <span class="closeBtn">&times;</span>
                <div id="modalDetails"></div>
            </div>
        </div>

        <!-- Route details Map Modal -->
        <div id="routeModal" class="modal">
            <div class="modal-content shadow-lg border-0 rounded-4">
                <span class="closeBtn">&times;</span>
                <h3 class="mb-2">Route Map</h3>
                <p id="routeAddresses" class="text-muted small"></p>
                <div id="routeMap" class="rounded-3 border"></div>
                <p id="routeError" class="text-danger mt-2 small" style="display: none;">Unable to load route. Please check the addresses.</p>
            </div>
        </div>
    </div>

    <!-- Javascripts -->
    <script src="javascripts/Dasboardscripts.js"></script>
    <script>
        function viewDriverOnMap(lat, lng) {
            if (!lat || !lng) {
                alert("Location not available");
                return;
            }
            const mapUrl = `https://www.google.com/maps?q=${lat},${lng}`;
            window.open(mapUrl, '_blank');
        }
    </script>
    
    <!-- Extract Data Popup Overlay -->
    <div id="carPopup" class="popup-overlay">
      <div class="popup-box shadow-lg border-0 rounded-4">
        <span class="popup-close">&times;</span>
        <div class="popup-content">
          <div class="data-labels">
            <h3 class="text-secondary small fw-semibold">Car Data</h3>
            <h3 class="text-secondary small fw-semibold">Driver Data</h3>
            <h3 class="text-secondary small fw-semibold">New User Data</h3>
          </div>
          <div class="download-section">
            <button id="downloadCar" class="download-btn btn-success"><i class="fas fa-file-excel me-1"></i> Car Excel</button>
            <button id="downloadDriver" class="download-btn btn-primary"><i class="fas fa-file-excel me-1"></i> Driver Excel</button>
            <button id="downloadUser" class="download-btn btn-warning"><i class="fas fa-file-excel me-1"></i> User Excel</button>
          </div>
        </div>
      </div>
    </div>
</body>
</html>