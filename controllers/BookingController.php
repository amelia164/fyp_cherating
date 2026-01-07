<?php

class BookingController extends Controller
{
    // Declare the model property
    private $bookingModel, $paymentModel, $roomModel, $db;

    // Constructor to initialize the model
    public function __construct()
    {
        // Initialize the model (only once)
        $this->bookingModel = $this->model('BookingModel');
        $this->paymentModel = $this->model('PaymentModel');
    }

    public function index()
    {
        // Number of results per page
        $resultsPerPage = 10;
        $currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $search = isset($_GET['search']) ? $_GET['search'] : '';

        $offset = ($currentPage - 1) * $resultsPerPage;

        // Get the total number of bookings
        $totalBookings = $this->bookingModel->getTotalBookings($search);
        $totalPages = ceil($totalBookings / $resultsPerPage);

        // Fetch the bookings for the current page
        $bookings = $this->bookingModel->getAllBookings($offset, $resultsPerPage, $search);

        // Pass the pagination data to the view
        $this->view('admin/bookings/index', [
            'bookings' => $bookings,
            'totalPages' => $totalPages,
            'currentPage' => $currentPage,
            'offset' => $offset,
            'resultsPerPage' => $resultsPerPage,
            'totalBookings' => $totalBookings,
            'search' => $search
        ]);
    }

    // view bookings detail
    public function viewBooking($id)
    {
        // get the booking details by ID
        $booking = $this->bookingModel->getBookingById($id);

        if (!$booking) {
            Flash::set('error', 'Booking not found');
            header('Location: ' . APP_URL . '/admin/bookings');
            exit;
        }

        $this->view('admin/bookings/view', ['booking' => $booking]);
    }

    // Delete bookings
    public function delete($id)
    {
        $booking = $this->bookingModel->getBookingById($id);

        if (!$booking) {
            Flash::set('error', 'Booking not found');
            header('Location: ' . APP_URL . '/admin/bookings');
            exit;
        }

        $this->bookingModel->deleteBooking($id);

        Flash::set('success', 'Booking deleted successfully');
        header('Location: ' . APP_URL . '/admin/bookings');
        exit;
    }

    /**
     * Displays the customer's personal booking dashboard, 
     * categorized into Upcoming and Past bookings.
    */
    public function customerDashboard()
    {
        // 1. Authentication Check & Customer ID Retrieval
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'customer') {
            header('Location: ' . APP_URL . '/login');
            exit;
        }

        $customerId = (int)$_SESSION['user_id'];
        
        // 2. Fetch all bookings for the customer
        $bookings = $this->bookingModel->getBookingsByCustomer($customerId);

        // 3. Sort Bookings into Upcoming and Past
        $currentDate = date('Y-m-d');
        $upcomingBookings = [];
        $pastBookings = [];

        if (!empty($bookings)) {
            foreach ($bookings as $booking) {
                if ($booking['check_out'] >= $currentDate) {
                    $upcomingBookings[] = $booking;
                } else {
                    $pastBookings[] = $booking;
                }
            }
        }
        
        // 4. Load the enhanced dashboard view
        $data = [
            'upcomingBookings' => $upcomingBookings,
            'pastBookings' => $pastBookings,
        ];

        $this->view('home/dashboard', $data); 
    }

    // Cancel Booking
    public function cancelBooking($id)
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        
        $bookingModel = $this->model('BookingModel');
        $booking = $bookingModel->getBookingById($id);

        if (!$booking || $booking['customer_id'] != $_SESSION['user_id']) {
            Flash::set('error', "Unauthorized action.");
            header("Location: " . APP_URL . "/dashboard");
            exit;
        }

        $result = $bookingModel->updateBookingStatus($id, 'unpaid', 'cancelled');

        if ($result) {
            Flash::set('success', "Booking cancelled successfully. Your refund is being processed.");
        } else {
            Flash::set('error', "Failed to cancel booking. Please contact support.");
        }

        header("Location: " . APP_URL . "/dashboard");
        exit;
    }
}