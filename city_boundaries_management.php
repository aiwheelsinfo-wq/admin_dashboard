<?php
// city_boundaries_management.php - Manage dynamic service boundaries for cities
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
    <title>City Boundaries - Agni Car Rental</title>
    <link rel="icon" type="image/png" href="images/pnglogoagni.png">
    
    <!-- CSS & Fonts -->
    <link rel="stylesheet" type="text/css" href="css/Dashboard_styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyC41U3p08LqY8G15ruxDCEfTvBLkG_OrsM&libraries=places"></script>

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
            max-width: 320px;
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

        .help-panel {
            background: rgba(255, 179, 0, 0.05);
            border: 1px dashed rgba(255, 179, 0, 0.3);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .help-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--primary-amber);
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .help-panel ol {
            margin-bottom: 0;
            padding-left: 20px;
            color: var(--text-grey);
            font-size: 14px;
        }

        .help-panel li {
            margin-bottom: 6px;
        }

        .table-container {
            background: rgba(30, 30, 30, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
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
        }

        .table {
            color: var(--text-light) !important;
        }

        .table th {
            border-bottom: 2px solid rgba(255, 179, 0, 0.3) !important;
            color: var(--primary-amber) !important;
            text-transform: uppercase;
            font-size: 12px;
            font-weight: 700;
            padding: 15px 10px;
        }

        .table td {
            padding: 15px 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
            font-size: 14px;
            vertical-align: middle;
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

        /* Modal styling */
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

        .pac-container {
            z-index: 9999 !important;
        }
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <nav class="top-nav">
        <div class="logo-container">
            <img src="images/logo_rentox.png" alt="Company Logo" class="logo">
        </div>
        <h1 class="dashboard-heading">City Service Boundaries</h1>
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
                <li><a href="vehicle_entries_admin.php"><i class="fas fa-file-invoice me-2"></i> Vehicle Entries</a></li>
                <li class="active"><a href="city_boundaries_management.php"><i class="fas fa-map-marked-alt me-2"></i> City Boundaries</a></li>
            </ul>
        </nav>

        <!-- Main Workspace -->
        <main class="content">
            
            <div class="row">
                <div class="col-md-4">
                    <!-- Metric Card -->
                    <div class="stat-card">
                        <h3>Active Service Cities</h3>
                        <p id="totalCities">0</p>
                    </div>
                </div>
                <div class="col-md-8">
                    <!-- Guide Card -->
                    <div class="help-panel">
                        <div class="help-title"><i class="fa-solid fa-circle-question me-2"></i> Quick Guide: Setting City Geofences</div>
                        <ol>
                            <li>Go to <strong><a href="https://maps.google.com" target="_blank" class="text-warning">Google Maps</a></strong>.</li>
                            <li>Find the boundaries corners of the city service area (North-most, South-most, West-most, East-most).</li>
                            <li>Right-click on any map location to copy its latitude and longitude.</li>
                            <li><strong>minLat / maxLat</strong>: The lowest and highest latitude lines (bottom and top boundary).</li>
                            <li><strong>minLng / maxLng</strong>: The lowest and highest longitude lines (left and right boundary).</li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- Table Container -->
            <div class="table-container">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0 text-warning uppercase" style="font-size: 16px; font-weight: 700; letter-spacing: 0.5px;">City Boundaries Grid</h4>
                    <button class="btn btn-amber" onclick="openAddModal()"><i class="fa-solid fa-plus-circle me-2"></i> Add Service City</button>
                </div>

                <table class="table table-striped table-hover text-center align-middle">
                    <thead>
                        <tr>
                            <th>City Name</th>
                            <th>Min Latitude</th>
                            <th>Max Latitude</th>
                            <th>Min Longitude</th>
                            <th>Max Longitude</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="boundariesTableBody">
                        <tr>
                            <td colspan="7" class="text-muted py-4">Loading boundaries database...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </main>
    </div>

    <!-- Add/Edit Record Modal -->
    <div class="modal fade" id="boundaryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <form id="boundaryForm">
                    <input type="hidden" id="cityId" name="id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitleText">Add New City Boundary</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- City Name -->
                        <div class="mb-3">
                            <label class="form-label text-warning uppercase">City Name</label>
                            <input type="text" class="form-control" id="cityName" name="city_name" placeholder="e.g. Pune" required>
                        </div>
                        
                        <!-- Drawing Control -->
                        <div class="mb-3">
                            <button type="button" class="btn btn-amber w-100" id="drawBtn" onclick="toggleMapEditor()">
                                <i class="fa-solid fa-draw-polygon me-2"></i> Draw Boundary on Map
                            </button>
                        </div>
                        <input type="hidden" id="polygonCoords" name="polygon_coords">

                        <!-- Map wrapper inside modal -->
                        <div id="mapWrapper" class="mb-4" style="display: none;">
                            <label class="form-label text-warning uppercase">Click on map to place boundary points. Drag points to edit.</label>
                            <div id="mapEditor" style="height: 380px; width: 100%; border-radius: 12px; border: 1px solid rgba(255, 179, 0, 0.3);"></div>
                            <div class="mt-2 d-flex justify-content-between align-items-center">
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearMapPolygon()">
                                    <i class="fa-solid fa-trash-can me-1"></i> Clear Map
                                </button>
                                <span class="text-secondary small">Right-click on a point/vertex to delete it</span>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Min Lat -->
                            <div class="col-6 mb-3">
                                <label class="form-label text-warning uppercase">Min Latitude</label>
                                <input type="number" step="0.00000001" class="form-control" id="minLat" name="min_lat" placeholder="Computed from map" readonly required style="background-color: rgba(30, 30, 30, 0.4) !important; color: #aaa !important;">
                            </div>
                            <!-- Max Lat -->
                            <div class="col-6 mb-3">
                                <label class="form-label text-warning uppercase">Max Latitude</label>
                                <input type="number" step="0.00000001" class="form-control" id="maxLat" name="max_lat" placeholder="Computed from map" readonly required style="background-color: rgba(30, 30, 30, 0.4) !important; color: #aaa !important;">
                            </div>
                        </div>
                        <div class="row">
                            <!-- Min Lng -->
                            <div class="col-6 mb-3">
                                <label class="form-label text-warning uppercase">Min Longitude</label>
                                <input type="number" step="0.00000001" class="form-control" id="minLng" name="min_lng" placeholder="Computed from map" readonly required style="background-color: rgba(30, 30, 30, 0.4) !important; color: #aaa !important;">
                            </div>
                            <!-- Max Lng -->
                            <div class="col-6 mb-3">
                                <label class="form-label text-warning uppercase">Max Longitude</label>
                                <input type="number" step="0.00000001" class="form-control" id="maxLng" name="max_lng" placeholder="Computed from map" readonly required style="background-color: rgba(30, 30, 30, 0.4) !important; color: #aaa !important;">
                            </div>
                        </div>
                        <!-- Status -->
                        <div class="mb-3">
                            <label class="form-label text-warning uppercase">Status</label>
                            <select class="form-select" id="cityStatus" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-amber">Save Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JS Handling CRUD -->
    <script>
        let modalInstance;
        let autocomplete;
        let map;
        let polygon = null;
        let mapLoaded = false;

        function toggleMapEditor() {
            const wrapper = document.getElementById('mapWrapper');
            if (wrapper.style.display === 'none') {
                wrapper.style.display = 'block';
                // Trigger map initialization or resize
                setTimeout(initMap, 200);
            } else {
                wrapper.style.display = 'none';
            }
        }

        function initMap() {
            if (mapLoaded && map) {
                google.maps.event.trigger(map, 'resize');
                return;
            }

            // Default center is Pune/Mumbai region
            let centerLoc = { lat: 19.18, lng: 72.97 };
            
            const curLat = parseFloat($('#minLat').val()) || parseFloat($('#maxLat').val());
            const curLng = parseFloat($('#minLng').val()) || parseFloat($('#maxLng').val());
            if (curLat && curLng) {
                centerLoc = { lat: curLat, lng: curLng };
            }

            map = new google.maps.Map(document.getElementById('mapEditor'), {
                zoom: 11,
                center: centerLoc,
                mapTypeId: google.maps.MapTypeId.ROADMAP
            });

            // Parse existing polygon coordinates if present
            let pathCoords = [];
            const rawCoords = $('#polygonCoords').val();
            if (rawCoords) {
                try {
                    pathCoords = JSON.parse(rawCoords).map(pt => new google.maps.LatLng(pt.lat, pt.lng));
                } catch(e) {
                    console.error("Failed parsing coords", e);
                }
            }

            // Create Editable and Draggable Polygon
            polygon = new google.maps.Polygon({
                paths: pathCoords,
                strokeColor: '#FFB300',
                strokeOpacity: 0.8,
                strokeWeight: 2.5,
                fillColor: '#FFB300',
                fillOpacity: 0.3,
                editable: true,
                draggable: true
            });
            polygon.setMap(map);

            if (pathCoords.length > 0) {
                const bounds = new google.maps.LatLngBounds();
                pathCoords.forEach(pt => bounds.extend(pt));
                map.fitBounds(bounds);
            }

            // Click on map to add vertex point
            google.maps.event.addListener(map, 'click', function(event) {
                const path = polygon.getPath();
                path.push(event.latLng);
                updateBoundsFromPath(path);
            });

            // Listeners to detect when the polygon vertices are modified (dragged, added, or deleted)
            google.maps.event.addListener(polygon.getPath(), 'set_at', function() {
                updateBoundsFromPath(polygon.getPath());
            });
            google.maps.event.addListener(polygon.getPath(), 'insert_at', function() {
                updateBoundsFromPath(polygon.getPath());
            });
            google.maps.event.addListener(polygon.getPath(), 'remove_at', function() {
                updateBoundsFromPath(polygon.getPath());
            });

            // Right-click a vertex to delete it
            google.maps.event.addListener(polygon, 'rightclick', function(event) {
                if (event.vertex !== undefined) {
                    polygon.getPath().removeAt(event.vertex);
                    updateBoundsFromPath(polygon.getPath());
                }
            });

            mapLoaded = true;
        }

        function updateBoundsFromPath(path) {
            const coords = [];
            let minLat = 90.0, maxLat = -90.0;
            let minLng = 180.0, maxLng = -180.0;

            for (let i = 0; i < path.getLength(); i++) {
                const xy = path.getAt(i);
                const lat = xy.lat();
                const lng = xy.lng();
                coords.push({ lat: lat, lng: lng });

                if (lat < minLat) minLat = lat;
                if (lat > maxLat) maxLat = lat;
                if (lng < minLng) minLng = lng;
                if (lng > maxLng) maxLng = lng;
            }

            if (coords.length > 0) {
                $('#polygonCoords').val(JSON.stringify(coords));
                $('#minLat').val(minLat.toFixed(8));
                $('#maxLat').val(maxLat.toFixed(8));
                $('#minLng').val(minLng.toFixed(8));
                $('#maxLng').val(maxLng.toFixed(8));
            } else {
                $('#polygonCoords').val('');
                $('#minLat').val('');
                $('#maxLat').val('');
                $('#minLng').val('');
                $('#maxLng').val('');
            }
        }

        function clearMapPolygon() {
            if (polygon) {
                polygon.setPath([]);
                updateBoundsFromPath(polygon.getPath());
            }
        }

        function initAutocomplete() {
            const input = document.getElementById('cityName');
            const options = {
                types: ['(cities)'],
                componentRestrictions: { country: 'in' }
            };
            autocomplete = new google.maps.places.Autocomplete(input, options);
            
            autocomplete.addListener('place_changed', function() {
                const place = autocomplete.getPlace();
                if (!place.geometry) {
                    return;
                }
                
                let cityBaseName = place.name;
                
                if (place.address_components) {
                    for (let i = 0; i < place.address_components.length; i++) {
                        const comp = place.address_components[i];
                        if (comp.types.includes('locality')) {
                            cityBaseName = comp.long_name;
                            break;
                        }
                    }
                }
                
                $('#cityName').val(cityBaseName);
                
                // Auto-fill min/max lat/lng from viewport bounds
                const viewport = place.geometry.viewport;
                if (viewport) {
                    $('#minLat').val(viewport.getSouthWest().lat().toFixed(8));
                    $('#maxLat').val(viewport.getNorthEast().lat().toFixed(8));
                    $('#minLng').val(viewport.getSouthWest().lng().toFixed(8));
                    $('#maxLng').val(viewport.getNorthEast().lng().toFixed(8));
                } else if (place.geometry.location) {
                    const lat = place.geometry.location.lat();
                    const lng = place.geometry.location.lng();
                    $('#minLat').val((lat - 0.1).toFixed(8));
                    $('#maxLat').val((lat + 0.1).toFixed(8));
                    $('#minLng').val((lng - 0.1).toFixed(8));
                    $('#maxLng').val((lng + 0.1).toFixed(8));
                }

                // If map is already initialized, pan map to the place location
                if (map && place.geometry) {
                    if (place.geometry.viewport) {
                        map.fitBounds(place.geometry.viewport);
                    } else if (place.geometry.location) {
                        map.setCenter(place.geometry.location);
                        map.setZoom(12);
                    }
                }
            });
        }

        function loadBoundaries() {
            $.getJSON('api_city_boundary.php', { action: 'list' }, function(res) {
                if (res.success) {
                    let activeCount = 0;
                    let html = '';
                    
                    if (res.data.length === 0) {
                        html = `<tr><td colspan="7" class="text-muted py-4">No city boundaries configured.</td></tr>`;
                    } else {
                        res.data.forEach(item => {
                            if (item.status === 'active') activeCount++;
                            
                            html += `
                                <tr id="row-${item.id}">
                                    <td><strong>${item.city_name}</strong></td>
                                    <td>${parseFloat(item.min_lat).toFixed(4)}</td>
                                    <td>${parseFloat(item.max_lat).toFixed(4)}</td>
                                    <td>${parseFloat(item.min_lng).toFixed(4)}</td>
                                    <td>${parseFloat(item.max_lng).toFixed(4)}</td>
                                    <td>
                                        <span class="badge ${item.status === 'active' ? 'bg-success' : 'bg-danger'}">
                                            ${item.status.toUpperCase()}
                                        </span>
                                    </td>
                                    <td>
                                        <button class="action-btn btn-edit me-1" onclick="openEditModal(${JSON.stringify(item).replace(/"/g, '&quot;')})">
                                            <i class="fa-solid fa-pen-to-square"></i> Edit
                                        </button>
                                        <button class="action-btn btn-delete" onclick="deleteCity(${item.id})">
                                            <i class="fa-solid fa-trash-can"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            `;
                        });
                    }
                    
                    $('#totalCities').text(activeCount);
                    $('#boundariesTableBody').html(html);
                } else {
                    $('#boundariesTableBody').html(`<tr><td colspan="7" class="text-danger py-4">${res.message}</td></tr>`);
                }
            }).fail(function() {
                $('#boundariesTableBody').html(`<tr><td colspan="7" class="text-danger py-4">Failed to load data from api_city_boundary.php.</td></tr>`);
            });
        }

        function openAddModal() {
            $('#cityId').val('');
            $('#boundaryForm')[0].reset();
            $('#polygonCoords').val('');
            $('#modalTitleText').text('Add New City Boundary');
            document.getElementById('mapWrapper').style.display = 'none';
            mapLoaded = false;
            if (polygon) {
                polygon.setMap(null);
                polygon = null;
            }
            
            modalInstance = new bootstrap.Modal(document.getElementById('boundaryModal'));
            modalInstance.show();
        }

        function openEditModal(item) {
            $('#cityId').val(item.id);
            $('#cityName').val(item.city_name);
            $('#minLat').val(item.min_lat);
            $('#maxLat').val(item.max_lat);
            $('#minLng').val(item.min_lng);
            $('#maxLng').val(item.max_lng);
            $('#polygonCoords').val(item.polygon_coords || '');
            $('#cityStatus').val(item.status);
            $('#modalTitleText').text('Edit City Boundary');
            document.getElementById('mapWrapper').style.display = 'none';
            mapLoaded = false;
            if (polygon) {
                polygon.setMap(null);
                polygon = null;
            }
            
            modalInstance = new bootstrap.Modal(document.getElementById('boundaryModal'));
            modalInstance.show();
        }

        $('#boundaryForm').on('submit', function(e) {
            e.preventDefault();
            const id = $('#cityId').val();
            const action = id ? 'edit' : 'add';
            const data = $(this).serialize() + '&action=' + action;

            $.post('api_city_boundary.php', data, function(res) {
                if (res.success) {
                    modalInstance.hide();
                    alert(res.message);
                    loadBoundaries();
                } else {
                    alert('Error: ' + res.message);
                }
            }, 'json').fail(function() {
                alert('Connection failure: could not save boundary settings.');
            });
        });

        function deleteCity(id) {
            if (confirm('Are you sure you want to delete this city boundary geofence? Mobile clients will lose this city service area immediately.')) {
                $.post('api_city_boundary.php', { action: 'delete', id: id }, function(res) {
                    if (res.success) {
                        $(`#row-${id}`).fadeOut(300, function() {
                            $(this).remove();
                            loadBoundaries();
                        });
                    } else {
                        alert('Error: ' + res.message);
                    }
                }, 'json').fail(function() {
                    alert('Connection failure: could not delete boundary.');
                });
            }
        }

        $(document).ready(function() {
            loadBoundaries();
            initAutocomplete();
        });
    </script>
</body>
</html>
