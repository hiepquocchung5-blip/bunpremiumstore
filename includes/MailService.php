<?php
// includes/MailService.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

class MailService {
    
    private $mail;

    public function __construct() {
        $this->mail = new PHPMailer(true);
        // Server settings
        $this->mail->isSMTP();
        $this->mail->Host       = 'mail.digitalmarketplacemm.com'; 
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = 'noreply@digitalmarketplacemm.com';
        $this->mail->Password   = 'your_secure_password'; // Update this!
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $this->mail->Port       = 465;
        $this->mail->setFrom('noreply@digitalmarketplacemm.com', 'DigitalMarketplaceMM');
        $this->mail->isHTML(true);
    }

    // ... Existing Order methods ...

    public function sendOrderConfirmation($userEmail, $userName, $orderId, $productName, $price) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($userEmail, $userName);
            $this->mail->Subject = "Order #$orderId - DigitalMarketplaceMM";
            $body = "
            <div style='font-family: Arial, sans-serif; background: #f9f9f9; padding: 20px;'>
                <h2 style='color: #2563eb;'>Order Received</h2>
                <p>Hi $userName,</p>
                <p>We are verifying your payment for <strong>$productName</strong>.</p>
                <p>Amount: <strong>" . number_format($price) . " Ks</strong></p>
                <a href='".BASE_URL."index.php?module=user&page=orders'>View Order</a>
            </div>";
            $this->mail->Body = $body;
            $this->mail->send();
            return true;
        } catch (Exception $e) { return false; }
    }

    public function sendAdminAlert($orderId, $price) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress('admin@digitalmarketplacemm.com');
            $this->mail->Subject = "New Order #$orderId";
            $this->mail->Body = "New order received. Value: $price Ks. <a href='".ADMIN_URL."order_detail.php?id=$orderId'>Check Admin</a>";
            $this->mail->send();
        } catch (Exception $e) {}
    }

    // --- NEW METHODS ---

    public function sendVerificationEmail($email, $token) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($email);
            $this->mail->Subject = "Verify your Email - DigitalMarketplaceMM";
            
            $link = BASE_URL . "index.php?module=auth&page=verify&token=" . $token;
            
            $body = "
            <div style='font-family: Arial, sans-serif; padding: 20px; text-align: center;'>
                <h2 style='color: #2563eb;'>Welcome to DMMM!</h2>
                <p>Please click the button below to verify your email address.</p>
                <a href='$link' style='background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0;'>Verify Email</a>
                <p style='color: #888; font-size: 12px;'>Or copy this link: $link</p>
            </div>";

            $this->mail->Body = $body;
            $this->mail->send();
            return true;
        } catch (Exception $e) { return false; }
    }

    public function sendPasswordReset($email, $token) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($email);
            $this->mail->Subject = "Reset Password Request";
            
            $link = BASE_URL . "index.php?module=auth&page=reset_password&token=" . $token;
            
            $body = "
            <div style='font-family: Arial, sans-serif; padding: 20px;'>
                <h3>Password Reset</h3>
                <p>Someone requested a password reset for your account. If this was you, click below:</p>
                <a href='$link' style='color: #2563eb;'>Reset Password</a>
                <p>If you didn't ask for this, you can ignore this email.</p>
            </div>";

            $this->mail->Body = $body;
            $this->mail->send();
            return true;
        } catch (Exception $e) { return false; }
    }
}
?>