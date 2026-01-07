<?php include_once __DIR__ . '/layouts/admin_header.php'; ?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Dashboard Overview</h1>
    </div>

    <!-- Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Total Collected Revenue</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                RM <?= number_format($totalRevenue, 2) ?>
                            </div>
                            <div class="text-xs mt-1 text-muted">
                                Actual verified payments
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-hand-holding-usd fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Total Bookings</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $totalBookings ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-check fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Rooms (Occupied)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $totalRooms ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-bed fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Total Messages</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $totalMessages ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-envelope fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Yearly Revenue Overview -->
    <div class="row">
        <div class="col-xl-12 col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Revenue Overview (<?= $selectedYear ?>)</h6>

                    <div class="form-group mb-0">
                        <select class="form-control form-control-sm" id="yearFilter">
                            <?php 
                            // Fallback in case there are no bookings yet
                            if(empty($availableYears)) $availableYears = [date('Y')];
                            
                            foreach($availableYears as $year): ?>
                                <option value="<?= $year ?>" <?= ($year == $selectedYear) ? 'selected' : '' ?>>
                                    <?= $year ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>
                <div class="card-body">
                    <div class="chart-area" style="height: 320px;">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Forecasting Analytic -->
    <div class="row">
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">7-Day Occupancy Forecast</h6>
                    <div>
                        <?php if ($lowOccupancyAlert): ?>
                            <span class="badge badge-danger animate-pulse" title="Some days have < 20% occupancy">
                                <i class="fas fa-exclamation-triangle"></i> Low Occupancy Detected
                            </span>
                        <?php endif; ?>
                        <span class="badge badge-info ml-2">Total Capacity: <?= $totalCapacity ?> Rooms</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-bar" style="height: 300px;">
                        <canvas id="forecastChart"></canvas>
                    </div>
                    <hr>
                    <div class="small text-muted text-center">
                        Percentage shows how "Full" the guest house is for that day.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Best Selling Rooms</h6>
                </div>
                <div class="card-body">
                    <?php foreach($popularRooms as $room): ?>
                        <?php 
                            $totalBookingsSum = array_sum(array_column($popularRooms, 'total_bookings')) ?: 1;
                            $percent = ($room['total_bookings'] / $totalBookingsSum) * 100;
                        ?>
                        <h4 class="small font-weight-bold"><?= htmlspecialchars($room['name']) ?> 
                            <span class="float-right"><?= $room['total_bookings'] ?> bookings</span>
                        </h4>
                        <div class="progress mb-4">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= $percent ?>%"></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Booking Details (Unpaid/Waiting Verification Admin) -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-danger">Action Required: Unpaid Bookings</h6>
                    <span id="unpaid-count" class="badge badge-danger"><?= count($unpaidBookings) ?> Pending</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Booking Date</th>
                                    <th>Ref No</th>
                                    <th>Customer</th>
                                    <th>Check In</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="unpaid-booking-list">
                                <?php foreach($unpaidBookings as $booking): ?>
                                    <?php
                                        $isWaitingQR = ($booking['payment_method'] === 'qr' && $booking['verified'] === 'pending');
                                    ?>
                                <tr>
                                    <td>
                                        <?php 
                                            if (!empty($booking['booking_date'])) {
                                                echo date('d M Y', strtotime($booking['booking_date']));
                                            } elseif (!empty($booking['created_at'])) {
                                                echo date('d M Y', strtotime($booking['created_at']));
                                            } else {
                                                echo '<span class="text-danger">No Date Found</span>';
                                            }
                                        ?>
                                    </td>
                                    <td><?= $booking['booking_ref_no'] ?></td>
                                    <td><?= $booking['full_name'] ?></td>
                                    <td><?= date('d M Y', strtotime($booking['check_in'])) ?></td>
                                    <td>RM <?= number_format($booking['total_amount'], 2) ?></td>
                                    <td>
                                        <?php if ($isWaitingQR): ?>
                                            <span class="badge badge-info animate-pulse"><i class="fas fa-clock"></i> Waiting Verification</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger"><?= strtoupper($booking['payment_status']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?= APP_URL ?>/admin/bookings/view/<?= $booking['id'] ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                        <?php if ($isWaitingQR): ?>
                                            <a href="<?= APP_URL ?>/admin/payments/verify/<?= $booking['payment_id'] ?>" class="btn btn-sm btn-success">Verify QR</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Helper to safely check for elements before adding listeners
    function safeAddListener(id, event, callback) {
        const el = document.getElementById(id);
        if (el) el.addEventListener(event, callback);
    }

    function refreshUnpaidBookings() {
        fetch('<?= APP_URL ?>/admin/getUnpaidBookingsJson')
            .then(response => response.json())
            .then(data => {
                const tableBody = document.getElementById('unpaid-booking-list');
                const countBadge = document.getElementById('unpaid-count');
                
                if (!tableBody || !countBadge) return;

                // Update the count badge
                countBadge.innerText = `${data.length} Pending`;

                // If no data is returned, show a message instead of an empty white space
                if (data.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="6" class="text-center">No pending actions at the moment.</td></tr>';
                    return;
                }

                let rows = '';
                data.forEach(booking => {
                    let statusHtml = '';
                    let actionButtons = '';
                    const isWaitingQR = (booking.payment_method === 'qr' && booking.verified === 'pending');
                    
                    // 1. Logic for QR Verification
                    if (booking.payment_method === 'qr' && booking.verified === 'pending') {
                        statusHtml = `<span class="badge badge-info animate-pulse"><i class="fas fa-clock"></i> Waiting Verification</span>`;
                        actionButtons = `
                            <a href="<?= APP_URL ?>/admin/bookings/view/${booking.id}" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> View</a>
                            <a href="<?= APP_URL ?>/admin/payments/verify/${booking.payment_id}" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Verify QR</a>
                        `;
                    } 
                    // 2. Logic for General Unpaid (Cash or QR not yet uploaded)
                    else {
                        const statusText = booking.payment_status ? booking.payment_status.toUpperCase() : 'UNPAID';
                        statusHtml = `<span class="badge badge-danger">${statusText}</span>`;
                        actionButtons = `<a href="<?= APP_URL ?>/admin/bookings/view/${booking.id}" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> View</a>`;
                    }

                    // Simple date format (DD/MM/YYYY) to prevent JS date errors
                    const dateParts = booking.check_in.split('-'); // Assumes YYYY-MM-DD
                    const formattedDate = (dateParts.length === 3) ? `${dateParts[2]}/${dateParts[1]}/${dateParts[0]}` : booking.check_in;

                    const bookingDateParts = booking.booking_date.split(' ')[0].split('-');
                    const formattedBookingDate = (bookingDateParts.length === 3) ? `${bookingDateParts[2]}/${bookingDateParts[1]}/${bookingDateParts[0]}` : 'N/A';

                    rows += `
                        <tr>
                            <td>${formattedBookingDate}</td>
                            <td>${booking.booking_ref_no}</td>
                            <td>${booking.full_name || 'Guest'}</td>
                            <td>${formattedDate}</td>
                            <td>RM ${parseFloat(booking.total_amount).toFixed(2)}</td>
                            <td>${statusHtml}</td>
                            <td>${actionButtons}</td>
                        </tr>
                    `;
                });
                tableBody.innerHTML = rows;
            })
            .catch(err => {
                console.error('Fetch Error:', err);
                // If the URL is wrong or controller crashes, this will show:
                document.getElementById('unpaid-booking-list').innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading data. Check console (F12).</td></tr>';
            });
    }

    document.addEventListener("DOMContentLoaded", function() {

        safeAddListener('yearFilter', 'change', function() {
            window.location.href = "<?= APP_URL ?>/auth/dashboard?year=" + this.value;
        });
        
        // 1. FORECAST CHART
        const forecastElement = document.getElementById('forecastChart');
        if (forecastElement) {
            const capacity = <?= (int)$totalCapacity ?>;
            const forecastValues = <?= json_encode($forecastValues) ?>;
            const forecastLabels = <?= json_encode($forecastLabels) ?>;
            
            const ctx = forecastElement.getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: forecastLabels,
                    datasets: [{
                        label: "Rooms Occupied",
                        data: forecastValues,
                        backgroundColor: forecastValues.map(val => {
                            let pct = (val / (capacity || 1)) * 100;
                            return pct < 20 ? 'rgba(231, 74, 59, 0.8)' : 'rgba(78, 115, 223, 0.8)';
                        }),
                        borderColor: forecastValues.map(val => {
                            let pct = (val / (capacity || 1)) * 100;
                            return pct < 20 ? '#e74a3b' : '#4e73df';
                        }),
                        borderWidth: 1
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true, max: capacity + 1, ticks: { stepSize: 1 } }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                afterLabel: function(context) {
                                    let pct = ((context.raw / (capacity || 1)) * 100).toFixed(1);
                                    return `Occupancy: ${pct}%`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // 2. REVENUE CHART
        const revElement = document.getElementById('revenueChart');
        if (revElement) {
            new Chart(revElement.getContext('2d'), {
                type: 'line',
                data: {
                    labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
                    datasets: [{
                        label: "Revenue",
                        data: <?= json_encode($revenueChartValues) ?>,
                        borderColor: "#1cc88a",
                        fill: true,
                        backgroundColor: "rgba(28, 200, 138, 0.05)",
                        tension: 0.3
                    }]
                },
                options: { maintainAspectRatio: false }
            });
        }

        // 3. YEAR FILTER (Safe approach)
        const yearFilter = document.getElementById('yearFilter');
        if (yearFilter) {
            yearFilter.addEventListener('change', function() {
                window.location.href = "<?= APP_URL ?>/auth/dashboard?year=" + this.value;
            });
        }

        refreshUnpaidBookings();
        setInterval(refreshUnpaidBookings, 10000);
    });
</script>
<?php include_once __DIR__ . '/layouts/admin_footer.php'; ?>