$(document).ready(function () {
    // Today and tomorrow date 
    const today = new Date();
    const todayStr = today.toISOString().split('T')[0];
    const tomorrow = new Date(today);
    tomorrow.setDate(today.getDate() + 1);
    const tomorrowStr = tomorrow.toISOString().split('T')[0];

    let allBookings = [];
    let allDrivers = [];
    let allCars = [];
    let allwaitingforapproval = [];
    let allonRide = [];
    let allnewUser=[];
    let allBlocked_Customers=[];
    let allSharedOnboardings = [];
    let currentFilter = "all";
    const rowsPerPage = 7;
    let currentBookingPage = 1;
    let currentDriverPage = 1;
    let currentCarPage = 1;
    let currentWaitingPage = 1;
    let currentonRidePage = 1;
    let currentnewUserPage=1;
    let currentBlocked_CustomerPage=1;
    let currentSharedPage = 1;
    let newUserNotificationCount = 0; // New variable for notification count
    let lastMaxBookingId = 0;
    let unreadNotifications = [];
    function formatDate(dateStr) {
        const [year, month, day] = dateStr.split('-');
        return `${day}-${month}-${year}`;
    }

    function formatTime(timeStr) {
    if (!timeStr || typeof timeStr !== 'string' || !timeStr.includes(":")) {
        return "N/A"; // or return ""; depending on your design
    }
    const [hours, minutes] = timeStr.split(':');
    let hour = parseInt(hours, 10);
    if (isNaN(hour)) return "N/A"; // if somehow not a number
    const period = hour >= 12 ? 'PM' : 'AM';
    hour = hour % 12 || 12;
    return `${hour.toString().padStart(2, '0')}:${minutes} ${period}`;
}


    // Fetch single booking by ID
    function fetchBookingById(bookingId) {
        return $.ajax({
            url: "https://agnicarrental.com/admin2025/admin_booking_list_Agni.php",
            method: "GET",
            dataType: "json"
        }).then(data => {
            const bookings = data.bookingsdata || [];
            const booking = bookings.find(b => b.booking_id == bookingId);
            if (booking) {
                booking.formattedDate = formatDate(booking.date);
                booking.formattedTime = formatTime(booking.time);
                booking.sortTime = new Date(`${booking.date} ${booking.time}`).getTime();
                return booking;
            }
            return null;
        }).catch(error => {
            console.error("Error fetching booking:", error);
            return null;
        });
    }

    // Render pagination controls
    function renderPagination(totalRows, currentPage, tableId, renderFunction) {
        const totalPages = Math.ceil(totalRows / rowsPerPage);
        let paginationHtml = '<div class="pagination">';
        if (totalPages > 1) {
            paginationHtml += `<button class="page-btn" data-page="1" ${currentPage === 1 ? 'disabled' : ''}>First</button>`;
            paginationHtml += `<button class="page-btn" data-page="${currentPage - 1}" ${currentPage === 1 ? 'disabled' : ''}>Previous</button>`;
            const maxPagesToShow = 3;
            let startPage = Math.max(1, currentPage - 1);
            let endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);
            if (endPage - startPage + 1 < maxPagesToShow) {
                startPage = Math.max(1, endPage - maxPagesToShow + 1);
            }
            for (let i = startPage; i <= endPage; i++) {
                paginationHtml += `<button class="page-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
            }
            paginationHtml += `<button class="page-btn" data-page="${currentPage + 1}" ${currentPage === totalPages ? 'disabled' : ''}>Next</button>`;
            paginationHtml += `<button class="page-btn" data-page="${totalPages}" ${currentPage === totalPages ? 'disabled' : ''}>Last</button>`;
        }
        paginationHtml += '</div>';
        $(`#${tableId}Pagination`).html(paginationHtml);
        $(`#${tableId}Pagination .page-btn`).on('click', function () {
            const newPage = parseInt($(this).data('page'));
            if (newPage >= 1 && newPage <= totalPages) {
                if (tableId === 'bookingTable') {
                    currentBookingPage = newPage;
                    filterBookings();
                } else if (tableId === 'driverTable') {
                    currentDriverPage = newPage;
                    renderFunction();
                } else if (tableId === 'carTable') {
                    currentCarPage = newPage;
                    renderFunction();
                } else if (tableId === 'waitingforapprovalTable') {
                    currentWaitingPage = newPage;
                    renderFunction();
                } else if (tableId === 'onrideTable') {
                    currentonRidePage = newPage;
                    renderFunction();
                }
                else if (tableId === 'newuserTable') {
                    currentnewUserPage = newPage;
                    renderFunction();
                }
                else if (tableId === 'sharedOnboardingsTable') {
                    currentSharedPage = newPage;
                    renderFunction();
                }
            }
        });
    }

    // Render booking table with pagination
    function renderBookingTable(bookings, page = currentBookingPage) {
        currentBookingPage = page;
        const start = (page - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        const paginatedBookings = bookings.slice(start, end);

        let tableBody = "";
        if (paginatedBookings.length === 0) {
            tableBody = `<tr><td colspan="12">No bookings found</td></tr>`;
        } else {
            paginatedBookings.forEach((booking, index) => {
                let statusClass = booking.booking_status === "Accepted" ? "badge-accepted" : "badge-pending";
                tableBody += `
                    <tr>
                        <td>${start + index + 1}</td>
                        <td>${booking.booking_id}</td>
                        <td>${booking.from_address.split(',').slice(-3).join(',')}</td>
                        <td>${booking.to_address.split(',').slice(-3).join(',')}</td>
                        <td>
                            <button 
                                class="routeBtn" 
                                data-from="${booking.from_address}" 
                                data-to="${booking.to_address}"
                            >
                                <i class="fas fa-map"></i>
                                <span>Route</span>
                            </button>
                        </td>
                        <td>${booking.formattedDate}</td>
                        <td>${booking.formattedTime}</td>
                        <td>
                            ${(() => {
                                const accType = (booking.accountType || booking.accounttype || '').trim().toLowerCase();
                                return accType === 'agent'
                                    ? `<div><strong>${booking.user_name || 'N/A'}</strong></div>
                                       <div style="font-size: 11px; color: #666; margin-top: 2px;">
                                           AGENT ${booking.user_agency && booking.user_agency !== 'N/A' && booking.user_agency !== '' ? ` - ${booking.user_agency}` : ''}
                                       </div>`
                                    : `${booking.user_name || 'N/A'}`;
                            })()}
                        </td>
                        <td>${booking.booker_id}</td>
                        <td>${booking.booking_status}</td>
                        <td>
                            <button 
                                class="confirmBtn" 
                                data-booking-id="${booking.booking_id}" 
                                data-status="${booking.booking_status}"
                                ${['Accepted', 'Completed', 'Cancelled', 'Started', 'Deleted','temp'].includes(booking.booking_status) ? 'disabled' : ''}
                            >
                                ${booking.booking_status === 'Not Confirmed' ? 'Confirm' : 'Decline'}
                            </button>
                        </td>
                        <td>
                            <button 
                                class="showBtn" 
                                data-booking-id="${booking.booking_id}" 
                                data-index="${start + index}"
                            >
                                <i class="fas fa-eye"></i>
                                <span>Show</span>
                            </button>
                        </td>
                    </tr>
                `;
            });
        }
        $("#bookingTable").html(tableBody);
        renderPagination(bookings.length, currentBookingPage, 'bookingTable', () => filterBookings());
    }


// Show booking modal
function showBookingModal(booking) {
    let html = `
        <p style="color: #a72828; text-align:center;"><strong>Booking Date & Time:</strong> ${booking.booked_at || 'N/A'}</p>
        <h3>Booking Details</h3>
        <p><strong>Booking ID:</strong> ${booking.booking_id || 'N/A'}</p>
        <p><strong>Trip Type:</strong> ${booking.trip_type || 'N/A'}</p>
    `;

    // Function to render modal content for temp status
    function renderModal(distance, carsData) {
        console.log('Rendering modal with distance:', distance, 'carsData:', carsData);
        // Declare driverAllowance outside if-else to ensure scope
        let driverAllowance = distance > 200 ? 400 : 300;
        
        const carTypes = ['Sedan', 'Ertiga', 'Innova', 'Crysta'];
        let priceHtml = '';
        try {
            // Validate carsData
            if (!Array.isArray(carsData)) {
                throw new Error('carsData is not an array');
            }
            carTypes.forEach(carType => {
                // Case-insensitive comparison
                const carData = carsData.find(car => 
                    car.carType && car.carType.toLowerCase() === carType.toLowerCase()
                );
                let kmRate = carData && carData.kmRate ? parseFloat(carData.kmRate) : null;
                // Validate kmRate
                if (kmRate !== null && isNaN(kmRate)) {
                    console.warn(`Invalid kmRate for ${carType}:`, carData.kmRate);
                    kmRate = null;
                }
                const gst = kmRate && distance ? (distance * kmRate * 0.05) : 0;
                const price = kmRate && distance ? (distance * kmRate + distance * 2.25 + driverAllowance + gst) : 'N/A';
                priceHtml += `
                    <p><strong>${carType}:</strong> kmRate: ₹${kmRate ? kmRate.toFixed(2) : 'N/A'}/km, Price: ₹${price !== 'N/A' ? price.toFixed(2) : 'N/A'}</p>
                `;
            });
        } catch (e) {
            console.error('Error generating priceHtml:', e, 'carsData:', carsData);
            priceHtml = '<p>Unable to load pricing data. Please try again.</p>';
        }

        html += `
            <p><strong>From:</strong> ${booking.from_address || 'N/A'}</p>
        `;
        if (booking.trip_type !== "Local-Duty") {
            html += `
                <p><strong>To:</strong> ${booking.to_address || 'N/A'}</p>
            `;
        }
        html += `
            <p><strong>Distance:</strong> ${distance ? distance.toFixed(2) : 'N/A'} km</p>
            <h4>Customer Information</h4>
            <p><strong>Name:</strong> ${booking.user_name || 'N/A'}</p>
            <p><strong>Email:</strong> ${booking.email || 'N/A'}</p>
            <p><strong>Mobile:</strong> ${booking.booker_id || 'N/A'}</p>
            ${(() => {
                const accType = (booking.accountType || booking.accounttype || '').trim().toLowerCase();
                return accType === 'agent' ? `
                    <p><strong>Account Type:</strong> AGENT</p>
                    ${booking.user_agency && booking.user_agency !== 'N/A' && booking.user_agency !== '' ? `<p><strong>Agency:</strong> ${booking.user_agency}</p>` : ''}
                ` : '';
            })()}
            <hr>
            <h4>Estimated Charges</h4>
            ${priceHtml}
        `;
        try {
            console.log('Updating modal with HTML:', html);
            $('#modalDetails').empty();
            $('#modalDetails').html(html);
            $('#bookingModal').css('display', 'block');
            console.log('Modal should now be visible');
        } catch (e) {
            console.error('Error updating modal:', e);
        }
    }

    // Clear any existing loading state
    try {
        $('#modalDetails').empty();
        console.log('Cleared modalDetails content');
    } catch (e) {
        console.error('Error clearing modalDetails:', e);
    }

    // Condition for 'temp' status
    if (booking.booking_status === 'temp') {
        if (typeof $ === 'undefined') {
            console.error('jQuery not loaded. Cannot update modal.');
            return;
        }
        if (typeof google === 'undefined' || !google.maps || !google.maps.DistanceMatrixService) {
            console.error('Google Maps API not loaded. Rendering modal with N/A distance and prices.');
            renderModal(null, []);
            return;
        }

        console.log('Booking object:', booking);

        $.ajax({
            url: `https://agnicarrental.com/2025/selectCarCostList.php?tripType=${encodeURIComponent(booking.trip_type || '')}&bookingId=${encodeURIComponent(booking.booking_id || '')}`,
            method: 'GET',
            dataType: 'json',
            success: function (cars) {
                console.log('API response:', cars);
                const service = new google.maps.DistanceMatrixService();
                service.getDistanceMatrix({
                    origins: [booking.from_address || ''],
                    destinations: [booking.to_address || ''],
                    travelMode: 'DRIVING',
                    unitSystem: google.maps.UnitSystem.METRIC
                }, (response, status) => {
                    if (status === 'OK' && response.rows[0]?.elements[0]?.status === 'OK') {
                        const distanceInKm = response.rows[0].elements[0].distance.value / 1000;
                        console.log(`Distance calculated: ${distanceInKm} km`);
                        renderModal(distanceInKm, cars || []);
                    } else {
                        console.error('Distance Matrix API error:', status, response);
                        renderModal(null, cars || []);
                    }
                });
            },
            error: function (err) {
                console.error('Error fetching kmRate from API:', err);
                const service = new google.maps.DistanceMatrixService();
                service.getDistanceMatrix({
                    origins: [booking.from_address || ''],
                    destinations: [booking.to_address || ''],
                    travelMode: 'DRIVING',
                    unitSystem: google.maps.UnitSystem.METRIC
                }, (response, status) => {
                    if (status === 'OK' && response.rows[0]?.elements[0]?.status === 'OK') {
                        const distanceInKm = response.rows[0].elements[0].distance.value / 1000;
                        console.log(`Distance calculated: ${distanceInKm} km, kmRate: N/A (API failed)`);
                        renderModal(distanceInKm, []);
                    } else {
                        console.error('Distance Matrix API error:', status, response);
                        renderModal(null, []);
                    }
                });
            }
        });
    } else {
        if (booking.trip_type === "Round-Trip") {
            html += `
                <p><strong>Return Date-time:</strong> ${booking.return_date || 'N/A'}-${booking.return_time || 'N/A'}</p>
            `;
        }
        html += `
            <p><strong>From:</strong> ${booking.from_address || 'N/A'}</p>
        `;
        if (booking.trip_type !== "Local-Duty") {
            html += `
                <p><strong>To:</strong> ${booking.to_address || 'N/A'}</p>
            `;
        }
        html += `
            <p><strong>Distance:</strong> ${booking.distance || 'N/A'} km</p>
            <p><strong>Car Type:</strong> ${booking.car_type || 'N/A'}</p>
            <hr>
            <h4>Customer Information</h4>
            <p><strong>Name:</strong> ${booking.user_name || 'N/A'}</p>
            <p><strong>Email:</strong> ${booking.email || 'N/A'}</p>
            <p><strong>Mobile:</strong> ${booking.booker_id || 'N/A'}</p>
            ${(() => {
                const accType = (booking.accountType || booking.accounttype || '').trim().toLowerCase();
                return accType === 'agent' ? `
                    <p><strong>Account Type:</strong> AGENT</p>
                    ${booking.user_agency && booking.user_agency !== 'N/A' && booking.user_agency !== '' ? `<p><strong>Agency:</strong> ${booking.user_agency}</p>` : ''}
                ` : '';
            })()}
        `;
        if (!['Not Confirmed', 'Pending', 'Deleted', 'temp'].includes(booking.booking_status)) {
            html += `
                <hr>
                <h4>Driver Information</h4>
                <p><strong>Driver Name:</strong> ${booking.driver_name || 'N/A'}</p>
                <p><strong>Driver Number:</strong> ${booking.driver_id || 'N/A'}</p>
                <p><strong>Car Number:</strong> ${booking.vehicle_id || 'N/A'}</p>
                <hr>
                <h4>Vendor Information</h4>
                <p><strong>Agency Name:</strong> ${booking.driver_agency || 'N/A'}</p>
                <p><strong>Vendor Name:</strong> ${booking.driver_agency || 'N/A'}</p>
                <p><strong>Vendor Number:</strong> ${booking.vender_id || 'N/A'}</p>
            `;
        }
        html += `
            <hr>
            <h4>Charges & Payment</h4>
            <p><strong>Base Charge:</strong> ₹${booking.base_charge || 'N/A'}</p>
            <p><strong>Driver TA:</strong> ₹${booking.driver_ta || 'N/A'}</p>
            <p><strong>Toll Charge:</strong> ₹${booking.toll_charge || 'N/A'}</p>
            <p><strong>Total Amount:</strong> ₹${booking.total_amount || 'N/A'}</p>
            <p><strong>Vendor Amount:</strong> ${booking.vendor_amount || 'N/A'}</p>
            <p><strong>Agni Amount:</strong> ${booking.agni_amount || 'N/A'}</p>
            <p><strong>Commission:</strong> ${booking.agent_commission || 'N/A'}</p>
            <p><strong>Payment Status:</strong> ${booking.payment_status || 'N/A'}</p>
        `;
        try {
            console.log('Updating modal for non-temp status with HTML:', html);
            $('#modalDetails').empty();
            $('#modalDetails').html(html);
            $('#modalBookingModal').css('display', 'block');
        } catch (e) {
            console.error('Error updating modal for non-temp:', e);
        }
    }
}

 // Show route modal with Google Map
    function showRouteModal(fromAddress, toAddress) {
        $('#routeAddresses').html(`<strong>From:</strong> ${fromAddress}<br><strong>To:</strong> ${toAddress}`);
        $('#routeError').hide();
        $('#routeModal').fadeIn();

        const map = new google.maps.Map(document.getElementById('routeMap'), {
            zoom: 12,
            center: { lat: 19.0760, lng: 72.8777 } // Default center (Mumbai)
        });

        const geocoder = new google.maps.Geocoder();
        const directionsService = new google.maps.DirectionsService();
        const directionsRenderer = new google.maps.DirectionsRenderer({
            map: map,
            suppressMarkers: true // We'll add custom markers
        });

        let fromLatLng, toLatLng;

        // Geocode "From" address
        geocoder.geocode({ address: fromAddress }, (results, status) => {
            if (status === 'OK' && results[0]) {
                fromLatLng = results[0].geometry.location;
                map.setCenter(fromLatLng);
                new google.maps.Marker({
                    position: fromLatLng,
                    map: map,
                    label: 'A',
                    title: 'From'
                });

                // Geocode "To" address
                geocoder.geocode({ address: toAddress }, (results, status) => {
                    if (status === 'OK' && results[0]) {
                        toLatLng = results[0].geometry.location;
                        new google.maps.Marker({
                            position: toLatLng,
                            map: map,
                            label: 'B',
                            title: 'To'
                        });

                        // Calculate and display driving route
                        directionsService.route(
                            {
                                origin: fromLatLng,
                                destination: toLatLng,
                                travelMode: google.maps.TravelMode.DRIVING
                            },
                            (result, status) => {
                                if (status === 'OK') {
                                    directionsRenderer.setDirections(result);

                                    // Adjust map bounds to fit the route
                                    const bounds = new google.maps.LatLngBounds();
                                    bounds.extend(fromLatLng);
                                    bounds.extend(toLatLng);
                                    map.fitBounds(bounds);
                                } else {
                                    $('#routeError').text(`Unable to calculate route (${status}). Please check the addresses or try again.`);
                                    $('#routeError').show();
                                    console.error('Directions request failed:', status);
                                }
                            }
                        );
                    } else {
                        $('#routeError').text(`Unable to geocode destination address (${status}). Please verify the address.`);
                        $('#routeError').show();
                        console.error('Geocoding "To" failed:', status);
                    }
                });
            } else {
                $('#routeError').text(`Unable to geocode starting address (${status}). Please verify the address.`);
                $('#routeError').show();
                console.error('Geocoding "From" failed:', status);
            }
        });
    }

    // Route button click handler
    $(document).on('click', '.routeBtn', function () {
        const fromAddress = $(this).data('from');
        const toAddress = $(this).data('to');
        console.log('Route button clicked:', { fromAddress, toAddress }); // Debug log
        showRouteModal(fromAddress, toAddress);
    });

    // Show button click handler
    $(document).on('click', '.showBtn', function () {
        const bookingId = $(this).data('booking-id');
        const index = $(this).data('index');
        $('#modalDetails').html('<p>Loading...</p>');
        $('#bookingModal').fadeIn();
        fetchBookingById(bookingId).then(booking => {
            if (booking) {
                allBookings[index] = booking;
                showBookingModal(booking);
            } else {
                const fallbackBooking = allBookings[index];
                if (fallbackBooking) {
                    showBookingModal(fallbackBooking);
                } else {
                    $('#modalDetails').html('<p>Error: Booking not found.</p>');
                }
            }
        });
    });

    // Close modals
    $('.closeBtn').on('click', function () {
        $('#bookingModal').fadeOut();
        $('#routeModal').fadeOut();
    });

    $(window).on('click', function (e) {
        if ($(e.target).is('#bookingModal') || $(e.target).is('#routeModal')) {
            $('#bookingModal').fadeOut();
            $('#routeModal').fadeOut();
        }
    });

function renderDriverTable(drivers, page = currentDriverPage) {
    console.log('Rendering driver table with drivers:', drivers); // Debug: Log all drivers
    currentDriverPage = page;
    const start = (page - 1) * rowsPerPage;
    const end = start + rowsPerPage;
    const paginatedDrivers = drivers.slice(start, end);
    let tableBody = "";
    if (paginatedDrivers.length === 0) {
        tableBody = `<tr><td colspan="12">No Drivers found</td></tr>`;
    } else {
        paginatedDrivers.forEach((driver, index) => {
            console.log(`Driver ID: ${driver.driver_id}, Vehicle Number: ${driver.vehicle_number}`); // Debug: Log each driver
            let statusClass = driver.status === "active" ? "badge-accepted" : "badge-pending";
            // Apply inline style based on vehicle_number with !important
            const rowStyle = driver.vehicle_number ? 'style="background-color: #00ff00 !important;"' : 'style="background-color: #fcfafa !important;"';
            tableBody += `
                <tr>
                    <td>${driver.driver_id}</td>
                    <td>${driver.phone_number}</td>
                    <td>${driver.full_name || 'N/A'}</td>
                    <td>${formatDate(driver.created_at.split(' ')[0])}</td>
                    <td>${driver.driver_city || 'N/A'}</td>
                    <td>${driver.license_doe || 'N/A'}</td>
                    <td ${rowStyle}>${driver.vehicle_number || 'Not filled<br> car details'}</td>
                    <td>
                      <a href="regForm.php?phone_number=${encodeURIComponent(driver.phone_number)}" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm">
                        Edit
                      </a>
                    </td>
                    <td>${driver.status || 'N/A'}</td>
                    <td>
                   <button 
                  class="confirmBtn1 btn btn-sm"
                  data-driver-id="${driver.driver_id}" 
                  data-status="${driver.status}"
                  ${!['active', 'inactive', 'filled'].includes(driver.status) ? 'disabled title="Status change not allowed"' : ''}
                  style="background-color: ${
                    driver.status === 'active' ? '#dc3545' : 
                    (driver.status === 'inactive' || driver.status === 'filled') ? '#28a745' : '#6c757d'
                  }; color: white;">
                  ${
                    driver.status === 'active' ? 'Inactivate' : 
                    (driver.status === 'inactive' || driver.status === 'filled') ? 'Activate' : 'Not Allowed'
                  }
                </button>
                </td>
                </tr>`;
        });
    }
    $("#driverTable").html(tableBody);
    renderPagination(drivers.length, currentDriverPage, 'driverTable', () => renderDriverTable(drivers));
}


    // Render car table
    function renderCarTable(cars, page = currentCarPage) {
        currentCarPage = page;
        const start = (page - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        const paginatedCars = cars.slice(start, end);
        let tableBody = "";
        if (paginatedCars.length === 0) {
            tableBody = `<tr><td colspan="12">No Cars found</td></tr>`;
        } else {
        paginatedCars.forEach((car, index) => {
            let statusClass = car.status === "active" ? "badge-accepted" : "badge-pending";
            tableBody += `
                <tr>
                    <td>${car.id}</td>
                    <td>${car.vehicle_number}</td>
                    <td>${car.vehicle_type}</td>
                    <td>${car.vehicle_name}</td>
                    <td>${car.fuel_type}</td>
                    <td>${car.owner_id}</td>
                    <td>${car.driver_city}</td>
                    <td><a href="carForm.php?vehicle_number=${encodeURIComponent(car.vehicle_number)}" target="_blank" class="btn btn-primary btn-sm">Edit</a></td>
                    <td>${car.status || 'N/A'}</td>
                    <td>
                      <button 
                      class="confirmBtn3 btn btn-sm"
                      data-car-id="${car.id}" 
                      data-status="${car.status}"
                      ${!['active', 'inactive'].includes(car.status) ? 'disabled title="Status change not allowed"' : ''}
                      style="background-color: ${
                        car.status === 'active' ? '#dc3545' : 
                        (car.status === 'inactive' || car.status === 'filled') ? '#28a745' : '#6c757d'
                      }; color: white;">
                      ${
                        car.status === 'active' ? 'Inactivate' : 
                        (car.status === 'inactive' || car.status === 'filled') ? 'Activate' : 'Not Allowed'
                      }
                    </button>
                    </td>
                </tr>`;
        });
        }
        $("#carTable").html(tableBody);
        renderPagination(cars.length, currentCarPage, 'carTable', () => renderCarTable(cars));
    }

    // Render waiting for approval table
    function renderwaitingforapprovalTable(waitingforapproval, page = currentWaitingPage) {
        const filtered1 = waitingforapproval.filter(item => item.status === "Notified");
        currentWaitingPage = page;
        const start = (page - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        const paginatedWaiting = filtered1.slice(start, end);
        

        let tableBody = "";
        if (paginatedWaiting.length === 0) {
            tableBody = `<tr><td colspan="12">No Documents found</td></tr>`;
        } else {
        paginatedWaiting.forEach((waitingforapproval, index) => {
            let statusClass = waitingforapproval.status === "active" ? "badge-accepted" : "badge-pending";
            tableBody += `
                <tr>
                    <td>${waitingforapproval.id}</td>
                    <td>${waitingforapproval.vehicle_number}</td>
                    <td>${waitingforapproval.owner_id || 'N/A'}</td>
                    
                    
                     <!-- Insurance -->
                <td>Number:${waitingforapproval.insurance_number || 'N/A'} <br>
                <strong>
                D.O.E: ${waitingforapproval.insurance_doe || 'N/A'}</strong></td>
                
                <!-- PUC -->
                <td style="min-width: 100px;">D.O.I: ${waitingforapproval.puc_doi || 'N/A'}<br/>
                  <strong>  D.O.E: ${waitingforapproval.puc_doe || 'N/A'}</strong></td>
                
                <!-- Taxi Permit -->
                <td>Number: ${waitingforapproval.texi_permit_no || 'N/A'}<br>
                D.O.I: ${waitingforapproval.texi_permit_doi || 'N/A'}<br>
                <strong>D.O.E: ${waitingforapproval.texi_permit_doe || 'N/A'}</strong>
                </td>
                
                <!-- Fitness Certificate -->
                <td>Number: ${waitingforapproval.fitness_certificate_no || 'N/A'}<br>
                D.O.I: ${waitingforapproval.fitness_certificate_doi || 'N/A'}<br>
                <strong>D.O.E: ${waitingforapproval.fitness_certificate_doe || 'N/A'}</strong>
                </td>

                <!-- Status -->
                <td>${waitingforapproval.status || 'N/A'}</td>

                <!-- Confirmation Button -->
                <td>
                   <button
                         class="confirmBtn2 btn btn-sm"
                         data-waiting-id="${waitingforapproval.id}"
                         data-status="${waitingforapproval.status}"
                         ${['Inactivate', 'DocExpaire', 'waiting for approval'].includes(waitingforapproval.status) ? 'disabled="disabled"' : ''}
                       >
                         ${waitingforapproval.status === 'active' ? 'Notified' : 'active'}
                       </button>
                </td>
                </tr>`;
        });
        }
        $("#waitingforapprovalTable").html(tableBody);
        renderPagination(filtered1.length, currentWaitingPage, 'waitingforapprovalTable', () => renderwaitingforapprovalTable(waitingforapproval));
    }
    
    // render onride table 
        function renderonRideTable(onrides, page = currentonRidePage) {
            currentonRidePage = page;
            const start = (page - 1) * rowsPerPage;
            const end = start + rowsPerPage;
            const paginatedonRides = onrides.slice(start, end);
            let tableBody = "";
        if (paginatedonRides.length === 0) {
                    tableBody = `<tr><td colspan="12">Not Current trip Available</td></tr>`;
                } else {
            paginatedonRides.forEach((onride, index) => {
                let statusClass = onride.booking_status === "Pending" ? "badge-accepted" : "badge-pending";
        
                // Ensure latitude and longitude are valid before showing the button
                const hasLocation = onride.latitude && onride.longitude;
                const mapButton = hasLocation
            ? `<button class="view-map-button" onclick="viewDriverOnMap(${parseFloat(onride.latitude)}, ${parseFloat(onride.longitude)})">
            <i class="fas fa-map-marker-alt"></i> Location
        </button>
        `
            : `<span class="no-location-text">Null</span>`;
        
        
        
                tableBody += `
                    <tr>
                        <td>${onride.booking_id}</td>
                        <td>${onride.driver_name}</td>
                        <td>${onride.driver_id || 'N/A'}</td>
                        <td>
                            ${(() => {
                                const accType = (onride.accountType || onride.accounttype || '').trim().toLowerCase();
                                return accType === 'agent'
                                    ? `<div><strong>${onride.user_name || 'N/A'}</strong></div>
                                       <div style="font-size: 11px; color: #666; margin-top: 2px;">
                                           AGENT ${onride.user_agency && onride.user_agency !== 'N/A' && onride.user_agency !== '' ? ` - ${onride.user_agency}` : ''}
                                       </div>`
                                    : `${onride.user_name || 'N/A'}`;
                            })()}
                        </td>
                        <td>${onride.mobile || 'N/A'}</td>
                        <td>${mapButton}</td>
                        <td><strong>${onride.booking_status || 'N/A'}</strong></td>
                        <td><button 
                                        class="showBtn" 
                                        data-booking-id="${onride.booking_id}" 
                                        data-index="${start + index}"
                                    >
                                        <i class="fas fa-eye"></i>
                                        <span>Show</span>
                                    </button></td>
                    </tr>`;
            });
        }
            $("#onrideTable").html(tableBody);
            renderPagination(onrides.length, currentonRidePage, 'onrideTable', () => renderonRideTable(onrides));
        }


// Render newuser table
   function rendernewUserTable(newusers, page = currentnewUserPage) {
    console.log("Rendering users:", newusers); // Debug log
    currentnewUserPage = page;
    const start = (page - 1) * rowsPerPage;
    const end = start + rowsPerPage;
    const paginatednewUsers = newusers.slice(start, end);
    let tableBody = "";
    if (paginatednewUsers.length === 0) {
        tableBody = `<tr><td colspan="12">No user found</td></tr>`;
    } else {
        paginatednewUsers.forEach((newuser, index) => {
            let statusClass = newuser.status === "active" ? "badge-accepted" : "badge-pending";
            tableBody += `
                <tr>
                    <td>${start + index + 1}</td>
                    <td>${newuser.id}</td>
                    <td>${newuser.phone_number}</td>
                    <td>${newuser.name || 'N/A'}</td>
                    <td>${newuser.email || 'N/A'}</td>
                    <td>${newuser.city || 'N/A'}</td>
                    <td>${newuser.pincode || 'N/A'}</td>
                    <td>${formatDate(newuser.created_at.split(' ')[0])}</td>
                    <td>${newuser.accountType || 'N/A'}</td>
                    <td>${newuser.agency_name || 'N/A'}</td>
                </tr>`;
        });
    }
    $("#newuserTable").html(tableBody);
    renderPagination(newusers.length, currentnewUserPage, 'newuserTable', () => rendernewUserTable(newusers));
}

        function updateNewUserNotificationCount(users) {
            const lastChecked = localStorage.getItem('newUserLastChecked');
            let newUsers = users;
            if (lastChecked) {
                const lastCheckedDate = new Date(lastChecked);
                newUsers = users.filter(user => new Date(user.created_at) > lastCheckedDate);
            }
            newUserNotificationCount = newUsers.length;
            $("#newuserCount").text(newUserNotificationCount);
            $("#newuserCount").css('display', newUserNotificationCount > 0 ? 'inline-block' : 'none');
        }


// Render Blocked Customer table
   function renderBlocked_CustomerTable(Blocked_Customers, page = currentBlocked_CustomerPage) {
    console.log("Rendering Blocked Customer:", Blocked_Customers); // Debug log
    currentBlocked_CustomerPage = page;
    const start = (page - 1) * rowsPerPage;
    const end = start + rowsPerPage;
    const paginatedBlocked_Customers = Blocked_Customers.slice(start, end);
    let tableBody = "";
    if (paginatedBlocked_Customers.length === 0) {
        tableBody = `<tr><td colspan="12">No Blocked Customer found</td></tr>`;
    } else {
        paginatedBlocked_Customers.forEach((Blocked_Customer, index) => {
            let statusClass = Blocked_Customer.status === "active" ? "badge-accepted" : "badge-pending";
            tableBody += `
                <tr>
                    <td>${start + index + 1}</td>
                    <td>${Blocked_Customer.id}</td>
                    <td>${Blocked_Customer.phone_number}</td>
                    <td>${Blocked_Customer.name || 'N/A'}</td>
                    <td>${Blocked_Customer.email || 'N/A'}</td>
                    <td>${Blocked_Customer.city || 'N/A'}</td>
                    <td>${Blocked_Customer.pincode || 'N/A'}</td>
                    <td>${formatDate(Blocked_Customer.created_at.split(' ')[0])}</td>
                    <td>${Blocked_Customer.accountType || 'N/A'}</td>
                    <td>${Blocked_Customer.agency_name || 'N/A'}</td>
                </tr>`;
        });
    }
    $("#Blocked_CustomerTable").html(tableBody);
    renderPagination(Blocked_Customers.length, currentBlocked_CustomerPage, 'Blocked_CustomerTable', () => renderBlocked_CustomerTable(Blocked_Customers));
}
    // Fetch bookings
    function fetchBookings() {
        $.getJSON("https://agnicarrental.com/admin2025/admin_booking_list_Agni.php", function (data) {
            allBookings = data.bookingsdata || [];
            if (lastMaxBookingId === 0 && allBookings.length > 0) {
                lastMaxBookingId = Math.max(...allBookings.map(b => parseInt(b.booking_id) || 0));
                console.log("Initial max booking ID set to:", lastMaxBookingId);
            }
            let todayCount = 0;
            let tomorrowCount = 0;
            allBookings.forEach(booking => {
                booking.formattedDate = formatDate(booking.date);
                booking.formattedTime = formatTime(booking.time);
                booking.sortTime = new Date(`${booking.date} ${booking.time}`).getTime();
                if (booking.date === todayStr && booking.booking_status !== "Completed") todayCount++;
                if (booking.date === tomorrowStr && booking.booking_status !== "Completed") tomorrowCount++;
            });
            currentFilter = "all";
            currentBookingPage = 1;
            filterBookings();
            const urlParamsLocal = new URLSearchParams(window.location.search);
            if (!urlParamsLocal.get('tab')) {
                $("#bookingTableContainer").removeClass("hidden");
                $("#driverTableContainer").addClass("hidden");
                $("#carTableContainer").addClass("hidden");
                $("#waitingforapprovalTableContainer").addClass("hidden");
            }
            $("#latestBooking").text(todayCount + tomorrowCount);
            $("#refreshBookings").show();
        }).fail(function () {
            $("#bookingTable").html("<tr><td colspan='12'>Error loading bookings</td></tr>");
            $("#bookingHeading").text("All Bookings");
            $("#refreshBookings").show();
        });
    }

    // Fetch drivers
    function fetchDrivers() {
        $.getJSON("https://agnicarrental.com/admin2025/driver_list_Agni.php", function (data) {
            allDrivers = data.driversdata || [];
            $("#driverCount").text(allDrivers.length);
            currentDriverPage = 1;
            renderDriverTable(allDrivers);
        }).fail(function () {
            $("#driverTable").html("<tr><td colspan='9'>Error loading drivers</td></tr>");
        });
    }

    // Fetch cars
    function fetchCars() {
        $.getJSON("https://agnicarrental.com/admin2025/car_list_Agni.php", function (data) {
            allCars = data.carsdata || [];
            $("#carCount").text(allCars.length);
            currentCarPage = 1;
            renderCarTable(allCars);
        }).fail(function () {
            $("#carTable").html("<tr><td colspan='9'>Error loading cars</td></tr>");
        });
    }

    // Fetch waiting for approval
    function fetchwaitingforapproval() {
        $.getJSON("https://agnicarrental.com/admin2025/car_list_Agni.php", function (data) {
            allwaitingforapproval = data.carsdata || [];
            const filtered1 = allwaitingforapproval.filter(item => item.status === "Notified");
            $("#waitingforapprovalCount").text(filtered1.length);
            currentWaitingPage = 1;
            renderwaitingforapprovalTable(allwaitingforapproval);
        }).fail(function () {
            $("#waitingforapprovalTable").html("<tr><td colspan='9'>Error loading documents</td></tr>");
        });
    }
    
    // Fetch onrides
    function fetchonRides() {
        $.getJSON("https://agnicarrental.com/admin2025/onride_list_Agni.php", function (data) {
            allonRides = data.onridesdata || [];
            $("#onRideCount").text(allonRides.length);
            currentonRidePage = 1;
            renderonRideTable(allonRides);
        }).fail(function () {
            $("#onrideTable").html("<tr><td colspan='9'>Error loading onrides</td></tr>");
        });
    }
    
    // Fetch newusers
   function fetchnewUsers() {
    $.getJSON("https://agnicarrental.com/admin2025/newuser_list_Agni.php", function (data) {
        allnewUsers = data.newusersdata || [];
        console.log("Fetched users:", allnewUsers);
        updateNewUserNotificationCount(allnewUsers); // Update notification count
        currentnewUserPage = 1;
        rendernewUserTable(allnewUsers);
    }).fail(function () {
        console.error("Failed to fetch users");
        $("#newuserTable").html("<tr><td colspan='9'>Error loading Users</td></tr>");
    });
}
    
    
     // Fetch Blocked_Customer
   function fetchBlocked_Customer() {
    $.getJSON("https://agnicarrental.com/admin2025/blocked_customer.php", function (data) {
        allBlocked_Customers = data.Blocked_Customerdata || [];
        console.log("Fetched users:", allBlocked_Customers);
        
        currentBlocked_CustomerPage = 1;
        renderBlocked_CustomerTable(allBlocked_Customers);
    }).fail(function () {
        console.error("Failed to fetch Blocked_Customer");
        $("#Blocked_CustomerTable").html("<tr><td colspan='9'>Error loading Blocked_Customer</td></tr>");
    });
}

    // Tab click handler
    $("#bookingTabs .nav-link").on("click", function () {
        currentFilter = $(this).data("filter");
        currentBookingPage = 1;
        $("#bookingSearchContainer").show();
        filterBookings();
    });
 // Completed sidebar link
    $("#Complete").on("click", function () {
        currentFilter = "completed";
        currentBookingPage = 1;
        $("#bookingSearchContainer").show();
        filterBookings();
        $("#bookingTableContainer").removeClass("hidden");
        $("#carTableContainer").addClass("hidden");
        $("#driverTableContainer").addClass("hidden");
        $("#waitingforapprovalTableContainer").addClass("hidden");
        $("#onrideTableContainer").addClass("hidden");
        $("#newuserTableContainer").addClass("hidden");
        $("#Blocked_CustomerTableContainer").addClass("hidden");
        $("#sharedOnboardingsTableContainer").addClass("hidden");

        $("#refreshBookings").show();
    });
    
        // customer sidebar link
   $("#newuser").on("click", function () {
    currentnewUserPage = 1;
    rendernewUserTable(allnewUsers);
    $("#newuserTableContainer").removeClass("hidden");
    $("#carTableContainer").addClass("hidden");
    $("#bookingTableContainer").addClass("hidden");
    $("#waitingforapprovalTableContainer").addClass("hidden");
    $("#onrideTableContainer").addClass("hidden");
    $("#driverTableContainer").addClass("hidden");
    $("#Blocked_CustomerTableContainer").addClass("hidden");
    $("#sharedOnboardingsTableContainer").addClass("hidden");
    $("#refreshnewUser").show();
    // Reset notification count
    newUserNotificationCount = 0;
    localStorage.setItem('newUserLastChecked', new Date().toISOString());
    $("#newuserCount").text(newUserNotificationCount);
    $("#newuserCount").css('display', 'none');
});

     // Blocked_Customer sidebar link
   $("#Blocked_Customer").on("click", function () {
    currentBlocked_CustomerPage = 1;
    renderBlocked_CustomerTable(allBlocked_Customers);
    $("#Blocked_CustomerTableContainer").removeClass("hidden");
    $("#carTableContainer").addClass("hidden");
    $("#newuserTableContainer").addClass("hidden");
    $("#bookingTableContainer").addClass("hidden");
    $("#waitingforapprovalTableContainer").addClass("hidden");
    $("#onrideTableContainer").addClass("hidden");
    $("#driverTableContainer").addClass("hidden");
    $("#sharedOnboardingsTableContainer").addClass("hidden");
});
   
    // driver sidebar link
    $("#driver").on("click", function () {
        currentDriverPage = 1;
        renderDriverTable(allDrivers);
        $("#driverTableContainer").removeClass("hidden");
        $("#carTableContainer").addClass("hidden");
        $("#bookingTableContainer").addClass("hidden");
        $("#waitingforapprovalTableContainer").addClass("hidden");
        $("#onrideTableContainer").addClass("hidden");
        $("#newuserTableContainer").addClass("hidden");
        $("#Blocked_CustomerTableContainer").addClass("hidden");
        $("#sharedOnboardingsTableContainer").addClass("hidden");
    });
    
    // car sidebar link
    $("#cab").on("click", function () {
        currentCarPage = 1;
        renderCarTable(allCars);
        $("#carTableContainer").removeClass("hidden");
        $("#driverTableContainer").addClass("hidden");
        $("#bookingTableContainer").addClass("hidden");
        $("#waitingforapprovalTableContainer").addClass("hidden");
        $("#onrideTableContainer").addClass("hidden");
        $("#newuserTableContainer").addClass("hidden");
        $("#Blocked_CustomerTableContainer").addClass("hidden");
        $("#sharedOnboardingsTableContainer").addClass("hidden");
    });
    
    
    // Latest Bookings card click
    $("#latestBookingCard").on("click", function () {
        currentFilter = "latest";
        currentBookingPage = 1;
        $("#bookingSearchContainer").show();
        filterBookings();
        $("#bookingTableContainer").removeClass("hidden");
        $("#carTableContainer").addClass("hidden");
        $("#driverTableContainer").addClass("hidden");
        $("#waitingforapprovalTableContainer").addClass("hidden");
        $("#onrideTableContainer").addClass("hidden");
        $("#newuserTableContainer").addClass("hidden");
        $("#Blocked_CustomerTableContainer").addClass("hidden");
        $("#sharedOnboardingsTableContainer").addClass("hidden");
        $("#refreshBookings").show();
    });

    // Driver card click
    $("#driverCard").on("click", function () {
        currentDriverPage = 1;
        renderDriverTable(allDrivers);
        $("#driverTableContainer").removeClass("hidden");
        $("#carTableContainer").addClass("hidden");
        $("#bookingTableContainer").addClass("hidden");
        $("#waitingforapprovalTableContainer").addClass("hidden");
        $("#onrideTableContainer").addClass("hidden");
        $("#newuserTableContainer").addClass("hidden");
        $("#Blocked_CustomerTableContainer").addClass("hidden");
        $("#sharedOnboardingsTableContainer").addClass("hidden");

    });

    // Car card click
    $("#carCard").on("click", function () {
        currentCarPage = 1;
        renderCarTable(allCars);
        $("#carTableContainer").removeClass("hidden");
        $("#driverTableContainer").addClass("hidden");
        $("#bookingTableContainer").addClass("hidden");
        $("#waitingforapprovalTableContainer").addClass("hidden");
        $("#onrideTableContainer").addClass("hidden");
        $("#newuserTableContainer").addClass("hidden");
        $("#Blocked_CustomerTableContainer").addClass("hidden");
        $("#sharedOnboardingsTableContainer").addClass("hidden");

    });
    

    
    // Waiting for approval card click
    $("#waitingforapprovalCard").on("click", function () {
        currentWaitingPage = 1;
        renderwaitingforapprovalTable(allwaitingforapproval);
        $("#waitingforapprovalTableContainer").removeClass("hidden");
        $("#carTableContainer").addClass("hidden");
        $("#driverTableContainer").addClass("hidden");
        $("#bookingTableContainer").addClass("hidden");
        $("#onrideTableContainer").addClass("hidden");
        $("#newuserTableContainer").addClass("hidden");
        $("#Blocked_CustomerTableContainer").addClass("hidden");
        $("#sharedOnboardingsTableContainer").addClass("hidden");

    });


// onRide card click
    $("#onrideCard").on("click", function () {
        currentonRidePage = 1;
        renderonRideTable(allonRides);
        $("#onrideTableContainer").removeClass("hidden");
        $("#driverTableContainer").addClass("hidden");
        $("#carTableContainer").addClass("hidden");
        $("#bookingTableContainer").addClass("hidden");
        $("#waitingforapprovalTableContainer").addClass("hidden");
        $("#newuserTableContainer").addClass("hidden");
        $("#sharedOnboardingsTableContainer").addClass("hidden");
    });
    

    
    // Refresh bookings
    $("#refreshBookings").on("click", function (e) {
        e.preventDefault();
        currentFilter = "all";
        currentBookingPage = 1;
        fetchBookings();
    });

    // Refresh Driver
    $("#refreshDriver").on("click", function (e) {
        e.preventDefault();
        currentDriverPage = 1;
        fetchDrivers();
    });

    // Refresh Car
    $("#refreshCar").on("click", function (e) {
        e.preventDefault();
        currentCarPage = 1;
        fetchCars();
    });

    // Refresh waitingforapproval
    $("#refreshwaitingforapproval").on("click", function (e) {
        e.preventDefault();
        currentWaitingPage = 1;
        fetchwaitingforapproval();
    });

    // Refresh onRide
    $("#refreshonRide").on("click", function (e) {
        e.preventDefault();
        currentonRidePage = 1;
        fetchonRide();
    });
    
     // Refresh newUser
    $("#refreshnewUser").on("click", function (e) {
        e.preventDefault();
        currentnewUserPage = 1;
        fetchnewUsers();
    });
    
    // Refresh Blocked_Customer 
     $("#refreshBlocked_Customer").on("click", function (e) {
        e.preventDefault();
        currentBlocked_CustomerPage = 1;
        fetchBlocked_Customer();
    });
    
    // Filter bookings
    function filterBookings(searchTerm = '') {
        let filteredBookings = allBookings;
        let headingText = "";
        if (currentFilter === "completed") {
            filteredBookings = allBookings.filter(booking => booking.booking_status === "Completed");
            headingText = "Completed Bookings";
        } else if (currentFilter === "latest") {
            filteredBookings = allBookings.filter(booking =>
                (booking.date === todayStr || booking.date === tomorrowStr) &&
                booking.booking_status !== "Completed"
            );
            filteredBookings.sort((a, b) => a.sortTime - b.sortTime);
            headingText = "Latest Bookings";
        } else {
            filteredBookings = allBookings.filter(booking => booking.booking_status !== "Completed");
            if (currentFilter !== "all") {
                filteredBookings = filteredBookings.filter(booking => 
                    booking.trip_type.toLowerCase() === currentFilter.toLowerCase()
                );
            }
            switch (currentFilter) {
                case "all": headingText = "All Bookings"; break;
                case "One-way": headingText = "One Way Trip Bookings"; break;
                case "Round-Trip": headingText = "Round Trip Bookings"; break;
                case "Local-taxi": headingText = "Local Taxi Bookings"; break;
                case "Local-Duty": headingText = "Local Duty Bookings"; break;
            }
        }
        if (searchTerm) {
            filteredBookings = filteredBookings.filter(b =>
                (b.booking_id || "").toLowerCase().includes(searchTerm) ||
                (b.from_address || "").toLowerCase().includes(searchTerm) ||
                (b.to_address || "").toLowerCase().includes(searchTerm) ||
                (b.formattedDate || "").toLowerCase().includes(searchTerm) ||
                (b.formattedTime || "").toLowerCase().includes(searchTerm) ||
                (b.user_name || "").toLowerCase().includes(searchTerm) ||
                (b.mobile || "").toLowerCase().includes(searchTerm) ||
                (b.booking_status || "").toLowerCase().includes(searchTerm) ||
                (b.car_type || "").toLowerCase().includes(searchTerm) ||
                (b.distance || "").toLowerCase().includes(searchTerm) ||
                (b.total_amount || "").toLowerCase().includes(searchTerm) ||
                (b.booking_authority || "").toLowerCase().includes(searchTerm) ||
                (b.trip_type || "").toLowerCase().includes(searchTerm) ||
                (b.vehicle_id || "").toLowerCase().includes(searchTerm) ||
                (b.vender_id || "").toLowerCase().includes(searchTerm) ||
                (b.driver_name || "").toLowerCase().includes(searchTerm) ||
                (b.user_phone || "").toLowerCase().includes(searchTerm)
            );
        }
        renderBookingTable(filteredBookings, currentBookingPage);
        $("#bookingHeading").text(headingText);
        $("#refreshBookings").show();
    }

    // Booking search bar
    $("#bookingSearchInput").on("input", function () {
        const searchTerm = $(this).val().toLowerCase();
        currentBookingPage = 1;
        filterBookings(searchTerm);
        $("#refreshBookings").show();
    });

    // Driver search bar
    $("#driverSearchInput").on("input", function () {
        const searchTerm = $(this).val().toLowerCase();
        const filtered = allDrivers.filter(d =>
            (d.driver_id?.toString() || "").includes(searchTerm) ||
            (d.phone_number || "").toLowerCase().includes(searchTerm) ||
            (d.driver_city || "").toLowerCase().includes(searchTerm) ||
            (d.license_doe || "").toLowerCase().includes(searchTerm) ||
            (d.full_name || "").toLowerCase().includes(searchTerm) ||
            (formatDate(d.created_at) || "").toLowerCase().includes(searchTerm) ||
            (d.vehicle_id?.toString().toLowerCase() || "").includes(searchTerm) ||
            (d.vehicle_name || "").toLowerCase().includes(searchTerm) ||
            (d.fuel_type || "").toLowerCase().includes(searchTerm) ||
            (d.status || "").toLowerCase().includes(searchTerm)
        );
        currentDriverPage = 1;
        renderDriverTable(filtered);
    });

    // Car search bar
    $("#carSearchInput").on("input", function () {
        const searchTerm = $(this).val().toLowerCase();
        const filtered = allCars.filter(d =>
            (d.id?.toString() || "").includes(searchTerm) ||
            (d.owner_id || "").toLowerCase().includes(searchTerm) ||
            (d.vehicle_name || "").toLowerCase().includes(searchTerm) ||
            (d.vehicle_id?.toString().toLowerCase() || "").includes(searchTerm) ||
            (d.vehicle_type || "").toLowerCase().includes(searchTerm) ||
            (d.vehicle_number || "").toLowerCase().includes(searchTerm) ||
            (d.fuel_type || "").toLowerCase().includes(searchTerm) ||
            (d.status || "").toLowerCase().includes(searchTerm)
        );
        currentCarPage = 1;
        renderCarTable(filtered);
    });

    // Waiting for approval search bar
    $("#waitingforapprovalSearchInput").on("input", function () {
        const searchTerm = $(this).val().toLowerCase();
        const filtered = allwaitingforapproval.filter(w =>
        
            (w.id || "").toLowerCase().includes(searchTerm) ||
            (w.vehicle_number || "").toLowerCase().includes(searchTerm) ||
            (w.owner_id || "").toLowerCase().includes(searchTerm) ||
            (w.vehicle_type || "").toLowerCase().includes(searchTerm)||
            (w.vehicle_name || "").toLowerCase().includes(searchTerm)||
            (w.fuel_type || "").toLowerCase().includes(searchTerm)||
            (w.status || "").toLowerCase().includes(searchTerm)||
            (w.rc_no || "").toLowerCase().includes(searchTerm)||
            (w.rc_name || "").toLowerCase().includes(searchTerm)||
            (w.rc_manufecture_date || "").toLowerCase().includes(searchTerm)||
            (w.insurance_number || "").toLowerCase().includes(searchTerm)||
            (w.insurance_doe || "").toLowerCase().includes(searchTerm)||
            (w.puc_doi || "").toLowerCase().includes(searchTerm)||
            (w.puc_doe || "").toLowerCase().includes(searchTerm)||
            (w.texi_permit_no || "").toLowerCase().includes(searchTerm)||
            (w.texi_permit_doi || "").toLowerCase().includes(searchTerm)||
            (w.texi_permit_doe || "").toLowerCase().includes(searchTerm)||
            (w.fitness_certificate_no || "").toLowerCase().includes(searchTerm)||
            (w.fitness_certificate_doi || "").toLowerCase().includes(searchTerm)||
            (w.fitness_certificate_doe || "").toLowerCase().includes(searchTerm)
        );
        currentWaitingPage = 1;
        renderwaitingforapprovalTable(filtered);
    });
    
    // onRide search bar
    $("#onrideSearchInput").on("input", function () {
        const searchTerm = $(this).val().toLowerCase();
        const filtered = allonRides.filter(OR =>
         (OR.booking_id || "").toLowerCase().includes(searchTerm) ||
                (OR.from_address || "").toLowerCase().includes(searchTerm) ||
                (OR.to_address || "").toLowerCase().includes(searchTerm) ||
                (OR.formattedDate || "").toLowerCase().includes(searchTerm) ||
                (OR.formattedTime || "").toLowerCase().includes(searchTerm) ||
                (OR.user_name || "").toLowerCase().includes(searchTerm) ||
                (OR.mobile || "").toLowerCase().includes(searchTerm) ||
                (OR.booking_status || "").toLowerCase().includes(searchTerm) ||
                (OR.car_type || "").toLowerCase().includes(searchTerm) ||
                (OR.distance || "").toLowerCase().includes(searchTerm) ||
                (OR.total_amount || "").toLowerCase().includes(searchTerm) ||
                (OR.booking_authority || "").toLowerCase().includes(searchTerm) ||
                (OR.trip_type || "").toLowerCase().includes(searchTerm) ||
                (OR.vehicle_id || "").toLowerCase().includes(searchTerm) ||
                (OR.vender_id || "").toLowerCase().includes(searchTerm) ||
                (OR.driver_name || "").toLowerCase().includes(searchTerm) ||
                (OR.user_phone || "").toLowerCase().includes(searchTerm)||
            (OR.driver_id?.toString() || "").includes(searchTerm) ||
            (OR.phone_number || "").toLowerCase().includes(searchTerm) ||
            (OR.full_name || "").toLowerCase().includes(searchTerm) ||
            (OR.vehicle_id?.toString().toLowerCase() || "").includes(searchTerm) ||
            (OR.vehicle_name || "").toLowerCase().includes(searchTerm) ||
            (OR.fuel_type || "").toLowerCase().includes(searchTerm) ||
            (OR.booking_status || "").toLowerCase().includes(searchTerm)
        );
        currentonRidePage = 1;
        renderonRideTable(filtered);
    });
    
    // newUser search bar
    $("#newuserSearchInput").on("input", function () {
        const searchTerm = $(this).val().toLowerCase();
        const filtered = allnewUsers.filter(u =>
            (u.id?.toString() || "").includes(searchTerm) ||
            (u.phone_number || "").toLowerCase().includes(searchTerm) ||
            (u.name || "").toLowerCase().includes(searchTerm) ||
            (u.email || "").toLowerCase().includes(searchTerm) ||
            (u.city || "").toLowerCase().includes(searchTerm) ||
            (u.pincode || "").toLowerCase().includes(searchTerm) ||
            (formatDate(u.created_at.split(' ')[0]) || "").toLowerCase().includes(searchTerm) ||
            (u.accountType || "").toLowerCase().includes(searchTerm) ||
            (u.agency_name || "").toLowerCase().includes(searchTerm)
        );
        currentnewUserPage = 1;
        rendernewUserTable(filtered);
    });
    // Blocked Customer search bar
        $("#Blocked_CustomerSearchInput").on("input", function () {
            const searchTerm = $(this).val().toLowerCase();
            const filtered = allBlocked_Customers.filter(BlockC =>
                (BlockC.id?.toString() || "").includes(searchTerm) ||
                (BlockC.phone_number || "").toLowerCase().includes(searchTerm) ||
                (BlockC.name || "").toLowerCase().includes(searchTerm) ||
                (BlockC.email || "").toLowerCase().includes(searchTerm) ||
                (BlockC.city || "").toLowerCase().includes(searchTerm) ||
                (BlockC.pincode || "").toLowerCase().includes(searchTerm) ||
                (formatDate(BlockC.created_at.split(' ')[0]) || "").toLowerCase().includes(searchTerm) ||
                (BlockC.accountType || "").toLowerCase().includes(searchTerm) ||
                (BlockC.agency_name || "").toLowerCase().includes(searchTerm)
            );
            currentBlocked_CustomerPage = 1;
            renderBlocked_CustomerTable(filtered);
        });
    // Placeholder Hamburger Toggle
        $(document).ready(() => {
            const hamburger = $('#hamburger');
            const sidebar = $('#sidebar');

            hamburger.on('click', () => {
                sidebar.toggleClass('active');
                hamburger.attr('aria-expanded', sidebar.hasClass('active'));
            });

            $(document).on('click', (e) => {
                if (!sidebar.is(e.target) && !sidebar.has(e.target).length && !hamburger.is(e.target) && !hamburger.has(e.target).length) {
                    sidebar.removeClass('active');
                    hamburger.attr('aria-expanded', 'false');
                }
            });
        });
        
        
    

    // Initial calls
    fetchBookings();
    fetchDrivers();
    fetchCars();
    fetchwaitingforapproval();
    fetchonRides();
    fetchnewUsers();
    fetchBlocked_Customer();

    // Play pleasant notification chime using Web Audio API
    function playNotificationSound() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const ctx = new AudioContext();
            
            const playNote = (frequency, startTime, duration) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                
                osc.type = 'sine';
                osc.frequency.setValueAtTime(frequency, startTime);
                
                gain.gain.setValueAtTime(0.35, startTime);
                gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);
                
                osc.connect(gain);
                gain.connect(ctx.destination);
                
                osc.start(startTime);
                osc.stop(startTime + duration);
            };
            
            const now = ctx.currentTime;
            playNote(659.25, now, 0.25); // E5 note
            playNote(880.00, now + 0.1, 0.35); // A5 note
        } catch (e) {
            console.warn("AudioContext failed to play sound:", e);
        }
    }

    // Dismiss a toast alert
    function dismissToastElement(element) {
        element.addClass("hide");
        setTimeout(() => {
            element.remove();
        }, 400);
    }

    // Build and slide down a new booking toast notification
    function showNewBookingNotification(booking) {
        const container = $("#notificationContainer");
        if (container.length === 0) return;

        const bookingId = booking.booking_id || 'N/A';
        const fromLoc = booking.from_address ? booking.from_address.split(',').slice(-3).join(',') : 'N/A';
        const toLoc = booking.to_address ? booking.to_address.split(',').slice(-3).join(',') : 'N/A';
        const customer = booking.user_name || 'N/A';

        let toastHtml = '';
        if (bookingId === 'TEST') {
            toastHtml = `
                <div class="notification-toast" id="toast-test" style="border-left-color: #3498db; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3), 0 0 15px rgba(52, 152, 219, 0.25);">
                    <div class="notification-toast-header">
                        <span style="color: #3498db; font-weight: bold;">🔔 Notification System Active</span>
                        <button class="notification-toast-close">&times;</button>
                    </div>
                    <div class="notification-toast-body" style="color: #ecf0f1;">
                        Real-time system is active! Dashboard will alert and play sound automatically when new bookings are received.
                    </div>
                </div>
            `;
        } else {
            toastHtml = `
                <div class="notification-toast" id="toast-${bookingId}">
                    <div class="notification-toast-header">
                        <span>🔔 New Booking Received</span>
                        <button class="notification-toast-close">&times;</button>
                    </div>
                    <div class="notification-toast-body">
                        <strong>Booking ID:</strong> #${bookingId}<br>
                        <strong>Customer:</strong> ${customer}<br>
                        <strong>From:</strong> ${fromLoc}<br>
                        <strong>To:</strong> ${toLoc}
                    </div>
                </div>
            `;
        }

        const toastElement = $(toastHtml);
        container.append(toastElement);

        // Slide/fade in transition
        setTimeout(() => {
            toastElement.addClass("show");
        }, 50);

        // Bind manual dismiss click
        toastElement.find(".notification-toast-close").on("click", function() {
            dismissToastElement(toastElement);
        });

        // Automatically dismiss toast after 8 seconds
        setTimeout(() => {
            dismissToastElement(toastElement);
        }, 8000);
    }

    // Poll the backend periodically and compare with lastMaxBookingId to find new bookings
    function checkForNewBookings() {
        $.getJSON("https://agnicarrental.com/admin2025/admin_booking_list_Agni.php", function (data) {
            const bookings = data.bookingsdata || [];
            if (bookings.length === 0) return;

            let newBookingsFound = [];
            let currentMaxId = lastMaxBookingId;

            bookings.forEach(booking => {
                const bookingId = parseInt(booking.booking_id) || 0;
                if (lastMaxBookingId > 0 && bookingId > lastMaxBookingId) {
                    newBookingsFound.push(booking);
                    if (bookingId > currentMaxId) {
                        currentMaxId = bookingId;
                    }
                }
            });

            if (newBookingsFound.length > 0) {
                lastMaxBookingId = currentMaxId;
                
                // Show notification for each new booking and prepend to unread list
                newBookingsFound.forEach(b => {
                    unreadNotifications.unshift(b);
                    showNewBookingNotification(b);
                });

                // Update UI badge and dropdown list
                updateNotificationUI();

                // Play pleasant notification sound
                playNotificationSound();

                // Automatically update current bookings list if user is on the bookings tab
                fetchBookings();
            }
        }).fail(function (err) {
            console.error("Error checking for new bookings:", err);
        });
    }

    // Toggle notification dropdown
    $("#notificationBellBtn").on("click", function (e) {
        e.stopPropagation();
        $("#notificationDropdown").toggleClass("active");
    });

    // Close dropdown when clicking outside
    $(document).on("click", function (e) {
        if (!$(e.target).closest(".header-notification-container").length) {
            $("#notificationDropdown").removeClass("active");
        }
    });

    // Clear all notifications
    $("#clearNotificationsBtn").on("click", function (e) {
        e.stopPropagation();
        unreadNotifications = [];
        updateNotificationUI();
    });

    // Open booking from dropdown list
    $(document).on("click", ".notification-dropdown-item", function () {
        const bookingId = $(this).data("booking-id");
        if (bookingId === 'TEST') {
            unreadNotifications = unreadNotifications.filter(n => n.booking_id !== 'TEST');
            updateNotificationUI();
            $("#notificationDropdown").removeClass("active");
            return;
        }
        
        $('#modalDetails').html('<p>Loading...</p>');
        $('#bookingModal').fadeIn();
        fetchBookingById(bookingId).then(booking => {
            if (booking) {
                showBookingModal(booking);
            } else {
                $('#modalDetails').html('<p>Error loading details.</p>');
            }
        });

        // Mark as read
        unreadNotifications = unreadNotifications.filter(n => n.booking_id != bookingId);
        updateNotificationUI();
        $("#notificationDropdown").removeClass("active");
    });

    // Update Notification badge and dropdown content
    function updateNotificationUI() {
        const badge = $("#notificationBadge");
        const list = $("#notificationDropdownList");
        
        const count = unreadNotifications.length;
        if (count > 0) {
            badge.text(count).show();
            
            // Build dropdown items
            let itemsHtml = "";
            unreadNotifications.forEach(n => {
                const bookingId = n.booking_id;
                if (bookingId === 'TEST') {
                    itemsHtml += `
                        <div class="notification-dropdown-item" data-booking-id="TEST">
                            <div class="notification-item-title">
                                <span>System Alert</span>
                                <span class="notification-item-time">Just now</span>
                            </div>
                            <div class="notification-item-details">
                                Notification system active and listening.
                            </div>
                        </div>
                    `;
                } else {
                    const fromLoc = n.from_address ? n.from_address.split(',').slice(-3).join(',') : 'N/A';
                    const toLoc = n.to_address ? n.to_address.split(',').slice(-3).join(',') : 'N/A';
                    itemsHtml += `
                        <div class="notification-dropdown-item" data-booking-id="${bookingId}">
                            <div class="notification-item-title">
                                <span>New Booking #${bookingId}</span>
                                <span class="notification-item-time">Just now</span>
                            </div>
                            <div class="notification-item-details">
                                <strong>Customer:</strong> ${n.user_name || 'N/A'}<br>
                                <strong>Route:</strong> ${fromLoc} &rarr; ${toLoc}
                            </div>
                        </div>
                    `;
                }
            });
            list.html(itemsHtml);
        } else {
            badge.hide();
            list.html('<div class="notification-empty-state">No new notifications</div>');
        }
    }

    // Run polling check every 10 seconds
    setInterval(checkForNewBookings, 10000);

    // Show a test notification 2 seconds after load to confirm the container and CSS are working perfectly
    setTimeout(() => {
        const testAlert = { booking_id: 'TEST' };
        unreadNotifications.unshift(testAlert);
        updateNotificationUI();
        showNewBookingNotification(testAlert);
    }, 2000);
});

// Booking confirmation toggle
$(document).on('click', '.confirmBtn', function () {
    const button = $(this);
    if (button.prop('disabled')) return;
    const bookingId = button.data('booking-id');
    const currentStatus = button.data('status');
    const newStatus = currentStatus === 'Not Confirmed' ? 'Pending' : 'Not Confirmed';
    if (!confirm(`Are you sure you want to ${newStatus === 'Pending' ? 'confirm' : 'decline'} this booking?`)) return;
    $.ajax({
        url: 'https://agnicarrental.com/admin2025/status_change.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            id: bookingId,
            status: newStatus
        }),
        dataType: 'json',
        success: function (response) {
            console.log('Booking Status Toggle Response:', response);
            if (response.status === 'success') {
                button.text(newStatus === 'Pending' ? 'Decline' : 'Confirm');
                button.data('status', newStatus);
                if (newStatus === 'Pending') {
                    button.css('background-color', '#dc3545');
                } else {
                    button.css('background-color', '#28a745');
                }
                const statusCell = button.closest('tr').find('td').eq(9);
                statusCell.text(newStatus);
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function (xhr, status, error) {
            console.error('AJAX Error:', status, error);
            alert('Server error. Please try again.');
        }
    });
});

$(document).on('click', '.confirmBtn1', function () {
    const button = $(this);
    if (button.prop('disabled')) return;

    const driverId = button.data('driver-id');
    const currentStatus = button.data('status');

    // Only allow toggle if status is 'active', 'inactive', or 'filled'
    if (!['active', 'inactive', 'filled'].includes(currentStatus)) {
        alert('This status cannot be changed via this button.');
        return;
    }

    const newStatus = (currentStatus === 'active') ? 'inactive' : 'active';

    if (!confirm(`Are you sure you want to change status to ${newStatus}?`)) return;

    $.ajax({
        url: 'https://agnicarrental.com/admin2025/driver_confirmation_status.php',
        method: 'POST',
        data: {
            driver_id: driverId,
            status: newStatus
        },
        dataType: 'json',
        success: function (response) {
            console.log('Driver Status Toggle Response:', response);
            if (response.status === 'success') {
                button.text(newStatus === 'active' ? 'Inactivate' : 'Activate');
                button.data('status', newStatus);

                // Update button color
                if (newStatus === 'active') {
                    button.css('background-color', '#dc3545');
                } else {
                    button.css('background-color', '#28a745');
                }

                // Update status cell in table
                const statusCell = button.closest('tr').find('td').eq(8);
                statusCell.text(newStatus);
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function (xhr, status, error) {
            console.error('AJAX Error:', status, error);
            alert('Server error. Please try again.');
        }
    });
});


// Car active/inactive toggle
$(document).on('click', '.confirmBtn3', function () {
    const button = $(this);
    if (button.prop('disabled')) return;
    const carId = button.data('car-id');
    const currentStatus = button.data('status');
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    if (!confirm(`Are you sure you want to ${newStatus} this car?`)) return;
    $.ajax({
        url: 'https://agnicarrental.com/admin2025/car_confirmation_status.php',
        method: 'POST',
        data: {
            id: carId,
            status: newStatus
        },
        dataType: 'json',
        success: function (response) {
            console.log('Car Status Toggle Response:', response);
            if (response.status === 'success') {
                button.text(newStatus === 'active' ? 'Inactivate' : 'Activate');
                button.data('status', newStatus);
                if (newStatus === 'active') {
                    button.css('background-color', '#dc3545');
                } else {
                    button.css('background-color', '#28a745');
                }
                const statusCell = button.closest('tr').find('td').eq(8);
                statusCell.text(newStatus);
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function (xhr, status, error) {
            console.error('AJAX Error:', status, error);
            alert('Server error. Please try again.');
        }
    });
});

// waiting for approval active/waiting for approval toggle
$(document).on('click', '.confirmBtn2', function () {
    const button = $(this);
    if (button.prop('disabled')) return;
    const waitingId = button.data('waiting-id');
    const currentStatus = button.data('status');
    const newStatus = currentStatus === 'active' ? 'Notified' : 'active';
    if (!confirm(`Are you sure you want to ${newStatus} this car?`)) return;
    $.ajax({
        url: 'https://agnicarrental.com/admin2025/waitingforapproval_confirmation_status.php',
        method: 'POST',
        data: {
            id: waitingId,
            status: newStatus
        },
        dataType: 'json',
        success: function (response) {
            console.log('Waiting Status Toggle Response:', response);
            if (response.status === 'success') {
                button.text(newStatus === 'active' ? 'Notified' : 'active');
                button.data('status', newStatus);
                if (newStatus === 'active') {
                    button.css('background-color', '#dc3545');
                } else {
                    button.css('background-color', '#28a745');
                }
                const statusCell = button.closest('tr').find('td').eq(7);
                statusCell.text(newStatus);
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function (xhr, status, error) {
            console.error('AJAX Error:', status, error);
            alert('Server error. Please try again.');
        }
    });

    // Fetch and render Shared Onboarding Requests
    function fetchSharedOnboardings(searchTerm = '') {
        $.getJSON("https://agnicarrental.com/admin2025/get_shared_onboardings.php", { search: searchTerm }, function (response) {
            if (response.status) {
                allSharedOnboardings = response.data || [];
                renderSharedOnboardingsTable(allSharedOnboardings);
            } else {
                $("#sharedOnboardingsTable").html("<tr><td colspan='11'>Error: " + response.message + "</td></tr>");
            }
            $("#refreshSharedOnboardings").show();
        }).fail(function () {
            $("#sharedOnboardingsTable").html("<tr><td colspan='11'>Error loading onboarding requests</td></tr>");
            $("#refreshSharedOnboardings").show();
        });
    }

    function renderSharedOnboardingsTable(requests, page = currentSharedPage) {
        currentSharedPage = page;
        const start = (page - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        const paginatedRequests = requests.slice(start, end);

        let tableBody = "";
        if (paginatedRequests.length === 0) {
            tableBody = `<tr><td colspan="11">No registration requests found</td></tr>`;
        } else {
            paginatedRequests.forEach((req, index) => {
                let statusBadge = "";
                if (req.status === "Approved") {
                    statusBadge = `<span class="badge bg-success">Approved</span>`;
                } else if (req.status === "Rejected") {
                    statusBadge = `<span class="badge bg-danger">Rejected</span>`;
                } else {
                    statusBadge = `<span class="badge bg-warning text-dark">Pending</span>`;
                }

                let actions = "";
                if (req.status === "Pending") {
                    actions = `
                        <button class="btn btn-success btn-sm approveOnboardBtn me-1" data-id="${req.id}">
                            <i class="fas fa-check"></i> Approve
                        </button>
                        <button class="btn btn-danger btn-sm rejectOnboardBtn" data-id="${req.id}">
                            <i class="fas fa-times"></i> Reject
                        </button>
                    `;
                } else {
                    actions = `<span class="text-muted">Processed</span>`;
                }

                tableBody += `
                    <tr>
                        <td>${start + index + 1}</td>
                        <td><strong>${req.car_no}</strong></td>
                        <td>${req.car_type}</td>
                        <td>${req.owner_name}</td>
                        <td>${req.owner_mobile}</td>
                        <td>${req.driver_name}</td>
                        <td>${req.driver_mobile}</td>
                        <td>${req.location}</td>
                        <td>${formatDate(req.created_at.split(' ')[0])}</td>
                        <td>${statusBadge}</td>
                        <td>${actions}</td>
                    </tr>
                `;
            });
        }
        $("#sharedOnboardingsTable").html(tableBody);
        renderPagination(requests.length, currentSharedPage, 'sharedOnboardingsTable', () => renderSharedOnboardingsTable(requests));
    }

    // Sidebar menu click
    $("#shared_onboardings_menu").on("click", function (e) {
        e.preventDefault();
        currentSharedPage = 1;
        $("#sharedOnboardingsSearchInput").val('');
        fetchSharedOnboardings();
        
        $("#sharedOnboardingsTableContainer").removeClass("hidden");
        $("#bookingTableContainer").addClass("hidden");
        $("#driverTableContainer").addClass("hidden");
        $("#carTableContainer").addClass("hidden");
        $("#waitingforapprovalTableContainer").addClass("hidden");
        $("#onrideTableContainer").addClass("hidden");
        $("#newuserTableContainer").addClass("hidden");
        $("#Blocked_CustomerTableContainer").addClass("hidden");
    });

    // Refresh shared onboardings
    $("#refreshSharedOnboardings").on("click", function (e) {
        e.preventDefault();
        currentSharedPage = 1;
        fetchSharedOnboardings($("#sharedOnboardingsSearchInput").val());
    });

    // Search filter
    $("#sharedOnboardingsSearchInput").on("input", function () {
        const searchTerm = $(this).val();
        currentSharedPage = 1;
        fetchSharedOnboardings(searchTerm);
    });

    // Copy Shareable Registration Link
    $("#copyRegLinkBtn").on("click", function () {
        const link = "https://agnicarrental.com/admin2025/onboard.php";
        navigator.clipboard.writeText(link).then(function () {
            alert("Shareable fleet registration link copied to clipboard!");
        }).catch(function (err) {
            console.error("Could not copy link: ", err);
        });
    });

    // Action buttons (Approve/Reject) click handlers
    $(document).on("click", ".approveOnboardBtn", function () {
        const id = $(this).data("id");
        if (confirm("Are you sure you want to approve this fleet request? This will automatically register the vehicle and driver.")) {
            updateOnboardStatus(id, "Approved");
        }
    });

    $(document).on("click", ".rejectOnboardBtn", function () {
        const id = $(this).data("id");
        if (confirm("Are you sure you want to reject this fleet request?")) {
            updateOnboardStatus(id, "Rejected");
        }
    });

    function updateOnboardStatus(id, status) {
        $.ajax({
            url: "https://agnicarrental.com/admin2025/update_shared_onboarding_status.php",
            method: "POST",
            data: { id: id, status: status },
            dataType: "json",
            success: function (response) {
                if (response.status) {
                    alert(response.message);
                    fetchSharedOnboardings($("#sharedOnboardingsSearchInput").val());
                    // Proactively refresh drivers & cars lists in background
                    fetchDrivers();
                    fetchCars();
                } else {
                    alert("Error: " + response.message);
                }
            },
            error: function (xhr, status, error) {
                console.error("AJAX Error:", status, error);
                alert("Server error. Please try again.");
            }
    }

    // Parse URL parameter to activate correct tab on load after all event listeners are registered
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    if (tabParam) {
        if (tabParam === 'driver') {
            $("#driver").click();
        } else if (tabParam === 'cab') {
            $("#cab").click();
        } else if (tabParam === 'booking') {
            // Already default, but make sure
            $("#booking").click();
        } else if (tabParam === 'completed') {
            $("#Complete").click();
        } else if (tabParam === 'newuser') {
            $("#newuser").click();
        } else if (tabParam === 'blocked_customer') {
            $("#Blocked_Customer").click();
        } else if (tabParam === 'extract_data') {
            $("#Extract_Data").click();
        } else if (tabParam === 'shared_onboardings') {
            $("#shared_onboardings_menu").click();
        }
    }
});

document.addEventListener("DOMContentLoaded", function () {
  const extractBtn = document.getElementById("Extract_Data");
  const popup = document.getElementById("carPopup");
  const closeBtn = document.querySelector(".popup-close");

  const downloadCarBtn = document.getElementById("downloadCar");
  const downloadDriverBtn = document.getElementById("downloadDriver");
  const downloadUserBtn = document.getElementById("downloadUser");

  let carData = [], driverData = [], userData = [];

  // Open popup and fetch all data
  extractBtn.addEventListener("click", async function (e) {
    e.preventDefault();
    popup.style.display = "flex";

    try {
      // Cars
      const carRes = await fetch("https://agnicarrental.com/admin2025/car_list_Agni.php");
      const carResult = await carRes.json();
      carData = carResult.carsdata || [];

      // Drivers
      const driverRes = await fetch("https://agnicarrental.com/admin2025/driver_list_Agni.php");
      const driverResult = await driverRes.json();
      driverData = driverResult.driversdata || [];

      // New Users
      const userRes = await fetch("https://agnicarrental.com/admin2025/newuser_list_Agni.php");
      const userResult = await userRes.json();
      userData = userResult.newusersdata || [];

    } catch (err) {
      alert("Failed to fetch some data.");
    }
  });

  // Close popup
  closeBtn.addEventListener("click", function () {
    popup.style.display = "none";
  });

  // Close when clicking outside
  popup.addEventListener("click", function (e) {
    if (e.target === popup) popup.style.display = "none";
  });

  // Helper: Download as CSV
  function downloadCSV(data, filename) {
    if (!data || data.length === 0) {
      alert("No data available.");
      return;
    }
    const headers = Object.keys(data[0]);
    const csvRows = [];
    csvRows.push(headers.join(","));
    data.forEach((row) => {
      const values = headers.map((h) => `"${row[h] || ""}"`);
      csvRows.push(values.join(","));
    });

    const csvString = csvRows.join("\n");
    const blob = new Blob([csvString], { type: "text/csv" });
    const url = window.URL.createObjectURL(blob);

    const a = document.createElement("a");
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  }

  // Buttons export
  downloadCarBtn.addEventListener("click", () => downloadCSV(carData, "CarData.csv"));
  downloadDriverBtn.addEventListener("click", () => downloadCSV(driverData, "DriverData.csv"));
  downloadUserBtn.addEventListener("click", () => downloadCSV(userData, "NewUserData.csv"));
});


// Fetch and display notifications
function fetchAndDisplay(endpoint, elementId) {
    fetch(endpoint)
        .then(response => response.text())
        .then(raw => {
            try {
                const data = JSON.parse(raw);
                document.getElementById(elementId).innerText =
                    `✅ ${new Date().toLocaleTimeString()}:\n` +
                    JSON.stringify(data, null, 2);
            } catch (e) {
                document.getElementById(elementId).innerText =
                    `❌ JSON Parse Error at ${new Date().toLocaleTimeString()}:\n${e}\n\nRaw Response:\n${raw}`;
            }
        })
        .catch(error => {
            document.getElementById(elementId).innerText =
                `❌ Fetch Error at ${new Date().toLocaleTimeString()}:\n${error}`;
        });
}

function runBothRequests() {
    fetchAndDisplay('https://agnicarrental.com/2025/send_notification_forExpaird_car_documtns.php', 'carStatus');
    fetchAndDisplay('https://agnicarrental.com/2025/send_notification_forExpaird_driver_documents.php', 'driverStatus');
}

// Run on page load and every 30 seconds
window.onload = () => {
    runBothRequests();
    setInterval(runBothRequests, 30000);
};
