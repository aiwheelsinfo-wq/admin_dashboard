<!DOCTYPE html>
<html>
<head>
  <title>Driver Locations</title>

  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>

  <style>
    html, body {
      height: 100%;
      margin: 0;
    }
    #map {
      height: 100%;
    }
  </style>
</head>

<body>

<div id="map"></div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<script>
async function initMap() {

  const response = await fetch('https://agnicarrental.com/driver2025/driver_list_Agni.php');
  const data = await response.json();
  const drivers = data.driversdata;

  // Initialize map
  const map = L.map('map').setView([20.5937, 78.9629], 5);

  // OpenStreetMap tiles
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  // Custom car icon
  const carIcon = L.icon({
    iconUrl: 'https://img.icons8.com/color/48/car--v1.png',
    iconSize: [40, 40]
  });

  drivers.forEach(driver => {
    const lat = parseFloat(driver.latitude);
    const lng = parseFloat(driver.longitude);

    if (!isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {

      const marker = L.marker([lat, lng], { icon: carIcon }).addTo(map);

      marker.bindPopup(`
        <strong>Driver:</strong> ${driver.full_name || 'No Name'}<br>
        <strong>Phone:</strong> ${driver.phone_number}
      `);

    }
  });
}

window.onload = initMap;
</script>

</body>
</html>