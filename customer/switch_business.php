<?php
// ============================================================================
// FILE: customer/switch_business.php
// Handler untuk switch business - FIXED (no slug)
// ============================================================================

require_once '../config/db.php';
session_start();

// Check if user is logged in and is customer
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['switch_to_business_id'])) {
    $newBusinessId = (int)$_POST['switch_to_business_id'];
    $customerPhone = $_SESSION['phone'];
    
    // Verify that customer is actually registered in this business
    $query = "SELECT u.id, u.business_id, b.business_name, u.total_points
              FROM users u
              JOIN businesses b ON u.business_id = b.id
              WHERE u.phone = ? AND u.role = 'customer' AND u.business_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $customerPhone, $newBusinessId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $userData = $result->fetch_assoc();
        
        // Update session with new business context
        $_SESSION['user_id'] = $userData['id'];
        $_SESSION['business_id'] = $userData['business_id'];
        $_SESSION['business_name'] = $userData['business_name'];
        $_SESSION['total_points'] = $userData['total_points'];
        
        // Log activity
        logActivity($conn, $newBusinessId, $userData['id'], 'SWITCH_BUSINESS', 
            "Customer switched to business: {$userData['business_name']}");
        
        // Redirect to dashboard with success message
        $_SESSION['switch_success'] = "Berhasil berganti ke {$userData['business_name']}";
        header('Location: dashboard.php');
    } else {
        // Customer not registered in this business
        $_SESSION['switch_error'] = "Anda tidak terdaftar di UMKM tersebut";
        header('Location: dashboard.php');
    }
    
    $stmt->close();
} else {
    // Invalid request
    header('Location: dashboard.php');
}

$conn->close();
exit;
?>
