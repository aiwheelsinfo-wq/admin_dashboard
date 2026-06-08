<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../adminlogin.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Call Bookings - Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <style>
    body {
      background-color: #f8f9fa;
    }
    .table-container {
      background-color: #fff;
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
    }
    h2 {
      font-weight: 600;
    }
  </style>
</head>
<body>

<div class="container my-5">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <h2 class="mb-0">📞 Call Bookings</h2>
    <div class="d-flex gap-2">
      <input type="text" id="searchInput" class="form-control" placeholder="Search phone or destination..." oninput="filterTable()">
      <button class="btn btn-primary" onclick="fetchData()">Refresh</button>
    </div>
  </div>

  <div class="table-container">
    <table class="table table-bordered table-hover align-middle">
      <thead class="table-dark">
        <tr>
          <th>ID</th>
          <th>Phone Number</th>
          <th>Destination</th>
          <th>Booked At</th>
        </tr>
      </thead>
      <tbody id="bookingTableBody">
        <tr>
          <td colspan="4" class="text-center">Loading...</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<script>
  let allBookings = [];

  async function fetchData() {
  const tbody = document.getElementById("bookingTableBody");
  const searchValue = document.getElementById("searchInput").value.toLowerCase();

  try {
    const response = await fetch("https://agnicarrental.com/specialPage/get_bookacalls.php");
    const result = await response.json();

    if (result.status === "success") {
      allBookings = result.data;
      const filtered = allBookings.filter(row =>
        row.phone_number.toLowerCase().includes(searchValue) ||
        row.destination.toLowerCase().includes(searchValue)
      );
      renderTable(filtered);
    } else {
      tbody.innerHTML = `<tr><td colspan="4" class="text-center">No bookings found.</td></tr>`;
    }
  } catch (error) {
    tbody.innerHTML = `<tr><td colspan="4" class="text-danger text-center">Error fetching data.</td></tr>`;
    console.error(error);
  }
}

  function renderTable(data) {
  const tbody = document.getElementById("bookingTableBody");

  if (data.length === 0) {
    tbody.innerHTML = `<tr><td colspan="4" class="text-center">No matching records.</td></tr>`;
    return;
  }

  const now = new Date();

  tbody.innerHTML = data.map(row => {
    // Convert string to Date object
    const bookedAt = new Date(row.booked_at.replace(" ", "T"));
    const diffHours = (now - bookedAt) / (1000 * 60 ); // difference in hours

    const rowClass = diffHours < 10 ? 'table-success' : '';

    // Format date back to "YYYY-MM-DD HH:MM:SS"
    const formattedDate = `${bookedAt.getFullYear()}-${String(bookedAt.getMonth() + 1).padStart(2, '0')}-${String(bookedAt.getDate()).padStart(2, '0')} ${String(bookedAt.getHours()).padStart(2, '0')}:${String(bookedAt.getMinutes()).padStart(2, '0')}:${String(bookedAt.getSeconds()).padStart(2, '0')}`;

    return `
      <tr class="${rowClass}">
        <td>${row.id}</td>
        <td><a href="tel:${row.phone_number}" class="text-primary text-decoration-underline">${row.phone_number}</a></td>
        <td>${row.destination}</td>
        <td>${formattedDate}</td>
      </tr>
    `;
  }).join("");
}




  function filterTable() {
    const searchValue = document.getElementById("searchInput").value.toLowerCase();
    const filtered = allBookings.filter(row =>
      row.phone_number.toLowerCase().includes(searchValue) ||
      row.destination.toLowerCase().includes(searchValue)
    );
    renderTable(filtered);
  }

  // Load data on page load
  fetchData();
  
  setInterval(fetchData, 1000);
</script>

</body>
</html>
