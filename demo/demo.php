<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Responsive Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        /* Custom CSS for Responsive Components */
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
        }

        /* Navigation Bar */
        .top-nav {
            background-color: #494487;
            padding: 10px 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .top-nav .logo-container {
            flex: 0 0 auto;
        }

        .top-nav .logo-container img {
            height: 60px;
            width: 100px;
            background-color: white;
            border-radius: 5px;
            display: block;
        }

        .top-nav .nav-links {
            display: flex;
            gap: 15px;
            flex: 1;
            justify-content: center;
        }

        .top-nav .nav-links a {
            color: white;
            text-decoration: none;
            font-size: 16px;
            padding: 10px 15px;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .top-nav .nav-links a:hover {
            background-color: #3a3a6b;
        }

        .top-nav .logout-container {
            margin-left: auto;
        }

        .top-nav .logout-container a {
            color: white;
            text-decoration: none;
            font-size: 16px;
            padding: 10px 15px;
            border-radius: 5px;
            background-color: #e74c3c;
            transition: background-color 0.3s;
        }

        .top-nav .logout-container a:hover {
            background-color: #c0392b;
        }

        .top-nav .hamburger {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background-color: #494487;
            color: white;
            padding: 20px;
            height: calc(100vh - 65px);
            position: fixed;
            top: 65px;
            left: 0;
            transition: transform 0.3s ease;
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
        }

        .sidebar ul li {
            margin: 15px 0;
        }

        .sidebar ul li a {
            color: white;
            text-decoration: none;
            font-size: 16px;
            display: flex;
            align-items: center;
            padding: 10px;
            border-radius: 5px;
            transition: background 0.3s;
        }

        .sidebar ul li a:hover {
            background-color: #465c71;
        }

        .sidebar ul li a i {
            margin-right: 10px;
        }

        /* Main Content */
        .content {
            margin-left: 250px;
            padding: 20px;
            min-height: calc(100vh - 65px);
        }

        /* Cards */
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            text-align: center;
            transition: transform 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card h3 {
            font-size: 18px;
            color: #34495e;
            margin-bottom: 10px;
        }

        .card p {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }

        /* Table */
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            overflow-x: auto;
        }

        .table-container table {
            width: 100%;
            border-collapse: collapse;
        }

        .table-container th, .table-container td {
            padding: 12px;
            text-align: center;
            border-bottom: 1px solid #ddd;
        }

        .table-container th {
            background-color: #ced0f0;
            color: black;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .page-btn {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #fff;
            color: #333;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 14px;
            min-width: 40px;
            text-align: center;
        }

        .page-btn:hover:not(:disabled) {
            background: #2b6cb0;
            color: white;
            border-color: #2b6cb0;
        }

        .page-btn.active {
            background: #2b6cb0;
            color: white;
            border-color: #2b6cb0;
            font-weight: 500;
        }

        .page-btn:disabled {
            background: #f5f5f5;
            color: #999;
            cursor: not-allowed;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .top-nav .nav-links {
                display: none;
            }

            .top-nav .logout-container {
                display: none;
            }

            .top-nav .hamburger {
                display: block;
                flex: 0 0 auto;
            }

            .top-nav .logo-container {
                flex: 1;
            }

            .top-nav {
                padding: 8px 15px;
            }

            .top-nav .logo-container img {
                height: 40px;
                width: 80px;
            }

            .sidebar {
                transform: translateX(-100%);
                z-index: 1000;
                width: 200px;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .content {
                margin-left: 0;
                padding: 15px;
            }

            .card-grid {
                grid-template-columns: 1fr;
            }

            .table-container th, .table-container td {
                font-size: 14px;
                padding: 8px;
            }

            .page-btn {
                padding: 6px 10px;
                font-size: 12px;
                min-width: 36px;
            }
        }

        @media (max-width: 576px) {
            .top-nav {
                padding: 8px 10px;
            }

            .top-nav .logo-container img {
                height: 35px;
                width: 70px;
            }

            .table-container th, .table-container td {
                font-size: 12px;
                padding: 6px;
            }
        }

        @media (min-width: 769px) {
            .sidebar {
                transform: translateX(0);
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="top-nav">
        <div class="logo-container">
            <img src="images/logo.png" alt="Company Logo">
        </div>
        <div class="nav-links">
            <a href="#home"><i class="fas fa-home"></i> Home</a>
            <a href="#dashboard"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        </div>
        <div class="logout-container">
            <a href="#logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
        <button class="hamburger" id="hamburger" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>
    </nav>

    <!-- Dashboard Container -->
    <div class="dashboard-container">
        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <ul>
                <li><a href="#dashboard"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="#drivers"><i class="fas fa-user"></i> Drivers</a></li>
                <li><a href="#cars"><i class="fas fa-car"></i> Cars</a></li>
                <li><a href="#bookings"><i class="fas fa-calendar"></i> Bookings</a></li>
                <li><a href="#completed"><i class="fas fa-calendar-check"></i> Completed</a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="content">
            <h1>Dashboard</h1>

            <!-- Cards -->
            <div class="card-grid">
                <div class="card">
                    <h3>Drivers</h3>
                    <p>25</p>
                </div>
                <div class="card">
                    <h3>Cars</h3>
                    <p>15</p>
                </div>
                <div class="card">
                    <h3>Bookings</h3>
                    <p>40</p>
                </div>
                <div class="card">
                    <h3>On Ride</h3>
                    <p>5</p>
                </div>
                <div class="card">
                    <h3>Pending</h3>
                    <p>10</p>
                </div>
            </div>

            <!-- Table -->
            <div class="table-container">
                <h3>Booking List</h3>
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Vehicle</th>
                            <th>Status</th>
                            <th>Price</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="booking-table">
                        <!-- Table rows will be populated by JavaScript -->
                    </tbody>
                </table>
                <div class="pagination" id="pagination"></div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar Toggle
        $(document).ready(() => {
            const hamburger = $('#hamburger');
            const sidebar = $('#sidebar');

            hamburger.on('click', () => {
                sidebar.toggleClass('active');
                hamburger.attr('aria-expanded', sidebar.hasClass('active'));
            });

            // Close sidebar when clicking outside
            $(document).on('click', (e) => {
                if (!sidebar.is(e.target) && !sidebar.has(e.target).length && !hamburger.is(e.target) && !hamburger.has(e.target).length) {
                    sidebar.removeClass('active');
                    hamburger.attr('aria-expanded', 'false');
                }
            });
        });

        // Pagination and Table Population
        const tableBody = $('#booking-table');
        const paginationContainer = $('#pagination');
        const itemsPerPage = 5;
        let currentPage = 1;

        // Sample data (replace with actual data source)
        const bookings = Array.from({ length: 20 }, (_, i) => ({
            id: i + 1,
            customer: `Customer ${i + 1}`,
            from: `City A${i % 5}`,
            to: `City B${i % 5}`,
            date: `2025-05-${(i % 30) + 1}`,
            time: `${(i % 12) + 1}:00 ${i % 2 ? 'AM' : 'PM'}`,
            vehicle: `Car ${i % 3 + 1}`,
            status: ['Pending', 'Accepted', 'Completed'][i % 3],
            price: `$${(i + 1) * 50}`,
            action: `<button class="btn btn-sm btn-primary">View</button>`
        }));

        function renderTable(page) {
            tableBody.empty();
            const start = (page - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            const pageData = bookings.slice(start, end);

            pageData.forEach(booking => {
                tableBody.append(`
                    <tr>
                        <td>${booking.id}</td>
                        <td>${booking.customer}</td>
                        <td>${booking.from}</td>
                        <td>${booking.to}</td>
                        <td>${booking.date}</td>
                        <td>${booking.time}</td>
                        <td>${booking.vehicle}</td>
                        <td>${booking.status}</td>
                        <td>${booking.price}</td>
                        <td>${booking.action}</td>
                    </tr>
                `);
            });
        }

        function renderPagination() {
            paginationContainer.empty();
            const totalPages = Math.ceil(bookings.length / itemsPerPage);

            for (let i = 1; i <= totalPages; i++) {
                paginationContainer.append(`
                    <button class="page-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>
                `);
            }

            $('.page-btn').on('click', function() {
                currentPage = parseInt($(this).data('page'));
                renderTable(currentPage);
                renderPagination();
            });
        }

        // Initial render
        renderTable(currentPage);
        renderPagination();
    </script>
</body>
</html>