<?php
// modules/auth/register.php
require_once 'includes/MailService.php'; // Include MailService

if (is_logged_in()) redirect('index.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) die("Invalid CSRF Token");

    $fullname = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']); 
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
            $token = bin2hex(random_bytes(32)); // Verification Token
            
            // Insert into DB
            $stmt = $pdo->prepare("INSERT INTO users (full_name, username, email, phone, password, verify_token, is_verified) VALUES (?, ?, ?, ?, ?, ?, 0)");
            $phoneVal = empty($phone) ? null : $phone;
            
            if ($stmt->execute([$fullname, $username, $email, $phoneVal, $hashed, $token])) {
                
                // Send Verification Email
                $mailer = new MailService();
                $mailer->sendVerificationEmail($email, $token);

                // Redirect to Login with Email param to trigger Popup
                header("Location: index.php?module=auth&page=login&registered=1&email=" . urlencode($email));
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
    <title>Join DigitalMarketplaceMM</title>
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
<body class="flex items-center justify-center min-h-screen p-4 bg-[url('https://images.unsplash.com/photo-1614853316476-de00d14cb1fc?q=80&w=2070&auto=format&fit=crop')] bg-cover bg-center">
    
    <div class="absolute inset-0 bg-gray-900/90 backdrop-blur-sm"></div>

    <div class="w-full max-w-lg glass p-8 rounded-2xl relative z-10 animate-fade-in-up border-t border-gray-700">
        
        <div class="text-center mb-8">
            <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-blue-600/20">
                <i class="fas fa-user-plus text-xl text-white"></i>
            </div>
            <h2 class="text-3xl font-bold tracking-tight">Create Account</h2>
            <p class="text-gray-400 mt-2 text-sm">Join the premium digital community</p>
        </div>
        
        <?php if($error): ?>
            <div class="bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-xl mb-6 text-sm flex items-center gap-3">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="input-group">
                    <input type="text" name="full_name" placeholder="Full Name" required 
                           class="input-field w-full bg-gray-900/50 border border-gray-600 rounded-xl p-3 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                    <i class="fas fa-user input-icon"></i>
                </div>
                <div class="input-group">
                    <input type="text" name="username" placeholder="Username" required 
                           class="input-field w-full bg-gray-900/50 border border-gray-600 rounded-xl p-3 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                    <i class="fas fa-at input-icon"></i>
                </div>
            </div>

            <div class="input-group">
                <input type="email" name="email" placeholder="Email Address" required 
                       class="input-field w-full bg-gray-900/50 border border-gray-600 rounded-xl p-3 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                <i class="fas fa-envelope input-icon"></i>
            </div>

            <div class="input-group">
                <input type="tel" name="phone" placeholder="Phone (Optional)" 
                       class="input-field w-full bg-gray-900/50 border border-gray-600 rounded-xl p-3 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                <i class="fas fa-phone input-icon"></i>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="input-group">
                    <input type="password" name="password" placeholder="Password" required 
                           class="input-field w-full bg-gray-900/50 border border-gray-600 rounded-xl p-3 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                    <i class="fas fa-lock input-icon"></i>
                </div>
                <div class="input-group">
                    <input type="password" name="confirm_password" placeholder="Confirm" required 
                           class="input-field w-full bg-gray-900/50 border border-gray-600 rounded-xl p-3 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                    <i class="fas fa-check-double input-icon"></i>
                </div>
            </div>

            <label class="flex items-center gap-3 cursor-pointer group mt-2 select-none">
                <input type="checkbox" name="terms" required class="w-5 h-5 rounded border-gray-600 bg-gray-900 text-blue-600 focus:ring-blue-500 cursor-pointer">
                <span class="text-sm text-gray-400 group-hover:text-gray-300 transition">
                    I accept the <a href="#" class="text-blue-400 hover:underline">Terms</a> & <a href="#" class="text-blue-400 hover:underline">Privacy</a>
                </span>
            </label>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3.5 rounded-xl shadow-lg transition transform active:scale-[0.98] mt-6">
                Register Account
            </button>
        </form>

        <div class="mt-6 text-center pt-6 border-t border-gray-700/50">
            <p class="text-sm text-gray-400">
                Already have an account? <a href="index.php?module=auth&page=login" class="text-blue-400 font-bold hover:underline">Log in</a>
            </p>
        </div>
    </div>
</body>
</html>