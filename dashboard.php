<?php
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
    <title>Dashboard - Rentox Vendor & Driver</title>
    <link rel="icon" type="image/png" href="images/pnglogoagni.png">
    <link rel="stylesheet" type="text/css" href="css/Dashboard_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/js/all.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBz4vqQWuT-s_3UEWk6pnSMxSIt7QOZEqk&libraries=places"></script>
    
</head>
<body>
    <nav class="top-nav">
        
        <div class="logo-container">
            <img src="images/logo.png" alt="Company Logo" class="logo">
        </div>
        <h1 class="dashboard-heading">Dashboard</h1>
        <button class="hamburger" id="hamburger" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>
    </nav>

    <div class="container-fluid separator"></div>

    <div class="dashboard-container">
        <nav class="sidebar" id="sidebar">
            <ul>
                <li><a href="#" id="driver"><i class="fas fa-user me-2"></i> Driver</a></li>
                <li><a href="#" id="cab"><i class="fas fa-taxi me-2"></i> Cab</a></li>
                <li><a href="dashboard.php" id="booking"><i class="fas fa-calendar me-2"></i> Booking</a></li>
                <li><a href="#" id="Complete"><i class="fas fa-calendar-check me-2"></i> Completed</a></li>
                <li>
                    <a href="#" id="newuser">
                        <i class="fa-solid fa-users me-2"></i> Customers
                        <span id="newuserCount" class="badge bg-danger ms-2" style="display: none;">0</span>
                    </a>
                </li>
                <li><a href="bookacall/admin-bookings.php" id="bookacall"><i class="fa-solid fa-phone me-2"></i>BookACall</a></li>
                <li><a href="#" id="Blocked_Customer"><i class="fas fa-user-slash me-2"></i>Blocked Customer</a></li>
                <li><a href="#" id="Extract_Data">
    <i class="fas fa-file-excel me-2"></i> Extract Data
  </a>
</li>
                <li><a href="partner/index.php" id="partner_api">
    <i class="fas fa-handshake me-2"></i> Partner API
  </a>
</li>
                <li><a href="partner/monitor.php" id="partner_monitor">
    <i class="fas fa-desktop me-2"></i> Partner Monitor
  </a>
</li>
                <li><a href="car_categories.php" id="car_categories_menu">
    <i class="fas fa-tags me-2"></i> Car Categories
  </a>
</li>
                <li class="mt-4 pt-3 border-top" style="border-color: rgba(255, 255, 255, 0.15) !important;">
                    <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" style="color: #ff4b2b; font-weight: 600;">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                    <form id="logout-form" action="logout.php" method="POST" style="display: none;"></form>
                </li>
            
            </ul>
        </nav>

        <main class="content">
            
            <div class="grid">
                <div class="card" id="driverCard">
                    <h3>Drivers</h3>
                    <p id="driverCount">Loading...</p>
                </div>
                <div class="card" id="carCard">
                    <h3>Cars</h3>
                    <p id="carCount">Loading...</p>
                </div>
                <div class="card" id="latestBookingCard">
                    <h3>Latest Bookings</h3>
                    <p id="latestBooking">Loading...</p>
                </div>
                <div class="card" id="onrideCard">
                    <h3>On Ride</h3>
                    <p id="onRideCount">Loading...</p>
                </div>
                <div class="card" id="waitingforapprovalCard">
                    <h3>Waiting for Approval</h3>
                    <p id="waitingforapprovalCount">Loading...</p>
                </div>
            </div>

            <div class="table-container" id="bookingTableContainer">
                <h4 class="mb-3 d-flex justify-content-center align-items-center position-relative" id="bookingHeading">
                    Booking List
                    <button id="refreshBookings" class="btn btn-link p-0 position-absolute start-0" title="Refresh Bookings">
                        <i class="bi bi-arrow-clockwise fs-5"></i>
                    </button>
                </h4>
                <ul class="nav nav-tabs mb-3" id="bookingTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-filter="all" type="button">All</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="oneway-tab" data-bs-toggle="tab" data-filter="One-way" type="button">One Way Trip</button>
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
                <div class="search-container" id="bookingSearchContainer">
                    <div class="input-group">
                        <input type="text" id="bookingSearchInput" class="form-control" placeholder="Search bookings...">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                    </div>
                </div>
                <table class="table table-striped table-hover text-center">
                    <thead>
                        <tr >
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
                        <!-- Add data-label attributes dynamically in JavaScript -->
                    </tbody>
                </table>
                <div id="bookingTablePagination" class="pagination"></div>
            </div>

            <div class="table-container hidden" id="driverTableContainer">
                <div style="text-align: right; padding: 10px;">
                    <button onclick="window.open('show_map.php', '_blank')" id="showMapBtn">Show Map</button>
                </div>
                <h4 class="mb-3 d-flex justify-content-center align-items-center position-relative">
                    Driver List
                    <button id="refreshDriver" class="btn btn-link p-0 position-absolute start-0" title="Refresh Driver">
                        <i class="bi bi-arrow-clockwise fs-5"></i>
                    </button>
                </h4>
                <div class="search-container" id="driverSearchContainer">
                    <div class="input-group">
                        <input type="text" id="driverSearchInput" class="form-control" placeholder="Search drivers...">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                    </div>
                </div>
                <table class="table table-hover text-center">
                    <thead>
                        <tr >
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
                <div id="driverTablePagination" class="pagination"></div>
            </div>

            <div class="table-container hidden" id="carTableContainer">
                <h4 class="mb-3 d-flex justify-content-center align-items-center position-relative">
                    Car List
                    <button id="refreshCar" class="btn btn-link p-0 position-absolute start-0" title="Refresh Car">
                        <i class="bi bi-arrow-clockwise fs-5"></i>
                    </button>
                </h4>
                <div class="search-container" id="carSearchContainer">
                    <div class="input-group">
                        <input type="text" id="carSearchInput" class="form-control" placeholder="Search car...">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                    </div>
                </div>
                <table class="table table-striped table-hover text-center">
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
                <div id="carTablePagination" class="pagination"></div>
            </div>

            <div class="table-container hidden" id="waitingforapprovalTableContainer">
                <h4 class="mb-3 d-flex justify-content-center align-items-center position-relative">
                    Document List
                    <button id="refreshwaitingforapproval" class="btn btn-link p-0 position-absolute start-0" title="Refresh Documents">
                        <i class="bi bi-arrow-clockwise fs-5"></i>
                    </button>
                </h4>
                <div class="search-container" id="waitingforapprovalSearchContainer">
                    <div class="input-group">
                        <input type="text" id="waitingforapprovalSearchInput" class="form-control" placeholder="Search Document...">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                    </div>
                </div>
                <table class="table table-striped table-hover text-center">
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
                <div id="waitingforapprovalTablePagination" class="pagination"></div>
            </div>
            
            <div class="table-container hidden" id="onrideTableContainer">
                <h4 class="mb-3 d-flex justify-content-center align-items-center position-relative">
                    On Ride Vehicle 
                    <button id="refreshonRide" class="btn btn-link p-0 position-absolute start-0" title="Refresh onRide">
                        <i class="bi bi-arrow-clockwise fs-5"></i>
                    </button>
                </h4>
                <div class="search-container" id="onrideSearchContainer">
                    <div class="input-group">
                        <input type="text" id="onrideSearchInput" class="form-control" placeholder="Search drivers...">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                    </div>
                </div>
                <table class="table table-striped table-hover text-center">
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
                <div id="onrideTablePagination" class="pagination"></div>
            </div>
            
            <div class="table-container hidden" id="newuserTableContainer">
                <h4 class="mb-3 d-flex justify-content-center align-items-center position-relative">
                    User List
                    <button id="refreshnewUser" class="btn btn-link p-0 position-absolute start-0" title="Refresh newUser">
                        <i class="bi bi-arrow-clockwise fs-5"></i>
                    </button>
                </h4>
                <div class="search-container" id="newuserSearchContainer">
                    <div class="input-group">
                        <input type="text" id="newuserSearchInput" class="form-control" placeholder="Search Users...">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                    </div>
                </div>
                <table class="table table-striped table-hover text-center">
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
                <div id="newuserTablePagination" class="pagination"></div>
            </div>
            
            
            <div class="table-container hidden" id="Blocked_CustomerTableContainer">
                <h4 class="mb-3 d-flex justify-content-center align-items-center position-relative">
                    Blocked Customer
                    <button id="refreshBlocked_Customer" class="btn btn-link p-0 position-absolute start-0" title="Refresh Blocked_Customer">
                        <i class="bi bi-arrow-clockwise fs-5"></i>
                    </button>
                </h4>
                <div class="search-container" id="Blocked_CustomerSearchContainer">
                    <div class="input-group">
                        <input type="text" id="Blocked_CustomerSearchInput" class="form-control" placeholder="Search Users...">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                    </div>
                </div>
                <table class="table table-striped table-hover text-center">
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
                <div id="Blocked_CustomerTablePagination" class="pagination"></div>
            </div>
        </main>

        <div id="bookingModal" class="modal">
            <div class="modal-content">
                <span class="closeBtn">×</span>
                <div id="modalDetails"></div>
            </div>
        </div>
        <div id="routeModal" class="modal">
            <div class="modal-content">
                <span class="closeBtn">×</span>
                <h3>Route Map</h3>
                <p id="routeAddresses"></p>
                <div id="routeMap" style="height: 400px; width: 100%;"></div>
                <p id="routeError" style="color: red; display: none;">Unable to load route. Please check the addresses.</p>
            </div>
        </div>
    </div>

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
    <!-- Popup (must be outside table, better at end of <body>) -->
<div id="carPopup" class="popup-overlay">
  <div class="popup-box">
    <span class="popup-close">&times;</span>
    <div class="popup-content">
      <!-- Left labels -->
      <div class="data-labels">
        <h3>Car Data</h3>
        <h3>Driver Data</h3>
        <h3>New User Data</h3>
      </div>
      <!-- Right buttons -->
      <div class="download-section">
        <button id="downloadCar" class="download-btn">Download Car Excel</button>
        <button id="downloadDriver" class="download-btn">Download Driver Excel</button>
        <button id="downloadUser" class="download-btn">Download User Excel</button>
      </div>
    </div>
  </div>
</div>
</body>
</html>