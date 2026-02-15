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
        $error = "Security token expired. Please refresh.";
    } else {
        $email = trim($_POST['email']);
        
        // Check user status
        $stmt = $pdo->prepare("SELECT id, username, is_verified FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            if ($user['is_verified'] == 1) {
                $success = "Account is already verified.";
            } else {
                // Generate new token
                $token = bin2hex(random_bytes(32));
                $update = $pdo->prepare("UPDATE users SET verify_token = ? WHERE id = ?");
                
                if ($update->execute([$token, $user['id']])) {
                    // Send Email
                    $mailer = new MailService();
                    if ($mailer->sendVerificationEmail($email, $token)) {
                        $success = "New link sent! Please check your inbox.";
                    } else {
                        $error = "System error sending email. Try again later.";
                    }
                }
            }
        } else {
            // Security: Generic message to prevent email enumeration
            // We pretend it sent to avoid revealing which emails exist
            $success = "If an account exists with this email, a link has been sent."; 
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #0f172a; color: white; font-family: 'Inter', sans-serif; }
        .glass { background: rgba(30, 41, 59, 0.75); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.08); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
        .input-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; transition: color 0.2s; }
        .input-field { padding-left: 2.75rem; transition: all 0.2s ease; }
        .input-field:focus + .input-icon { color: #10b981; } /* Emerald Green */
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4 bg-[url('https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?q=80&w=2564&auto=format&fit=crop')] bg-cover bg-center">
    
    <div class="absolute inset-0 bg-slate-900/90 backdrop-blur-sm"></div>

    <div class="w-full max-w-md glass p-8 rounded-2xl relative z-10 animate-fade-in-down border-t border-gray-700/50">
        
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-green-600 to-emerald-600 mb-4 shadow-lg shadow-green-500/20">
                <i class="fas fa-paper-plane text-2xl text-white"></i>
            </div>
            <h2 class="text-2xl font-bold tracking-tight text-white">Resend Verification</h2>
            <p class="text-slate-400 mt-2 text-sm">Activate your account to continue</p>
        </div>
        
        <?php if($error): ?>
            <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-xl mb-6 text-sm flex items-center gap-3">
                <i class="fas fa-exclamation-circle text-lg"></i>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="bg-green-500/10 border border-green-500/20 text-green-400 px-4 py-3 rounded-xl mb-6 text-sm flex items-center gap-3">
                <i class="fas fa-check-circle text-lg"></i>
                <span><?php echo $success; ?></span>
            </div>
            
            <a href="index.php?module=auth&page=login" class="block w-full bg-slate-800 hover:bg-slate-700 text-white font-bold py-3.5 rounded-xl transition text-center shadow-lg border border-slate-600">
                Go to Login
            </a>
        <?php else: ?>

        <form method="POST" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="relative">
                <input type="email" name="email" placeholder="Email Address" required value="<?php echo $email_prefill; ?>"
                       class="input-field w-full bg-slate-900/50 border border-slate-600 rounded-xl p-3.5 text-white focus:border-green-500 focus:ring-1 focus:ring-green-500 outline-none placeholder-slate-500 shadow-inner text-sm">
                <i class="fas fa-envelope input-icon text-sm"></i>
            </div>

            <button type="submit" class="w-full bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-500 hover:to-emerald-500 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-green-900/30 transform transition active:scale-[0.98] text-sm tracking-wide">
                Send Verification Link
            </button>
        </form>
        <?php endif; ?>

        <div class="mt-8 pt-6 border-t border-slate-700/50 text-center">
            <a href="index.php?module=auth&page=login" class="inline-flex items-center gap-2 text-xs text-slate-500 hover:text-white transition">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>
</body>
</html>