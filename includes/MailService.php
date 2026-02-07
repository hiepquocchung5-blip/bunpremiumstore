<?php
// includes/MailService.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Ensure Composer autoload is loaded

class MailService {
    
    private $mail;

    public function __construct() {
        $this->mail = new PHPMailer(true);
        // Server settings (Update with your cPanel/Gmail SMTP)
        $this->mail->isSMTP();
        $this->mail->Host       = 'mail.scottsub.com'; // e.g., smtp.gmail.com
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = 'noreply@scottsub.com';
        $this->mail->Password   = 'your_secure_password';
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $this->mail->Port       = 465;
        $this->mail->setFrom('noreply@scottsub.com', 'ScottSub Store');
        $this->mail->isHTML(true);
    }

    public function sendOrderConfirmation($userEmail, $userName, $orderId, $productName, $price) {
        try {
            $this->mail->addAddress($userEmail, $userName);
            $this->mail->Subject = "Order #$orderId Received - ScottSub";
            
            // Nice HTML Template
            $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f9f9f9; padding: 20px; border-radius: 10px;'>
                <h2 style='color: #2563eb;'>Thank You for Your Order!</h2>
                <p>Hi <strong>$userName</strong>,</p>
                <p>We have received your order and are currently verifying your payment. Once approved, you will receive your product details immediately.</p>
                
                <div style='background: #fff; padding: 15px; border-radius: 5px; border: 1px solid #ddd; margin: 20px 0;'>
                    <p><strong>Order ID:</strong> #$orderId</p>
                    <p><strong>Product:</strong> $productName</p>
                    <p><strong>Amount:</strong> " . number_format($price) . " Ks</p>
                </div>

                <p>You can track your status here: <a href='".BASE_URL."index.php?module=user&page=orders' style='color: #2563eb;'>My Orders</a></p>
                <p style='font-size: 12px; color: #888;'>If you have any questions, reply to this email or chat with us on the website.</p>
            </div>";

            $this->mail->Body = $body;
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mail Error: {$this->mail->ErrorInfo}");
            return false;
        }
    }

    public function sendAdminAlert($orderId, $price) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress('admin@scottsub.com'); // Your Admin Email
            $this->mail->Subject = "New Order #$orderId ($price Ks)";
            $this->mail->Body = "<h3>New Order Received</h3><p>Please check the admin panel to verify payment.</p><a href='".BASE_URL."admin/order_detail.php?id=$orderId'>Manage Order</a>";
            $this->mail->send();
        } catch (Exception $e) {}
    }
}
?>