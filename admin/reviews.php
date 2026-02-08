<?php
// admin/reviews.php

// 1. Handle Delete Review
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM reviews WHERE id = ?")->execute([$id]);
    redirect(admin_url('reviews', ['deleted' => 1]));
}

// 2. Fetch Reviews
$sql = "
    SELECT r.*, u.username, p.name as product_name 
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    JOIN products p ON r.product_id = p.id
    ORDER BY r.created_at DESC
";
$reviews = $pdo->query($sql)->fetchAll();

// Calculate Stats
$total_reviews = count($reviews);
$avg_rating = $total_reviews > 0 ? array_sum(array_column($reviews, 'rating')) / $total_reviews : 0;
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-bold text-white">Review Management</h1>
        <p class="text-slate-400 text-sm mt-1">Moderate user feedback and ratings.</p>
    </div>
    
    <div class="bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 flex items-center gap-3">
        <div class="text-center">
            <span class="block text-xs text-slate-500 uppercase font-bold">Total</span>
            <span class="font-mono text-white font-bold"><?php echo $total_reviews; ?></span>
        </div>
        <div class="h-8 w-px bg-slate-700"></div>
        <div class="text-center">
            <span class="block text-xs text-slate-500 uppercase font-bold">Avg Rating</span>
            <span class="font-mono text-yellow-400 font-bold"><?php echo number_format($avg_rating, 1); ?> <i class="fas fa-star text-[10px]"></i></span>
        </div>
    </div>
</div>

<?php if(isset($_GET['deleted'])): ?>
    <div class="bg-green-500/20 text-green-400 p-4 rounded-xl border border-green-500/50 mb-6 flex items-center gap-3">
        <i class="fas fa-check-circle"></i> Review deleted successfully.
    </div>
<?php endif; ?>

<div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden shadow-lg">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-700/50 text-slate-400 uppercase text-xs">
                <tr>
                    <th class="p-4 pl-6">Product</th>
                    <th class="p-4">Customer</th>
                    <th class="p-4">Rating</th>
                    <th class="p-4 w-1/3">Comment</th>
                    <th class="p-4 text-right">Date</th>
                    <th class="p-4 text-right pr-6">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700">
                <?php foreach($reviews as $r): ?>
                    <tr class="hover:bg-slate-700/30 transition group">
                        <td class="p-4 pl-6 font-medium text-white"><?php echo htmlspecialchars($r['product_name']); ?></td>
                        <td class="p-4 text-slate-300"><?php echo htmlspecialchars($r['username']); ?></td>
                        <td class="p-4">
                            <div class="flex text-yellow-500 text-xs">
                                <?php for($i=1; $i<=5; $i++) echo ($i <= $r['rating']) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star text-slate-600"></i>'; ?>
                            </div>
                        </td>
                        <td class="p-4 text-slate-400 italic">"<?php echo htmlspecialchars($r['comment']); ?>"</td>
                        <td class="p-4 text-right text-slate-500 text-xs">
                            <?php echo date('M d, Y', strtotime($r['created_at'])); ?>
                        </td>
                        <td class="p-4 text-right pr-6">
                            <a href="<?php echo admin_url('reviews', ['delete' => $r['id']]); ?>" 
                               class="text-slate-500 hover:text-red-400 transition p-2 rounded hover:bg-slate-700" 
                               onclick="return confirm('Delete this review?')"
                               title="Delete Review">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if(empty($reviews)): ?>
                    <tr><td colspan="6" class="p-8 text-center text-slate-500">No reviews found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>