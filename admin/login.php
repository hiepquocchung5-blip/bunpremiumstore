<?php
// admin/login.php
require_once 'config/db.php';

// 1. Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: index.php");
    exit;
}

$error = '';

// 2. Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Credentials required.";
    } else {
        // Fetch Admin
        $stmt = $pdo->prepare("SELECT * FROM adm_user WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        // Verify Hash
        if ($admin && password_verify($password, $admin['password'])) {
            // Set Session
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_role'] = $admin['role'];

            // Update Login Time
            $pdo->prepare("UPDATE adm_user SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);

            // Redirect to Router (Dashboard)
            header("Location: index.php");
            exit;
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Access - ScottSub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #0f172a; color: white; }
        .glass-panel {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4 bg-[url('https://images.unsplash.com/photo-1642425149556-b6f90e946859?q=80&w=2070&auto=format&fit=crop')] bg-cover bg-center">
    
    <!-- Dark Overlay -->
    <div class="absolute inset-0 bg-slate-950/80 backdrop-blur-sm"></div>

    <div class="w-full max-w-sm glass-panel p-8 rounded-2xl relative z-10 border-t border-slate-600">
        <div class="text-center mb-8">
            <div class="w-14 h-14 bg-gradient-to-br from-red-600 to-red-800 rounded-xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-red-900/50">
                <i class="fas fa-user-shield text-2xl text-white"></i>
            </div>
            <h2 class="text-xl font-bold tracking-wide text-white">Admin Portal</h2>
            <p class="text-slate-400 text-xs mt-1">Secure Environment</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-lg mb-6 text-xs font-medium flex items-center gap-3 animate-pulse">
                <i class="fas fa-ban"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div class="space-y-1">
                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">ID</label>
                <div class="relative">
                    <i class="fas fa-fingerprint absolute left-3 top-3 text-slate-500 text-sm"></i>
                    <input type="text" name="username" required 
                           class="w-full bg-slate-900/50 border border-slate-600/50 rounded-lg py-2.5 pl-10 pr-4 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 transition shadow-inner"
                           placeholder="Username">
                </div>
            </div>

            <div class="space-y-1">
                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Key</label>
                <div class="relative">
                    <i class="fas fa-key absolute left-3 top-3 text-slate-500 text-sm"></i>
                    <input type="password" name="password" required 
                           class="w-full bg-slate-900/50 border border-slate-600/50 rounded-lg py-2.5 pl-10 pr-4 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 transition shadow-inner"
                           placeholder="Password">
                </div>
            </div>

            <button type="submit" class="w-full bg-gradient-to-r from-red-600 to-red-700 hover:from-red-500 hover:to-red-600 text-white font-bold py-3 rounded-lg shadow-lg shadow-red-900/30 transition transform active:scale-[0.98] flex items-center justify-center gap-2 text-sm mt-4">
                <span>Authenticate</span> <i class="fas fa-arrow-right"></i>
            </button>
        </form>

        <div class="mt-8 text-center">
            <a href="../index.php" class="text-slate-500 hover:text-white text-xs transition">
                &larr; Return to Store
            </a>
        </div>
    </div>

</body>
</html>