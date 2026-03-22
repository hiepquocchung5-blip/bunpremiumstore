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

// ==========================================================================
// SECURITY: RATE LIMITING (Brute Force Protection)
// ==========================================================================
if (!isset($_SESSION['reg_attempts'])) $_SESSION['reg_attempts'] = 0;
if (!isset($_SESSION['reg_lockout'])) $_SESSION['reg_lockout'] = 0;

// Block if too many attempts
if ($_SESSION['reg_attempts'] >= 3 && time() < $_SESSION['reg_lockout']) {
    $remaining = ceil(($_SESSION['reg_lockout'] - time()) / 60);
    $error = "Too many attempts. Please wait $remaining minutes.";
} 

// ==========================================================================
// HANDLE FORM SUBMISSION
// ==========================================================================
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 2. SECURITY: CSRF Check
    $session_token = $_SESSION['csrf_token'] ?? '';
    $post_token = $_POST['csrf_token'] ?? '';

    // 3. SECURITY: Honeypot Trap (Anti-Bot)
    // 'fax' field is hidden via CSS. If filled, it's a bot.
    $honeypot = $_POST['fax'] ?? '';

    if (!empty($honeypot)) {
        // Silent failure for bots (don't tell them they failed)
        die("System error. Please contact support.");
    }

    if (empty($session_token) || !hash_equals($session_token, $post_token)) {
        $error = "Security token expired. Please refresh the page.";
    } else {
        // 4. SECURITY: Input Sanitization
        // FIX: Store raw UTF-8 in DB to prevent encoding errors (Error 1366). Escape on output instead.
        // We ensure the connection is UTF-8mb4 before query execution.
        $fullname = trim($_POST['full_name']);
        $username = trim($_POST['username']);
        
        // Remove illegal characters from email
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $phone = trim($_POST['phone']);
        $password = $_POST['password'];
        $confirm = $_POST['confirm_password'];
        $terms = isset($_POST['terms']);

        // 5. SECURITY: Input Validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } elseif (!$terms) {
            $error = "You must agree to the Terms of Service.";
        } elseif ($password !== $confirm) {
            $error = "Passwords do not match.";
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters.";
        } elseif (!preg_match("/[A-Z]/", $password) || !preg_match("/[0-9]/", $password)) {
            $error = "Password must contain at least 1 Capital letter and 1 Number.";
        } else {
            // Force UTF-8MB4 connection for Emoji/Special char support
            $pdo->exec("SET NAMES 'utf8mb4'");

            // Check Database for duplicates (Using Prepared Statement)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $username]);
            
            if ($stmt->rowCount() > 0) {
                $error = "Username or Email is already registered.";
            } else {
                try {
                    // 6. SECURITY: Password Hashing (Bcrypt)
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $token = bin2hex(random_bytes(32)); // Cryptographically secure token
                    $phoneVal = empty($phone) ? null : $phone;

                    // 7. SECURITY: Prepared Statement (Prevents SQL Injection)
                    $stmt = $pdo->prepare("INSERT INTO users (full_name, username, email, phone, password, verify_token, is_verified) VALUES (?, ?, ?, ?, ?, ?, 0)");
                    
                    if ($stmt->execute([$fullname, $username, $email, $phoneVal, $hashed, $token])) {
                        // Success - Send Verification Email
                        $mailer = new MailService();
                        $mailer->sendVerificationEmail($email, $token);

                        // Reset rate limit on success
                        $_SESSION['reg_attempts'] = 0;

                        // Redirect to Login
                        header("Location: index.php?module=auth&page=login&registered=1&email=" . urlencode($email));
                        exit;
                    } else {
                        $error = "Registration failed due to a system error.";
                    }
                } catch (PDOException $e) {
                    // Catch SQL errors specifically
                    if ($e->getCode() == 23000) { // Integrity constraint violation
                        $error = "Username or Email already exists.";
                    } elseif ($e->getCode() == 'HY000' && strpos($e->getMessage(), '1366') !== false) {
                        // Catch Error 1366 (Incorrect string value) explicitly
                        $error = "Your name contains characters not supported by the database. Please use standard characters.";
                    } else {
                         // Don't show raw DB errors in production
                        error_log($e->getMessage()); 
                        $error = "An error occurred. Please try again.";
                    }
                } catch (Exception $e) {
                    error_log($e->getMessage()); 
                    $error = "An error occurred. Please try again.";
                }
            }
        }
        
        // Increment rate limit counter on failure
        if ($error) {
            $_SESSION['reg_attempts']++;
            if ($_SESSION['reg_attempts'] >= 3) {
                $_SESSION['reg_lockout'] = time() + (10 * 60); // 10 min lock
            }
        }
    }
}

// Google Auth Link (Safe variable fetch)
$google_client_id = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '';
$google_redirect_url = defined('GOOGLE_REDIRECT_URL') ? GOOGLE_REDIRECT_URL : '';
$google_login_url = "https://accounts.google.com/o/oauth2/v2/auth?response_type=code&client_id=" . urlencode($google_client_id) . "&redirect_uri=" . urlencode($google_redirect_url) . "&scope=email%20profile";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Deploy Account - DigitalMarketplaceMM</title>
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
        
        /* Mobile Optimized Inputs */
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
        .input-field:focus + .input-icon { color: #00f0ff; }
        .input-field:focus { 
            border-color: #00f0ff; 
            box-shadow: inset 0 0 10px rgba(0, 240, 255, 0.1); 
        }

        /* Abstract Background Animations */
        @Passwordframes blob {
            0% { transform: translate(0px, 0px) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
            100% { transform: translate(0px, 0px) scale(1); }
        }
        .animate-blob { animation: blob 7s infinite; }
        .animation-delay-2000 { animation-delay: 2s; }
        .animation-delay-4000 { animation-delay: 4s; }
        
        .visually-hidden { position: absolute; left: -9999px; top: -9999px; visibility: hidden; }
    </style>
</head>
<body class="flex items-center justify-center relative overflow-x-hidden px-4 py-12 md:py-8 min-h-screen">
    
    <!-- Animated Cyberpunk Background -->
    <div class="fixed inset-0 w-full h-full bg-slate-950 -z-20"></div>
    <div class="fixed top-0 -left-4 w-72 h-72 bg-blue-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-30 animate-blob -z-10"></div>
    <div class="fixed top-0 -right-4 w-72 h-72 bg-[#00f0ff] rounded-full mix-blend-multiply filter blur-[128px] opacity-20 animate-blob animation-delay-2000 -z-10"></div>
    <div class="fixed -bottom-8 left-20 w-72 h-72 bg-purple-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-30 animate-blob animation-delay-4000 -z-10"></div>

    <!-- Main Container -->
    <div class="w-full max-w-lg glass p-6 md:p-10 rounded-3xl relative z-10 animate-fade-in-down border-t border-[#00f0ff]/30">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <a href="index.php" class="inline-block mb-4 group">
                <div class="w-16 h-16 bg-slate-900 border border-[#00f0ff]/30 rounded-2xl flex items-center justify-center mx-auto shadow-[0_0_15px_rgba(0,240,255,0.2)] group-hover:shadow-[0_0_25px_rgba(0,240,255,0.4)] transition duration-300">
                    <i class="fas fa-satellite-dish text-3xl text-[#00f0ff]"></i>
                </div>
            </a>
            <h2 class="text-3xl font-black tracking-tight text-white mb-1">Initialize Operative</h2>
            <p class="text-slate-400 text-sm">Deploy your identity into the network</p>
        </div>
        
        <!-- Alerts -->
        <?php if($error): ?>
            <div class="bg-red-900/20 border border-red-500/50 text-red-400 p-4 rounded-xl mb-6 text-sm backdrop-blur-md shadow-lg flex items-start gap-3">
                <i class="fas fa-exclamation-triangle text-lg mt-0.5 shrink-0"></i>
                <span class="font-medium leading-snug"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Google Sign-Up -->
        <?php // if(!empty($google_client_id)): ?>
        <!-- <a href="<?php // echo $google_login_url; ?>" class="w-full bg-white hover:bg-gray-100 text-slate-900 font-black py-3.5 px-4 rounded-xl shadow-lg transition flex items-center justify-center gap-3 mb-6 group">
            <img src="https://www.svgrepo.com/show/475656/google-color.svg" class="w-6 h-6 transition-transform group-hover:scale-110" alt="Google">
            <span class="text-sm tracking-wide">Fast Deploy with Google</span>
        </a> -->

        <!-- <div class="flex items-center gap-4 mb-6">
            <div class="h-px bg-slate-700/80 flex-1"></div>
            <span class="text-[10px] text-slate-500 uppercase font-black tracking-widest">Or manual setup</span>
            <div class="h-px bg-slate-700/80 flex-1"></div>
        </div> -->
        <?php // endif; ?>

        <!-- Form -->
        <form method="POST" class="space-y-5" onsubmit="return validateForm()">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <!-- Honeypot -->
            <input type="text" name="fax" class="visually-hidden" tabindex="-1" autocomplete="off">
            
            <!-- Row 1: Identity -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="input-group">
                    <input type="text" name="full_name" placeholder="Full Designation" required 
                           class="input-field w-full bg-slate-900/60 border border-slate-600 rounded-xl py-3.5 text-white focus:border-[#00f0ff] outline-none placeholder-slate-500 backdrop-blur-sm">
                    <i class="fas fa-id-badge input-icon"></i>
                </div>
                <div class="input-group">
                    <input type="text" name="username" placeholder="Handle (Username)" required 
                           class="input-field w-full bg-slate-900/60 border border-slate-600 rounded-xl py-3.5 text-white focus:border-[#00f0ff] outline-none placeholder-slate-500 backdrop-blur-sm">
                    <i class="fas fa-at input-icon"></i>
                </div>
            </div>

            <!-- Row 2: Comms -->
            <div class="input-group">
                <input type="email" name="email" placeholder="Secure Comm (Email)" required 
                       class="input-field w-full bg-slate-900/60 border border-slate-600 rounded-xl py-3.5 text-white focus:border-[#00f0ff] outline-none placeholder-slate-500 backdrop-blur-sm">
                <i class="fas fa-envelope input-icon"></i>
            </div>

            <div class="input-group">
                <input type="tel" name="phone" placeholder="Local Link (Optional Phone)" 
                       class="input-field w-full bg-slate-900/60 border border-slate-600 rounded-xl py-3.5 text-white focus:border-[#00f0ff] outline-none placeholder-slate-500 backdrop-blur-sm">
                <i class="fas fa-phone-alt input-icon text-sm"></i>
            </div>

            <!-- Row 3: Security -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="input-group">
                    <input type="password" name="password" id="password" placeholder="Master Password" required 
                           class="input-field w-full bg-slate-900/60 border border-slate-600 rounded-xl py-3.5 text-white focus:border-[#00f0ff] outline-none placeholder-slate-500 backdrop-blur-sm pr-12">
                    <i class="fas fa-lock input-icon"></i>
                    <button type="button" id="togglePassword" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400 hover:text-[#00f0ff] transition-colors duration-200">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="input-group">
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Verify Password" required 
                           class="input-field w-full bg-slate-900/60 border border-slate-600 rounded-xl py-3.5 text-white focus:border-[#00f0ff] outline-none placeholder-slate-500 backdrop-blur-sm pr-12">
                    <i class="fas fa-check-double input-icon"></i>
                    <button type="button" id="toggleConfirmPassword" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400 hover:text-[#00f0ff] transition-colors duration-200">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <!-- Security Requirements -->
            <div class="text-[10px] text-slate-500 flex flex-wrap justify-between gap-2 px-2 font-medium tracking-wide">
                <span id="len" class="flex items-center gap-1 transition-colors"><i class="fas fa-circle text-[5px]"></i> 8+ Chars</span>
                <span id="cap" class="flex items-center gap-1 transition-colors"><i class="fas fa-circle text-[5px]"></i> Uppercase</span>
                <span id="num" class="flex items-center gap-1 transition-colors"><i class="fas fa-circle text-[5px]"></i> Number</span>
            </div>

            <!-- Agreements -->
            <label class="flex items-start gap-3 cursor-pointer group mt-4 select-none px-1">
                <div class="relative flex items-center pt-0.5">
                    <input type="checkbox" name="terms" required class="peer sr-only">
                    <div class="w-5 h-5 rounded border border-slate-600 bg-slate-900 peer-checked:bg-[#00f0ff] peer-checked:border-[#00f0ff] transition flex items-center justify-center">
                        <i class="fas fa-check text-slate-900 text-xs opacity-0 peer-checked:opacity-100 transition"></i>
                    </div>
                </div>
                <span class="text-xs text-slate-400 group-hover:text-white transition leading-snug">
                    I acknowledge the <a href="index.php?module=info&page=terms" target="_blank" class="text-[#00f0ff] font-bold hover:underline">Terms of Service</a> & <a href="index.php?module=info&page=privacy" target="_blank" class="text-[#00f0ff] font-bold hover:underline">Privacy Policy</a>
                </span>
            </label>

            <!-- Submit -->
            <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-[#00f0ff] hover:from-blue-500 hover:to-[#00f0ff] text-slate-900 font-black py-4 rounded-xl shadow-[0_0_20px_rgba(0,240,255,0.2)] hover:shadow-[0_0_30px_rgba(0,240,255,0.4)] transform transition active:scale-[0.98] text-sm uppercase tracking-widest mt-6 flex justify-center items-center gap-2 group/btn">
                <span>Deploy Credentials</span>
                <i class="fas fa-upload group-hover/btn:-translate-y-1 transition-transform"></i>
            </button>
        </form>

        <!-- Footer -->
        <div class="mt-8 pt-6 border-t border-slate-700/50 text-center">
            <p class="text-sm text-slate-400 font-medium">
                Already registered? <a href="index.php?module=auth&page=login" class="text-[#00f0ff] font-bold hover:underline ml-1">Initiate Login</a>
            </p>
        </div>
    </div>

    <!-- Validation Script -->
    <script>
        const passwordInput = document.getElementById('password');
        const reqLen = document.getElementById('len');
        const reqCap = document.getElementById('cap');
        const reqNum = document.getElementById('num');

        if(passwordInput) {
            passwordInput.addEventListener('input', function() {
                const val = this.value;
                
                if(val.length >= 8) { reqLen.classList.replace('text-slate-500', 'text-green-400'); }
                else { reqLen.classList.replace('text-green-400', 'text-slate-500'); }

                if(/[A-Z]/.test(val)) { reqCap.classList.replace('text-slate-500', 'text-green-400'); }
                else { reqCap.classList.replace('text-green-400', 'text-slate-500'); }

                if(/[0-9]/.test(val)) { reqNum.classList.replace('text-slate-500', 'text-green-400'); }
                else { reqNum.classList.replace('text-green-400', 'text-slate-500'); }
            });
        }

        // Password visibility toggles
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const confirmPasswordInput = document.getElementById('confirm_password');
            const icon = this.querySelector('i');
            
            if (confirmPasswordInput.type === 'password') {
                confirmPasswordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                confirmPasswordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        function validateForm() {
            const p1 = document.getElementById('password').value;
            const p2 = document.getElementById('confirm_password').value;
            if(p1 !== p2) {
                alert("Master Passwords do not match.");
                return false;
            }
            return true;
        }
    </script>
</body>
</html>