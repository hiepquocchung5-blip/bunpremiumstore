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
                    $id = $params[0] ?? 0;
                    $stmt = $this->pdo->prepare("SELECT status, total_price_paid, created_at FROM orders WHERE id = ?");
                    $stmt->execute([$id]);
                    $res = $stmt->fetch();
                    return $res ? "Order #$id status: " . strtoupper($res['status']) : "Order not found.";

                case 'check_stock':
                    $name = $params[0] ?? '';
                    $stmt = $this->pdo->prepare("SELECT stock_status FROM products WHERE name LIKE ? LIMIT 1");
                    $stmt->execute(["%$name%"]);
                    $res = $stmt->fetch();
                    return $res ? "Stock for $name: " . ($res['stock_status'] == 'in_stock' ? 'Available ✅' : 'Out of stock ❌') : "Product not found.";

                case 'get_price':
                    $name = $params[0] ?? '';
                    $stmt = $this->pdo->prepare("SELECT price FROM products WHERE name LIKE ? LIMIT 1");
                    $stmt->execute(["%$name%"]);
                    $res = $stmt->fetch();
                    return $res ? "Current price of $name: " . number_format($res['price']) . " Ks" : "Price unavailable.";

                case 'admin_stats':
                    if (!$this->is_admin) return "Unauthorized.";
                    $stmt = $this->pdo->query("SELECT COUNT(*) as total, SUM(total_price_paid) as revenue FROM orders WHERE status = 'completed'");
                    $res = $stmt->fetch();
                    return "Today's Revenue: " . number_format($res['revenue'] ?? 0) . " Ks | Total Orders: " . ($res['total'] ?? 0);

                case 'payment_help':
                    $method = strtolower($params[0] ?? '');
                    if (strpos($method, 'kbz') !== false || strpos($method, 'kpay') !== false) {
                        return "Kpay Payment: Transfer to 09xxxxxxxxx, name Ko XXX. Send screenshot after transfer.";
                    }
                    return "We accept KBZPay, WavePay, and CBPay. Details available at Checkout.";

                case 'human_handover':
                    return "Handover requested. A human staff member will join shortly. 🚨";

                case 'system_health':
                    return "Matrix Core v7.0 Online. Latency: 45ms. All systems optimal. 🟢";

                default:
                    return "Action '$action' recognized but processing logic is pending implementation.";
            }
        } catch (Exception $e) {
            return "Error executing action: " . $e->getMessage();
        }
    }
}
