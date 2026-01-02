<?php

require_once 'helpers/WhatsAppHelper.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class HomeController extends Controller
{
    // Declare the model property
    private $bookingModel, $contactModel, $roomModel, $customerModel, $db;

    // Constructor to initialize the model
    public function __construct()
    {
        // Initialize the model (only once)
        $this->bookingModel = $this->model('BookingModel');
        $this->roomModel = $this->model('RoomModel');
        $this->contactModel = $this->model('ContactModel');
        $this->customerModel = $this->model('CustomerModel');

        //Initialized the DB property
        $this->db = Database::getInstance();
    }

    // Home Page
    public function index()
    {
        $this->view("home/index", [
            "title" => "Welcome to " . APP_NAME
        ]);
    }

    // About Page
    public function about()
    {
        $this->view("home/about", [
            "title" => "About Us - " . APP_NAME
        ]);
    }

    // Rooms page after selecting dates, show availability and prices
    public function rooms()
    {
        // Get dates from POST (form submission) or GET (filter links)
        $arrival = $_POST['arrival_date'] ?? $_GET['arrival_date'] ?? null;
        $departure = $_POST['departure_date'] ?? $_GET['departure_date'] ?? null;
        $guests = $_POST['guests'] ?? $_GET['guests'] ?? 1;

        // Check if the arrival date is before the departure date
        if ($arrival && $departure && $arrival > $departure) {
            Flash::set('error', 'Departure date must be after arrival date.');
            header('Location: ' . APP_URL);
            exit;
        }

        // Get all rooms
        if ($arrival && $departure) {
            // Get all rooms with their availability counts based on the dates
            $rooms = $this->roomModel->getAvailableRoomsWithCounts($arrival, $departure, $guests);

            // --- START DYNAMIC PRICING CALCULATION ---
            $adjustments = $this->roomModel->getPriceAdjustments($arrival, $departure);
            $checkInDate = new \DateTime($arrival);
            $checkOutDate = new \DateTime($departure);
            $totalNights = $checkInDate->diff($checkOutDate)->days;

            if ($totalNights > 0) {
                foreach ($rooms as &$room) {
                    $totalPriceForStay = 0;

                    for ($i = 0; $i < $totalNights; $i++) {
                        $loopDate = clone $checkInDate;
                        $loopDate->modify("+$i day");
                        $formattedDate = $loopDate->format('Y-m-d');
                        $dayOfWeek = $loopDate->format('N'); // 5=Fri, 6=Sat

                        $multiplier = 1.0;

                        // Priority 1: Check Holiday Adjustments
                        foreach ($adjustments as $adj) {
                            if ($formattedDate >= $adj['start_date'] && $formattedDate <= $adj['end_date']) {
                                $multiplier = (float)$adj['multiplier'];
                                break;
                            }
                        }

                        // Priority 2: Weekend Surcharge (if no holiday)
                        if ($multiplier == 1.0 && ($dayOfWeek == 5 || $dayOfWeek == 6)) {
                            $multiplier = 1.20;
                        }

                        $totalPriceForStay += ($room['price'] * $multiplier);
                    }

                    // Set new prices for the View
                    $room['display_price'] = $totalPriceForStay / $totalNights; // Average per night
                    $room['is_dynamic'] = ($room['display_price'] != $room['price']);
                    $room['total_stay_price'] = $totalPriceForStay;
                }
            }

        } else {
            $rooms = $this->roomModel->getAllRooms();

            foreach ($rooms as &$room) {
                $room['available'] = null;
                $room['display_price'] = $room['price'];
                $room['is_dynamic'] = false;
            }
        }

        // Filter rooms by price range if provided
        $priceRange = $_GET['price_range'] ?? null;
        $roomType = $_GET['room_type'] ?? null;

        if ($priceRange) {
            $priceParts = explode('-', $priceRange);
            $minPrice = $priceParts[0];
            $maxPrice = isset($priceParts[1]) ? $priceParts[1] : 999999;

            $rooms = array_filter($rooms, function($room) use ($minPrice, $maxPrice) {
                return $room['price'] >= $minPrice && $room['price'] <= $maxPrice;
            });
        }

        // Filter rooms by room type if provided
        if ($roomType) {
            $rooms = array_filter($rooms, function($room) use ($roomType) {
                return $room['id'] == $roomType;
            });
        }

        // Ratings & Reviews
        foreach ($rooms as &$room) {
            $ratingData = $this->roomModel->getAverageRating($room['id']);
            $room['avg_rating'] = $ratingData['avg_rating'];
            $room['total_reviews'] = $ratingData['total'];
        }
        unset($room);

        // Render the view with the filtered rooms
        $this->view('home/rooms', ['rooms' => $rooms, 'arrival' => $arrival, 'departure' => $departure, 'guests' => $guests]);
    }

    public function contact()
    {
        $this->view("home/contact", [
            "title" => "Contact Us - " . APP_NAME
        ]);
    }

    // Booking confirmation form (after selecting rooms and quantities)
    public function bookingConfirmation()
    {
        $arrival = $_POST['arrival_date'] ?? $_SESSION['check_in'] ?? null;
        $departure = $_POST['departure_date'] ?? $_SESSION['check_out'] ?? null;
        $guests = $_POST['guests'] ?? $_SESSION['guests'] ?? 1;
        $selectedRooms = $_POST['rooms'] ?? $_SESSION['selected_rooms'] ?? [];
        $totalAmount = 0.0;
        $totalCapacityProvided = 0;

        if (!$arrival || !$departure || empty($selectedRooms)) {
            Flash::set('error', 'Please select dates and rooms');
            header('Location: ' . APP_URL);
            exit;
        }

        // --- 1. Calculate Total Nights ---
        try {
            $checkDate = new \DateTime($arrival);
            $endDate = new \DateTime($departure);
            // The difference in days is the number of nights
            $interval = $checkDate->diff($endDate);
            $totalNights = $interval->days;

            if ($totalNights <= 0) {
                Flash::set('error', 'Departure date must be after arrival date.');
                header('Location: ' . APP_URL);
                exit;
            }
        } catch (\Exception $e) {
            Flash::set('error', 'Invalid date format.');
            header('Location: ' . APP_URL);
            exit;
        }

        // Check if the selected dates are fully booked
        $fullDates = $this->bookingModel->getFullyBookedDates();

        // Create a temporary clone for the loop so we don't modify the original arrival object
        $loopDate = clone $checkDate;
        
        // Check if the arrival date is in the "Full" list
        while ($loopDate < $endDate) {
            $formattedDate = $loopDate->format('Y-m-d');
            if (in_array($formattedDate, $fullDates)) {
                // die("Date $formattedDate is full!");
                Flash::set('error', "Sorry! The guesthouse is fully booked on " . $loopDate->format('d/m/Y') . ". Please choose different dates.");
                header('Location: ' . APP_URL);
                exit;
            }
            $loopDate->modify('+1 day');
        }

        // 4. Room Processing & Cost Calculation
        $rooms = $this->roomModel->getRoomsByIds(array_keys($selectedRooms));
        $costPerNight = 0.0;
        $rooms_for_session = [];

        foreach ($rooms as $room) {
            $roomId = (int)$room['id'];
            $quantity = (int)($selectedRooms[$roomId] ?? 0);

            if ($quantity > 0) {
                if ($quantity > (int)$room['total_rooms']) {
                    Flash::set('error', "You requested $quantity {$room['name']}(s), but we only have {$room['total_rooms']} units in total.");
                    header('Location: ' . APP_URL);
                    exit;
                }

                $totalCapacityProvided += ((int)$room['capacity'] * $quantity);
                $room['quantity'] = $quantity;
                $rooms_for_session[] = $room;

                // Calculate the subtotal for this room type for one night
                $subtotal = (float)$room['price'] * $quantity;
                $costPerNight += $subtotal;
            }
        }

        if ($totalCapacityProvided < $guests) {
            Flash::set('error', "The rooms you selected can only accommodate $totalCapacityProvided guests, but you have $guests guests. Please select more rooms or a larger room type.");
            header('Location: ' . APP_URL . '/rooms'); // Redirect back to room selection
            exit;
        }

        // 5. Calculate Final Total Amount
        $adjustments = $this->roomModel->getPriceAdjustments($arrival, $departure);

        foreach ($rooms_for_session as &$room) {
            $room['calculated_total'] = 0;
        }

        // Loop through each night of the stay
        for ($i = 0; $i < $totalNights; $i++) {
            $currentNight = clone $checkDate;
            $currentNight->modify("+$i day");
            $formattedNight = $currentNight->format('Y-m-d');
            $dayOfWeek = $currentNight->format('N'); // 1 (Mon) to 7 (Sun)

            // A. Determine the multiplier for THIS specific night
            $nightMultiplier = 1.0;

            // Check for Holiday Multiplier first (Priority)
            foreach ($adjustments as $adj) {
                if ($formattedNight >= $adj['start_date'] && $formattedNight <= $adj['end_date']) {
                    $nightMultiplier = (float)$adj['multiplier'];
                    break; // Stop looking if holiday found
                }
            }

            // If no holiday, check for Weekend (Friday=5, Saturday=6)
            if ($nightMultiplier == 1.0) {
                if ($dayOfWeek == 5 || $dayOfWeek == 6) {
                    $nightMultiplier = 1.20; // 20% increase for weekends
                }
            }

            // B. Add the cost of all selected rooms for this specific night
            foreach ($rooms_for_session as &$room) {
                $nightlyPrice = ($room['price'] * $nightMultiplier) * $room['quantity'];
                $room['calculated_total'] += $nightlyPrice;
                $totalAmount += $nightlyPrice;
            }
        }
        unset($room);

        $depositAmount = $totalAmount * 0.35;
        $customerInfo = null;
        $isLoggedIn = false;

        if (isset($_SESSION['user_id'])) {
            $isLoggedIn = true;
            $customerInfo = $this->customerModel->getById($_SESSION['user_id']);
        }

        // Generate the QR URL
        $merchantId = '110329060755';
        $rawPaymentLink = "https://www.tngdigital.com.my/pay?amount=" . number_format($totalAmount, 2, '.', '') . "&merchant_id=" . $merchantId;

        // 6. Save selected rooms and booking details to session
        $_SESSION['selected_rooms'] = $selectedRooms;
        $_SESSION['rooms_data'] = $rooms_for_session;
        $_SESSION['check_in'] = $arrival;
        $_SESSION['check_out'] = $departure;
        $_SESSION['guests'] = $guests;
        $_SESSION['total_amount'] = $totalAmount;
        $_SESSION['deposit_amount'] = $depositAmount;
        $_SESSION['total_nights'] = $totalNights;
        $_SESSION['qr_url_raw'] = $rawPaymentLink;

        // Booking Timer
        if (isset($_SESSION['booking_expires_at']) && time() > $_SESSION['booking_expires_at']) {
            $this->bookingModel->releaseLocks(session_id());
            $this->clearBookingSession();
            Flash::set('error', 'Your booking session has expired. Please start over.');
            header('Location: ' . APP_URL . '/rooms');
            exit;
        }

        if (!isset($_SESSION['booking_expires_at'])) {
            $minutes = 3;
            $_SESSION['booking_expires_at'] = time() + ($minutes * 60);

            $this->bookingModel->holdRooms($selectedRooms, $_SESSION['booking_expires_at']);
        }

        $remainingSeconds = $_SESSION['booking_expires_at'] - time();

        // 7. Render View
        $this->view('home/booking-confirmation', [
            'arrival' => $arrival,
            'departure' => $departure,
            'guests' => $guests,
            'rooms' => $rooms_for_session,
            'totalAmount' => $totalAmount,
            'depositAmount' => $depositAmount,
            'totalNights' => $totalNights,
            'qrUrl' => $rawPaymentLink,
            'expires_at' => $_SESSION['booking_expires_at'],
            'remaining_seconds' => $remainingSeconds,
            'customerInfo' => $customerInfo,
            'isLoggedIn' => $isLoggedIn
        ]);
    }

    // Save booking to DB
    public function confirmBooking()
    {
        file_put_contents('debug.txt', 'confirmBooking reached' . PHP_EOL, FILE_APPEND);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            file_put_contents('debug.txt', 'Not a POST request' . PHP_EOL, FILE_APPEND);
            header('Location: ' . APP_URL);
            exit;
        }
        if (!isset($_SESSION['booking_expires_at']) || time() > $_SESSION['booking_expires_at']) {
            $this->bookingModel->releaseLocks(session_id());
            $this->clearBookingSession();
            Flash::set('error', 'Your booking session has expired. Please re-select your rooms.');
            header('Location: ' . APP_URL . '/rooms');
            exit;
        }

        // 1. Retrieve Data
        $name = $_POST['name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $email = $_POST['email'] ?? '';
        $username = $_POST['username'] ?? null;
        $password = $_POST['password'] ?? null;
        $check_in = $_POST['check_in'] ?? '';
        $check_out = $_POST['check_out'] ?? '';
        $rooms = $_POST['rooms'] ?? [];
        $payment_method = $_POST['payment_method'] ?? '';
        $payment_details = $_POST['payment_details'] ?? '';
        $totalAmount = $_POST['total_amount'] ?? 0.0;

        // 2. Validate inputs
        if (!$name || !$phone || !$email || !$check_in || !$check_out || empty($rooms)) {
            Flash::set('error', 'Please fill in all required fields.');
            header('Location: ' . APP_URL);
            exit;
        }

        // Validate if receipt is uploaded
        if ($payment_method === 'qr') {
            if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
                Flash::set('error', 'Please upload your payment receipt for QR payment.');
                header('Location: ' . APP_URL . '/booking-confirmation');
                exit;
            }
        }

        // 3. Customer Existence Check & Flow Control
        $customerExists = $this->customerModel->checkExistence($email);
        $customerId = null;

        if (!$customerExists) {
            if (!empty($username) && !empty($password)) {
                $customerId = $this->customerModel->findOrCreateGuest($name, $phone, $email, $username, $password);
                
                if (!$customerId) {
                    Flash::set('error', 'Account creation failed. The username or email might already be registered. Please try a different username.');

                    $_SESSION['show_new_customer_fields'] = true;
                    header('Location: ' . APP_URL . '/booking-confirmation');
                    exit;
                }
                // Customer created successfully, proceed to booking finalization
                $this->handleFinalBooking($customerId, $_POST, $_FILES['receipt']);
            } else {
                $_SESSION['pending_booking_data'] = $_POST;

                Flash::set('prompt', 'It looks like you are a new customer. Please provide a **username** and **password** to create your account and complete the booking.', 'warning');

                $_SESSION['show_new_customer_fields'] = true;

                header('Location: ' . APP_URL . '/booking-confirmation'); 
                exit;
            }
        } else {
            $customerData = $this->customerModel->getByEmail($email);
            $customerId = $customerData['id'] ?? null;

            if (!$customerId) {
                Flash::set('error', 'Could not retrieve customer account data. Please check your email.');
                header('Location: ' . APP_URL . '/booking-confirmation');
                exit;
            }

            // Customer exists and is identified, proceed to final booking
            $this->handleFinalBooking($customerId, $_POST, $_FILES['receipt']);
        }
    }

    private function handleFinalBooking($customerId, $postData, $fileData = null)
    {
        if (time() > ($_SESSION['booking_expires_at'] ?? 0)) {
            Flash::set('error', 'Session timed out during account creation. Please try again.');
            header('Location: ' . APP_URL . '/rooms');
            exit;
        }

        // 1. Retrieve essential details
        $check_in = $postData['check_in']; 
        $check_out = $postData['check_out'];
        $rooms = $postData['rooms'];
        $payment_method = $postData['payment_method'];
        $totalAmount = $postData['total_amount'] ?? 0.0;
        $totalNights = $_SESSION['total_nights'] ?? 1;
        $payment_details = '';
        $targetPath = null;

        // Calculate 35% Deposit
        $depositAmount = $totalAmount * 0.35;
        $balanceRemaining = $totalAmount - $depositAmount;

        // 2. Handle QR receipt Upload Only
        if ($payment_method === 'qr') {
            if (!isset($fileData) || $fileData['error'] !== UPLOAD_ERR_OK) {
                Flash::set('error', 'Please upload a payment receipt for QR payment.');
                header('Location: ' . APP_URL . '/booking-confirmation');
                exit;
            }

            $uploadDir = 'uploads/receipts/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $fileExtension = pathinfo($fileData['name'], PATHINFO_EXTENSION);
            $fileName = 'receipt_' . time() . '_' . uniqid() . '.' . $fileExtension;
            $targetPath = $uploadDir . $fileName;

            if (!move_uploaded_file($fileData['tmp_name'], $targetPath)) {
                Flash::set('error', 'Failed to save the receipt image.');
                header('Location: ' . APP_URL . '/booking-confirmation');
                exit;
            }

            $orderId = strtoupper(substr(uniqid(), -8));
            $payment_details = "35% Deposit Paid via QR - Total: RM" . number_format($totalAmount, 2) . ", Order ID: " . $orderId;
        }

        // 3. Save Booking Record
        $booking_id = $this->bookingModel->createBooking(
            $customerId, $check_in, $check_out, $rooms, $payment_method,
            $payment_details, $totalAmount, $totalNights, $targetPath
        );

        if (!$booking_id) {
            Flash::set('error', 'Failed to save booking. Please try again.');
            header('Location: ' . APP_URL . '/booking-confirmation');
            exit;
        }

        // 4. Handle Payment logic
        if ($payment_method === 'fpx') {
            $cust_name  = trim($postData['name'] ?? 'Guest');
            $cust_email = trim($postData['email'] ?? '');
            $cust_phone = trim($postData['phone'] ?? '');

            if (empty($cust_email) || !filter_var($cust_email, FILTER_VALIDATE_EMAIL)) {
                Flash::set('error', 'A valid email is required for FPX payment.');
                header('Location: ' . APP_URL . '/booking-confirmation');
                exit;
            }

            // Create the Billplz Bill
            $bill = $this->createBillplzBill($cust_name, $cust_email, $cust_phone, $depositAmount, $booking_id);
            
            if (isset($bill['id']) && isset($bill['url'])) {
                $this->bookingModel->updatePaymentWithBillplz($booking_id, $bill['id']);
                
                $this->bookingModel->releaseLocks(session_id());
                $this->clearBookingSession();
                header('Location: ' . $bill['url']);
                exit;
            } else {
                $errorMessage = 'Connection failed';
                if (isset($bill['error'])) {
                    $errorMessage = is_array($bill['error']) ? json_encode($bill['error']) : $bill['error'];
                }
                
                Flash::set('error', 'Billplz API Error: ' . $errorMessage);
                header('Location: ' . APP_URL . '/booking-confirmation');
                exit;
            }
        } else {
            $this->bookingModel->releaseLocks(session_id());
            $this->clearBookingSession();
            header('Location: ' . APP_URL . '/confirmation-done/' . $booking_id);
            exit;
        }
    }

    public function confirmationDone($booking_id)
    {
        $booking = $this->bookingModel->getBookingById($booking_id);
        $payment = $this->bookingModel->getPaymentByBookingId($booking_id);

        if (!$booking) {
            (new ErrorController())->notFound("Booking not found");
            return;
        }

        $this->view('home/confirmation-done', [
            'booking' => $booking, 
            'payment' => $payment,
            'booking_id' => $booking_id
        ]);
    }

    public function downloadInvoice($booking_id)
    {
        // 1. Setup Logo
        $logoPath = $_SERVER['DOCUMENT_ROOT'] . '/fyp_cherating/public/assets/images/Cherating_Indah_Logo.png';
        $logoData = '';

        if (file_exists($logoPath)) {
            $type = pathinfo($logoPath, PATHINFO_EXTENSION);
            $data = file_get_contents($logoPath);
            $logoData = 'data:image/' . $type . ';base64,' . base64_encode($data);
        }

        // 2. Fetch Booking
        $booking = $this->bookingModel->getBookingById($booking_id);

        if (!$booking) {
            (new ErrorController())->notFound("Booking not found");
            return;
        }

        // 3. Prepare Calculations
        $totalNights = (int)$booking['total_nights'];
        $totalAmount = (float)$booking['total_amount'];
        $depositPaid = (float)($booking['deposit_paid'] ?? 0);
        $balanceDue = $totalAmount - $depositPaid;

        $totalRoomsCount = 0;
        foreach($booking['rooms'] as $r) { 
            $totalRoomsCount += (int)$r['rooms_booked']; 
        }
        
        // Average rate logic to handle dynamic pricing accurately
        $avgRate = $totalAmount / ($totalNights * $totalRoomsCount);
        $html_rooms = '';
        
        foreach ($booking['rooms'] as $room) {
            $qty = (int)$room['rooms_booked'];
            $subtotal = $avgRate * $qty * $totalNights;

            $html_rooms .= '
                <tr>
                    <td>' . htmlspecialchars($room['name']) . '</td>
                    <td>' . $qty . '</td>
                    <td>' . number_format($avgRate, 2) . '</td>
                    <td>' . $totalNights . '</td>
                    <td>' . number_format($subtotal, 2) . '</td>
                </tr>';
        }
        
        // 4. Build HTML
        $html = '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Invoice - ' . htmlspecialchars($booking['booking_ref_no']) . '</title>
            <style>
                /* Styles specific to printing (hides buttons/unnecessary elements) */
                @media print {
                    .no-print {
                        display: none !important;
                    }
                }
                body { font-family: Arial, sans-serif; font-size: 14px; margin: 20px; }
                .container { max-width: 800px; margin: 0 auto; padding: 20px; border: 1px solid #ccc; }
                .header { text-align: center; margin-bottom: 20px; }
                .logo { max-width: 100px; height: auto; }
                h2 { margin-bottom: 5px; }
                .contact { font-size: 12px; margin-bottom: 20px; }
                .details, .rooms { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                .details td { padding: 5px; }
                .rooms th, .rooms td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                .rooms th { background-color: #f2f2f2; }
                .total-row td { font-weight: bold; background-color: #f2f2f2; }
                .footer { text-align: center; font-size: 12px; margin-top: 30px; }
            </style>
        </head>
        <body onload="window.print();">
            <div class="container">
                <div class="header">
                    ' . ($logoData ? '<img src="' . $logoData . '" class="logo" alt="Logo">' : '') . '
                    <h2>Cherating Guest House</h2>
                    <p class="contact">Contact: +601111034533 | Address: 4/1000 Kampung Budaya, Jalan Kampung Cherating Lama, 26080 Kuantan, Pahang</p>
                </div>

                <h3>Invoice - #' . htmlspecialchars($booking['booking_ref_no']) . '</h3>

                <table class="details">
                    <tr>
                        <td><strong>Customer:</strong></td>
                        <td>' . htmlspecialchars($booking['full_name']) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Check-in:</strong></td>
                        <td>' . htmlspecialchars($booking['check_in']) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Check-out:</strong></td>
                        <td>' . htmlspecialchars($booking['check_out']) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Email:</strong></td>
                        <td>' . htmlspecialchars($booking['email']) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Payment Method:</strong></td>
                        <td>' . htmlspecialchars(ucfirst($booking['payment_method'] ?? 'N/A')) . '</td>
                    </tr>
                </table>

                <h4>Breakdown</h4>
                <table class="rooms">
                    <thead>
                        <tr>
                            <th>Room Name</th>
                            <th>Qty</th>
                            <th>Rate (Avg)</th>
                            <th>Nights</th>
                            <th>Subtotal (RM)</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $html_rooms . '
                        <tr class="total-row">
                            <td colspan="4" style="text-align: right;">Grand Total:</td>
                            <td>RM ' . number_format($totalAmount, 2) . '</td>
                        </tr>
                        <tr class="total-row">
                            <td colspan="4" style="text-align: right;">Deposit Paid:</td>
                            <td style="color: green;">- RM ' . number_format($depositPaid, 2) . '</td>
                        </tr>
                        <tr class="total-row balance-row">
                            <td colspan="4" style="text-align: right;">Balance Due (at Check-in):</td>
                            <td>RM ' . number_format($balanceDue, 2) . '</td>
                        </tr>
                    </tbody>
                </table>

                <div class="footer">
                    This is a computer-generated invoice. No signature required.<br>
                    <strong>Thank you for choosing Cherating Guest House!</strong>
                </div>
            </div>
        </body>
        </html>';

        echo $html;
        exit;
    }

    public function handleContactForm()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $message = trim($_POST['message'] ?? '');

            // Simple validation
            if (!$name || !$email || !$message) {
                $_SESSION['error'] = 'Please fill in all required fields.';
                header('Location: ' . APP_URL . '/contact');
                exit;
            }
        }

        // save to database
        $saved = $this->contactModel->saveContact($name, $email, $phone, $message);

        // WhatsApp API simulation
        if ($saved) {
            $wa_url = WhatsappHelper::generateWhatsAppLink($name, $email, $phone, $message);
            $_SESSION['whatsapp_url'] = $wa_url;
            $_SESSION['success'] = 'Your message has been sent successfully!';
        } else {
            $_SESSION['error'] = 'Failed to send your message. Please try again.';
        }

        header('Location: ' . APP_URL . '/contact');
        exit;
    }

    /**
     * Handles the display of the booking confirmation page when accessed via GET 
     * (e.g., after a redirect from the confirmBooking POST logic).
    */
    public function handleConfirmationView()
    {
        // Retrieve necessary data from the session
        $arrival = $_SESSION['check_in'] ?? null;
        $departure = $_SESSION['check_out'] ?? null;
        $totalNights = $_SESSION['total_nights'] ?? 0;
        $totalAmount = $_SESSION['total_amount'] ?? 0.0;
        
        $selectedRooms = $_SESSION['selected_rooms'] ?? [];
        $rooms = [];
        
        if (!empty($selectedRooms)) {
            $rooms = $this->roomModel->getRoomsByIds(array_keys($selectedRooms));

            foreach ($rooms as &$room) {
                $roomId = (int)$room['id'];
                $room['quantity'] = (int)($selectedRooms[$roomId] ?? 0);
            }
            unset($room);
        }


        $this->view('home/booking-confirmation', [
            'arrival' => $arrival,
            'departure' => $departure,
            'rooms' => $rooms,
            'totalAmount' => $totalAmount,
            'totalNights' => $totalNights,
        ]);
    }

    public function getReviews($room_id)
    {
        $reviews = $this->roomModel->getRoomReviews($room_id);
        
        // Check if the currently logged-in user can write a review
        $canReview = false;
        if (isset($_SESSION['user_id'])) {
            $canReview = $this->roomModel->canUserReview($_SESSION['user_id'], $room_id);
        }

        if (ob_get_length()) ob_clean();

        header('Content-Type: application/json');
        echo json_encode([
            'reviews' => $reviews,
            'canReview' => $canReview
        ]);
        exit;
    }

    public function submitReview()
    {
        // 1. Authentication Check
        if (!isset($_SESSION['user_id'])) {
            Flash::set('error', 'You must be logged in to post a review.');
            header('Location: ' . APP_URL . '/login');
            exit;
        }

        $customerId = $_SESSION['user_id'];
        $roomId = $_POST['room_id'] ?? null;
        $rating = (int)($_POST['rating'] ?? 5);
        $comment = trim($_POST['comment'] ?? '');

        // 2. Validation
        if (!$roomId || empty($comment)) {
            Flash::set('error', 'Please provide both a rating and a comment.');
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit;
        }

        // 3. Eligibility Check (Security Layer)
        // We re-verify on the server that they actually stayed in this room
        $canReview = $this->roomModel->canUserReview($customerId, $roomId);

        if (!$canReview) {
            Flash::set('error', 'You can only review rooms you have stayed in after your departure date.');
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit;
        }

        // 4. Save to Database
        $success = $this->roomModel->saveReview($roomId, $customerId, $rating, $comment);

        if ($success) {
            Flash::set('success', 'Thank you! Your review has been posted.');
        } else {
            Flash::set('error', 'Something went wrong. Please try again later.');
        }

        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }

    // FPX API Payment Gateway
    private function createBillplzBill($name, $email, $phone, $amount, $booking_id)
    {
        $api_key = 'd6f9bdfc-70fd-4f17-8129-7daa4302905f'; // Get from Billplz Settings
        $collection_id = 'w610xxd0'; // Get from Billplz Collections
        
        $url = 'https://www.billplz-sandbox.com/api/v3/bills';

        $data = [
            'collection_id' => $collection_id,
            'email'         => $email,
            'phone'         => $phone,
            'name'          => $name,
            'amount'        => round($amount * 100),
            'callback_url'  => APP_URL . '/payment-callback',
            'redirect_url'  => APP_URL . '/confirmation-done/' . $booking_id,
            'description'   => 'Deposit for Booking #' . $booking_id
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $api_key . ":");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            return ['error' => ['message' => curl_error($ch)]];
        }
        curl_close($ch);

        return json_decode($response, true);
    }

    public function paymentCallback()
    {
        // 1. Get the data sent by Billplz
        $id = $_POST['id'] ?? null;
        $paid = $_POST['paid'] ?? null;
        $status = $_POST['state'] ?? null;
        $signature = $_POST['x_signature'] ?? null;

        // 2. Security Check (Optional but recommended)
        // You can verify the X-Signature here using your API Salt

        // 3. Find the booking ID from the Billplz Description or an external reference
        // Since we put 'Deposit for Booking #123' in the description, we can parse it,
        // but it's better to use the 'id' (Billplz Bill ID) if you saved it to your DB.
        
        // For this example, let's assume 'paid' means we update the status
        if ($paid === 'true' && $status === 'paid') {
            // Best practice: Query your payments table for the Billplz ID.
            $paymentData = $this->bookingModel->getPaymentByBillplzId($id);
            
            if ($paymentData) {
                $booking_id = $paymentData['booking_id'];

                // 4. Update Payment status to 'completed'
                $this->bookingModel->updatePaymentStatus($id, 'completed');

                // 5. Update Booking payment_status to 'partial' or 'paid'
                $this->bookingModel->updateBookingStatus($booking_id, 'partial', 'confirmed');
                
                file_put_contents('payment_log.txt', "Booking $booking_id paid successfully via Billplz.\n", FILE_APPEND);
            }
        }
    }

    public function manualVerify($bill_id)
    {
        $payment = $this->bookingModel->getPaymentByBillplzId($bill_id);

        if (!$payment) {
            Flash::set('error', 'Payment record not found.');
            header('Location: ' . APP_URL);
            exit;
        }

        $isPaid = $this->bookingModel->verifyBillplzPayment($bill_id);
        
        if ($isPaid) {
            $this->bookingModel->updateBookingStatus($payment['booking_id'], 'partial', 'confirmed');
            Flash::set('success', 'Payment verified successfully!');
        } else {
            Flash::set('error', 'Payment not detected yet. If you have paid, please wait a moment.');
        }
        
        header('Location: ' . APP_URL . '/confirmation-done/' . $payment['booking_id']);
        exit;
    }

    // 360 Virtual Tour
    public function virtualTour($roomId)
    {
        $tourModel = new RoomVirtualModel(); 
        $tourData = $tourModel->getTourByRoomId($roomId);
        
        if (!$tourData) {
            die("Virtual tour not available for this room.");
        }

        $hotspots = $tourModel->getHotspotsByTourId($tourData['tour_id']);

        $this->view('home/virtual_tour', [
            'tourData' => $tourData,
            'hotspots' => $hotspots,
            'roomId'   => $roomId,
        ]);
    }

    // Cleanup all session
    private function clearBookingSession()
    {
        $vars = [
            'selected_rooms', 
            'rooms_data', 
            'check_in', 
            'check_out', 
            'guests', 
            'total_amount', 
            'deposit_amount', 
            'total_nights', 
            'qr_url_raw', 
            'booking_expires_at', 
            'pending_booking_data',
            'show_new_customer_fields'
        ];
        
        foreach ($vars as $var) {
            if (isset($_SESSION[$var])) {
                unset($_SESSION[$var]);
            }
        }
    }
}