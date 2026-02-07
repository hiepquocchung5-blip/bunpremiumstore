<?php
// modules/auth/verify.php

$token = isset($_GET['token']) ? $_GET['token'] : '';
$message = '';
$status = 'error'; // success or error

if ($token) {
    // Check Token
    $stmt = $pdo->prepare("SELECT id FROM users WHERE verify_token = ? AND is_verified = 0");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        // Verify User
        $update = $pdo->prepare("UPDATE users SET is_verified = 1, verify_token = NULL WHERE id = ?");
        if ($update->execute([$user['id']])) {
            $status = 'success';
            $message = "Email Verified Successfully! You can now login.";
        } else {
            $message = "Database error occurred.";
        }
    } else {
        $message = "Invalid or expired verification token.";
    }
} else {
    $message = "No token provided.";
}
?>

<div class="flex items-center justify-center min-h-[60vh] p-4">
    <div class="glass p-8 rounded-2xl max-w-md w-full text-center border border-gray-700 shadow-2xl">
        
        <?php if ($status === 'success'): ?>
            <div class="w-20 h-20 bg-green-500/20 rounded-full flex items-center justify-center mx-auto mb-6 text-green-500">
                <i class="fas fa-check-circle text-4xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-white mb-2">Verified!</h2>
            <p class="text-gray-400 mb-8"><?php echo $message; ?></p>
            <a href="index.php?module=auth&page=login" class="block w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-xl transition">
                Go to Login
            </a>
        <?php else: ?>
            <div class="w-20 h-20 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-6 text-red-500">
                <i class="fas fa-times-circle text-4xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-white mb-2">Verification Failed</h2>
            <p class="text-gray-400 mb-8"><?php echo $message; ?></p>
            <a href="index.php" class="block w-full bg-gray-700 hover:bg-gray-600 text-white font-bold py-3 rounded-xl transition">
                Back to Home
            </a>
        <?php endif; ?>

    </div>
</div>