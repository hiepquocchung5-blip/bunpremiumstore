<?php
// modules/auth/register.php

if (is_logged_in()) redirect('index.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) die("Invalid CSRF Token");

    $fullname = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']); // Optional
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    $terms = isset($_POST['terms']);

    // Validation Logic
    if (!$terms) {
        $error = "You must accept the Terms & Policy.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif (!preg_match("/[A-Z]/", $password) || !preg_match("/[0-9]/", $password)) {
        $error = "Password must contain at least 1 Capital letter and 1 Number.";
    } else {
        // Check uniqueness
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        if ($stmt->rowCount() > 0) {
            $error = "Username or Email already exists.";
        } else {
            // Create User
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert into DB
            $stmt = $pdo->prepare("INSERT INTO users (full_name, username, email, phone, password) VALUES (?, ?, ?, ?, ?)");
            $phoneVal = empty($phone) ? null : $phone;
            
            if ($stmt->execute([$fullname, $username, $email, $phoneVal, $hashed])) {
                // Optional: Send Welcome Email here
                // ...
                header("Location: index.php?module=auth&page=login&registered=1");
                exit;
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Join ScottSub - Premium Digital Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #111827; color: white; font-family: 'Inter', sans-serif; }
        
        /* Glass Effect matching Login */
        .glass { 
            background: rgba(31, 41, 55, 0.7); 
            backdrop-filter: blur(12px); 
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08); 
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        }
        
        .input-group { position: relative; }
        .input-icon { 
            position: absolute; 
            left: 1rem; 
            top: 50%; 
            transform: translateY(-50%); 
            color: #9ca3af; 
            transition: color 0.3s;
        }
        .input-field { 
            padding-left: 2.75rem; 
            transition: all 0.3s ease;
        }
        .input-field:focus + .input-icon, .input-group:focus-within .input-icon {
            color: #3b82f6; /* Blue-500 */
        }
        
        /* Checkbox custom style */
        .custom-checkbox:checked {
            background-color: #2563eb;
            border-color: #2563eb;
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4 bg-[url('https://images.unsplash.com/photo-1550745165-9bc0b252726f?auto=format&fit=crop&q=80')] bg-cover bg-center bg-no-repeat bg-fixed">
    
    <!-- Dark Overlay -->
    <div class="absolute inset-0 bg-gray-900/85 backdrop-blur-sm"></div>

    <div class="w-full max-w-lg glass p-8 rounded-2xl shadow-2xl relative z-10 animate-fade-in-up">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <a href="index.php" class="inline-block mb-4 transform hover:scale-110 transition duration-300">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-600 to-blue-800 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/30">
                    <i class="fas fa-shopping-bag text-2xl text-white"></i>
                </div>
            </a>
            <h2 class="text-3xl font-bold tracking-tight text-white">Create Account</h2>
            <p class="text-gray-400 mt-2 text-sm">Join Myanmar's #1 Digital Marketplace</p>
        </div>
        
        <!-- Error Alert -->
        <?php if($error): ?>
            <div class="bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-xl mb-6 text-sm flex items-start gap-3 animate-pulse">
                <i class="fas fa-exclamation-circle text-lg mt-0.5"></i>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5" onsubmit="return validateForm()">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <!-- Row 1: Name & Username -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="input-group">
                    <input type="text" name="full_name" placeholder="Full Name" required 
                           class="input-field w-full bg-gray-900/50 border border-gray-600 rounded-xl p-3.5 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none placeholder-gray-500">
                    <i class="fas fa-user input-icon"></i>
                </div>
                <div class="input-group">
                    <input type="text" name="username" placeholder="Username" required 
                           class="input-field w-full bg-gray-900/50 border border-gray-600 rounded-xl p-3.5 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none placeholder-gray-500">
                    <i class="fas fa-at input-icon"></i>
                </div>
            </div>

            <!-- Row 2: Contact -->
            <div class="input-group">
                <input type="email" name="email" placeholder="Email Address" required 
                       class="input-field w-full bg-gray-900/50 border border-gray-600 rounded-xl p-3.5 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none placeholder-gray-500">
                <i class="fas fa-envelope input-icon"></i>
            </div>

            <div class="input-group">
                <input type="tel" name="phone" placeholder="Phone Number (Optional)" 
                       class="input-field w-full bg-gray-900/50 border border-gray-600 rounded-xl p-3.5 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none placeholder-gray-500">
                <i class="fas fa-phone input-icon"></i>
            </div>

            <!-- Row 3: Security -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="input-group">
                    <input type="password" name="password" id="password" placeholder="Password" required 
                           class="input-field w-full bg-gray-900/50 border border-gray-600 rounded-xl p-3.5 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none placeholder-gray-500">
                    <i class="fas fa-lock input-icon"></i>
                </div>
                <div class="input-group">
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required 
                           class="input-field w-full bg-gray-900/50 border border-gray-600 rounded-xl p-3.5 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none placeholder-gray-500">
                    <i class="fas fa-check-double input-icon"></i>
                </div>
            </div>

            <!-- Password Requirements Hint -->
            <div class="text-xs text-gray-500 flex flex-wrap gap-2 px-1">
                <span id="len" class="transition-colors"><i class="fas fa-circle text-[6px] mr-1"></i> 8+ Chars</span>
                <span id="cap" class="transition-colors"><i class="fas fa-circle text-[6px] mr-1"></i> 1 Uppercase</span>
                <span id="num" class="transition-colors"><i class="fas fa-circle text-[6px] mr-1"></i> 1 Number</span>
            </div>

            <!-- Terms Checkbox -->
            <label class="flex items-start gap-3 cursor-pointer group mt-2 select-none">
                <div class="relative flex items-center">
                    <input type="checkbox" name="terms" required 
                           class="custom-checkbox w-5 h-5 rounded border-gray-600 bg-gray-900/50 text-blue-600 focus:ring-blue-500 focus:ring-offset-gray-900 cursor-pointer transition">
                </div>
                <span class="text-sm text-gray-400 group-hover:text-gray-300 transition leading-snug">
                    I agree to the <a href="index.php?module=info&page=terms" target="_blank" class="text-blue-400 font-medium hover:underline hover:text-blue-300">Terms of Service</a> & <a href="index.php?module=info&page=privacy" target="_blank" class="text-blue-400 font-medium hover:underline hover:text-blue-300">Privacy Policy</a>
                </span>
            </label>

            <!-- Submit Button -->
            <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-blue-900/40 transform transition active:scale-[0.98] mt-6 flex justify-center items-center gap-2 group">
                <span>Create Account</span>
                <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
            </button>
        </form>

        <!-- Footer -->
        <div class="mt-8 pt-6 border-t border-gray-700/50 text-center">
            <p class="text-sm text-gray-400">
                Already have an account? <a href="index.php?module=auth&page=login" class="text-blue-400 font-bold hover:underline hover:text-blue-300 transition">Log in</a>
            </p>
        </div>
    </div>

    <!-- Client-side Password Validation Script -->
    <script>
        const passwordInput = document.getElementById('password');
        const reqLen = document.getElementById('len');
        const reqCap = document.getElementById('cap');
        const reqNum = document.getElementById('num');

        passwordInput.addEventListener('input', function() {
            const val = this.value;
            
            // Length
            if(val.length >= 8) reqLen.classList.replace('text-gray-500', 'text-green-400');
            else reqLen.classList.replace('text-green-400', 'text-gray-500');

            // Capital
            if(/[A-Z]/.test(val)) reqCap.classList.replace('text-gray-500', 'text-green-400');
            else reqCap.classList.replace('text-green-400', 'text-gray-500');

            // Number
            if(/[0-9]/.test(val)) reqNum.classList.replace('text-gray-500', 'text-green-400');
            else reqNum.classList.replace('text-green-400', 'text-gray-500');
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