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
            
            // Insert into DB (Phone is optional)
            $stmt = $pdo->prepare("INSERT INTO users (full_name, username, email, phone, password) VALUES (?, ?, ?, ?, ?)");
            $phoneVal = empty($phone) ? null : $phone;
            
            if ($stmt->execute([$fullname, $username, $email, $phoneVal, $hashed])) {
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
    <title>Create Account - ScottSub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #111827; color: white; font-family: 'Inter', sans-serif; }
        .glass { background: rgba(31, 41, 55, 0.7); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.08); }
        .input-group { position: relative; }
        .input-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #9ca3af; }
        .input-field { padding-left: 2.75rem; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4 bg-[url('https://images.unsplash.com/photo-1550745165-9bc0b252726f?auto=format&fit=crop&q=80')] bg-cover bg-center">
    <div class="absolute inset-0 bg-gray-900/90 backdrop-blur-sm"></div>

    <div class="w-full max-w-lg glass p-8 rounded-2xl shadow-2xl relative z-10 border border-gray-700">
        <div class="text-center mb-8">
            <div class="w-12 h-12 bg-blue-600 rounded-lg flex items-center justify-center mx-auto mb-4 shadow-lg shadow-blue-500/30">
                <i class="fas fa-bolt text-2xl text-white"></i>
            </div>
            <h2 class="text-3xl font-bold">Join ScottSub</h2>
            <p class="text-gray-400 mt-2">Create an account to access premium deals</p>
        </div>
        
        <?php if($error): ?>
            <div class="bg-red-500/10 text-red-400 p-4 rounded-lg mb-6 text-sm flex items-center gap-3 border border-red-500/20">
                <i class="fas fa-exclamation-circle text-lg"></i>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="input-group">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" name="full_name" placeholder="Full Name" required 
                           class="input-field w-full bg-gray-900/50 border border-gray-600 rounded-lg p-3 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition text-white placeholder-gray-500">
                </div>
                <div class="input-group">
                    <i class="fas fa-at input-icon"></i>
                    <input type="text" name="username" placeholder="Username" required 
                           class="input-field w-full bg-gray-900/50 border border-gray-600 rounded-lg p-3 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition text-white placeholder-gray-500">
                </div>
            </div>

            <div class="input-group">
                <i class="fas fa-envelope input-icon"></i>
                <input type="email" name="email" placeholder="Email Address" required 
                       class="input-field w-full bg-gray-900/50 border border-gray-600 rounded-lg p-3 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition text-white placeholder-gray-500">
            </div>

            <div class="input-group">
                <i class="fas fa-phone input-icon"></i>
                <input type="text" name="phone" placeholder="Phone Number (Optional)" 
                       class="input-field w-full bg-gray-900/50 border border-gray-600 rounded-lg p-3 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition text-white placeholder-gray-500">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="input-group">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" placeholder="Password" required 
                           class="input-field w-full bg-gray-900/50 border border-gray-600 rounded-lg p-3 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition text-white placeholder-gray-500">
                </div>
                <div class="input-group">
                    <i class="fas fa-check-circle input-icon"></i>
                    <input type="password" name="confirm_password" placeholder="Confirm Pass" required 
                           class="input-field w-full bg-gray-900/50 border border-gray-600 rounded-lg p-3 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition text-white placeholder-gray-500">
                </div>
            </div>

            <div class="text-xs text-gray-500 ml-1">
                <i class="fas fa-info-circle mr-1"></i> Pass: 8+ chars, 1 Capital, 1 Number
            </div>

            <label class="flex items-center gap-3 cursor-pointer group mt-2">
                <input type="checkbox" name="terms" required 
                       class="w-5 h-5 rounded border-gray-600 bg-gray-900 text-blue-600 focus:ring-blue-500 cursor-pointer">
                <span class="text-sm text-gray-400 group-hover:text-gray-300 transition">
                    I accept the <a href="index.php?module=info&page=terms" target="_blank" class="text-blue-400 hover:underline">Terms & Policy</a>
                </span>
            </label>

            <button class="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white font-bold py-3.5 rounded-lg shadow-lg shadow-blue-900/30 transform transition active:scale-[0.98] mt-6">
                Create Account <i class="fas fa-arrow-right ml-2"></i>
            </button>
        </form>

        <p class="mt-8 text-center text-sm text-gray-400">
            Already have an account? <a href="index.php?module=auth&page=login" class="text-blue-400 font-bold hover:underline">Login here</a>
        </p>
    </div>
</body>
</html>