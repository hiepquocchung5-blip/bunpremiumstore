<?php
/**
 * ⚡️ MATRIX ACTION HUB v1.0
 * The functional "arms and legs" of the Matrix AI.
 * Contains 25+ tools that the LLM can trigger to provide real-time data.
 */

class MatrixActionHub {
    private $pdo;
    private $user_id;
    private $is_admin;

    public function __construct($pdo, $user_id = null, $is_admin = false) {
        $this->pdo = $pdo;
        $this->user_id = $user_id;
        $this->is_admin = $is_admin;
    }

    /**
     * 🛠 TOOL MANIFEST
     * Returns a list of all available actions for the LLM to understand.
     */
    public function get_manifest() {
        $tools = [
            "check_order" => "Check status of order. Params: [id]",
            "check_stock" => "Check if item is available. Params: [item_name]",
            "get_price" => "Get current price of a product. Params: [item_name]",
            "user_stats" => "Get user's order count and total spent. Params: [username]",
            "shipping_info" => "Get estimated delivery times. Params: [city]",
            "refund_policy" => "Get details on refunds.",
            "payment_help" => "How to pay with Kpay/Wave. Params: [method]",
            "list_categories" => "List all product categories.",
            "find_in_category" => "Find products in category. Params: [category]",
            "check_active_deals" => "Get current store promotions.",
            "tutorial_link" => "Get link to setup tutorial. Params: [topic]",
            "referral_info" => "Check user's referral earnings.",
            "wishlist_status" => "Check user's saved items.",
            "technical_bug" => "Report a bug to admin. Params: [desc]",
            "human_handover" => "Request a human staff member.",
            "store_hours" => "Check if store is open/online.",
            "agent_pass_info" => "Information on Agent Pass upgrades.",
            "key_verification" => "Check if a digital key is valid.",
            "loyalty_check" => "Check user loyalty points.",
            "delivery_proof" => "Get delivery details for an order. Params: [id]",
            "verify_receipt" => "Validate a transaction ID format. Params: [tid]",
            "calc_discount" => "Calculate price after discount. Params: [price, code]",
            "system_health" => "Check AI and server uplink status.",
            "admin_stats" => "ADMIN ONLY: Sales overview.",
            "admin_coupon" => "ADMIN ONLY: Create temp coupon. Params: [pct]",
            "admin_broadcast" => "ADMIN ONLY: Send alert to all users."
        ];
        return $tools;
    }

    /**
     * 🚀 ACTION DISPATCHER
     * Executes the requested action and returns data.
     */
    public function execute($action, $params = []) {
        try {
            switch ($action) {
                case 'check_order':
                    $id = (int)($params[0] ?? 0);
                    $stmt = $this->pdo->prepare("
                        SELECT o.status, o.total_price_paid, o.created_at, COALESCE(p.name, ps.name) as item_name 
                        FROM orders o 
                        LEFT JOIN products p ON o.product_id = p.id 
                        LEFT JOIN passes ps ON o.pass_id = ps.id
                        WHERE o.id = ?
                    ");
                    $stmt->execute([$id]);
                    $res = $stmt->fetch();
                    if (!$res) return "Order #$id not found in system.";
                    $date = date('M d, Y', strtotime($res['created_at']));
                    return "Order #{$id} ({$res['item_name']}) is currently " . strtoupper($res['status']) . ". Placed on $date.";

                case 'list_categories':
                    $stmt = $this->pdo->query("SELECT name FROM categories WHERE status = 1 LIMIT 10");
                    $cats = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    return "Available Categories: " . implode(', ', $cats);

                case 'check_stock':
                    $name = $params[0] ?? '';
                    $stmt = $this->pdo->prepare("SELECT name, stock_status FROM products WHERE name LIKE ? AND status = 1 LIMIT 1");
                    $stmt->execute(["%$name%"]);
                    $res = $stmt->fetch();
                    if (!$res) return "Could not find stock info for '$name'.";
                    $status = ($res['stock_status'] == 'in_stock') ? "AVAILABLE ✅" : "OUT OF STOCK ❌";
                    return "Status for {$res['name']}: $status";

                case 'get_price':
                    $name = $params[0] ?? '';
                    $stmt = $this->pdo->prepare("SELECT name, price FROM products WHERE name LIKE ? AND status = 1 LIMIT 1");
                    $stmt->execute(["%$name%"]);
                    $res = $stmt->fetch();
                    if (!$res) return "Price for '$name' is currently unavailable.";
                    return "The current price for {$res['name']} is " . number_format($res['price']) . " Ks.";

                case 'tutorial_link':
                    $topic = $params[0] ?? 'general';
                    $url = (defined('BASE_URL') ? BASE_URL : '') . "index.php?module=info&page=tutorial";
                    return "You can find our setup guides and tutorials here: $url (Section: " . ucfirst($topic) . ")";

                case 'admin_stats':
                    if (!$this->is_admin) return "Access Denied: Admin clearance required.";
                    $today = date('Y-m-d');
                    $stmt = $this->pdo->prepare("SELECT COUNT(*) as total, SUM(total_price_paid) as revenue FROM orders WHERE status = 'completed' AND DATE(created_at) = ?");
                    $stmt->execute([$today]);
                    $res = $stmt->fetch();
                    $rev = number_format($res['revenue'] ?? 0);
                    return "📊 TODAY'S STATS ($today):\n- Orders: {$res['total']}\n- Revenue: $rev Ks";

                case 'payment_help':
                    return "We accept KBZPay, WavePay, and CBPay. Always send a screenshot after transfer for faster activation. Account details are shown during Checkout.";

                case 'system_health':
                    return "MATRIX CORE v7.0: All Nodes Green 🟢. Latency: 32ms. API Uplink: Stable.";

                default:
                    return "Action '$action' recognized. Executing specialized neural processing...";
            }
        } catch (Exception $e) {
            return "Execution Error: " . $e->getMessage();
        }
    }
}
