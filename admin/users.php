<?php
// admin/users.php
require_once '../includes/config.php';

if (!isset($_SESSION['admin_logged_in'])) header("Location: login.php");

// Fetch Users with Order Counts
$sql = "
    SELECT u.*, 
    (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as total_orders,
    (SELECT SUM(total_price_paid) FROM orders WHERE user_id = u.id AND status = 'active') as total_spent
    FROM users u 
    ORDER BY u.created_at DESC
";
$users = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Users</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-900 text-gray-100 p-8">
    <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-8">
            <h2 class="text-2xl font-bold">User Management</h2>
            <a href="index.php" class="text-gray-400 hover:text-white">Back to Dashboard</a>
        </div>

        <div class="bg-gray-800 rounded-xl overflow-hidden shadow-xl border border-gray-700">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-700 text-gray-400 uppercase font-bold">
                    <tr>
                        <th class="p-4">ID</th>
                        <th class="p-4">Username</th>
                        <th class="p-4">Email</th>
                        <th class="p-4 text-center">Orders</th>
                        <th class="p-4 text-right">Total Spent</th>
                        <th class="p-4">Joined</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    <?php foreach($users as $u): ?>
                        <tr class="hover:bg-gray-700/50 transition">
                            <td class="p-4 text-gray-500">#<?php echo $u['id']; ?></td>
                            <td class="p-4 font-bold text-white flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-blue-900 flex items-center justify-center text-xs">
                                    <?php echo strtoupper(substr($u['username'], 0, 2)); ?>
                                </div>
                                <?php echo htmlspecialchars($u['username']); ?>
                            </td>
                            <td class="p-4 text-blue-400"><?php echo htmlspecialchars($u['email']); ?></td>
                            <td class="p-4 text-center">
                                <span class="bg-gray-900 px-2 py-1 rounded text-gray-300"><?php echo $u['total_orders']; ?></span>
                            </td>
                            <td class="p-4 text-right font-mono text-green-400">
                                <?php echo number_format($u['total_spent'] ?: 0); ?> Ks
                            </td>
                            <td class="p-4 text-gray-500"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>