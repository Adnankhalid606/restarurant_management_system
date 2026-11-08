<?php
/**
 * AJAX / POST Endpoint for Kitchen Status Updates & Cancellations
 * Supports direct asynchronous updates with JSON responses, or standard POST-redirect with session flash.
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role.php';
require_once __DIR__ . '/service.php';

// Authentication & Permission Verification
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_role = $_SESSION['role'] ?? '';
if (!in_array($user_role, ['admin', 'kitchen'], true)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized action. Kitchen privileges required.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST request.'
    ]);
    exit;
}

// Support both form-urlencoded POST and JSON payloads
$input_json = json_decode(file_get_contents('php://input'), true);
$order_id = (int) ($input_json['order_id'] ?? $_POST['order_id'] ?? 0);
$target_status = trim((string) ($input_json['status'] ?? $_POST['status'] ?? ''));

if ($order_id <= 0 || empty($target_status)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required order ID or target status parameter.'
    ]);
    exit;
}

$result = transitionKitchenOrderStatus($conn, $order_id, $target_status);

if (!$result['success']) {
    http_response_code(422);
    echo json_encode($result);
    exit;
}

// Compute new stage counts for seamless KDS UI update
$counts_query = mysqli_query($conn, "SELECT status, COUNT(*) as cnt FROM orders WHERE status IN ('pending', 'preparing', 'ready') GROUP BY status");
$counts = ['pending' => 0, 'preparing' => 0, 'ready' => 0];
while ($r = mysqli_fetch_assoc($counts_query)) {
    if (isset($counts[$r['status']])) {
        $counts[$r['status']] = (int) $r['cnt'];
    }
}

$result['counts'] = $counts;
$result['total_active'] = array_sum($counts);

echo json_encode($result);
exit;
