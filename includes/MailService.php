<?php
// includes/MailService.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

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
            
            // Explicitly map your new variables
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
                <h2 style='color: #ffffff; font-size: 24px; margin-bottom: 10px; font-weight: 900;'>Order Received! 📦</h2>
                <p style='color: #94a3b8; font-size: 16px; line-height: 1.6;'>Hi <strong style='color:#00f0ff;'>$userName</strong>, thank you for your purchase. We are currently verifying your payment and will deliver your item shortly via the Order Chat.</p>
                
                <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #0f172a; border-radius: 8px; margin: 20px 0; padding: 15px; border: 1px solid #1e293b;'>
                    <tr>
                        <td style='padding: 8px 0; color: #64748b;'>Order ID:</td>
                        <td style='padding: 8px 0; color: #00f0ff; font-weight: bold; text-align: right; font-family: monospace;'>#$orderId</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #64748b; border-bottom: 1px solid #1e293b;'>Product:</td>
                        <td style='padding: 8px 0; color: #ffffff; font-weight: bold; text-align: right; border-bottom: 1px solid #1e293b;'>$productName</td>
                    </tr>
                    <tr>
                        <td style='padding: 12px 0 0 0; color: #64748b; font-size: 18px;'>Total Paid:</td>
                        <td style='padding: 12px 0 0 0; color: #10b981; font-weight: bold; font-size: 18px; text-align: right; font-family: monospace;'>" . number_format($price) . " Ks</td>
                    </tr>
                </table>

                <div style='text-align: center; margin-top: 30px;'>
                    <a href='".BASE_URL."index.php?module=user&page=orders&view_chat=$orderId' style='text-decoration: none;'>
                        <button style='cursor: pointer; background: linear-gradient(90deg, #2563eb, #00f0ff); color: #0f172a; padding: 16px 32px; border-radius: 8px; border: 1px solid #00f0ff; font-weight: 900; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 0 15px rgba(0, 240, 255, 0.3);'>Open Fulfillment Terminal</button>
                    </a>
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
                <h2 style='color: #ffffff; font-weight: 900;'>New Order Received 💰</h2>
                <p style='color: #94a3b8;'>A new order has been placed and is waiting for manual verification.</p>
                <div style='background-color: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                    <p style='margin:0; font-size: 20px; font-weight: bold; color: #34d399; font-family: monospace;'>Amount: " . number_format($price) . " Ks</p>
                </div>
                
                <div style='text-align: center; margin-top: 30px;'>
                    <a href='".ADMIN_URL."index.php?page=order_detail&id=$orderId' style='text-decoration: none;'>
                        <button style='cursor: pointer; background: linear-gradient(90deg, #10b981, #34d399); color: #0f172a; padding: 16px 32px; border-radius: 8px; border: 1px solid #34d399; font-weight: 900; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 0 15px rgba(52, 211, 153, 0.3);'>Process Order in Matrix</button>
                    </a>
                </div>
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
            $this->mail->Subject = "Verify Your Identity - DigitalMarketplaceMM";
            
            $link = BASE_URL . "index.php?module=auth&page=verify&token=" . $token;
            
            $content = "
                <h2 style='color: #ffffff; text-align: center; font-weight: 900;'>Welcome to DMMM! 👋</h2>
                <p style='color: #94a3b8; text-align: center;'>Please confirm your email address to activate your account and start shopping for premium digital goods.</p>
                
                <div style='text-align: center; margin: 35px 0;'>
                    <a href='$link' style='text-decoration: none;'>
                        <button style='cursor: pointer; background: linear-gradient(90deg, #2563eb, #00f0ff); color: #0f172a; padding: 16px 32px; border-radius: 8px; border: 1px solid #00f0ff; font-weight: 900; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 0 15px rgba(0, 240, 255, 0.4);'>Verify My Account</button>
                    </a>
                </div>
                
                <div style='background-color: rgba(0, 240, 255, 0.05); padding: 15px; border-radius: 8px; border-left: 4px solid #00f0ff;'>
                    <p style='color: #64748b; font-size: 11px; text-align: center; margin: 0; word-break: break-all;'>If the button doesn't work, copy this link into your browser:<br><br><a href='$link' style='color: #00f0ff; text-decoration: underline;'>$link</a></p>
                </div>
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
            $this->mail->Subject = "Reset Your Master Key - DigitalMarketplaceMM";
            
            $link = BASE_URL . "index.php?module=auth&page=reset_password&token=" . $token;
            
            $content = "
                <h2 style='color: #ffffff; font-weight: 900;'>Master Key Reset Request 🔒</h2>
                <p style='color: #94a3b8;'>We received a request to reset your password. If this was you, execute the command below to set a new master key:</p>
                
                <div style='text-align: center; margin: 35px 0;'>
                    <a href='$link' style='text-decoration: none;'>
                        <button style='cursor: pointer; background: linear-gradient(90deg, #dc2626, #ef4444); color: #ffffff; padding: 16px 32px; border-radius: 8px; border: 1px solid #f87171; font-weight: 900; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 0 15px rgba(239, 68, 68, 0.4);'>Reset Password</button>
                    </a>
                </div>
                
                <p style='color: #64748b; font-size: 12px; text-align: center;'>If you didn't initiate this protocol, you can safely ignore this communication. Your account remains secure.</p>
            ";

            $this->mail->Body = $this->getHtmlTemplate($content);
            $this->mail->send();
            return true;
        } catch (Exception $e) { return false; }
    }

    /**
     * 5. Send Google Auth Generated Password
     */
    public function sendGoogleAuthPassword($email, $name, $password) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($email, $name);
            $this->mail->Subject = "Your New Account Credentials - DigitalMarketplaceMM";
            
            $login_url = BASE_URL . "index.php?module=auth&page=login";
            
            $content = "
                <h2 style='color: #ffffff; font-weight: 900;'>Welcome, $name! 🚀</h2>
                <p style='color: #94a3b8;'>An account has been automatically created for you using Google Sign-In.</p>
                
                <div style='background-color: rgba(0, 240, 255, 0.05); padding: 20px; border-radius: 8px; border-left: 4px solid #00f0ff; margin: 20px 0;'>
                    <p style='margin: 0 0 10px 0; color: #e2e8f0; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;'><strong>Login Credentials:</strong></p>
                    <p style='margin: 0; font-family: monospace; color: #00f0ff; font-size: 16px;'>
                        Email: <span style='color: #ffffff;'>$email</span><br>
                        Generated Password: <strong>$password</strong>
                    </p>
                </div>
                
                <p style='color: #64748b; font-size: 12px;'>You can continue logging in with Google, or use these credentials to sign in manually. We highly recommend updating this password in your Account Settings.</p>
                
                <div style='text-align: center; margin-top: 35px;'>
                    <a href='$login_url' style='text-decoration: none;'>
                        <button style='cursor: pointer; background: linear-gradient(90deg, #2563eb, #00f0ff); color: #0f172a; padding: 16px 32px; border-radius: 8px; border: 1px solid #00f0ff; font-weight: 900; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 0 15px rgba(0, 240, 255, 0.4);'>Access Portal</button>
                    </a>
                </div>
            ";

            $this->mail->Body = $this->getHtmlTemplate($content);
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Send Google Auth Password Error: " . $this->mail->ErrorInfo);
            return false;
        }
    }

    /**
     * --------------------------------------------------------------------------
     * HTML TEMPLATE ENGINE
     * Futuristic Dark Mode "Neon Tech" Container
     * --------------------------------------------------------------------------
     */
    private function getHtmlTemplate($content) {
        $year = date('Y');
        $homeUrl = BASE_URL;
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #020617; }
                .container { max-width: 600px; margin: 0 auto; background-color: #0f172a; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.8); border: 1px solid rgba(0, 240, 255, 0.2); }
                .header { background-color: #020617; padding: 30px 20px; text-align: center; border-bottom: 2px solid #00f0ff; }
                .header h1 { margin: 0; color: #ffffff; font-size: 24px; letter-spacing: 1px; font-weight: 900; }
                .header span { color: #00f0ff; }
                .content { padding: 40px 30px; color: #e2e8f0; line-height: 1.6; }
                .footer { background-color: #020617; padding: 25px 20px; text-align: center; color: #475569; font-size: 11px; border-top: 1px solid rgba(255, 255, 255, 0.05); }
                .footer a { color: #64748b; text-decoration: none; margin: 0 8px; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; }
                .footer a:hover { color: #00f0ff; }
            </style>
        </head>
        <body>
            <div style='padding: 40px 10px; background-color: #020617;'>
                <div class='container'>
                    <!-- Header -->
                    <div class='header'>
                        <h1>Digital<span style='color: #00f0ff;'>MM</span></h1>
                    </div>
                    
                    <!-- Main Content -->
                    <div class='content'>
                        $content
                    </div>
                    
                    <!-- Footer -->
                    <div class='footer'>
                        <p style='margin-bottom: 5px;'>&copy; $year DigitalMarketplaceMM. All rights reserved.</p>
                        <p style='margin-bottom: 15px;'>Yangon, Myanmar • Premium Digital Goods</p>
                        <div>
                            <a href='{$homeUrl}'>Matrix Hub</a> • 
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