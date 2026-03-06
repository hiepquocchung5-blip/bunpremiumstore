<?php
// modules/auth/verify.php

$token = isset($_GET['token']) ? $_GET['token'] : '';
$status = 'error'; 
$message = 'Invalid request.';

if ($token) {
    // 1. Check Token against DB
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE verify_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        // 2. Activate Account & Clear Token
        // We set is_verified = 1 and NULL the token so it can't be reused
        $update = $pdo->prepare("UPDATE users SET is_verified = 1, verify_token = NULL WHERE id = ?");
        
        if ($update->execute([$user['id']])) {
            $status = 'success';
            $message = "Welcome, <strong>" . htmlspecialchars($user['username']) . "</strong>! Your email has been successfully verified.";
        } else {
            $message = "Database error during activation. Please contact support.";
        }
    } else {
        $message = "This verification link is invalid or has already been used.";
    }
} else {
    $message = "No verification token provided.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Account - DigitalMarketplaceMM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #0f172a; color: white; font-family: 'Inter', sans-serif; }
        .glass { 
            background: rgba(30, 41, 59, 0.75); 
            backdrop-filter: blur(16px); 
            border: 1px solid rgba(255, 255, 255, 0.08); 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); 
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4 bg-[url('https://images.unsplash.com/photo-1550745165-9bc0b252726f?q=80&w=2070&auto=format&fit=crop')] bg-cover bg-center">
    
    <div class="absolute inset-0 bg-slate-900/90 backdrop-blur-sm"></div>

    <div class="w-full max-w-md glass p-8 rounded-2xl relative z-10 animate-fade-in-down border-t border-gray-700/50 text-center">
        
        <?php if ($status === 'success'): ?>
            <!-- Success State -->
            <div class="w-20 h-20 bg-green-500/10 rounded-full flex items-center justify-center mx-auto mb-6 ring-1 ring-green-500/30 shadow-[0_0_20px_rgba(34,197,94,0.3)]">
                <i class="fas fa-check text-4xl text-green-500"></i>
            </div>
            
            <h2 class="text-3xl font-bold text-white mb-2 tracking-tight">Verified!</h2>
            <p class="text-slate-400 mb-8 leading-relaxed"><?php echo $message; ?></p>
            
            <a href="index.php?module=auth&page=login" class="block w-full bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-500 hover:to-emerald-500 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-green-900/20 transform transition active:scale-[0.98]">
                Login to Continue
            </a>

        <?php else: ?>
            <!-- Error State -->
            <div class="w-20 h-20 bg-red-500/10 rounded-full flex items-center justify-center mx-auto mb-6 ring-1 ring-red-500/30 shadow-[0_0_20px_rgba(239,68,68,0.3)]">
                <i class="fas fa-times text-4xl text-red-500"></i>
            </div>
            
            <h2 class="text-3xl font-bold text-white mb-2 tracking-tight">Verification Failed</h2>
            <p class="text-slate-400 mb-8 leading-relaxed"><?php echo $message; ?></p>
            
            <div class="space-y-3">
                <a href="index.php?module=auth&page=verify_resend" class="block w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3.5 rounded-xl transition shadow-lg">
                    Resend Verification Link
                </a>
                <a href="index.php" class="block w-full bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium py-3.5 rounded-xl transition">
                    Return Home
                </a>
            </div>
        <?php endif; ?>

    </div>
</body>
</html><?php
// modules/auth/verify.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$token = isset($_GET['token']) ? $_GET['token'] : '';
$status = 'error'; 
$message = 'Invalid request.';
$user_email = '';

if ($token) {
    // 1. Check Token against DB
    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE verify_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        // 2. Activate Account & Clear Token
        $update = $pdo->prepare("UPDATE users SET is_verified = 1, verify_token = NULL WHERE id = ?");
        
        if ($update->execute([$user['id']])) {
            $status = 'success';
            $message = "Identity confirmed, <strong>" . htmlspecialchars($user['username']) . "</strong>. Your account is now fully operational.";
            $user_email = $user['email'];

            // 3. SEND HTML WELCOME EMAIL
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = $_ENV['MAIL_HOST'] ?? 'ps10.zwhhosting.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = $_ENV['MAIL_USER'];
                $mail->Password   = $_ENV['MAIL_PASS'];
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = $_ENV['MAIL_PORT'] ?? 465;
                
                $mail->setFrom($_ENV['MAIL_USER'], $_ENV['MAIL_FROM_NAME'] ?? 'DigitalMarketplaceMM');
                $mail->addAddress($user['email'], $user['username']);
                $mail->isHTML(true);
                $mail->Subject = "Welcome to DigitalMarketplaceMM! 🚀";

                $login_url = BASE_URL . "index.php?module=auth&page=login";
                
                // Futuristic HTML Email Template
                $htmlBody = "
                <div style='background-color:#0f172a; padding:40px 20px; font-family:\"Segoe UI\", Tahoma, Geneva, Verdana, sans-serif; color:#e2e8f0;'>
                    <div style='max-width:600px; margin:0 auto; background-color:#1e293b; border-radius:16px; overflow:hidden; border:1px solid rgba(0, 240, 255, 0.2); box-shadow:0 10px 25px rgba(0,0,0,0.5);'>
                        
                        <div style='text-align:center; padding:30px; background-color:#0f172a; border-bottom:2px solid #00f0ff;'>
                            <h1 style='color:#ffffff; margin:0; font-size:28px; letter-spacing:1px;'>Digital<span style='color:#00f0ff;'>MM</span></h1>
                        </div>
                        
                        <div style='padding:40px 30px; text-align:center;'>
                            <h2 style='color:#ffffff; font-size:24px; margin-bottom:15px;'>Verification Successful! ✅</h2>
                            <p style='color:#94a3b8; font-size:16px; line-height:1.6; margin-bottom:30px;'>
                                Hello <strong>{$user['username']}</strong>,<br><br>
                                Your email has been successfully verified. You are now officially part of Myanmar's premier digital marketplace. You can now access premium game keys, software, and exclusive reseller discounts.
                            </p>
                            
                            <a href='{$login_url}' style='display:inline-block; background:linear-gradient(90deg, #2563eb, #00f0ff); color:#0f172a; text-decoration:none; padding:14px 32px; border-radius:8px; font-weight:bold; font-size:16px; text-transform:uppercase; letter-spacing:1px;'>Access Portal</a>
                        </div>
                        
                        <div style='padding:20px; text-align:center; background-color:#0f172a; color:#64748b; font-size:12px; border-top:1px solid rgba(255,255,255,0.05);'>
                            <p style='margin:0;'>&copy; " . date('Y') . " DigitalMarketplaceMM. All rights reserved.</p>
                            <p style='margin:5px 0 0 0;'>Secure Automated System</p>
                        </div>
                    </div>
                </div>";

                $mail->Body = $htmlBody;
                $mail->send();
            } catch (Exception $e) {
                // Silently log error so user still sees the success screen
                error_log("Welcome Email Error: " . $e->getMessage());
            }
        } else {
            $message = "Database error during activation. Please contact support.";
        }
    } else {
        $message = "This verification link is invalid or has already been used.";
    }
} else {
    $message = "No verification token provided.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Verify Account - DigitalMarketplaceMM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            background: #0f172a; 
            color: white; 
            font-family: 'Inter', sans-serif; 
            min-height: 100vh;
        }
        .glass { 
            background: rgba(15, 23, 42, 0.85); 
            backdrop-filter: blur(20px); 
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(0, 240, 255, 0.15); 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 30px rgba(0, 240, 255, 0.05); 
        }
        /* Abstract Background Animations */
        @keyframes blob {
            0% { transform: translate(0px, 0px) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
            100% { transform: translate(0px, 0px) scale(1); }
        }
        .animate-blob { animation: blob 7s infinite; }
        .animation-delay-2000 { animation-delay: 2s; }
        .animation-delay-4000 { animation-delay: 4s; }
    </style>
</head>
<body class="flex items-center justify-center relative overflow-hidden px-4 py-8 md:p-4">
    
    <!-- Animated Cyberpunk Background -->
    <div class="fixed inset-0 w-full h-full bg-slate-950 -z-20"></div>
    <div class="fixed top-0 -left-4 w-72 h-72 bg-blue-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-40 animate-blob -z-10"></div>
    <div class="fixed top-0 -right-4 w-72 h-72 bg-[#00f0ff] rounded-full mix-blend-multiply filter blur-[128px] opacity-30 animate-blob animation-delay-2000 -z-10"></div>
    <div class="fixed -bottom-8 left-20 w-72 h-72 bg-purple-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-30 animate-blob animation-delay-4000 -z-10"></div>

    <div class="w-full max-w-md glass p-8 md:p-10 rounded-3xl relative z-10 animate-fade-in-down text-center transform transition-all">
        
        <?php if ($status === 'success'): ?>
            <!-- Success State -->
            <div class="w-24 h-24 bg-green-500/10 border border-green-500/30 rounded-full flex items-center justify-center mx-auto mb-8 shadow-[0_0_30px_rgba(34,197,94,0.3)] relative">
                <div class="absolute inset-0 bg-green-500/20 rounded-full animate-ping opacity-50"></div>
                <i class="fas fa-shield-check text-5xl text-green-400 relative z-10"></i>
            </div>
            
            <h2 class="text-3xl font-black text-white mb-3 tracking-tight">Access Granted</h2>
            <p class="text-slate-400 mb-8 leading-relaxed text-sm"><?php echo $message; ?></p>
            
            <div class="space-y-4">
                <a href="index.php?module=auth&page=login" class="block w-full bg-gradient-to-r from-blue-600 to-[#00f0ff] hover:from-blue-500 hover:to-[#00f0ff] text-slate-900 font-black py-4 rounded-xl shadow-[0_0_20px_rgba(0,240,255,0.3)] transform transition active:scale-[0.98] text-sm uppercase tracking-widest flex items-center justify-center gap-2">
                    <span>Initialize Login</span>
                    <i class="fas fa-sign-in-alt"></i>
                </a>
                <p class="text-[10px] text-slate-500 font-mono"><i class="fas fa-envelope text-green-400 mr-1"></i> A welcome email has been dispatched.</p>
            </div>

        <?php else: ?>
            <!-- Error State -->
            <div class="w-24 h-24 bg-red-500/10 border border-red-500/30 rounded-full flex items-center justify-center mx-auto mb-8 shadow-[0_0_30px_rgba(239,68,68,0.3)]">
                <i class="fas fa-times-hexagon text-5xl text-red-500"></i>
            </div>
            
            <h2 class="text-3xl font-black text-white mb-3 tracking-tight">Verification Failed</h2>
            <p class="text-slate-400 mb-8 leading-relaxed text-sm"><?php echo $message; ?></p>
            
            <div class="space-y-4">
                <a href="index.php?module=auth&page=verify_resend" class="block w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3.5 rounded-xl transition shadow-lg text-sm">
                    Request New Link
                </a>
                <a href="index.php" class="block w-full bg-slate-800 hover:bg-slate-700 border border-slate-600 text-slate-300 font-bold py-3.5 rounded-xl transition text-sm">
                    Return to Hub
                </a>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>