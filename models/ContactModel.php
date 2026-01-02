<?php

class ContactModel extends Model
{
    public function saveContact($name, $email, $phone, $message)
    {
        $stmt = $this->db->prepare("INSERT INTO contacts (name, email, phone, message) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$name, $email, $phone, $message]);
    }

    // Get all active messages
    public function getAllMessages()
    {
        $stmt = $this->db->prepare("SELECT * FROM contacts");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalMessages()
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM contacts");
        $stmt->execute();

        return $stmt->fetchColumn();
    }

    // Get a single message by ID
    public function getMessageById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM contacts WHERE id = ?");
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getUnreadMessages($limit = 4)
    {
        $sql = "SELECT * FROM contact_messages 
                WHERE status = 'unread' 
                ORDER BY created_at DESC LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUnreadMessagesCount()
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM contact_messages WHERE status = 'unread'");
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function deleteMessage($id)
    {
        $stmt = $this->db->prepare("DELETE FROM contacts WHERE id = ?");
        return $stmt->execute([$id]);
    }
}