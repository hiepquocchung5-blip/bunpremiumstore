<?php
// api/coupon.php
// PRODUCTION v2.0 - Dynamic CORS & Secure Validation

// ⚡️ DYNAMIC CORS SECURITY HEADERS
$origin = $_SERVER['HTTP_ORIGIN'] ?? 'https://digitalmarketplacemm.com';
$allowed_origins = [
    'https://digitalmarketplacemm.com', 
    'https://www.digitalmarketplacemm.com',
    'http://localhost' // For local testing
];

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: https://digitalmarketplacemm.com");
}

header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");

// Handle Browser Preflight OPTIONS request instantly
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['valid' => false, 'message' => 'Invalid request method.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$code = strtoupper(trim($input['code'] ?? ''));

if (empty($code)) {
    echo json_encode(['valid' => false, 'message' => 'Please enter a promo code.']);
    exit;
}

try {
    // Check Coupon
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch();

    if (!$coupon) {
        echo json_encode(['valid' => false, 'message' => 'Invalid promotional code.']);
        exit;
    }

    // Check Expiry
    if (strtotime($coupon['expires_at']) < time()) {
        echo json_encode(['valid' => false, 'message' => 'This promo code has expired.']);
        exit;
    }

    // Check Usage Limit
    if ($coupon['used_count'] >= $coupon['max_usage']) {
        echo json_encode(['valid' => false, 'message' => 'This promo code has reached its usage limit.']);
        exit;
    }

    // Valid
    echo json_encode([
        'valid' => true,
        'discount_percent' => (int)$coupon['discount_percent'],
        'message' => 'Code applied! You get ' . $coupon['discount_percent'] . '% off.'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['valid' => false, 'message' => 'System error. Please try again.']);
}
?>