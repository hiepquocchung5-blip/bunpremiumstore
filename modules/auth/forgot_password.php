<?php
// modules/auth/forgot_password.php
require_once 'includes/MailService.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) die("Invalid CSRF");

    $email = trim($_POST['email']);
    
    // Check if email exists
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        
        // Store token in DB (You might need to add reset_token column to users table or reuse verify_token temporarily)
        // Ideally: ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) NULL;
        // For now, we will reuse verify_token for simplicity, or assume column exists.
        // Let's assume you run: ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) DEFAULT NULL;
        
        $update = $pdo->prepare("UPDATE users SET verify_token = ? WHERE id = ?"); // Reusing verify_token for demo
        $update->execute([$token, $user['id']]);

        // Send Email
        $mailer = new MailService();
        if ($mailer->sendPasswordReset($email, $token)) {
            $message = "Reset link sent to your email.";
        } else {
            $error = "Failed to send email. Try again later.";
        }
    } else {
        // Generic message for security
        $message = "If that email exists, we have sent a reset link.";
    }
}
?>

<div class="flex items-center justify-center min-h-[70vh] p-4">
    <div class="glass p-8 rounded-2xl max-w-md w-full border border-gray-700 shadow-2xl">
        <div class="text-center mb-6">
            <h2 class="text-2xl font-bold text-white">Reset Password</h2>
            <p class="text-gray-400 text-sm mt-2">Enter your email to receive a reset link.</p>
        </div>

        <?php if($message): ?>
            <div class="bg-green-500/20 text-green-400 p-4 rounded-lg mb-6 text-sm text-center border border-green-500/30">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="bg-red-500/20 text-red-400 p-4 rounded-lg mb-6 text-sm text-center border border-red-500/30">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Email Address</label>
                <input type="email" name="email" required class="w-full bg-gray-900 border border-gray-600 rounded-lg p-3 text-white focus:border-blue-500 outline-none transition placeholder-gray-600" placeholder="name@example.com">
            </div>

            <button class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-lg transition shadow-lg">
                Send Reset Link
            </button>
        </form>

        <div class="mt-6 text-center">
            <a href="index.php?module=auth&page=login" class="text-gray-500 hover:text-white text-sm transition">Back to Login</a>
        </div>
    </div>
</div>