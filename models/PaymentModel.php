<?php

class PaymentModel extends Model
{
    public function getAllPayments($offset = 0, $limit = 10, $search = '')
    {
        $searchQuery = "";
        if (!empty($search)) {
            $searchQuery = "WHERE b.booking_ref_no LIKE :search 
                        OR c.full_name LIKE :search 
                        OR p.payment_ref_no LIKE :search ";
        }

        // Join with customers table to get name, email, and phone
        $sql = "SELECT p.*, b.booking_ref_no, c.full_name 
                FROM payments p
                JOIN bookings b ON p.booking_id = b.id
                JOIN customers c ON b.customer_id = c.id
                $searchQuery
                ORDER BY p.created_at DESC
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

    public function getTotalPayments($search = '')
    {
        $searchQuery = "";
        if (!empty($search)) {
            $searchQuery = " WHERE b.booking_ref_no LIKE :search 
                        OR c.full_name LIKE :search 
                        OR p.payment_ref_no LIKE :search ";
        }

        $sql = "SELECT COUNT(*) as total 
                FROM payments p
                JOIN bookings b ON p.booking_id = b.id
                JOIN customers c ON b.customer_id = c.id
                $searchQuery";

        $stmt = $this->db->prepare($sql);
        if (!empty($search)) {
            $stmt->bindValue(':search', "%$search%");
        }
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['total'] : 0;
    }

    public function deletePayment($id)
    {
        $this->db->beginTransaction();

        try {

            $stmt = $this->db->prepare("DELETE FROM payments WHERE id = ?");
            $stmt->execute([$id]);

            $this->db->commit();

            return true;
        } catch (Exception $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    public function addPaymentReceipt($bookingId, $imagePath, $amount)
    {
        $relativeImagePath = str_replace('public/', '', $imagePath);
        $relativeImagePath = ltrim($relativeImagePath, '/');

        $sql = "INSERT INTO payments (booking_id, payment_ref_no, amount, receipt_image, payment_type, verified) 
                VALUES (?, ?, ?, ?, 'full', 'pending')";
        
        $paymentRef = "PAY-" . strtoupper(uniqid());
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([$bookingId, $paymentRef, $amount, $imagePath]);
    }

    public function updateVerificationStatus($paymentId, $status, $reason = '')
    {
        $sql = "UPDATE payments SET verified = ?, rejection_reason = ? WHERE id = ?";

        return $this->db->prepare($sql)->execute([$status, $reason, $paymentId]);
    }

    public function getPaymentById($id)
    {
        $sql = "SELECT 
                    p.*, 
                    b.booking_ref_no, 
                    b.total_amount, 
                    b.check_in, 
                    b.check_out, 
                    b.created_at AS booking_created_at, 
                    c.full_name, 
                    c.email,
                    (SELECT SUM(amount) FROM payments WHERE booking_id = b.id AND verified = 'approved') as total_paid_to_date
                FROM payments p
                JOIN bookings b ON p.booking_id = b.id
                JOIN customers c ON b.customer_id = c.id
                WHERE p.id = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getPendingRevenue()
    {
        $sql = "SELECT SUM(amount) as total FROM payments WHERE verified = 'pending'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['total'] ?: 0;
    }
}
