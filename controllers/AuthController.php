<?php
require_once 'helpers/WhatsAppHelper.php';

class AuthController extends Controller
{
    // Declare the model property
    private $bookingModel, $contactModel, $roomModel, $customerModel, $adminModel, $paymentModel;

    // Constructor to initialize the model
    public function __construct()
    {
        // Initialize the model (only once)
        $this->bookingModel = $this->model('BookingModel');
        $this->contactModel = $this->model('ContactModel');
        $this->roomModel = $this->model('RoomModel');
        $this->customerModel = $this->model('CustomerModel');
        $this->adminModel = $this->model('AdminModel');
        $this->paymentModel = $this->model('PaymentModel');
    }

    public function login()
    {
        $this->view('auth/login');
    }

    public function authenticate()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        // Check admin first
        $admin = $this->adminModel->getByUsername($username);

        if ($admin && password_verify($password, $admin['password'])) {
            // reset any existing session
            session_unset();
            // set admin session
            $_SESSION['is_logged_in'] = true;
            $_SESSION['auth_type'] = 'admin';
            $_SESSION['user_id'] = $admin['id']; //unified key
            $_SESSION['username'] = $admin['username'] ?? $username;
            $_SESSION['full_name'] = $admin['full_name'] ?? 'Administrator';

            Flash::set('success', "Welcome back, Admin {$username}!");
            header("Location: " . APP_URL . "/dashboard");
            exit;
        }

        // Try customer next
        $customer = $this->customerModel->getByUsername($username);

        if ($customer && password_verify($password, $customer['password'])) {
            // reset any existing session
            session_unset();
            // set customer session
            $_SESSION['is_logged_in'] = true;
            $_SESSION['auth_type'] = 'customer';
            $_SESSION['user_id'] = $customer['id']; // unified key
            $_SESSION['username'] = $username;
            $_SESSION['full_name'] = $customer['full_name'] ?? '';

            Flash::set('success', "Welcome back, {$username}!");
            header("Location: " . APP_URL . "/dashboard");
            exit;
        }

        // if both fail
        Flash::set('error', "Invalid username or password.");
        header("Location: " . APP_URL . "/auth/login");
        exit;
    }

    public function register()
    {
        $this->view('auth/register');
    }

    public function registerProcess()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: " . APP_URL . "/auth/register");
            exit;
        }

        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($full_name) || empty($email) || empty($username) || empty($password) || empty($phone)) {
            Flash::set('error', "All fields are required.");
            header("Location: " . APP_URL . "/auth/register");
            exit;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Check if username or email already exists (verified + pending)
        $existingUsername = $this->customerModel->getByUsername($username, true);
        $pendingUsername = $this->customerModel->getPendingByUsername($username);

        if ($existingUsername || $pendingUsername) {
            Flash::set('error', "Username already taken.");
            header("Location: " . APP_URL . "/auth/register");
            exit;
        }

        $existingEmail = $this->customerModel->getByEmail($email, true);
        $pendingEmail = $this->customerModel->getPendingByEmail($email);

        if ($existingEmail || $pendingEmail) {
            Flash::set('error', "Email already registered.");
            header("Location: " . APP_URL . "/auth/register");
            exit;
        }

        // Check if a pending user exists by email or phone
        $pendingCustomer = $this->customerModel->getPendingByEmailOrPhone($email, $phone);

        if ($pendingCustomer) {
            // Previous OTP still valid?
            $now = new DateTime();
            $expires_at = new DateTime($pendingCustomer['verification_expires_at'] ?? 'now');

            if ($expires_at > $now && !empty($pendingCustomer['verification_code'])) {
                // Reuse previous OTP
                $verification_code = $pendingCustomer['verification_code'];
            } else {
                // Generate new OTP and update pending record
                $verification_code = rand(100000, 999999);
                $this->customerModel->updateVerificationCode(
                    $pendingCustomer['id'],
                    $verification_code,
                    $hashedPassword,
                    $full_name,
                    $username,
                    $phone
                );
            }
        } else {
            // No pending user, create new one
            $verification_code = rand(100000, 999999);
            $this->customerModel->createCustomer($full_name, $email, $username, $hashedPassword, $phone, $verification_code);
        }

        // Send OTP via WhatsApp
        $whatsappMessage = "🌴 *Cherating Guest House* 🌴\n\n" .
            "Hello *$full_name*,\n" .
            "Use the code below to verify your account:\n\n" .
            "🔹 *Code: $verification_code*\n" .
            "🔹 Username: $username\n" .
            "🔹 Email: $email\n\n" .
            "⏳ Valid for 10 mins. Thank you! 💚";

        $response = WhatsappHelper::sendOTP($phone, $whatsappMessage);

        if (strpos($response, 'Success') !== false) {
            Flash::set('success', "Verification code sent to WhatsApp!");
        } else {
            Flash::set('success', "Registered! (Demo OTP: $verification_code)");
            error_log("WhatsApp API Response: " . $response);
        }

        header("Location: " . APP_URL . "/auth/verify");
        exit;
    }

    public function verify()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $code = trim($_POST['verification_code'] ?? '');

            if (empty($code)) {
                Flash::set('error', "Please enter your verification code.");
                header("Location: " . APP_URL . "/auth/verify");
                exit;
            }

            // Check and mark verified
            if ($this->customerModel->verifyCustomer($code)) {
                Flash::set('success', "Verification successful! You can now log in.");
                header("Location: " . APP_URL . "/auth/login");
                exit;
            } else {
                Flash::set('error', "Invalid or expired verification code. Please try again.");
                header("Location: " . APP_URL . "/auth/verify");
                exit;
            }
        }

        // Show verification form
        $this->view('auth/verify');
    }

    public function dashboard()
    {
        // Ensure session is active
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check login
        if (empty($_SESSION['is_logged_in']) || empty($_SESSION['auth_type'])) {
            header("Location: " . APP_URL . "/auth/login");
            exit;
        }

        $type = $_SESSION['auth_type'];
        $userId = $_SESSION['user_id'];
        $data = [
            'username' => $_SESSION['username'] ?? '',
            'full_name' => $_SESSION['full_name'] ?? '',
            'type' => $type
        ];

        // Handle Admin Dashboard
        if ($type === 'admin') {
            $this->bookingModel->autoCleanupExpiredBookings();
            $selectedYear = $_GET['year'] ?? date('Y');
            $data['selectedYear'] = $selectedYear;
            $data['availableYears'] = $this->bookingModel->getBookingYears();

            // Summary Cards
            $data['totalBookings'] = $this->bookingModel->getTotalBookings();
            $data['totalRooms'] = $this->roomModel->getTotalRooms();
            $data['totalRevenue'] = $this->bookingModel->getTotalRevenue();
            $data['pendingRevenue'] = $this->paymentModel->getPendingRevenue();
            $data['totalMessages'] = $this->contactModel->getTotalMessages();
            
            // Physical capacity for occupancy percentage
            $totalCapacity = $this->roomModel->getTotalPhysicalCapacity();
            $data['totalCapacity'] = $totalCapacity;

            // Analytics
            $data['popularRooms'] = $this->bookingModel->getPopularRooms();
            $forecastRaw = $this->bookingModel->getOccupancyForecast();
            $data['forecastLabels'] = array_column($forecastRaw, 'date');
            $data['forecastValues'] = array_column($forecastRaw, 'occupied_count');

            // Revenue Chart Data
            $monthlyRaw = $this->bookingModel->getMonthlyRevenue($selectedYear);
            $chartData = array_fill(1, 12, 0);
            foreach ($monthlyRaw as $row) {
                $chartData[(int)$row['month']] = (float)$row['total'];
            }
            $data['revenueChartValues'] = array_values($chartData);

            // Low Occupancy Logic
            $data['lowOccupancyAlert'] = false;
            foreach ($data['forecastValues'] as $occupied) {
                $rate = ($totalCapacity > 0) ? ($occupied / $totalCapacity) * 100 : 0;
                if ($rate < 20) {
                    $data['lowOccupancyAlert'] = true;
                    break;
                }
            }

            $data['unpaidBookings'] = $this->bookingModel->getUnpaidBookingsWithPaymentStatus(5);
            $data['recentMessages'] = $this->contactModel->getAllMessages();

            $this->view('admin/dashboard', $data);
            return;
        }

        // Handle Customer Dashboard
        if ($type === 'customer') {
            // Load booking data
            $bookingModel = $this->model('BookingModel');

            $allBookings = method_exists($bookingModel, 'getBookingsByCustomer')
                ? $bookingModel->getBookingsByCustomer($userId): [];
            
            if (!is_array($allBookings)) {
                $allBookings = []; // Safety check
            }
            
            $currentDate = date('Y-m-d');
            $upcomingBookings = [];
            $pastBookings = [];

            if (!empty($allBookings)) {
                foreach ($allBookings as $booking) {
                    if ($booking['check_out'] >= $currentDate) {
                        $upcomingBookings[] = $booking;
                    } else {
                        $pastBookings[] = $booking;
                    }
                }
            }

            // Pass the two sorted arrays instead of the single 'bookings' array
            $data['upcomingBookings'] = $upcomingBookings;
            $data['pastBookings'] = $pastBookings;

            $this->view('home/dashboard', $data);
            return;
        }

        // Safety fallback
        echo "Access Denied: Invalid user role.";
    }

    public function profile()
    {
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id']) || !isset($_SESSION['auth_type'])) {
            header("Location: " . APP_URL . "/auth/login");
            exit;
        }

        $userId = $_SESSION['user_id'];
        $role = $_SESSION['auth_type'];
        $data = [];

        if ($role === 'admin') {
            $adminModel = $this->model('AdminModel');
            $idToFetch = $_SESSION['admin_id'] ?? $userId;
            $data = $adminModel->getAdminProfile($idToFetch);
        } elseif ($role === 'customer') {
            $customerModel = $this->model('CustomerModel');
            $idToFetch = $_SESSION['customer_id'] ?? $userId;
            $data = $customerModel->getProfile($idToFetch);
        } else {
            echo "Access denied.";
            exit;
        }

        // Pass role and any flash messages to the view
        $data['role'] = $role;
        $data['success'] = Flash::get('success');
        $data['error'] = Flash::get('error');

        $this->view('profile', $data);
    }

    public function updateProfile()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
            header("Location: " . APP_URL . "/auth/login");
            exit;
        }

        $userId = $_SESSION['user_id'];
        $role = $_SESSION['auth_type'] ?? 'customer';

        $fullName = $_POST['full_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $phone = $_POST['phone'] ?? '';

        try {
            if ($role === 'admin') {
                $adminModel = $this->model('AdminModel');
                $adminId = $_SESSION['admin_id'] ?? $userId;
                
                $adminModel->updateProfileDetails($adminId, $username, $email);
                if (!empty($password)) {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $adminModel->updateAdminPassword($adminId, $hashed);
                }
            } else {
                $customerModel = $this->model('CustomerModel');
                $customerId = $_SESSION['customer_id'] ?? $userId;
                
                $customerModel->updateProfileDetails($customerId, $username, $fullName, $email, $phone); 

                if (!empty($password)) {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $customerModel->updateCustomerPassword($customerId, $hashed);
                }
            }

            Flash::set('success', "Profile details updated successfully!");
            
        } catch (\Exception $e) {
            Flash::set('error', "Update failed: " . $e->getMessage());
        }
        
        header("Location: " . APP_URL . "/profile");
        exit;
    }

    private function uploadImage($file)
    {
        $targetDir = __DIR__ . '/../public/uploads/';
        $targetFile = $targetDir . basename($file['name']);
        move_uploaded_file($file['tmp_name'], $targetFile);
        
        return '/uploads/' . basename($file['name']);
    }

    public function logout()
    {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Clear all session variables
        $_SESSION = [];

        // Destroy the session cookie if it exists
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        // Destroy the session
        session_destroy();

        // Flash message and redirect
        Flash::set('success', "Logged out successfully.");
        header("Location: " . APP_URL . "/auth/login");
        exit;
    }

    // Show forgot password form
    public function forgotPassword()
    {
        $this->view('auth/forgot-password');
    }

    // Handle forgot password submission
    public function forgotPasswordProcess()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $email = trim($_POST['email'] ?? '');

        if (!$email) {
            Flash::set('error', 'Please enter your username or email.');
            header("Location: " . APP_URL . "/auth/forgot-password");
            exit;
        }

        $passwordResetModel = $this->model('PasswordResetModel');

        // Check Admin first
        $user = $this->adminModel->getByEmail($email);
        $userType = 'admin';

        // If not admin, check customer
        if (!$user) {
            $user = $this->customerModel->getByEmail($email, false);
            $userType = 'customer';
        }

        if (!$user) {
            Flash::set('error', 'No account found with that username/email.');
            header("Location: " . APP_URL . "/auth/forgot-password");
            exit;
        }

        // Generate token
        $token = bin2hex(random_bytes(16));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Save token
        $passwordResetModel->saveToken($user['id'], $userType, $token, $expiresAt);

        // Send reset link (email here, can replace with WhatsApp)
        $resetLink = APP_URL . "/auth/reset-password?token=$token";
        $subject = "Password Reset Request";
        $message = "Click this link to reset your password (valid 1 hour): $resetLink";

        if (!empty($user['email'])) {
            mail($user['email'], $subject, $message);
        }

        Flash::set('success', 'Password reset link has been sent.');
        header("Location: " . APP_URL . "/auth/login");
        exit;
    }

    // Show reset password form
    public function resetPassword()
    {
        $token = $_GET['token'] ?? '';

        if (!$token) {
            Flash::set('error', 'Invalid password reset token.');
            header("Location: " . APP_URL . "/auth/login");
            exit;
        }

        $passwordResetModel = $this->model('PasswordResetModel');
        $resetRecord = $passwordResetModel->getByToken($token);

        if (!$resetRecord || strtotime($resetRecord['expires_at']) < time()) {
            Flash::set('error', 'Reset token is invalid or expired.');
            header("Location: " . APP_URL . "/auth/forgot-password");
            exit;
        }

        $this->view('auth/reset_password', ['token' => $token]);
    }

    // Process reset password submission
    public function resetPasswordProcess()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!$token || !$password || !$confirmPassword) {
            Flash::set('error', 'All fields are required.');
            header("Location: " . APP_URL . "/auth/reset-password?token=$token");
            exit;
        }

        if ($password !== $confirmPassword) {
            Flash::set('error', 'Passwords do not match.');
            header("Location: " . APP_URL . "/auth/reset-password?token=$token");
            exit;
        }

        $passwordResetModel = $this->model('PasswordResetModel');
        $resetRecord = $passwordResetModel->getByToken($token);

        if (!$resetRecord || strtotime($resetRecord['expires_at']) < time()) {
            Flash::set('error', 'Reset token is invalid or expired.');
            header("Location: " . APP_URL . "/auth/forgot-password");
            exit;
        }

        // Update password in the correct table
        $passwordResetModel->updatePassword($resetRecord['user_id'], $password, $resetRecord['user_type']);
        $passwordResetModel->deleteToken($token);

        Flash::set('success', 'Password has been reset successfully.');
        header("Location: " . APP_URL . "/auth/login");
        exit;
    }
}