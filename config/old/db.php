<?php
// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'suricrypt_loyalty';

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set character set
$conn->set_charset("utf8mb4");

// ==========================================
// HELPER FUNCTIONS
// ==========================================

// Function to sanitize user inputs
function sanitize($conn, $data) {
    // Handle null/empty data to prevent trim() error
    if ($data === null || $data === '') {
        return '';
    }
    return mysqli_real_escape_string($conn, trim($data));
}

// ✅ RANDOM REDEMPTION CODE - NO CONFLICT!
function generateRedemptionCode() {
    // Generate random 4 uppercase letters (A-Z)
    $prefix = '';
    for($i = 0; $i < 4; $i++) {
        $prefix .= chr(rand(65, 90)); // ASCII A=65, Z=90
    }
    
    // Generate random 4 character suffix
    $suffix = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4));
    
    // Format: ABCD-1234
    return $prefix . '-' . $suffix;
}

// Function to generate business slug from name
function generateBusinessSlug($conn, $businessName) {
    // Convert to lowercase and replace spaces with hyphens
    $slug = strtolower(trim($businessName));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    
    // Check if slug exists
    $originalSlug = $slug;
    $counter = 1;
    
    while (true) {
        $query = "SELECT id FROM businesses WHERE business_slug = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            break;
        }
        
        $slug = $originalSlug . '-' . $counter;
        $counter++;
    }
    
    return $slug;
}

// ==========================================
// BUSINESS FUNCTIONS
// ==========================================

// Get business by ID
function getBusiness($conn, $businessId) {
    $query = "SELECT * FROM businesses WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $businessId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Get business by slug
function getBusinessBySlug($conn, $slug) {
    $query = "SELECT * FROM businesses WHERE business_slug = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Get all active businesses
function getAllActiveBusinesses($conn) {
    $query = "SELECT * FROM businesses WHERE status = 'active' AND subscription_status = 'active' ORDER BY business_name ASC";
    $result = $conn->query($query);
    
    $businesses = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $businesses[] = $row;
        }
    }
    
    return $businesses;
}

// Check if business subscription is active
function isBusinessSubscriptionActive($conn, $businessId) {
    $query = "SELECT subscription_status, subscription_end_date FROM businesses WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $businessId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $business = $result->fetch_assoc();
        
        // Check if subscription is active
        if ($business['subscription_status'] != 'active') {
            return false;
        }
        
        // Check if subscription has expired
        if ($business['subscription_end_date'] && strtotime($business['subscription_end_date']) < time()) {
            // Auto-suspend expired subscription
            $updateQuery = "UPDATE businesses SET subscription_status = 'suspended' WHERE id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("i", $businessId);
            $updateStmt->execute();
            
            return false;
        }
        
        return true;
    }
    
    return false;
}

// ==========================================
// USER MULTI-BUSINESS FUNCTIONS
// ==========================================

// Get all businesses a user (phone number) has registered with
function getUserBusinesses($conn, $phone) {
    $query = "
        SELECT b.*, u.id as user_id, u.role, u.total_points
        FROM businesses b
        INNER JOIN users u ON b.id = u.business_id
        WHERE u.phone = ? AND b.status = 'active'
        ORDER BY b.business_name ASC
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $businesses = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $businesses[] = $row;
        }
    }
    
    return $businesses;
}

// Check if user exists in a specific business
function userExistsInBusiness($conn, $phone, $businessId) {
    $query = "SELECT id FROM users WHERE phone = ? AND business_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $phone, $businessId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}

// Update user's last accessed business
function updateLastAccessedBusiness($conn, $phone, $businessId, $userId) {
    // Check if record exists
    $checkQuery = "SELECT id FROM user_business_access WHERE user_phone = ? AND business_id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("si", $phone, $businessId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        // Update existing record
        $updateQuery = "UPDATE user_business_access SET last_accessed = CURRENT_TIMESTAMP WHERE user_phone = ? AND business_id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("si", $phone, $businessId);
        return $updateStmt->execute();
    } else {
        // Insert new record
        $insertQuery = "INSERT INTO user_business_access (user_phone, business_id, user_id, last_accessed) VALUES (?, ?, ?, CURRENT_TIMESTAMP)";
        $insertStmt = $conn->prepare($insertQuery);
        $insertStmt->bind_param("sii", $phone, $businessId, $userId);
        return $insertStmt->execute();
    }
}

// ==========================================
// SETTINGS FUNCTIONS (BUSINESS-SPECIFIC)
// ==========================================

// Get setting value from database (business-specific)
function getSetting($conn, $key, $default = '', $businessId = null) {
    if ($businessId === null) {
        // Global setting (for superadmin)
        $query = "SELECT setting_value FROM settings WHERE setting_key = ? AND business_id IS NULL";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $key);
    } else {
        // Business-specific setting
        $query = "SELECT setting_value FROM settings WHERE setting_key = ? AND business_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $key, $businessId);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['setting_value'];
    }
    
    return $default;
}

// Update setting value in database (business-specific)
function updateSetting($conn, $key, $value, $businessId = null) {
    // Check if setting exists
    if ($businessId === null) {
        $checkQuery = "SELECT id FROM settings WHERE setting_key = ? AND business_id IS NULL";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("s", $key);
    } else {
        $checkQuery = "SELECT id FROM settings WHERE setting_key = ? AND business_id = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("si", $key, $businessId);
    }
    
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        // Update existing setting
        if ($businessId === null) {
            $updateQuery = "UPDATE settings SET setting_value = ? WHERE setting_key = ? AND business_id IS NULL";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("ss", $value, $key);
        } else {
            $updateQuery = "UPDATE settings SET setting_value = ? WHERE setting_key = ? AND business_id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("ssi", $value, $key, $businessId);
        }
        return $updateStmt->execute();
    } else {
        // Insert new setting
        $insertQuery = "INSERT INTO settings (setting_key, setting_value, business_id) VALUES (?, ?, ?)";
        $insertStmt = $conn->prepare($insertQuery);
        $insertStmt->bind_param("ssi", $key, $value, $businessId);
        return $insertStmt->execute();
    }
}

// ==========================================
// POINTS CALCULATION
// ==========================================

// Calculate points based on amount and business-specific ratio
function calculatePoints($conn, $amount, $businessId) {
    // Get points ratio for business
    $business = getBusiness($conn, $businessId);
    $pointsRatio = $business['points_ratio'] ?? 10000; // Default 10000 if not set
    
    // Calculate points (amount / ratio, rounded down)
    return floor($amount / $pointsRatio);
}

// ==========================================
// AUTHORIZATION FUNCTIONS
// ==========================================

// Check if user is authorized (has required role)
function isAuthorized($requiredRole) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        return false;
    }
    
    if ($requiredRole === 'any') {
        return true;
    }
    
    if (is_array($requiredRole)) {
        return in_array($_SESSION['role'], $requiredRole);
    }
    
    return $_SESSION['role'] === $requiredRole;
}

// Check if user has access to specific business
function hasBusinessAccess($businessId) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['business_id'])) {
        return false;
    }
    
    // Super admin has access to all businesses
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin') {
        return true;
    }
    
    // Check if user's business matches
    return $_SESSION['business_id'] == $businessId;
}

// ==========================================
// ACTIVITY LOGGING
// ==========================================

// Log activity to database
function logActivity($conn, $businessId, $userId, $action, $description = '') {
    $query = "INSERT INTO activity_logs (business_id, user_id, action, description, created_at) VALUES (?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiss", $businessId, $userId, $action, $description);
    return $stmt->execute();
}

// ==========================================
// FILE UPLOAD FUNCTIONS
// ==========================================

// Handle file upload
function handleFileUpload($file, $uploadDir = 'uploads/', $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg']) {
    // Check if file was uploaded
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'No file uploaded or upload error'];
    }
    
    // Check file type
    $fileType = mime_content_type($file['tmp_name']);
    if (!in_array($fileType, $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filePath = $uploadDir . $filename;
    
    // Create upload directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        return ['success' => true, 'path' => $filePath, 'filename' => $filename];
    }
    
    return ['success' => false, 'message' => 'Failed to move uploaded file'];
}

// ==========================================
// FORMATTING FUNCTIONS
// ==========================================

// Format phone number for display
function formatPhoneNumber($phone) {
    // Remove any non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Format as +62 8xx-xxxx-xxxx
    if (substr($phone, 0, 2) === '62') {
        return '+' . substr($phone, 0, 2) . ' ' . substr($phone, 2, 3) . '-' . substr($phone, 5, 4) . '-' . substr($phone, 9);
    }
    
    return $phone;
}

// Format currency (Indonesian Rupiah)
function formatCurrency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Format date
function formatDate($date, $format = 'd M Y H:i') {
    return date($format, strtotime($date));
}
?>