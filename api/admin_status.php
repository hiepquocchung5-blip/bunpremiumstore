<?php
// api/admin_status.php
// Matrix Real-Fetch: Provides live admin online/offline status for frontend UI

require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Security: Prevent excessive polling load
header("Cache-Control: private, max-age=15"); 

$status = get_admin_status();

echo json_encode([
    'status' => $status,
    'label' => ($status === 'online' ? 'Agent Online' : ($status === 'away' ? 'Agent Away' : 'Support Offline')),
    'color' => ($status === 'online' ? 'green' : ($status === 'away' ? 'orange' : 'slate')),
    'timestamp' => time()
]);
?>