<?php

class BookingModel extends Model
{
    public function createBooking($customerId, $check_in, $check_out, $rooms, $payment_method, $payment_details, $total_amount, $totalNights, $receipt_image = null)
    {
        try {
            // Begin transaction
            $this->db->beginTransaction();

            $guests = $_SESSION['guests'] ?? 1;
            $depositAmount = $total_amount * 0.35;
            $balanceRemaining = $total_amount - $depositAmount;

            // Generate a unique booking reference number
            $datePart = date('Ymd');
            $randomPart = strtoupper(substr(uniqid('', true), -6));
            $booking_ref_no = 'CHT-' . $datePart . '-' . $randomPart;

            // Insert main booking record (summary)
            $stmt = $this->db->prepare("
                INSERT INTO bookings 
                (customer_id, booking_ref_no, guests, check_in, check_out, total_nights, total_amount, status, payment_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'partial')
            ");

            $stmt->execute([
                $customerId, 
                $booking_ref_no,
                (int)$guests,
                $check_in, 
                $check_out,
                (int)$totalNights,
                $total_amount
            ]);

            $booking_id = $this->db->lastInsertId();

            // INSERT PAYMENT RECORD (New Block!)
            $payment_ref_no = 'P-' . $booking_ref_no . '-' . date('His');
            $payment_status_default = 'unpaid';

            $stmtPayment = $this->db->prepare("
                INSERT INTO payments 
                (booking_id, payment_ref_no, payment_method, amount, balance_after, payment_type, remarks, verified, receipt_image)
                VALUES (?, ?, ?, ?, ?, 'deposit', ?, 'pending', ?)
            ");

            // Assuming $payment_details from the controller is used as a 'remarks' field for now
            $stmtPayment->execute([
                (int)$booking_id,
                $payment_ref_no,
                $payment_method,
                $depositAmount,
                $balanceRemaining,
                $payment_details,
                $receipt_image
            ]);

            // OPTIONAL: Update booking payment status after successful payment record creation
            $stmtUpdate = $this->db->prepare("
                UPDATE bookings 
                SET payment_status = ? 
                WHERE id = ?
            ");
            $stmtUpdate->execute(['unpaid', (int)$booking_id]);

            // Insert each room booked
            $stmtRoom = $this->db->prepare("
                INSERT INTO booking_rooms (booking_id, room_id, rooms_booked)
                VALUES (?, ?, ?)
            ");

            foreach ($rooms as $room_id => $quantity) {
                file_put_contents('debug.txt', "Checking room booking: room_id = " . var_export($room_id, true) . ", quantity = " . var_export($quantity, true) . PHP_EOL, FILE_APPEND);

                // Validate room_id and quantity before insertion
                if (is_numeric($room_id) && is_numeric($quantity) && (int)$room_id > 0 && (int)$quantity > 0) {
                    $stmtRoom->execute([
                        (int)$booking_id,
                        (int)$room_id,
                        (int)$quantity
                    ]);
                    file_put_contents('debug.txt', "✅ Inserted room booking: room_id = $room_id, quantity = $quantity\n", FILE_APPEND);
                } else {
                    file_put_contents('debug.txt', "❌ Invalid room data. Skipping entry.\n", FILE_APPEND);
                }
            }

            $this->db->commit();

            file_put_contents('debug.txt', "Booking created with ID: $booking_id\n", FILE_APPEND);

            return $booking_id;

        } catch (Exception $e) {
            $this->db->rollBack();
            file_put_contents('debug.txt', "BookingModel error: " . $e->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }

    public function updatePaymentWithBillplz($booking_id, $bill_id)
    {
        $sql = "UPDATE payments SET billplz_id = ?, payment_ref_no = ? WHERE booking_id = ?";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([$bill_id, $bill_id, $booking_id]);
    }

    public function getBookingById($booking_id)
    {
        // Get booking details with rooms
        $stmt = $this->db->prepare("
            SELECT 
                b.*, 
                c.full_name, 
                c.email, 
                c.phone,
                p.payment_method,
                p.payment_ref_no,
                p.payment_date,
                p.remarks,
                p.balance_after,
                p.amount as deposit_paid
            FROM bookings b
            LEFT JOIN customers c ON b.customer_id = c.id
            LEFT JOIN payments p ON b.id = p.booking_id
            WHERE b.id = ?
            ORDER BY p.payment_date DESC
            LIMIT 1
        ");

        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            return null;
        }

        // 2. Fallback logic for payment method/status (optional, but good practice)
        $booking['payment_method'] = $booking['payment_method'] ?? 'N/A';
        $booking['payment_status'] = $booking['payment_status'] ?? 'unpaid';

        // 3. Get associated room details
        $stmtRooms = $this->db->prepare("
            SELECT r.name, r.price, br.rooms_booked 
            FROM booking_rooms br 
            JOIN rooms r ON br.room_id = r.id 
            WHERE br.booking_id = ?
        ");
        $stmtRooms->execute([$booking_id]);
        $booking['rooms'] = $stmtRooms->fetchAll(PDO::FETCH_ASSOC);

        return $booking;
    }

    // Helper function to generate the payment details string based on payment method
    public function generatePaymentDetails($payment_method, $amount, $order_id = null)
    {
        $payment_info = '';

        if ($payment_method === 'card') {
            $payment_info = "Payment Method: Credit Card, Amount:RM" . number_format($amount, 2);
        } elseif ($payment_method === 'paypal') {
            $payment_info = "Payment Method: PayPal, Amount: RM" . number_format($amount, 2);
        } elseif ($payment_method === 'qr') {
            // Generate QR payment URL dynamically (this is an example URL)
            $merchantId = 'YOUR_MERCHANT_ID'; //Replace with actual merchant id
            $payment_info = "Payment Method: QR Code, Amount: RM" . number_format($amount, 2) . ", QR Payment URL: https://www.tngdigital.com.my/pay?amount=" . $amount . "&merchant_id=" . $merchantId . "&order_id=" . $order_id;
        }

        return $payment_info;
    }

    public function getAllBookings($offset = 0, $limit = 10, $search = '')
    {
        $searchQuery = "";
        if (!empty($search)) {
            // Search by Ref No, Full Name, or Email
            $searchQuery = " WHERE b.booking_ref_no LIKE :search 
                            OR c.full_name LIKE :search 
                            OR c.email LIKE :search ";
        }

        // Join with customers table to get name, email, and phone
        $sql = "SELECT b.*, c.full_name, c.email, c.phone, 
                   p.payment_method, 
                   p.verified as payment_verify_status, 
                   p.amount as payment_amount, 
                   p.balance_after
                FROM bookings b
                LEFT JOIN customers c ON b.customer_id = c.id
                LEFT JOIN payments p ON b.id = p.booking_id
                $searchQuery
                ORDER BY b.created_at DESC
                LIMIT :offset, :limit";

        $stmt = $this->db->prepare($sql);

        if (!empty($search)) {
            $searchTerm = "%$search%";
            $stmt->bindValue(':search', $searchTerm);
        }

        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalBookings($search = '')
    {
        $searchQuery = "";
        if (!empty($search)) {
            $searchQuery = " WHERE b.booking_ref_no LIKE :search 
                        OR c.full_name LIKE :search 
                        OR c.email LIKE :search ";
        }

        $sql = "SELECT COUNT(*) as total 
            FROM bookings b
            LEFT JOIN customers c ON b.customer_id = c.id
            $searchQuery";

        $stmt = $this->db->prepare($sql);

        if (!empty($search)) {
            $searchTerm = "%$search%";
            $stmt->bindValue(':search', $searchTerm);
        }

        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (int)$row['total'] : 0;
    }

    // Delete booking and related data in dependent tables
    public function deleteBooking($id)
    {
        // Start a transaction to ensure both deletes are handled safely
        $this->db->beginTransaction();

        try {
            // delete related records from booking_rooms
            $stmt = $this->db->prepare("DELETE FROM booking_rooms WHERE booking_id = ?");
            $stmt->execute([$id]);

            // delete the booking itself
            $stmt = $this->db->prepare("DELETE FROM bookings WHERE id = ?");
            $stmt->execute([$id]);

            // Commit the transaction
            $this->db->commit();

            return true;
        } catch (Exception $error) {
            // If any error occurs, rollback the transaction
            $this->db->rollBack();
            throw $error;
        }
    }

    public function getBookingsByCustomer($customerId)
    {
        $sql = "SELECT b.*, 
                MAX(p.amount) AS deposit_paid, 
                MAX(p.payment_method) AS payment_method, 
                MAX(p.verified) AS payment_verified
                FROM bookings b
                LEFT JOIN payments p ON b.id = p.booking_id AND p.payment_type = 'deposit'
                WHERE b.customer_id = ? 
                GROUP BY b.id 
                ORDER BY b.check_in DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$customerId]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add room details for each booking
        foreach ($bookings as &$booking) {
            $booking['deposit_paid'] = $booking['deposit_paid'] ?? 0;
            $booking['payment_method'] = $booking['payment_method'] ?? 'N/A';

            $stmtRooms = $this->db->prepare("
                SELECT 
                    r.id as room_id, 
                    r.name, 
                    r.price, 
                    br.rooms_booked,
                    MAX(rv.id) as review_id
                FROM booking_rooms br 
                JOIN rooms r ON br.room_id = r.id 
                LEFT JOIN room_reviews rv ON rv.room_id = r.id AND rv.customer_id = ?
                WHERE br.booking_id = ?
                GROUP BY r.id, r.name, r.price, br.rooms_booked
            ");
            $stmtRooms->execute([$customerId, $booking['id']]);
            $booking['rooms'] = $stmtRooms->fetchAll(PDO::FETCH_ASSOC);
        }

        return $bookings;
    }

    // Get fully booked dates:
    public function getFullyBookedDates($daysAhead = 60)
    {
        // 1. Get total number of rooms available in the guesthouse
        $stmtTotal = $this->db->prepare("SELECT SUM(total_rooms) as total_capacity FROM rooms WHERE status = 'active'");
        $stmtTotal->execute();
        $totalCapacity = (int)$stmtTotal->fetchColumn();

        // 2. Query to get total rooms booked per day
        $stmt = $this->db->prepare("
            SELECT b.check_in, b.check_out, br.rooms_booked 
            FROM bookings b
            JOIN booking_rooms br ON b.id = br.booking_id
            WHERE (b.status = 'confirmed' OR (b.status = 'pending' AND b.expires_at > NOW()))
            AND b.check_out >= CURDATE()
        ");
        $stmt->execute();
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $occupancyMap = [];

        // 3. Process bookings to map occupancy per date
        foreach ($bookings as $b) {
            $start = new DateTime($b['check_in']);
            $end = new DateTime($b['check_out']);
            
            // Interval is 1 day. DatePeriod excludes the 'check_out' day 
            // which is correct (room is free on morning of check-out)
            $interval = new DateInterval('P1D');
            $period = new DatePeriod($start, $interval, $end);

            foreach ($period as $date) {
                $d = $date->format('Y-m-d');
                if (!isset($occupancyMap[$d])) {
                    $occupancyMap[$d] = 0;
                }
                $occupancyMap[$d] += (int)$b['rooms_booked'];
            }
        }

        // 4. Identify dates where occupancy >= total capacity
        $disabledDates = [];
        foreach ($occupancyMap as $date => $currentOccupancy) {
            if ($currentOccupancy >= $totalCapacity) {
                $disabledDates[] = $date;
            }
        }

        return $disabledDates;
    }

    public function getTotalRevenue()
    {
        $sql = "SELECT SUM(amount) as total FROM payments WHERE verified = 'approved'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['total'] ?: 0;
    }

    public function getMonthlyRevenue($year = null)
    {
        $year = $year ?: date('Y');
        // Using payment_status = 'paid' ensures we only chart actual income
        $sql = "SELECT MONTH(created_at) as month, SUM(amount) as total 
                FROM payments 
                WHERE YEAR(created_at) = :year AND verified = 'approved'
                GROUP BY MONTH(created_at)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':year' => $year]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPendingRevenue()
    {
        $sql = "SELECT SUM(amount) as total FROM payments WHERE verified = 'pending'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row['total'] ?: 0;
    }

    public function getUnpaidBookings($limit = 5)
    {
        $sql = "SELECT b.*, c.full_name, c.phone 
                FROM bookings b
                JOIN customers c ON b.customer_id = c.id
                WHERE b.payment_status != 'paid' AND b.status != 'cancelled'
                ORDER BY b.created_at DESC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get all unique years from the bookings table
    public function getBookingYears()
    {
        $stmt = $this->db->prepare("SELECT DISTINCT YEAR(created_at) as year FROM bookings ORDER BY year DESC");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Fetching 'pending' bookings as "New Alerts"
    public function getNewBookings($limit = 4)
    {
        $sql = "SELECT b.*, c.full_name 
                FROM bookings b 
                JOIN customers c ON b.customer_id = c.id 
                WHERE b.status = 'pending' 
                ORDER BY b.created_at DESC LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getNewBookingsCount()
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM bookings WHERE status = 'pending'");
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function addPayment($data)
    {
        $sql = "INSERT INTO payments (booking_id, billplz_id, payment_ref_no, payment_method, amount, balance_after, payment_type, receipt_image, verified, payment_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $this->db->prepare($sql);
        
        $result = $stmt->execute([
            $data['booking_id'],
            $data['billplz_id'] ?? null,
            $data['payment_ref_no'],
            $data['payment_method'],
            $data['amount'],
            $data['balance_after'] ?? 0,
            $data['payment_type'],
            $data['receipt_image'] ?? null,
            $data['verified'] ?? 'pending'
        ]);

        if (!$result) {
            error_log("Database Error in addPayment: " . implode(" ", $stmt->errorInfo()));
        }
        return $result;
    }

    public function getPaymentByBillplzId($billplz_id)
    {
        $stmt = $this->db->prepare("SELECT * FROM payments WHERE billplz_id = ?");
        $stmt->execute([$billplz_id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Update payment record based on Billplz ID
    public function updatePaymentStatus($billplz_id, $status)
    {
        $stmt = $this->db->prepare("UPDATE payments SET verified = 'approved' WHERE billplz_id = ?");
        
        return $stmt->execute([$billplz_id]);
    }

    // Update the main booking status
    public function updateBookingStatus($booking_id, $payment_status, $booking_status)
    {
        $stmt = $this->db->prepare("UPDATE bookings SET payment_status = ?, status = ? WHERE id = ?");

        return $stmt->execute([$payment_status, $booking_status, $booking_id]);
    }

    public function verifyBillplzPayment($bill_id)
    {
        $api_key = 'd6f9bdfc-70fd-4f17-8129-7daa4302905f';
        
        $ch = curl_init('https://www.billplz-sandbox.com/api/v3/bills/' . $bill_id);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $api_key . ":");
        
        $response = curl_exec($ch);
        $data = json_decode($response, true);
        
        if (isset($data['paid']) && $data['paid'] === true && $data['state'] === 'paid') {
            // Update database to 'approved'
            $stmt = $this->db->prepare("UPDATE payments SET verified = 'approved' WHERE billplz_id = ?");
            $stmt->execute([$bill_id]);
            return true;
        }

        // Log error if needed for debugging on localhost
        if (isset($data['error'])) {
            file_put_contents('debug_verify.txt', json_encode($data['error']), FILE_APPEND);
        }

        return false;
    }

    public function getPaymentByBookingId($booking_id)
    {
        $stmt = $this->db->prepare("SELECT * FROM payments WHERE booking_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$booking_id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result;
    }

    // Locked Room
    public function holdRooms($rooms, $expiresAt)
    {
        $stmt = $this->db->prepare("DELETE FROM room_locks WHERE session_id = ?");
        $stmt->execute([session_id()]);

        foreach ($rooms as $roomId => $quantity) {
            $sql = "INSERT INTO room_locks (room_id, quantity, expires_at, session_id) 
                    VALUES (?, ?, ?, ?)";
            $stmtInsert = $this->db->prepare($sql);
            $stmtInsert->execute([
                $roomId, 
                $quantity, 
                date('Y-m-d H:i:s', $expiresAt), 
                session_id()
            ]);
        }
    }

    /**
     * Release locks held by a specific session.
     * Usually called after a successful booking or an expired session.
     */
    public function releaseLocks($sessionId)
    {
        $stmt = $this->db->prepare("DELETE FROM room_locks WHERE session_id = ?");
        $stmt->execute([$sessionId]);

        $stmt2 = $this->db->prepare("DELETE FROM room_locks WHERE expires_at < NOW()");
        $stmt2->execute();
    }

    public function getPopularRooms()
    {
        $sql = "SELECT r.name, COUNT(br.id) as total_bookings 
                FROM rooms r
                JOIN booking_rooms br ON r.id = br.room_id
                JOIN bookings b ON br.booking_id = b.id
                WHERE b.status != 'cancelled'
                GROUP BY r.id 
                ORDER BY total_bookings DESC 
                LIMIT 5";

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOccupancyForecast()
    {
        $sql = "SELECT d.date, COALESCE(SUM(br.rooms_booked), 0) as occupied_count
                FROM (
                    SELECT CURDATE() as date UNION SELECT DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                    UNION SELECT DATE_ADD(CURDATE(), INTERVAL 2 DAY) UNION SELECT DATE_ADD(CURDATE(), INTERVAL 3 DAY)
                    UNION SELECT DATE_ADD(CURDATE(), INTERVAL 4 DAY) UNION SELECT DATE_ADD(CURDATE(), INTERVAL 5 DAY)
                    UNION SELECT DATE_ADD(CURDATE(), INTERVAL 6 DAY)
                ) d
                LEFT JOIN bookings b ON d.date >= b.check_in AND d.date < b.check_out AND b.status = 'confirmed'
                LEFT JOIN booking_rooms br ON b.id = br.booking_id
                GROUP BY d.date";
                
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUnpaidBookingsWithPaymentStatus($limit = 10)
    {
        $sql = "SELECT 
                    b.id, 
                    b.booking_ref_no, 
                    b.check_in, 
                    b.total_amount, 
                    b.payment_status,
                    b.created_at AS booking_date,
                    c.full_name,
                    p_agg.payment_id,
                    p_agg.verified,
                    p_agg.payment_method
                FROM bookings b
                LEFT JOIN customers c ON b.customer_id = c.id 
                LEFT JOIN (
                    SELECT 
                        booking_id, 
                        MAX(id) as payment_id, 
                        MAX(verified) as verified, 
                        MAX(payment_method) as payment_method
                    FROM payments 
                    GROUP BY booking_id
                ) p_agg ON b.id = p_agg.booking_id
                WHERE b.payment_status != 'paid' 
                AND b.status != 'cancelled'
                ORDER BY b.created_at DESC 
                LIMIT :limit";
                
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function autoCleanupExpiredBookings()
    {
        $today = date('Y-m-d');

        // 1. Mark 'pending' bookings that never paid as 'expired'
        $sqlExpired = "UPDATE bookings 
                    SET status = 'expired' 
                    WHERE status = 'pending' 
                    AND check_in < ?";
        $this->db->prepare($sqlExpired)->execute([$today]);

        // 2. Mark 'confirmed' bookings as 'paid' ONLY IF
        $sqlCompletePayment = "UPDATE bookings 
                            SET payment_status = 'paid' 
                            WHERE status = 'confirmed' 
                            AND payment_status = 'partial' 
                            AND check_out < ?";
        $this->db->prepare($sqlCompletePayment)->execute([$today]);
    }
}