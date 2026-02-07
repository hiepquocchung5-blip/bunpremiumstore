<?php
// modules/user/agent.php

// Auth Guard
if (!is_logged_in()) redirect('index.php?module=auth&page=login');

// Handle Buy Pass
if (isset($_GET['buy_pass'])) {
    $pass_id = (int)$_GET['buy_pass'];
    
    // Validate Pass
    $stmt = $pdo->prepare("SELECT * FROM passes WHERE id = ? AND is_active = 1");
    $stmt->execute([$pass_id]);
    $pass = $stmt->fetch();
    
    if ($pass) {
        // Create Active Pass Entry
        // In a real app, this would go through checkout. Here we simulate instant activation.
        $expiry = date('Y-m-d H:i:s', strtotime("+{$pass['duration_days']} days"));
        
        // Deactivate old passes
        $pdo->prepare("UPDATE user_passes SET status = 'expired' WHERE user_id = ?")->execute([$_SESSION['user_id']]);
        
        // Insert new
        $stmt = $pdo->prepare("INSERT INTO user_passes (user_id, pass_id, expires_at, status) VALUES (?, ?, ?, 'active')");
        $stmt->execute([$_SESSION['user_id'], $pass['id'], $expiry]);
        
        redirect('index.php?module=user&page=agent&success=1');
    }
}

// Fetch Available Passes
$stmt = $pdo->query("SELECT * FROM passes WHERE is_active = 1");
$passes = $stmt->fetchAll();

// Fetch Current User Pass
$stmt = $pdo->prepare("
    SELECT up.*, p.name, p.discount_percent, p.description 
    FROM user_passes up 
    JOIN passes p ON up.pass_id = p.id 
    WHERE up.user_id = ? AND up.status = 'active' AND up.expires_at > NOW()
");
$stmt->execute([$_SESSION['user_id']]);
$active_pass = $stmt->fetch();
?>

<div class="max-w-6xl mx-auto">
    
    <!-- Header Section -->
    <div class="text-center mb-12 relative">
        <div class="absolute inset-0 flex items-center justify-center opacity-10 pointer-events-none">
            <i class="fas fa-crown text-9xl text-yellow-500 blur-xl"></i>
        </div>
        <h1 class="text-4xl md:text-5xl font-bold mb-4 text-transparent bg-clip-text bg-gradient-to-r from-yellow-200 via-yellow-400 to-yellow-600 drop-shadow-sm">
            Agent Partnership
        </h1>
        <p class="text-gray-400 max-w-2xl mx-auto text-lg">
            Unlock wholesale pricing on all digital products. Start your own reselling business today with our premium membership tiers.
        </p>
    </div>

    <?php if(isset($_GET['success'])): ?>
        <div class="max-w-2xl mx-auto mb-10 bg-gradient-to-r from-green-900/50 to-green-800/50 border border-green-500/50 text-green-200 p-6 rounded-2xl flex items-center gap-4 shadow-lg animate-fade-in-up">
            <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center text-white shadow-lg shrink-0">
                <i class="fas fa-check text-xl"></i>
            </div>
            <div>
                <h4 class="font-bold text-lg">Membership Activated!</h4>
                <p class="text-sm opacity-90">Your account has been upgraded. Enjoy your new discounts.</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Plans Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 relative z-10">
        
        <!-- Standard / Free Tier Info -->
        <div class="glass p-8 rounded-3xl border border-gray-700/50 flex flex-col grayscale opacity-70 hover:opacity-100 hover:grayscale-0 transition duration-500">
            <div class="mb-6">
                <span class="text-xs font-bold tracking-widest text-gray-500 uppercase">Current Status</span>
                <h3 class="text-2xl font-bold text-white mt-1">Standard User</h3>
            </div>
            <ul class="space-y-4 mb-8 flex-1">
                <li class="flex items-center gap-3 text-gray-400">
                    <i class="fas fa-times-circle text-gray-600"></i> No Discounts
                </li>
                <li class="flex items-center gap-3 text-gray-400">
                    <i class="fas fa-check-circle text-blue-500"></i> Basic Support
                </li>
                <li class="flex items-center gap-3 text-gray-400">
                    <i class="fas fa-check-circle text-blue-500"></i> Instant Delivery
                </li>
            </ul>
            <button disabled class="w-full py-3 rounded-xl border border-gray-600 text-gray-500 font-bold cursor-not-allowed">
                Basic Plan
            </button>
        </div>

        <!-- Premium Passes -->
        <?php foreach($passes as $pass): ?>
            <?php 
                $isActive = $active_pass && $active_pass['pass_id'] == $pass['id'];
                $isUpgrade = !$active_pass || $active_pass['discount_percent'] < $pass['discount_percent'];
            ?>
            <div class="relative group">
                <!-- Glow Effect for Premium -->
                <div class="absolute -inset-0.5 bg-gradient-to-r from-yellow-600 to-yellow-300 rounded-3xl blur opacity-30 group-hover:opacity-75 transition duration-500"></div>
                
                <div class="relative glass p-8 rounded-3xl border border-yellow-500/30 flex flex-col h-full bg-gray-900/90">
                    <?php if($isUpgrade && !$isActive): ?>
                        <div class="absolute top-0 right-0 bg-gradient-to-r from-yellow-500 to-yellow-600 text-black text-xs font-bold px-4 py-1.5 rounded-bl-2xl rounded-tr-2xl shadow-lg">
                            BEST VALUE
                        </div>
                    <?php endif; ?>

                    <div class="mb-6">
                        <span class="text-xs font-bold tracking-widest text-yellow-500 uppercase flex items-center gap-2">
                            <i class="fas fa-star"></i> Premium Tier
                        </span>
                        <h3 class="text-3xl font-bold text-white mt-2"><?php echo htmlspecialchars($pass['name']); ?></h3>
                        <div class="mt-4 flex items-baseline gap-1">
                            <span class="text-4xl font-bold text-white"><?php echo number_format($pass['price']); ?></span>
                            <span class="text-sm text-gray-400">Ks / <?php echo $pass['duration_days']; ?> Days</span>
                        </div>
                    </div>

                    <div class="space-y-4 mb-8 flex-1">
                        <div class="flex items-center gap-4 bg-gray-800/50 p-3 rounded-xl border border-yellow-500/10">
                            <div class="w-10 h-10 rounded-full bg-yellow-500/10 flex items-center justify-center text-yellow-400 font-bold text-lg border border-yellow-500/20">
                                <?php echo $pass['discount_percent']; ?>%
                            </div>
                            <div>
                                <p class="text-sm text-gray-300 font-medium">Store-wide Discount</p>
                                <p class="text-xs text-gray-500">Applied automatically</p>
                            </div>
                        </div>
                        
                        <div class="text-sm text-gray-400 leading-relaxed pl-2">
                            <?php echo nl2br(htmlspecialchars($pass['description'])); ?>
                        </div>
                    </div>

                    <?php if ($isActive): ?>
                        <div class="w-full py-4 rounded-xl bg-green-900/30 border border-green-500/30 text-green-400 font-bold text-center flex flex-col items-center justify-center">
                            <span class="flex items-center gap-2"><i class="fas fa-check-circle"></i> Currently Active</span>
                            <span class="text-xs font-normal mt-1 opacity-80">Expires: <?php echo date('M d, Y', strtotime($active_pass['expires_at'])); ?></span>
                        </div>
                    <?php else: ?>
                        <a href="index.php?module=user&page=agent&buy_pass=<?php echo $pass['id']; ?>" 
                           onclick="return confirm('Activate <?php echo $pass['name']; ?> for <?php echo number_format($pass['price']); ?> Ks?')"
                           class="block w-full py-4 rounded-xl bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-400 hover:to-yellow-500 text-black font-bold text-center shadow-lg transform hover:-translate-y-1 transition duration-200">
                            Upgrade Now
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Enterprise / Custom -->
        <div class="glass p-8 rounded-3xl border border-gray-700/50 flex flex-col text-center justify-center grayscale opacity-70">
            <i class="fas fa-building text-5xl text-gray-600 mb-4"></i>
            <h3 class="text-xl font-bold text-white">Enterprise</h3>
            <p class="text-gray-400 text-sm mt-2 mb-6">Need API access or bulk automated keys?</p>
            <a href="index.php?module=info&page=support" class="text-blue-400 hover:text-white font-bold text-sm transition">Contact Support &rarr;</a>
        </div>

    </div>
</div>