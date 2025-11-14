<?php
// ============================================================================
// FILE BARU: admin/check_customer.php
// Purpose: AJAX endpoint untuk cek nomor HP customer
// ============================================================================

require_once '../config/db.php';
session_start();

// Check if user is logged in and is admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$businessId = $_SESSION['business_id'];
$businessName = $_SESSION['business_name'];

// Get phone number from POST
$phone = isset($_POST['phone']) ? sanitize($conn, $_POST['phone']) : '';

if (empty($phone)) {
    echo json_encode(['status' => 'error', 'message' => 'Nomor HP tidak boleh kosong']);
    exit;
}

// Format phone number
if (substr($phone, 0, 1) == '0') {
    $phone = '62' . substr($phone, 1);
} elseif (substr($phone, 0, 2) != '62') {
    $phone = '62' . $phone;
}

// Check if customer exists in THIS BUSINESS
$query = "SELECT id, name, phone, total_points FROM users WHERE phone = ? AND role = 'customer' AND business_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("si", $phone, $businessId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $customer = $result->fetch_assoc();
    
    echo json_encode([
        'status' => 'found',
        'message' => 'Customer ditemukan!',
        'customer' => [
            'id' => $customer['id'],
            'name' => $customer['name'],
            'phone' => $customer['phone'],
            'total_points' => $customer['total_points']
        ]
    ]);
} else {
    // Check if exists in OTHER business
    $queryOther = "SELECT b.business_name FROM users u 
                   JOIN businesses b ON u.business_id = b.id 
                   WHERE u.phone = ? AND u.role = 'customer' AND u.business_id != ?";
    $stmtOther = $conn->prepare($queryOther);
    $stmtOther->bind_param("si", $phone, $businessId);
    $stmtOther->execute();
    $resultOther = $stmtOther->get_result();
    
    if ($resultOther->num_rows > 0) {
        $otherBusiness = $resultOther->fetch_assoc();
        echo json_encode([
            'status' => 'not_found',
            'message' => "Nomor ini terdaftar di <strong>{$otherBusiness['business_name']}</strong>, tapi BELUM di <strong>{$businessName}</strong>",
            'in_other_business' => true
        ]);
    } else {
        echo json_encode([
            'status' => 'not_found',
            'message' => "Nomor ini belum terdaftar di sistem",
            'in_other_business' => false
        ]);
    }
    
    $stmtOther->close();
}

$stmt->close();
$conn->close();
?>
