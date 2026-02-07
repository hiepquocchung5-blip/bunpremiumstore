<?php
// modules/user/agent.php

// Buy Pass Logic
if (isset($_GET['buy_pass'])) {
    $pass_id = (int)$_GET['buy_pass'];
    
    // 1. Fetch Pass
    $stmt = $pdo->prepare("SELECT * FROM passes WHERE id = ?");
    $stmt->execute([$pass_id]);
    $pass = $stmt->fetch();
    
    if ($pass) {
        // In a real app, this would redirect to a Payment Gateway or specific Checkout
        // For simulation, we create an order immediately here or redirect to checkout
        // Let's redirect to a generic checkout with type 'pass' logic (simplified here)
        $expiry = date('Y-m-d H:i:s', strtotime("+{$pass['duration_days']} days"));
        
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
    SELECT up.*, p.name, p.discount_percent 
    FROM user_passes up 
    JOIN passes p ON up.pass_id = p.id 
    WHERE up.user_id = ? AND up.status = 'active' AND up.expires_at > NOW()
");
$stmt->execute([$_SESSION['user_id']]);
$active_pass = $stmt->fetch();
?>

<div class="text-center mb-10">
    <h2 class="text-4xl font-bold mb-4 text-transparent bg-clip-text bg-gradient-to-r from-yellow-200 to-yellow-500">Agent Hub</h2>
    <p class="text-gray-400">Unlock wholesale prices and resell to your clients.</p>
</div>

<?php if(isset($_GET['success'])): ?>
    <div class="bg-green-600 text-white p-4 rounded text-center mb-6 animate-pulse">
        Pass Activated Successfully! Discount applied.
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
    <?php foreach($passes as $pass): ?>
        <?php 
            $isActive = $active_pass && $active_pass['pass_id'] == $pass['id'];
        ?>
        <div class="glass p-8 rounded-xl border-2 <?php echo $isActive ? 'border-green-500' : 'border-yellow-500'; ?> relative transform hover:scale-105 transition shadow-2xl">
            <h3 class="text-2xl font-bold text-yellow-400"><?php echo $pass['name']; ?></h3>
            <p class="text-4xl font-bold text-white my-4">
                <?php echo format_price($pass['price']); ?>
                <span class="text-sm text-gray-400 font-normal">/<?php echo $pass['duration_days']; ?> days</span>
            </p>
            
            <div class="my-6 space-y-3 text-sm text-gray-300">
                <div class="flex items-center gap-3">
                    <i class="fas fa-percent text-yellow-500"></i>
                    <span><strong><?php echo $pass['discount_percent']; ?>% OFF</strong> Global Discount</span>
                </div>
                <div class="flex items-center gap-3">
                    <i class="fas fa-rocket text-yellow-500"></i>
                    <span>Instant Delivery</span>
                </div>
            </div>

            <?php if ($isActive): ?>
                <button disabled class="w-full bg-green-600 text-white py-3 rounded font-bold cursor-default">
                    <i class="fas fa-check-circle"></i> Active (Exp: <?php echo date('M d', strtotime($active_pass['expires_at'])); ?>)
                </button>
            <?php else: ?>
                <!-- Direct link for demo purposes. In production, use POST form -->
                <a href="index.php?module=user&page=agent&buy_pass=<?php echo $pass['id']; ?>" class="block text-center w-full bg-yellow-600 hover:bg-yellow-700 text-black font-bold py-3 rounded transition">
                    Get Started
                </a>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>