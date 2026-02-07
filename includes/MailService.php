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
        $this->mail->Username   = 'no-reply@digitalmarketplacemm.com'; // Updated
        $this->mail->Password   = 'S]VXk?_o.RG[r3=y'; // Updated
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $this->mail->Port       = 465;
        
        // Sender Info
        $this->mail->setFrom('no-reply@digitalmarketplacemm.com', 'DigitalMarketplaceMM');
        $this->mail->isHTML(true);
        $this->mail->CharSet = 'UTF-8';
    }

    /**
     * --------------------------------------------------------------------------
     * PUBLIC SENDING METHODS
     * --------------------------------------------------------------------------
     */

    // 1. Order Confirmation (To User)
    public function sendOrderConfirmation($userEmail, $userName, $orderId, $productName, $price) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($userEmail, $userName);
            $this->mail->Subject = "Order #$orderId Confirmed - DigitalMarketplaceMM";
            
            $content = "
                <h2 style='color: #1e293b; font-size: 24px; margin-bottom: 10px;'>Order Received! 📦</h2>
                <p style='color: #64748b; font-size: 16px; line-height: 1.6;'>Hi <strong>$userName</strong>, thank you for your purchase. We are currently verifying your payment and will deliver your item shortly via the Order Chat.</p>
                
                <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f8fafc; border-radius: 8px; margin: 20px 0; padding: 15px; border: 1px solid #e2e8f0;'>
                    <tr>
                        <td style='padding: 8px 0; color: #64748b;'>Order ID:</td>
                        <td style='padding: 8px 0; color: #1e293b; font-weight: bold; text-align: right;'>#$orderId</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #64748b; border-bottom: 1px solid #e2e8f0;'>Product:</td>
                        <td style='padding: 8px 0; color: #1e293b; font-weight: bold; text-align: right; border-bottom: 1px solid #e2e8f0;'>$productName</td>
                    </tr>
                    <tr>
                        <td style='padding: 12px 0 0 0; color: #64748b; font-size: 18px;'>Total:</td>
                        <td style='padding: 12px 0 0 0; color: #10b981; font-weight: bold; font-size: 18px; text-align: right;'>" . number_format($price) . " Ks</td>
                    </tr>
                </table>

                <div style='text-align: center; margin-top: 30px;'>
                    <a href='".BASE_URL."index.php?module=user&page=orders' style='background-color: #2563eb; color: #ffffff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold; display: inline-block;'>Track Order</a>
                </div>
            ";

            $this->mail->Body = $this->getHtmlTemplate($content);
            $this->mail->send();
            return true;
        } catch (Exception $e) { return false; }
    }

    // 2. New Order Alert (To Admin)
    public function sendAdminAlert($orderId, $price) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress('admin@digitalmarketplacemm.com'); // Admin Email
            $this->mail->Subject = "New Order #$orderId ($price Ks)";
            
            $content = "
                <h2 style='color: #1e293b;'>New Sale! 💰</h2>
                <p style='color: #64748b;'>A new order has been placed waiting for verification.</p>
                <p style='font-size: 20px; font-weight: bold; color: #10b981; margin: 10px 0;'>Amount: " . number_format($price) . " Ks</p>
                <p><a href='".ADMIN_URL."order_detail.php?id=$orderId' style='color: #2563eb; text-decoration: underline;'>Manage Order in Admin Panel</a></p>
            ";

            $this->mail->Body = $this->getHtmlTemplate($content);
            $this->mail->send();
        } catch (Exception $e) {}
    }

    // 3. Email Verification
    public function sendVerificationEmail($email, $token) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($email);
            $this->mail->Subject = "Verify Your Email - DigitalMarketplaceMM";
            
            $link = BASE_URL . "index.php?module=auth&page=verify&token=" . $token;
            
            $content = "
                <h2 style='color: #1e293b; text-align: center;'>Welcome to DMMM! 👋</h2>
                <p style='color: #64748b; text-align: center;'>Please confirm your email address to activate your account and start shopping.</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$link' style='background-color: #2563eb; color: #ffffff; padding: 14px 28px; border-radius: 50px; text-decoration: none; font-weight: bold; font-size: 16px; box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2);'>Verify My Account</a>
                </div>
                
                <p style='color: #94a3b8; font-size: 12px; text-align: center;'>If the button doesn't work, copy this link:<br><a href='$link' style='color: #2563eb;'>$link</a></p>
            ";

            $this->mail->Body = $this->getHtmlTemplate($content);
            $this->mail->send();
            return true;
        } catch (Exception $e) { return false; }
    }

    // 4. Password Reset
    public function sendPasswordReset($email, $token) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($email);
            $this->mail->Subject = "Reset Your Password";
            
            $link = BASE_URL . "index.php?module=auth&page=reset_password&token=" . $token;
            
            $content = "
                <h2 style='color: #1e293b;'>Password Reset Request 🔒</h2>
                <p style='color: #64748b;'>We received a request to reset your password. If this was you, click the button below:</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$link' style='background-color: #ef4444; color: #ffffff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold;'>Reset Password</a>
                </div>
                
                <p style='color: #94a3b8; font-size: 13px;'>If you didn't ask for this, you can safely ignore this email.</p>
            ";

            $this->mail->Body = $this->getHtmlTemplate($content);
            $this->mail->send();
            return true;
        } catch (Exception $e) { return false; }
    }

    /**
     * --------------------------------------------------------------------------
     * HTML TEMPLATE ENGINE
     * Wraps content in a responsive, styled container.
     * --------------------------------------------------------------------------
     */
    private function getHtmlTemplate($content) {
        $year = date('Y');
        $homeUrl = BASE_URL;
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f1f5f9; }
                .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
                .header { background-color: #1e293b; padding: 25px; text-align: center; }
                .header h1 { margin: 0; color: #ffffff; font-size: 20px; letter-spacing: 1px; }
                .header span { color: #3b82f6; }
                .content { padding: 40px 30px; color: #334155; line-height: 1.6; }
                .footer { background-color: #f8fafc; padding: 20px; text-align: center; color: #94a3b8; font-size: 12px; border-top: 1px solid #e2e8f0; }
                .footer a { color: #64748b; text-decoration: none; margin: 0 5px; }
                .footer a:hover { text-decoration: underline; }
            </style>
        </head>
        <body>
            <div style='padding: 40px 0;'>
                <div class='container'>
                    <!-- Header -->
                    <div class='header'>
                        <h1>DigitalMarketplace<span>MM</span></h1>
                    </div>
                    
                    <!-- Main Content -->
                    <div class='content'>
                        $content
                    </div>
                    
                    <!-- Footer -->
                    <div class='footer'>
                        <p>&copy; $year DigitalMarketplaceMM. All rights reserved.</p>
                        <p>
                            <a href='{$homeUrl}'>Visit Store</a> • 
                            <a href='{$homeUrl}index.php?module=info&page=support'>Support</a> • 
                            <a href='{$homeUrl}index.php?module=info&page=privacy'>Privacy</a>
                        </p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
?>