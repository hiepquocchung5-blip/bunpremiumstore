<?php
// api/search_suggest.php
// Autocomplete search suggestions API endpoint

require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$term = "%$q%";
// Fetch products that match the query by name or category name
$stmt = $pdo->prepare("
    SELECT p.id, p.name, p.slug, p.price, p.sale_price, p.image_path, c.name as cat_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.name LIKE ? OR c.name LIKE ?
    ORDER BY p.name ASC
    LIMIT 6
");
$stmt->execute([$term, $term]);
$products = $stmt->fetchAll();

echo json_encode($products);
exit;
