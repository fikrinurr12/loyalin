<?php
// Database configuration
$host = 'db.fr-pari1.bengt.wasmernet.com';
$username = '6d1089877b12800008ae27c6e542';
$password = '06916d10-8987-7ce2-8000-eab17d9e27bb';
$database = 'suricrypt_loyalin';

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

// Function to generate unique redemption code based on business
function generateRedemptionCode($businessName = '') {
    // Generate prefix from business name
    if (!empty($businessName)) {
        // Get first letters of each word (max 4 chars)
        $words = explode(' ', $businessName);
        $prefix = '';
        foreach ($words as $word) {
            if (strlen($prefix) < 4 && !empty($word)) {
                $prefix .= strtoupper(substr($word, 0, 1));
            }
        }
        // If still too short, add more chars from first word
        if (strlen($prefix) < 2) {
            $prefix = strtoupper(substr($businessName, 0, 4));
        }
        $prefix .= '-';
    } else {
        $prefix = "RDM-"; // Default if no business name
    }
    
    $code = $prefix . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4));
    return $code;
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
        $setting = $result->fetch_assoc();
        return $setting['setting_value'];
    }
    
    return $default;
}

// Update setting value (business-specific)
function updateSetting($conn, $key, $value, $businessId = null) {
    if ($businessId === null) {
        // Global setting
        $checkQuery = "SELECT id FROM settings WHERE setting_key = ? AND business_id IS NULL";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("s", $key);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $query = "UPDATE settings SET setting_value = ? WHERE setting_key = ? AND business_id IS NULL";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $value, $key);
        } else {
            $query = "INSERT INTO settings (setting_key, setting_value, business_id) VALUES (?, ?, NULL)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $key, $value);
        }
    } else {
        // Business-specific setting
        $checkQuery = "SELECT id FROM settings WHERE setting_key = ? AND business_id = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("si", $key, $businessId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $query = "UPDATE settings SET setting_value = ? WHERE setting_key = ? AND business_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssi", $value, $key, $businessId);
        } else {
            $query = "INSERT INTO settings (setting_key, setting_value, business_id) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssi", $key, $value, $businessId);
        }
    }
    
    return $stmt->execute();
}

// ==========================================
// POINTS CALCULATION
// ==========================================

// Calculate points based on transaction amount and business points ratio
function calculatePoints($conn, $amount, $businessId) {
    // Get points ratio from business settings
    $business = getBusiness($conn, $businessId);
    $pointsRatio = $business['points_ratio'] ?: 10000;
    
    return floor($amount / $pointsRatio);
}

// ==========================================
// AUTHORIZATION FUNCTIONS
// ==========================================

// Check if user is authorized for a role
function isAuthorized($requiredRole) {
    if (!isset($_SESSION['role'])) {
        return false;
    }
    
    // Role hierarchy: superadmin > admin > customer
    if ($_SESSION['role'] == 'superadmin') {
        return true; // Superadmin can access everything
    }
    
    if ($_SESSION['role'] == 'admin' && $requiredRole == 'admin') {
        return true; // Admin can access admin stuff
    }
    
    if ($_SESSION['role'] == $requiredRole) {
        return true; // Exact role match
    }
    
    return false;
}

// Check if user has access to specific business
function hasBusinessAccess($businessId) {
    if (!isset($_SESSION['business_id'])) {
        return false;
    }
    
    // Superadmin has access to all businesses
    if ($_SESSION['role'] == 'superadmin') {
        return true;
    }
    
    // Check if user's business matches
    return $_SESSION['business_id'] == $businessId;
}

// ==========================================
// ACTIVITY LOGGING
// ==========================================

// Log user activity
function logActivity($conn, $businessId, $userId, $action, $description = '') {
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $query = "INSERT INTO activity_logs (business_id, user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iissss", $businessId, $userId, $action, $description, $ipAddress, $userAgent);
    
    return $stmt->execute();
}

// ==========================================
// FILE UPLOAD HELPER
// ==========================================

// Handle file upload (for logo, payment proof, etc)
function handleFileUpload($file, $uploadDir = 'uploads/', $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg']) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'No file uploaded or upload error'];
    }
    
    // Check file type
    $fileType = $file['type'];
    if (!in_array($fileType, $allowedTypes)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    
    // Create upload directory if not exists
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $targetPath = $uploadDir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'filename' => $filename, 'path' => $targetPath];
    }
    
    return ['success' => false, 'error' => 'Failed to move uploaded file'];
}

// ==========================================
// FORMAT HELPERS
// ==========================================

// Format phone number to standard format (62xxx)
function formatPhoneNumber($phone) {
    // Remove any non-digit characters
    $phone = preg_replace('/\D/', '', $phone);
    
    // Remove leading zero
    if (substr($phone, 0, 1) == '0') {
        $phone = substr($phone, 1);
    }
    
    // Add 62 prefix if not present
    if (substr($phone, 0, 2) != '62') {
        $phone = '62' . $phone;
    }
    
    return $phone;
}

// Format currency
function formatCurrency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Format date
function formatDate($date, $format = 'd M Y H:i') {
    return date($format, strtotime($date));
}
?>