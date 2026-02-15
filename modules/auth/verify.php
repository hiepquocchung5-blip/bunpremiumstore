<?php
// modules/auth/verify.php

$token = isset($_GET['token']) ? $_GET['token'] : '';
$status = 'error'; 
$message = 'Invalid request.';

if ($token) {
    // 1. Check Token against DB
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE verify_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        // 2. Activate Account & Clear Token
        // We set is_verified = 1 and NULL the token so it can't be reused
        $update = $pdo->prepare("UPDATE users SET is_verified = 1, verify_token = NULL WHERE id = ?");
        
        if ($update->execute([$user['id']])) {
            $status = 'success';
            $message = "Welcome, <strong>" . htmlspecialchars($user['username']) . "</strong>! Your email has been successfully verified.";
        } else {
            $message = "Database error during activation. Please contact support.";
        }
    } else {
        $message = "This verification link is invalid or has already been used.";
    }
} else {
    $message = "No verification token provided.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Account - DigitalMarketplaceMM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #0f172a; color: white; font-family: 'Inter', sans-serif; }
        .glass { 
            background: rgba(30, 41, 59, 0.75); 
            backdrop-filter: blur(16px); 
            border: 1px solid rgba(255, 255, 255, 0.08); 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); 
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4 bg-[url('https://images.unsplash.com/photo-1550745165-9bc0b252726f?q=80&w=2070&auto=format&fit=crop')] bg-cover bg-center">
    
    <div class="absolute inset-0 bg-slate-900/90 backdrop-blur-sm"></div>

    <div class="w-full max-w-md glass p-8 rounded-2xl relative z-10 animate-fade-in-down border-t border-gray-700/50 text-center">
        
        <?php if ($status === 'success'): ?>
            <!-- Success State -->
            <div class="w-20 h-20 bg-green-500/10 rounded-full flex items-center justify-center mx-auto mb-6 ring-1 ring-green-500/30 shadow-[0_0_20px_rgba(34,197,94,0.3)]">
                <i class="fas fa-check text-4xl text-green-500"></i>
            </div>
            
            <h2 class="text-3xl font-bold text-white mb-2 tracking-tight">Verified!</h2>
            <p class="text-slate-400 mb-8 leading-relaxed"><?php echo $message; ?></p>
            
            <a href="index.php?module=auth&page=login" class="block w-full bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-500 hover:to-emerald-500 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-green-900/20 transform transition active:scale-[0.98]">
                Login to Continue
            </a>

        <?php else: ?>
            <!-- Error State -->
            <div class="w-20 h-20 bg-red-500/10 rounded-full flex items-center justify-center mx-auto mb-6 ring-1 ring-red-500/30 shadow-[0_0_20px_rgba(239,68,68,0.3)]">
                <i class="fas fa-times text-4xl text-red-500"></i>
            </div>
            
            <h2 class="text-3xl font-bold text-white mb-2 tracking-tight">Verification Failed</h2>
            <p class="text-slate-400 mb-8 leading-relaxed"><?php echo $message; ?></p>
            
            <div class="space-y-3">
                <a href="index.php?module=auth&page=verify_resend" class="block w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3.5 rounded-xl transition shadow-lg">
                    Resend Verification Link
                </a>
                <a href="index.php" class="block w-full bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium py-3.5 rounded-xl transition">
                    Return Home
                </a>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>