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
let map;
let markers = {}; // Maps driver_id -> Leaflet marker instance
const carIcon = L.icon({
  iconUrl: 'https://img.icons8.com/color/48/car--v1.png',
  iconSize: [40, 40]
});

async function initMap() {
  // Initialize map
  map = L.map('map').setView([20.5937, 78.9629], 5);

  // OpenStreetMap tiles
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  // Initial load
  await updateDriverLocations();

  // Set interval to update locations every 15 seconds
  setInterval(updateDriverLocations, 15000);
}

async function updateDriverLocations() {
  try {
    const response = await fetch('https://agnicarrental.com/driver2025/driver_list_Agni.php');
    const data = await response.json();
    const drivers = data.driversdata || [];

    // Keep track of drivers seen in this update to remove obsolete ones
    const activeDriverIds = new Set();

    drivers.forEach(driver => {
      const lat = parseFloat(driver.latitude);
      const lng = parseFloat(driver.longitude);
      const driverId = driver.driver_id;

      if (!isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
        activeDriverIds.add(driverId);
        const newLatLng = [lat, lng];
        const popupContent = `
          <strong>Driver:</strong> ${driver.full_name || 'No Name'}<br>
          <strong>Phone:</strong> ${driver.phone_number}<br>
          <strong>Last Update:</strong> ${driver.timestamp || 'N/A'}
        `;

        if (markers[driverId]) {
          // Update existing marker position and popup
          markers[driverId].setLatLng(newLatLng);
          markers[driverId].getPopup().setContent(popupContent);
        } else {
          // Create new marker
          const marker = L.marker(newLatLng, { icon: carIcon }).addTo(map);
          marker.bindPopup(popupContent);
          markers[driverId] = marker;
        }
      }
    });

    // Remove markers of drivers that are no longer active/present
    for (const id in markers) {
      if (!activeDriverIds.has(id)) {
        map.removeLayer(markers[id]);
        delete markers[id];
      }
    }
  } catch (error) {
    console.error("Error updating driver locations:", error);
  }
}

window.onload = initMap;
</script>

</body>
</html>