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
                        $success = "New link dispatched! Please check your secure inbox.";
                    } else {
                        $error = "System error sending email. Try again later.";
                    }
                }
            }
        } else {
            // Security: Generic message to prevent email enumeration
            // We pretend it sent to avoid revealing which emails exist
            $success = "If an account exists with this email, a link has been dispatched."; 
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Resend Verification - DigitalMarketplaceMM</title>
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
        
        .input-group { position: relative; }
        .input-icon { 
            position: absolute; 
            left: 1.25rem; 
            top: 50%; 
            transform: translateY(-50%); 
            color: #64748b; 
            transition: color 0.3s; 
            font-size: 1.1rem;
        }
        .input-field { 
            padding-left: 3.25rem; 
            padding-right: 1rem;
            transition: all 0.3s ease; 
            font-size: 16px !important; 
        }
        .input-field:focus + .input-icon { color: #10b981; }
        .input-field:focus { 
            border-color: #10b981; 
            box-shadow: inset 0 0 10px rgba(16, 185, 129, 0.15); 
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
    <div class="fixed top-0 -left-4 w-72 h-72 bg-emerald-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-20 animate-blob -z-10"></div>
    <div class="fixed top-0 -right-4 w-72 h-72 bg-blue-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-20 animate-blob animation-delay-2000 -z-10"></div>
    <div class="fixed -bottom-8 left-20 w-72 h-72 bg-[#00f0ff] rounded-full mix-blend-multiply filter blur-[128px] opacity-10 animate-blob animation-delay-4000 -z-10"></div>

    <!-- Main Container -->
    <div class="w-full max-w-md glass p-6 md:p-8 rounded-3xl relative z-10 animate-fade-in-down border-t border-emerald-500/30">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-600 to-green-600 mb-4 shadow-[0_0_20px_rgba(16,185,129,0.3)]">
                <i class="fas fa-paper-plane text-3xl text-white"></i>
            </div>
            <h2 class="text-3xl font-black tracking-tight text-white mb-1">Verify Identity</h2>
            <p class="text-slate-400 text-sm">Resend your account activation link</p>
        </div>
        
        <!-- Alerts -->
        <?php if($error): ?>
            <div class="bg-red-900/20 border border-red-500/50 text-red-400 p-4 rounded-xl mb-6 text-sm backdrop-blur-md shadow-lg flex items-start gap-3">
                <i class="fas fa-exclamation-triangle text-lg mt-0.5 shrink-0"></i>
                <span class="font-medium leading-snug"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="bg-emerald-900/20 border border-emerald-500/50 text-emerald-400 p-4 rounded-xl mb-6 text-sm backdrop-blur-md shadow-lg flex items-center gap-3">
                <i class="fas fa-shield-check text-xl shrink-0"></i>
                <span class="font-medium leading-snug"><?php echo $success; ?></span>
            </div>
            
            <a href="index.php?module=auth&page=login" class="block w-full bg-slate-800 hover:bg-slate-700 text-white font-bold py-4 rounded-xl transition text-center shadow-lg border border-slate-600 mt-4 tracking-wide">
                Return to Login
            </a>
        <?php else: ?>

        <!-- Form -->
        <form method="POST" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="input-group group">
                <input type="email" name="email" placeholder="Registered Email Address" required value="<?php echo $email_prefill; ?>" autocomplete="email"
                       class="input-field w-full bg-slate-900/60 border border-slate-600 rounded-xl py-4 text-white focus:border-emerald-500 outline-none placeholder-slate-500 backdrop-blur-sm">
                <i class="fas fa-envelope input-icon"></i>
            </div>

            <button type="submit" class="w-full bg-gradient-to-r from-emerald-600 to-green-600 hover:from-emerald-500 hover:to-green-500 text-white font-black py-4 rounded-xl shadow-[0_0_20px_rgba(16,185,129,0.3)] transform transition active:scale-[0.98] text-sm uppercase tracking-widest mt-2 flex items-center justify-center gap-2 group/btn">
                <span>Dispatch Link</span>
                <i class="fas fa-satellite-dish group-hover/btn:animate-pulse"></i>
            </button>
        </form>
        <?php endif; ?>

        <!-- Footer Link -->
        <div class="mt-8 pt-6 border-t border-slate-700/50 text-center">
            <a href="index.php?module=auth&page=login" class="inline-flex items-center gap-2 text-xs font-bold text-slate-500 hover:text-white transition uppercase tracking-wider group">
                <i class="fas fa-arrow-left group-hover:-translate-x-1 transition-transform"></i> Abort & Return
            </a>
        </div>
    </div>
</body>
</html>