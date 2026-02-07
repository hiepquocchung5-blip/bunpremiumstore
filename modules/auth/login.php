<?php
// modules/auth/login.php

// Redirect if already logged in
if (is_logged_in()) redirect('index.php');

$error = '';
$success = '';

// Check for success message from registration redirect
if (isset($_GET['registered'])) {
    $success = "Account created successfully! Please login.";
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Fix: Ensure tokens are strings to prevent TypeError if session expired
    $session_token = $_SESSION['csrf_token'] ?? '';
    $post_token = $_POST['csrf_token'] ?? '';

    if (empty($session_token) || !hash_equals($session_token, $post_token)) {
        die("Invalid CSRF Token. Please refresh the page and try again.");
    }

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Fetch User
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Verify Password
    if ($user && password_verify($password, $user['password'])) {
        // Set Session Variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['username'];
        $_SESSION['user_email'] = $user['email'];
        
        // Handle Redirect (Back to previous page or Home)
        $redirect_url = isset($_GET['redirect']) ? urldecode($_GET['redirect']) : 'index.php';
        header("Location: " . $redirect_url);
        exit;
    } else {
        $error = "Invalid email or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - ScottSub</title>
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
    <!-- Dark Overlay -->
    <div class="absolute inset-0 bg-gray-900/90 backdrop-blur-sm"></div>

    <div class="w-full max-w-md glass p-8 rounded-2xl shadow-2xl relative z-10 border border-gray-700">
        <!-- Logo Header -->
        <div class="text-center mb-8">
            <div class="w-12 h-12 bg-blue-600 rounded-lg flex items-center justify-center mx-auto mb-4 shadow-lg shadow-blue-500/30">
                <i class="fas fa-bolt text-2xl text-white"></i>
            </div>
            <h2 class="text-3xl font-bold">Welcome Back</h2>
            <p class="text-gray-400 mt-2">Login to manage your subscriptions</p>
        </div>
        
        <!-- Error Message -->
        <?php if($error): ?>
            <div class="bg-red-500/10 text-red-400 p-4 rounded-lg mb-6 text-sm flex items-center gap-3 border border-red-500/20 animate-pulse">
                <i class="fas fa-exclamation-circle text-lg"></i>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <!-- Success Message -->
        <?php if($success): ?>
            <div class="bg-green-500/10 text-green-400 p-4 rounded-lg mb-6 text-sm flex items-center gap-3 border border-green-500/20">
                <i class="fas fa-check-circle text-lg"></i>
                <span><?php echo $success; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="input-group">
                <i class="fas fa-envelope input-icon"></i>
                <input type="email" name="email" placeholder="Email Address" required 
                       class="input-field w-full bg-gray-900/50 border border-gray-600 rounded-lg p-3.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition text-white placeholder-gray-500 shadow-inner">
            </div>

            <div class="input-group">
                <i class="fas fa-lock input-icon"></i>
                <input type="password" name="password" placeholder="Password" required 
                       class="input-field w-full bg-gray-900/50 border border-gray-600 rounded-lg p-3.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition text-white placeholder-gray-500 shadow-inner">
            </div>

            <div class="flex justify-between items-center text-sm">
                <label class="flex items-center gap-2 cursor-pointer group">
                    <input type="checkbox" name="remember" class="w-4 h-4 rounded border-gray-600 bg-gray-900 text-blue-600 focus:ring-blue-500 cursor-pointer">
                    <span class="text-gray-400 group-hover:text-gray-300 transition">Remember me</span>
                </label>
                <a href="#" class="text-blue-400 hover:text-blue-300 transition">Forgot Password?</a>
            </div>

            <button class="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white font-bold py-3.5 rounded-lg shadow-lg shadow-blue-900/30 transform transition active:scale-[0.98] mt-2">
                Sign In <i class="fas fa-sign-in-alt ml-2"></i>
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-gray-700/50 text-center">
            <p class="text-sm text-gray-400 mb-4">
                Don't have an account? <a href="index.php?module=auth&page=register" class="text-blue-400 font-bold hover:underline">Register now</a>
            </p>
            <a href="index.php" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-white transition">
                <i class="fas fa-arrow-left"></i> Back to Store
            </a>
        </div>
    </div>
</body>
</html>