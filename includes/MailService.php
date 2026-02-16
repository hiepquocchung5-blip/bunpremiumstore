<?php
// includes/MailService.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
// use PHPMailer\PHPMailer\SMTP;

// Ensure Composer autoloader is loaded
require_once __DIR__ . '/../vendor/autoload.php';

class MailService {
    
    private $mail;

    public function __construct() {
        $this->mail = new PHPMailer(true);
        
        try {
            // Server Settings from .env
            // $this->mail->SMTPDebug = SMTP::DEBUG_OFF; // Enable verbose debug output if needed
            $this->mail->isSMTP();
            $this->mail->Host       = $_ENV['MAIL_HOST'] ?? 'ps10.zwhhosting.com';
            $this->mail->SMTPAuth   = true;
            $this->mail->Username   = $_ENV['MAIL_USER'] ?? 'no-reply@digitalmarketplacemm.com';
            $this->mail->Password   = $_ENV['MAIL_PASS'] ?? '';
            
            // Security Settings (Port 465 usually requires SMTPS)
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
            $this->mail->Port       = $_ENV['MAIL_PORT'] ?? 465;
            
            // Sender Info
            $fromName = $_ENV['MAIL_FROM_NAME'] ?? 'DigitalMarketplaceMM';
            $this->mail->setFrom($_ENV['MAIL_USER'], $fromName);
            
            // Character Set
            $this->mail->CharSet = 'UTF-8';
            $this->mail->isHTML(true);
            
        } catch (Exception $e) {
            error_log("Mail Constructor Error: " . $e->getMessage());
        }
    }

    /**
     * 1. Send Order Confirmation (To Customer)
     */
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
                        <td style='padding: 12px 0 0 0; color: #64748b; font-size: 18px;'>Total Paid:</td>
                        <td style='padding: 12px 0 0 0; color: #10b981; font-weight: bold; font-size: 18px; text-align: right;'>" . number_format($price) . " Ks</td>
                    </tr>
                </table>

                <div style='text-align: center; margin-top: 30px;'>
                    <a href='".BASE_URL."index.php?module=user&page=orders&view_chat=$orderId' style='background-color: #2563eb; color: #ffffff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold; display: inline-block;'>View Order & Chat</a>
                </div>
            ";

            $this->mail->Body = $this->getHtmlTemplate($content);
            $this->mail->send();
            return true;
        } catch (Exception $e) { 
            error_log("Send Order Error: " . $this->mail->ErrorInfo);
            return false; 
        }
    }

    /**
     * 2. Send New Order Alert (To Admin)
     */
    public function sendAdminAlert($orderId, $price) {
        try {
            // Admin email (can be set in .env or hardcoded)
            $adminEmail = 'admin@digitalmarketplacemm.com'; 
            
            $this->mail->clearAddresses();
            $this->mail->addAddress($adminEmail, 'Admin');
            $this->mail->Subject = "New Sale! Order #$orderId ($price Ks)";
            
            $content = "
                <h2 style='color: #1e293b;'>New Order Received 💰</h2>
                <p style='color: #64748b;'>A new order has been placed and is waiting for manual verification.</p>
                <div style='background-color: #ecfdf5; border: 1px solid #10b981; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                    <p style='margin:0; font-size: 20px; font-weight: bold; color: #047857;'>Amount: " . number_format($price) . " Ks</p>
                </div>
                <p><a href='".ADMIN_URL."index.php?page=order_detail&id=$orderId' style='color: #2563eb; text-decoration: underline; font-weight: bold;'>Process Order in Admin Panel &rarr;</a></p>
            ";

            $this->mail->Body = $this->getHtmlTemplate($content);
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 3. Send Email Verification Link
     */
    public function sendVerificationEmail($email, $token) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($email);
            $this->mail->Subject = "Verify Your Email - DigitalMarketplaceMM";
            
            $link = BASE_URL . "index.php?module=auth&page=verify&token=" . $token;
            
            $content = "
                <h2 style='color: #1e293b; text-align: center;'>Welcome to DMMM! 👋</h2>
                <p style='color: #64748b; text-align: center;'>Please confirm your email address to activate your account and start shopping for premium digital goods.</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$link' style='background-color: #2563eb; color: #ffffff; padding: 14px 28px; border-radius: 50px; text-decoration: none; font-weight: bold; font-size: 16px; box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2);'>Verify My Account</a>
                </div>
                
                <p style='color: #94a3b8; font-size: 12px; text-align: center;'>If the button doesn't work, copy this link:<br><a href='$link' style='color: #2563eb;'>$link</a></p>
            ";

            $this->mail->Body = $this->getHtmlTemplate($content);
            $this->mail->send();
            return true;
        } catch (Exception $e) { 
            error_log("Send Verify Error: " . $this->mail->ErrorInfo);
            return false; 
        }
    }

    /**
     * 4. Send Password Reset Link
     */
    public function sendPasswordReset($email, $token) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($email);
            $this->mail->Subject = "Reset Your Password - DigitalMarketplaceMM";
            
            $link = BASE_URL . "index.php?module=auth&page=reset_password&token=" . $token;
            
            $content = "
                <h2 style='color: #1e293b;'>Password Reset Request 🔒</h2>
                <p style='color: #64748b;'>We received a request to reset your password. If this was you, click the button below to set a new password:</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$link' style='background-color: #ef4444; color: #ffffff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold;'>Reset Password</a>
                </div>
                
                <p style='color: #94a3b8; font-size: 13px;'>If you didn't ask for this, you can safely ignore this email. Your account remains secure.</p>
            ";

            $this->mail->Body = $this->getHtmlTemplate($content);
            $this->mail->send();
            return true;
        } catch (Exception $e) { return false; }
    }

    /**
     * --------------------------------------------------------------------------
     * HTML TEMPLATE ENGINE
     * Wraps content in a branded, responsive container.
     * --------------------------------------------------------------------------
     */
    private function getHtmlTemplate($content) {
        $year = date('Y');
        $homeUrl = BASE_URL;
        $logoUrl = BASE_URL . 'assets/images/logo.png'; // Ensure you have a logo here or remove img tag
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f1f5f9; }
                .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
                .header { background-color: #0f172a; padding: 30px 20px; text-align: center; border-bottom: 4px solid #2563eb; }
                .header h1 { margin: 0; color: #ffffff; font-size: 22px; letter-spacing: 1px; font-weight: 800; text-transform: uppercase; }
                .header span { color: #3b82f6; }
                .content { padding: 40px 30px; color: #334155; line-height: 1.6; }
                .footer { background-color: #f8fafc; padding: 20px; text-align: center; color: #94a3b8; font-size: 12px; border-top: 1px solid #e2e8f0; }
                .footer a { color: #64748b; text-decoration: none; margin: 0 8px; }
                .footer a:hover { text-decoration: underline; color: #2563eb; }
                .socials { margin-top: 10px; }
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
                        <p>Yangon, Myanmar • Premium Digital Goods</p>
                        <div style='margin-top: 15px;'>
                            <a href='{$homeUrl}'>Visit Store</a> • 
                            <a href='{$homeUrl}index.php?module=info&page=support'>Support</a> • 
                            <a href='{$homeUrl}index.php?module=info&page=privacy'>Privacy</a>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
?>