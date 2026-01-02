<?php

class MessageController extends Controller
{
    // Declare the model property
    private $contactModel;

    // Constructor to initialize the model
    public function __construct()
    {
        // Initialize the model
        $this->contactModel = $this->model('ContactModel');
    }

    // List all rooms
    public function index()
    {
        // Number of results per page
        $resultsPerPage = 10;
        $currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;

        // Calculate the offset for SQL query
        $offset = ($currentPage - 1) * $resultsPerPage;

        // Get the total number of bookings
        $totalRooms = $this->contactModel->getTotalMessages();
        $totalPages = ceil($totalRooms / $resultsPerPage);

        $contacts = $this->contactModel->getAllMessages($offset, $resultsPerPage);

        // Pass the pagination data to the view
        $this->view('admin/messages/index', [
            'contacts' => $contacts,
            'totalPages' => $totalPages,
            'currentPage' => $currentPage
        ]);
    }

    // view contact detail
    public function viewMessage($id)
    {
        // get the contact details by ID
        $message = $this->contactModel->getMessageById($id);

        if (!$message) {
            // If no contact found, redirect with an error message
            Flash::set('error', 'Message not found');
            header('Location: ' . APP_URL . '/admin/messages');
            exit;
        }

        require_once 'helpers/WhatsAppHelper.php';

        $whatsappLink = WhatsAppHelper::generateWhatsAppLink(
            $message['name'],
            $message['email'],
            $message['phone'],
            $message['message']
        );

        // Pass the message data to the view
        $this->view('admin/messages/view', [
            'message' => $message,
            'whatsappLink' => $whatsappLink
        ]);
    }

    // Delete messages
    public function delete($id)
    {
        $contact = $this->contactModel->getMessageById($id);

        if (!$contact) {
            Flash::set('error', 'Messages not found');
            header('Location: ' . APP_URL . '/admin/messages');
            exit;
        }

        $this->contactModel->deleteMessage($id);

        Flash::set('success', 'Messages deleted successfully');
        header('Location: ' . APP_URL . '/admin/messages');
        exit;
    }
}