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
      position: relative;
    }
    #map {
      height: 100%;
      z-index: 1;
    }
    #search-container {
      position: absolute;
      top: 15px;
      left: 50%;
      transform: translateX(-50%);
      z-index: 9999;
      width: 340px;
      max-width: 90%;
    }
    #search-input {
      width: 100%;
      padding: 12px 18px;
      font-size: 14px;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      border: 1px solid rgba(0, 0, 0, 0.15);
      border-radius: 25px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
      outline: none;
      box-sizing: border-box;
      background-color: white;
      transition: all 0.3s ease;
    }
    #search-input:focus {
      border-color: #007bff;
      box-shadow: 0 4px 20px rgba(0, 123, 255, 0.25);
    }
  </style>
</head>

<body>

<div id="search-container">
  <input type="text" id="search-input" placeholder="Search driver by name or phone..."/>
</div>

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

function formatDateTime(dateTimeStr) {
  if (!dateTimeStr || dateTimeStr === 'N/A' || dateTimeStr === '0000-00-00 00:00:00') {
    return 'N/A';
  }
  try {
    const parts = dateTimeStr.split(' ');
    if (parts.length < 2) return dateTimeStr;
    
    const dateParts = parts[0].split('-');
    const timeParts = parts[1].split(':');
    
    if (dateParts.length === 3 && timeParts.length >= 2) {
      const year = dateParts[0];
      const month = dateParts[1];
      const day = dateParts[2];
      
      let hours = parseInt(timeParts[0], 10);
      const minutes = timeParts[1];
      const seconds = timeParts[2] || '00';
      
      const ampm = hours >= 12 ? 'PM' : 'AM';
      hours = hours % 12;
      hours = hours ? hours : 12; // 0 hour should be 12
      const strTime = `${hours.toString().padStart(2, '0')}:${minutes}:${seconds} ${ampm}`;
      
      return `${day}-${month}-${year} ${strTime}`;
    }
  } catch (e) {
    console.error("Error formatting date time:", e);
  }
  return dateTimeStr;
}

function filterDrivers() {
  const query = (document.getElementById('search-input').value || '').toLowerCase().trim();
  let matchedMarker = null;
  let matchCount = 0;

  for (const id in markers) {
    const marker = markers[id];
    const name = marker.driverData.name;
    const phone = marker.driverData.phone;

    if (name.includes(query) || phone.includes(query)) {
      if (!map.hasLayer(marker)) {
        marker.addTo(map);
      }
      matchedMarker = marker;
      matchCount++;
    } else {
      if (map.hasLayer(marker)) {
        map.removeLayer(marker);
      }
    }
  }

  // If there is exactly one match, zoom to it and open its popup
  if (matchCount === 1 && query.length > 0 && matchedMarker) {
    map.setView(matchedMarker.getLatLng(), 14);
    matchedMarker.openPopup();
  }
}

async function initMap() {
  // Initialize map
  map = L.map('map').setView([20.5937, 78.9629], 5);

  // OpenStreetMap tiles
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  // Add search input listener
  document.getElementById('search-input').addEventListener('input', filterDrivers);

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
          <strong>Last Update:</strong> ${formatDateTime(driver.timestamp)}
        `;

        if (markers[driverId]) {
          // Update existing marker position and popup
          markers[driverId].setLatLng(newLatLng);
          markers[driverId].getPopup().setContent(popupContent);
          markers[driverId].driverData = {
            name: (driver.full_name || '').toLowerCase(),
            phone: (driver.phone_number || '')
          };
        } else {
          // Create new marker
          const marker = L.marker(newLatLng, { icon: carIcon }).addTo(map);
          marker.bindPopup(popupContent);
          marker.driverData = {
            name: (driver.full_name || '').toLowerCase(),
            phone: (driver.phone_number || '')
          };
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

    // Apply search filter to new/updated markers
    filterDrivers();
  } catch (error) {
    console.error("Error updating driver locations:", error);
  }
}

window.onload = initMap;
</script>

</body>
</html>