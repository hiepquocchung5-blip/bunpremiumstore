<?php
// modules/auth/verify_resend.php
require_once 'includes/MailService.php';

if (is_logged_in()) redirect('index.php');

$error = '';
$success = '';
$email_prefill = isset($_GET['email']) ? htmlspecialchars(urldecode($_GET['email'])) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $session_token = $_SESSION['csrf_token'] ?? '';
    $post_token = $_POST['csrf_token'] ?? '';

    if (empty($session_token) || !hash_equals($session_token, $post_token)) {
        $error = "Session expired. Please refresh.";
    } else {
        $email = trim($_POST['email']);
        
        // Check user status
        $stmt = $pdo->prepare("SELECT id, username, is_verified FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            if ($user['is_verified'] == 1) {
                $success = "Account already verified. <a href='index.php?module=auth&page=login' class='underline'>Login here</a>.";
            } else {
                // Generate new token
                $token = bin2hex(random_bytes(32));
                $update = $pdo->prepare("UPDATE users SET verify_token = ? WHERE id = ?");
                
                if ($update->execute([$token, $user['id']])) {
                    // Send Email
                    $mailer = new MailService();
                    if ($mailer->sendVerificationEmail($email, $token)) {
                        $success = "Verification link sent! Check your inbox.";
                    } else {
                        $error = "Failed to send email. System error.";
                    }
                }
            }
        } else {
            $error = "Email address not found.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Resend Verification - DigitalMarketplaceMM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #111827; color: white; font-family: 'Inter', sans-serif; }
        .glass { background: rgba(31, 41, 55, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.08); box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37); }
        .input-group { position: relative; }
        .input-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #9ca3af; transition: color 0.3s; }
        .input-field { padding-left: 2.75rem; transition: all 0.3s ease; }
        .input-field:focus + .input-icon { color: #3b82f6; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4 bg-[url('https://images.unsplash.com/photo-1557683316-973673baf926?q=80&w=2029&auto=format&fit=crop')] bg-cover bg-center">
    
    <div class="absolute inset-0 bg-gray-900/90 backdrop-blur-sm"></div>

    <div class="w-full max-w-md glass p-8 rounded-2xl relative z-10 animate-fade-in-down border-t border-gray-700">
        
        <div class="text-center mb-8">
            <div class="w-14 h-14 bg-gradient-to-br from-green-600 to-emerald-600 rounded-xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-green-500/20">
                <i class="fas fa-envelope-open-text text-2xl text-white"></i>
            </div>
            <h2 class="text-2xl font-bold tracking-tight">Verify Account</h2>
            <p class="text-gray-400 mt-2 text-sm">Resend your activation link</p>
        </div>
        
        <?php if($error): ?>
            <div class="bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-xl mb-6 text-sm flex items-center gap-3 animate-pulse">
                <i class="fas fa-exclamation-circle text-lg"></i>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="bg-green-500/10 border border-green-500/20 text-green-400 p-4 rounded-xl mb-6 text-sm flex items-center gap-3">
                <i class="fas fa-check-circle text-lg"></i>
                <span><?php echo $success; ?></span>
            </div>
        <?php else: ?>

        <form method="POST" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="input-group">
                <input type="email" name="email" placeholder="Enter your email" required value="<?php echo $email_prefill; ?>"
                       class="input-field w-full bg-gray-900/50 border border-gray-600 rounded-xl p-3.5 text-white focus:border-green-500 focus:ring-1 focus:ring-green-500 outline-none placeholder-gray-500 shadow-inner">
                <i class="fas fa-envelope input-icon"></i>
            </div>

            <button type="submit" class="w-full bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-500 hover:to-emerald-500 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-green-900/30 transform transition active:scale-[0.98]">
                Resend Link
            </button>
        </form>
        <?php endif; ?>

        <div class="mt-8 pt-6 border-t border-gray-700/50 text-center">
            <a href="index.php?module=auth&page=login" class="inline-flex items-center gap-2 text-xs text-gray-500 hover:text-white transition">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>
</body>
</html>