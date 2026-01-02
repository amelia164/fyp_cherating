<?php

class PaymentController extends Controller
{
    private $paymentModel, $bookingModel, $db;

    // Constructor to initialize the model
    public function __construct()
    {
        $this->paymentModel = $this->model('PaymentModel');
        $this->bookingModel = $this->model('BookingModel');
    }

    public function index()
    {
        $resultsPerPage = 10;
        $currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $search = isset($_GET['search']) ? $_GET['search'] : '';

        $offset = ($currentPage - 1) * $resultsPerPage;

        $totalPayments = $this->paymentModel->getTotalPayments($search);
        $totalPages = ceil($totalPayments / $resultsPerPage);

        $payments = $this->paymentModel->getAllPayments($offset, $resultsPerPage, $search);

        // Pass the pagination data to the view
        $this->view('admin/payments/index', [
            'payments' => $payments,
            'totalPages' => $totalPages,
            'currentPage' => $currentPage,
            'offset' => $offset,
            'resultsPerPage' => $resultsPerPage,
            'totalPayments' => $totalPayments,
            'search' => $search
        ]);
    }

    public function updateStatus() 
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $payment_id = $_POST['payment_id'];
            $status = $_POST['status'];
            $reason = $_POST['reason'] ?? '';
            
            // Update the payment record
            $success = $this->paymentModel->updateVerificationStatus($payment_id, $status, $reason);
            
            if ($success) {
                $payment = $this->paymentModel->getPaymentById($payment_id);

                $booking_id = $payment['booking_id'];
                
                if ($status === 'approved') {
                    $this->bookingModel->updateBookingStatus($booking_id, 'partial', 'confirmed');
                    $_SESSION['success'] = "Payment approved and booking confirmed!";
                } else {
                    $this->bookingModel->updateBookingStatus($booking_id, 'unpaid', 'pending');
                    $_SESSION['warning'] = "Payment rejected. Customer will see the reason.";
                }
                
                $_SESSION['success'] = "Payment has been " . $status;
            }
            
            header("Location: " . APP_URL . "/dashboard");
            exit;
        }
    }

    public function verifyPayment($id)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formStatus = $_POST['status']; 
            $reason = $_POST['rejection_reason'] ?? '';

            $dbStatus = ($formStatus === 'verified') ? 'approved' : 'rejected';

            $success = $this->paymentModel->updateVerificationStatus($id, $dbStatus, $reason);

            if ($success) {
                $payment = $this->paymentModel->getPaymentById($id);
                
                if ($dbStatus === 'approved') {
                    $totalPaid = (float)($payment['total_paid_to_date'] ?? 0);
                    $bookingTotal = (float)$payment['total_amount'];

                    $newPaymentStatus = ($totalPaid >= $bookingTotal) ? 'paid' : 'partial';

                    $this->bookingModel->updateBookingStatus(
                        $payment['booking_id'], 
                        $newPaymentStatus,      
                        'confirmed'  
                    );
                    
                    Flash::set('success', 'Payment approved. Booking status updated to ' . strtoupper($newPaymentStatus));
                } else {
                    Flash::set('warning', 'Payment has been rejected.');
                }
            } else {
                Flash::set('error', 'Failed to update database.');
            }
            
            header('Location: ' . APP_URL . '/admin/payments/verify/' . $id);
            exit;
        }

        $payment = $this->paymentModel->getPaymentById($id);

        if (!$payment) {
            Flash::set('error', 'No payment record found.');
            header("Location: " . APP_URL . "/admin/payments");
            exit;
        }
        
        $this->view('admin/payments/view', ['payment' => $payment]);
    }
}