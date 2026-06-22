<?php
// modules/user/notifications.php

// 1. Security: Protect Route
if (!is_logged_in()) {
    redirect('index.php?module=auth&page=login');
}

$user_id = $_SESSION['user_id'];

// 2. Clear All Notifications Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_all'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) die("Invalid CSRF Token");
    
    $stmt = $pdo->prepare("DELETE FROM user_notifications WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // Invalidate Cache
    if (function_exists('matrix_cache_delete')) {
        matrix_cache_delete("user_unread_notif_count_{$user_id}");
    }
    
    $success = "All notifications cleared.";
}

// 3. Mark all as read when opening notifications page
try {
    $pdo->prepare("UPDATE user_notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")->execute([$user_id]);
    // Invalidate Cache
    if (function_exists('matrix_cache_delete')) {
        matrix_cache_delete("user_unread_notif_count_{$user_id}");
    }
} catch (PDOException $e) {}

// 4. Fetch User Notifications
$stmt = $pdo->prepare("SELECT * FROM user_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();
?>

<div class="max-w-4xl mx-auto px-6 py-12">
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-10 gap-6">
        <div>
            <h1 class="text-3xl md:text-4xl font-bold text-white tracking-tight flex items-center gap-3">
                <i class="fas fa-bell text-blue-500"></i> Notification Feed
            </h1>
            <p class="text-slate-400 mt-2 text-sm">Stay updated with order fulfillments, pass status, and announcements.</p>
        </div>
        
        <?php if(!empty($notifications)): ?>
            <form method="POST" onsubmit="return confirm('Are you sure you want to clear all notifications?');">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <button type="submit" name="clear_all" class="bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 px-5 py-2.5 rounded-xl text-xs font-bold transition-all active:scale-95 flex items-center gap-2">
                    <i class="fas fa-trash-alt"></i> Clear All
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if(isset($success)): ?>
        <div class="bg-green-500/10 border border-green-500/20 text-green-400 p-4 rounded-2xl text-sm mb-6 flex items-center gap-2">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <!-- Notification Feed List -->
    <div class="space-y-4">
        <?php if(empty($notifications)): ?>
            <div class="bg-slate-800/20 border border-white/5 p-20 rounded-[2.5rem] text-center shadow-2xl">
                <div class="w-20 h-20 bg-blue-600/10 rounded-full flex items-center justify-center mx-auto mb-6 text-[#00f0ff] border border-blue-500/20">
                    <i class="fas fa-bell-slash text-2xl"></i>
                </div>
                <h3 class="text-lg font-bold text-white mb-2">All Caught Up!</h3>
                <p class="text-slate-500 text-sm max-w-sm mx-auto">You have no active notifications. When we fulfill your orders, you'll see them here.</p>
            </div>
        <?php else: ?>
            <?php foreach($notifications as $notif): 
                $has_link = !empty($notif['url']);
                $card_bg = $notif['is_read'] ? 'bg-slate-800/10 hover:bg-slate-800/20' : 'bg-blue-600/5 border-blue-500/20 hover:bg-blue-600/10';
            ?>
                <div class="<?php echo $card_bg; ?> border border-white/5 p-6 rounded-3xl transition-all flex justify-between items-start gap-4 shadow-sm group">
                    <div class="flex gap-4">
                        <div class="w-10 h-10 rounded-xl bg-blue-600/10 flex items-center justify-center text-blue-400 border border-blue-500/20 shrink-0 mt-0.5">
                            <i class="fas fa-info-circle text-base"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-white text-sm md:text-base flex items-center gap-2">
                                <?php echo htmlspecialchars($notif['title']); ?>
                                <?php if(!$notif['is_read']): ?>
                                    <span class="inline-block w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span>
                                <?php endif; ?>
                            </h4>
                            <p class="text-slate-400 text-xs md:text-sm mt-1 leading-relaxed"><?php echo htmlspecialchars($notif['body']); ?></p>
                            
                            <?php if($has_link): ?>
                                <a href="<?php echo htmlspecialchars($notif['url']); ?>" class="inline-flex items-center gap-1.5 text-xs text-blue-400 hover:text-blue-300 font-bold mt-3 transition hover:underline">
                                    View Details <i class="fas fa-arrow-right text-[10px]"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <span class="text-[10px] text-slate-500 font-mono shrink-0 whitespace-nowrap">
                        <?php 
                            // Quick relative time calculation
                            $diff = time() - strtotime($notif['created_at']);
                            if ($diff < 60) {
                                echo 'Just now';
                            } elseif ($diff < 3600) {
                                echo floor($diff / 60) . 'm ago';
                            } elseif ($diff < 86400) {
                                echo floor($diff / 3600) . 'h ago';
                            } else {
                                echo date('M d', strtotime($notif['created_at']));
                            }
                        ?>
                    </span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
</div>
