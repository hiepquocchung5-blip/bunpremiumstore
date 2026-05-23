<?php
// api/coupon.php
// PRODUCTION v2.5 - Hardened Matrix Lite Speed & Secure Validation

// ⚡️ DYNAMIC CORS SECURITY HEADERS
$origin = $_SERVER['HTTP_ORIGIN'] ?? 'https://digitalmarketplacemm.com';
$allowed_origins = [
    'https://digitalmarketplacemm.com', 
    'https://www.digitalmarketplacemm.com'
];

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: https://digitalmarketplacemm.com");
}

header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['valid' => false, 'message' => 'Transmission method not authorized.']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $code = strtoupper(trim($input['code'] ?? ''));

    if (empty($code)) {
        echo json_encode(['valid' => false, 'message' => 'Please enter a valid node signature.']);
        exit;
    }

    // ⚡️ Matrix Speed Check: Coupon results are static until expiry
    $cache_key = "coupon_val_" . md5($code);
    $cached = matrix_cache_get($cache_key);
    
    if ($cached) {
        $etag = '"' . md5(json_encode($cached)) . '"';
        header("ETag: $etag");
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
            http_response_code(304);
            exit;
        }
        echo json_encode($cached);
        exit;
    }

    // ⚡️ Database Fetch
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch();

    if (!$coupon) {
        echo json_encode(['valid' => false, 'message' => 'Invalid promotional code.']);
        exit;
    }

    // Check Expiry
    if (strtotime($coupon['expires_at']) < time()) {
        echo json_encode(['valid' => false, 'message' => 'Code has expired.']);
        exit;
    }

    // Check Usage Limit
    if ($coupon['used_count'] >= $coupon['max_usage']) {
        echo json_encode(['valid' => false, 'message' => 'Usage limit exceeded.']);
        exit;
    }

    $final_data = [
        'valid' => true,
        'discount_percent' => (int)$coupon['discount_percent'],
        'message' => 'Node authorized! ' . $coupon['discount_percent'] . '% discount applied.'
    ];

    // Cache for 5 minutes to reduce DB load
    matrix_cache_set($cache_key, $final_data, 300);

    echo json_encode($final_data);

} catch (Exception $e) {
    error_log("Coupon API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['valid' => false, 'message' => 'Syncing with matrix...']);
}
?>