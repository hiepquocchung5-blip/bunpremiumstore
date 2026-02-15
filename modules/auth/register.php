<?php
// modules/auth/register.php

// 1. Redirect if already logged in
if (is_logged_in()) {
    redirect('index.php');
}

// Include Mail Service
require_once 'includes/MailService.php';

$error = '';
$success = '';

// 2. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check (Safe against null types)
    $session_token = $_SESSION['csrf_token'] ?? '';
    $post_token = $_POST['csrf_token'] ?? '';

    if (empty($session_token) || !hash_equals($session_token, $post_token)) {
        $error = "Security token expired. Please refresh the page.";
    } else {
        // Sanitize Inputs
        $fullname = trim($_POST['full_name']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $password = $_POST['password'];
        $confirm = $_POST['confirm_password'];
        $terms = isset($_POST['terms']);

        // Validation Rules
        if (!$terms) {
            $error = "You must agree to the Terms of Service.";
        } elseif ($password !== $confirm) {
            $error = "Passwords do not match.";
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters.";
        } elseif (!preg_match("/[A-Z]/", $password) || !preg_match("/[0-9]/", $password)) {
            $error = "Password must contain at least 1 Capital letter and 1 Number.";
        } else {
            // Check Database for duplicates
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $username]);
            
            if ($stmt->rowCount() > 0) {
                $error = "Username or Email is already registered.";
            } else {
                // Create User
                try {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $token = bin2hex(random_bytes(32)); // Email Verification Token
                    $phoneVal = empty($phone) ? null : $phone;

                    $stmt = $pdo->prepare("INSERT INTO users (full_name, username, email, phone, password, verify_token, is_verified) VALUES (?, ?, ?, ?, ?, ?, 0)");
                    
                    if ($stmt->execute([$fullname, $username, $email, $phoneVal, $hashed, $token])) {
                        // Send Verification Email
                        $mailer = new MailService();
                        $mailer->sendVerificationEmail($email, $token);

                        // Redirect to Login to show Verification Modal
                        header("Location: index.php?module=auth&page=login&registered=1&email=" . urlencode($email));
                        exit;
                    } else {
                        $error = "Registration failed due to a system error. Please try again.";
                    }
                } catch (Exception $e) {
                    $error = "Database Error: " . $e->getMessage();
                }
            }
        }
    }
}

// Google Auth Link (Reusing Config from config.php)
$google_login_url = "https://accounts.google.com/o/oauth2/v2/auth?response_type=code&client_id=" . GOOGLE_CLIENT_ID . "&redirect_uri=" . urlencode(GOOGLE_REDIRECT_URL) . "&scope=email%20profile";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Account - DigitalMarketplaceMM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #0f172a; color: white; font-family: 'Inter', sans-serif; }
        .glass { background: rgba(30, 41, 59, 0.75); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.08); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
        .input-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; transition: color 0.2s; }
        .input-field { padding-left: 2.75rem; transition: all 0.2s ease; }
        .input-field:focus + .input-icon { color: #60a5fa; }
        /* Custom Checkbox */
        .custom-checkbox:checked { background-color: #2563eb; border-color: #2563eb; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4 bg-[url('https://images.unsplash.com/photo-1550745165-9bc0b252726f?q=80&w=2070&auto=format&fit=crop')] bg-cover bg-center">
    
    <div class="absolute inset-0 bg-slate-900/90 backdrop-blur-sm"></div>

    <div class="w-full max-w-lg glass p-8 rounded-2xl relative z-10 animate-fade-in-down border-t border-gray-700/50">
        
        <div class="text-center mb-6">
            <a href="index.php" class="inline-block mb-4 hover:scale-110 transition duration-300">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/20">
                    <i class="fas fa-shopping-bag text-2xl text-white"></i>
                </div>
            </a>
            <h2 class="text-3xl font-bold tracking-tight text-white">Create Account</h2>
            <p class="text-slate-400 mt-2 text-sm">Join Myanmar's #1 Digital Marketplace</p>
        </div>
        
        <?php if($error): ?>
            <div class="bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-xl mb-6 text-sm flex items-start gap-3 animate-pulse">
                <i class="fas fa-exclamation-circle text-lg mt-0.5"></i>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <!-- Google Sign-Up -->
        <a href="<?php echo $google_login_url; ?>" class="w-full bg-white text-gray-900 font-bold py-3 rounded-xl shadow hover:bg-gray-100 transition flex items-center justify-center gap-3 mb-6 transform hover:scale-[1.01] duration-200">
            <img src="https://www.svgrepo.com/show/475656/google-color.svg" class="w-5 h-5" alt="Google">
            <span>Sign up with Google</span>
        </a>

        <div class="flex items-center gap-4 mb-6">
            <div class="h-px bg-slate-700 flex-1"></div>
            <span class="text-xs text-slate-500 uppercase font-bold">Or register with email</span>
            <div class="h-px bg-slate-700 flex-1"></div>
        </div>

        <form method="POST" class="space-y-4" onsubmit="return validateForm()">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <!-- Row 1: Name & Username -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="relative">
                    <input type="text" name="full_name" placeholder="Full Name" required 
                           class="input-field w-full bg-slate-900/50 border border-slate-600 rounded-xl p-3.5 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none placeholder-slate-500 text-sm">
                    <i class="fas fa-user input-icon text-sm"></i>
                </div>
                <div class="relative">
                    <input type="text" name="username" placeholder="Username" required 
                           class="input-field w-full bg-slate-900/50 border border-slate-600 rounded-xl p-3.5 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none placeholder-slate-500 text-sm">
                    <i class="fas fa-at input-icon text-sm"></i>
                </div>
            </div>

            <!-- Row 2: Contact -->
            <div class="relative">
                <input type="email" name="email" placeholder="Email Address" required 
                       class="input-field w-full bg-slate-900/50 border border-slate-600 rounded-xl p-3.5 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none placeholder-slate-500 text-sm">
                <i class="fas fa-envelope input-icon text-sm"></i>
            </div>

            <div class="relative">
                <input type="tel" name="phone" placeholder="Phone Number (Optional)" 
                       class="input-field w-full bg-slate-900/50 border border-slate-600 rounded-xl p-3.5 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none placeholder-slate-500 text-sm">
                <i class="fas fa-phone input-icon text-sm"></i>
            </div>

            <!-- Row 3: Security -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="relative">
                    <input type="password" name="password" id="password" placeholder="Password" required 
                           class="input-field w-full bg-slate-900/50 border border-slate-600 rounded-xl p-3.5 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none placeholder-slate-500 text-sm">
                    <i class="fas fa-lock input-icon text-sm"></i>
                </div>
                <div class="relative">
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm" required 
                           class="input-field w-full bg-slate-900/50 border border-slate-600 rounded-xl p-3.5 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none placeholder-slate-500 text-sm">
                    <i class="fas fa-check-double input-icon text-sm"></i>
                </div>
            </div>

            <!-- Requirements Hint -->
            <div class="text-[10px] text-slate-500 flex flex-wrap gap-3 px-1 font-medium">
                <span id="len" class="transition-colors flex items-center gap-1"><i class="fas fa-circle text-[4px]"></i> 8+ Chars</span>
                <span id="cap" class="transition-colors flex items-center gap-1"><i class="fas fa-circle text-[4px]"></i> Uppercase</span>
                <span id="num" class="transition-colors flex items-center gap-1"><i class="fas fa-circle text-[4px]"></i> Number</span>
            </div>

            <!-- Terms -->
            <label class="flex items-start gap-3 cursor-pointer group mt-2 select-none">
                <div class="relative flex items-center">
                    <input type="checkbox" name="terms" required 
                           class="w-4 h-4 rounded border-slate-600 bg-slate-800 text-blue-600 focus:ring-blue-500 focus:ring-offset-slate-900 cursor-pointer transition">
                </div>
                <span class="text-xs text-slate-400 group-hover:text-slate-300 transition leading-snug pt-0.5">
                    I agree to the <a href="index.php?module=info&page=terms" target="_blank" class="text-blue-400 font-bold hover:underline hover:text-blue-300">Terms of Service</a> & <a href="index.php?module=info&page=privacy" target="_blank" class="text-blue-400 font-bold hover:underline hover:text-blue-300">Privacy Policy</a>
                </span>
            </label>

            <!-- Submit -->
            <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-blue-900/40 transform transition active:scale-[0.98] mt-6 flex justify-center items-center gap-2 group">
                <span>Create Account</span>
                <i class="fas fa-arrow-right text-sm group-hover:translate-x-1 transition-transform"></i>
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-slate-700/50 text-center">
            <p class="text-sm text-slate-400">
                Already have an account? <a href="index.php?module=auth&page=login" class="text-blue-400 font-bold hover:text-blue-300 hover:underline transition">Log in</a>
            </p>
        </div>
    </div>

    <!-- Client-Side Validation -->
    <script>
        const passwordInput = document.getElementById('password');
        const reqLen = document.getElementById('len');
        const reqCap = document.getElementById('cap');
        const reqNum = document.getElementById('num');

        passwordInput.addEventListener('input', function() {
            const val = this.value;
            
            // Length Check
            if(val.length >= 8) { reqLen.classList.replace('text-slate-500', 'text-green-400'); }
            else { reqLen.classList.replace('text-green-400', 'text-slate-500'); }

            // Capital Check
            if(/[A-Z]/.test(val)) { reqCap.classList.replace('text-slate-500', 'text-green-400'); }
            else { reqCap.classList.replace('text-green-400', 'text-slate-500'); }

            // Number Check
            if(/[0-9]/.test(val)) { reqNum.classList.replace('text-slate-500', 'text-green-400'); }
            else { reqNum.classList.replace('text-green-400', 'text-slate-500'); }
        });

        function validateForm() {
            const p1 = document.getElementById('password').value;
            const p2 = document.getElementById('confirm_password').value;
            if(p1 !== p2) {
                alert("Passwords do not match!");
                return false;
            }
            return true;
        }
    </script>
</body>
</html>