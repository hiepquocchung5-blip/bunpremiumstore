<?php
// modules/user/invoice.php

// 1. Auth Guard
if (!is_logged_in()) redirect('index.php?module=auth&page=login');

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 2. Fetch Order Data
$stmt = $pdo->prepare("
    SELECT o.*, p.name as product_name, p.price as original_price, u.full_name, u.email, u.phone
    FROM orders o 
    JOIN products p ON o.product_id = p.id 
    JOIN users u ON o.user_id = u.id
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    die("<div class='p-10 text-center text-white'>Order not found or access denied.</div>");
}

// 3. Status Badge Helper
$status_color = match($order['status']) {
    'active' => 'green',
    'rejected' => 'red',
    default => 'yellow'
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #<?php echo $order['id']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; color: #1f2937; }
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .invoice-box { box-shadow: none !important; border: 1px solid #ddd !important; }
        }
    </style>
</head>
<body class="p-8">

    <!-- Actions -->
    <div class="max-w-3xl mx-auto mb-6 flex justify-between items-center no-print">
        <a href="index.php?module=user&page=orders" class="text-gray-500 hover:text-gray-800 transition">
            <i class="fas fa-arrow-left mr-2"></i> Back to Orders
        </a>
        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow-lg transition flex items-center gap-2">
            <i class="fas fa-print"></i> Print Invoice
        </button>
    </div>

    <!-- Invoice Container -->
    <div class="invoice-box max-w-3xl mx-auto bg-white p-10 rounded-xl shadow-xl border border-gray-200">
        
        <!-- Header -->
        <div class="flex justify-between items-start border-b border-gray-200 pb-8 mb-8">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-8 h-8 bg-blue-600 rounded flex items-center justify-center text-white">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <span class="text-xl font-bold tracking-tight">DigitalMarketplace<span class="text-blue-600">MM</span></span>
                </div>
                <p class="text-sm text-gray-500">Premium Digital Goods Store</p>
                <p class="text-sm text-gray-500">Yangon, Myanmar</p>
                <p class="text-sm text-gray-500">support@digitalmarketplacemm.com</p>
            </div>
            <div class="text-right">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">INVOICE</h1>
                <p class="text-sm text-gray-500">Reference: <span class="font-mono text-gray-800">#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></span></p>
                <p class="text-sm text-gray-500">Date: <span class="font-medium text-gray-800"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></span></p>
                
                <div class="mt-2 inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold bg-<?php echo $status_color; ?>-100 text-<?php echo $status_color; ?>-700 uppercase tracking-wide">
                    <?php echo ucfirst($order['status']); ?>
                </div>
            </div>
        </div>

        <!-- Bill To -->
        <div class="mb-8">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Bill To</h3>
            <p class="font-bold text-gray-800 text-lg"><?php echo htmlspecialchars($order['full_name']); ?></p>
            <p class="text-gray-600"><?php echo htmlspecialchars($order['email']); ?></p>
            <p class="text-gray-600"><?php echo htmlspecialchars($order['phone']); ?></p>
        </div>

        <!-- Items Table -->
        <table class="w-full mb-8">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="text-left py-3 px-4 text-xs font-bold text-gray-500 uppercase">Item Description</th>
                    <th class="text-right py-3 px-4 text-xs font-bold text-gray-500 uppercase">Type</th>
                    <th class="text-right py-3 px-4 text-xs font-bold text-gray-500 uppercase">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="py-4 px-4 text-gray-800 border-b border-gray-100">
                        <p class="font-bold"><?php echo htmlspecialchars($order['product_name']); ?></p>
                        <p class="text-xs text-gray-500 mt-1">Delivery via: <?php echo ucfirst($order['email_delivery_type']); ?></p>
                    </td>
                    <td class="py-4 px-4 text-right text-gray-600 text-sm border-b border-gray-100 uppercase">Digital</td>
                    <td class="py-4 px-4 text-right text-gray-800 font-bold border-b border-gray-100">
                        <?php echo number_format($order['total_price_paid']); ?> Ks
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Totals -->
        <div class="flex justify-end">
            <div class="w-64 space-y-2">
                <div class="flex justify-between text-sm text-gray-600">
                    <span>Subtotal</span>
                    <span><?php echo number_format($order['original_price']); ?> Ks</span>
                </div>
                <div class="flex justify-between text-sm text-green-600">
                    <span>Discount</span>
                    <span>- <?php echo number_format($order['original_price'] - $order['total_price_paid']); ?> Ks</span>
                </div>
                <div class="flex justify-between text-lg font-bold text-gray-900 border-t border-gray-200 pt-2 mt-2">
                    <span>Total Paid</span>
                    <span><?php echo number_format($order['total_price_paid']); ?> Ks</span>
                </div>
            </div>
        </div>

        <!-- Footer Info -->
        <div class="mt-12 pt-6 border-t border-gray-200 text-center text-xs text-gray-400">
            <p class="mb-1">Payment Method: <?php echo $order['transaction_last_6'] ? 'Manual Transfer (Txn: '.$order['transaction_last_6'].')' : 'Wallet/Unknown'; ?></p>
            <p>Thank you for your business. For support, contact us on Telegram @bunxmk.</p>
        </div>

    </div>
</body>
</html>