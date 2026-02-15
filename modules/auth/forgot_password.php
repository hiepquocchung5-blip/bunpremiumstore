<?php
// modules/auth/forgot_password.php
require_once 'includes/MailService.php';

if (is_logged_in()) redirect('index.php');

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    $session_token = $_SESSION['csrf_token'] ?? '';
    $post_token = $_POST['csrf_token'] ?? '';

    if (empty($session_token) || !hash_equals($session_token, $post_token)) {
        $error = "Session expired. Please refresh.";
    } else {
        $email = trim($_POST['email']);
        
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            
            // We use the 'verify_token' column for password resets to avoid schema changes
            // If you added a specific 'reset_token' column, use that instead.
            $update = $pdo->prepare("UPDATE users SET verify_token = ? WHERE id = ?");
            
            if ($update->execute([$token, $user['id']])) {
                // Send Email
                $mailer = new MailService();
                if ($mailer->sendPasswordReset($email, $token)) {
                    $message = "We have sent a password reset link to your email.";
                } else {
                    $error = "Failed to send email. Please try again later.";
                }
            }
        } else {
            // Security: Show success even if email doesn't exist to prevent enumeration
            $message = "If an account exists with this email, a reset link has been sent.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - DigitalMarketplaceMM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #0f172a; color: white; font-family: 'Inter', sans-serif; }
        .glass { background: rgba(30, 41, 59, 0.75); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.08); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
        .input-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; transition: color 0.2s; }
        .input-field { padding-left: 2.75rem; transition: all 0.2s ease; }
        .input-field:focus + .input-icon { color: #8b5cf6; } /* Purple */
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4 bg-[url('https://images.unsplash.com/photo-1620641788421-7a1c342ea42e?q=80&w=1974&auto=format&fit=crop')] bg-cover bg-center">
    
    <div class="absolute inset-0 bg-slate-900/90 backdrop-blur-sm"></div>

    <div class="w-full max-w-md glass p-8 rounded-2xl relative z-10 animate-fade-in-down border-t border-gray-700/50">
        
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-violet-600 to-purple-600 mb-4 shadow-lg shadow-purple-500/20">
                <i class="fas fa-key text-2xl text-white"></i>
            </div>
            <h2 class="text-2xl font-bold tracking-tight text-white">Forgot Password?</h2>
            <p class="text-slate-400 mt-2 text-sm">Enter your email to receive a reset link</p>
        </div>

        <?php if($message): ?>
            <div class="bg-green-500/10 border border-green-500/20 text-green-400 px-4 py-3 rounded-xl mb-6 text-sm flex items-center gap-3">
                <i class="fas fa-check-circle text-lg"></i>
                <span><?php echo $message; ?></span>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-xl mb-6 text-sm flex items-center gap-3">
                <i class="fas fa-exclamation-triangle text-lg"></i>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <?php if(!$message): ?>
        <form method="POST" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="relative">
                <input type="email" name="email" placeholder="Email Address" required 
                       class="input-field w-full bg-slate-900/50 border border-slate-600 rounded-xl p-3.5 text-white focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none placeholder-slate-500 shadow-inner text-sm">
                <i class="fas fa-envelope input-icon text-sm"></i>
            </div>

            <button type="submit" class="w-full bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-500 hover:to-purple-500 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-purple-900/20 transform transition active:scale-[0.98] text-sm tracking-wide">
                Send Reset Link
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