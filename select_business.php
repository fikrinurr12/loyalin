<?php
// Include database configuration
require_once 'config/db.php';

// Start session
session_start();

// Check if user has temporary credentials
if (!isset($_SESSION['temp_phone'])) {
    header('Location: login.php');
    exit;
}

$phone = $_SESSION['temp_phone'];
$password = $_SESSION['temp_password'];

// Get all businesses this user is registered with
$userBusinesses = getUserBusinesses($conn, $phone);

if (count($userBusinesses) == 0) {
    $_SESSION['error'] = 'Tidak ada UMKM yang terdaftar dengan nomor ini.';
    header('Location: login.php');
    exit;
}

// Process business selection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['business_id'])) {
    $businessId = (int)$_POST['business_id'];
    
    // Check if business subscription is active
    if (!isBusinessSubscriptionActive($conn, $businessId)) {
        $errorMsg = 'Maaf, langganan UMKM ini telah berakhir. Silakan hubungi administrator.';
    } else {
        // Get user data for selected business
        $userQuery = "SELECT * FROM users WHERE phone = ? AND business_id = ?";
        $userStmt = $conn->prepare($userQuery);
        $userStmt->bind_param("si", $phone, $businessId);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        
        if ($userResult->num_rows > 0) {
            $user = $userResult->fetch_assoc();
            $business = getBusiness($conn, $businessId);
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['phone'] = $user['phone'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['business_id'] = $business['id'];
            $_SESSION['business_name'] = $business['business_name'];
            
            // Update last accessed
            updateLastAccessedBusiness($conn, $phone, $businessId, $user['id']);
            
            // Log activity
            logActivity($conn, $businessId, $user['id'], 'LOGIN', 'User logged in via business selection');
            
            // Clear temporary session variables
            unset($_SESSION['temp_phone']);
            unset($_SESSION['temp_password']);
            
            // Redirect based on role
            if ($user['role'] == 'superadmin') {
                header('Location: superadmin/dashboard.php');
            } elseif ($user['role'] == 'admin') {
                header('Location: admin/dashboard.php');
            } else {
                header('Location: customer/dashboard.php');
            }
            exit;
        }
        
        $userStmt->close();
    }
}

$siteName = getSetting($conn, 'site_name', 'SuriCrypt Loyalty Platform');
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih UMKM - <?php echo $siteName; ?></title>
    <link rel="icon" href="assets/images/logo-sate.png" type="image/png">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        .business-card {
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
            background: white;
        }
        
        .business-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-3px);
        }
        
        .business-logo {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 50%;
            margin-bottom: 15px;
            border: 3px solid #f0f0f0;
        }
        
        .business-name {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 10px;
        }
        
        .business-info {
            color: #666;
            font-size: 0.9rem;
        }
        
        .points-badge {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
            margin-top: 10px;
        }
        
        .subscription-status {
            font-size: 0.8rem;
            padding: 4px 10px;
            border-radius: 10px;
            display: inline-block;
            margin-top: 5px;
        }
        
        .subscription-active {
            background-color: #d4edda;
            color: #155724;
        }
        
        .subscription-warning {
            background-color: #fff3cd;
            color: #856404;
        }
    </style>
</head>

<body>
    <!-- Background Image with Overlay -->
    <div class="auth-bg"></div>
    <div class="auth-overlay"></div>

    <div class="container py-5" style="position: relative; z-index: 1;">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-header text-center">
                        <h3><i class="fas fa-store me-2"></i>Pilih UMKM</h3>
                        <p class="text-muted mb-0">Anda terdaftar di beberapa UMKM. Pilih salah satu untuk melanjutkan.</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if(isset($errorMsg)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo $errorMsg; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <?php foreach($userBusinesses as $business): ?>
                            <div class="col-md-6 col-lg-4">
                                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                    <input type="hidden" name="business_id" value="<?php echo $business['id']; ?>">
                                    <button type="submit" class="business-card w-100 text-center border-0 bg-transparent">
                                        <?php if($business['logo_path'] && file_exists($business['logo_path'])): ?>
                                            <img src="<?php echo $business['logo_path']; ?>" alt="Logo" class="business-logo">
                                        <?php else: ?>
                                            <div class="business-logo mx-auto" style="background: linear-gradient(135deg, <?php echo $business['primary_color']; ?>, <?php echo $business['accent_color']; ?>); display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-store fa-2x text-white"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="business-name"><?php echo htmlspecialchars($business['business_name']); ?></div>
                                        
                                        <div class="business-info">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?php echo htmlspecialchars(substr($business['address'], 0, 30)); ?><?php echo strlen($business['address']) > 30 ? '...' : ''; ?>
                                        </div>
                                        
                                        <?php if($business['role'] == 'customer'): ?>
                                        <div class="points-badge">
                                            <i class="fas fa-coins me-1"></i>
                                            <?php echo number_format($business['total_points']); ?> Poin
                                        </div>
                                        <?php elseif($business['role'] == 'admin'): ?>
                                        <div class="points-badge" style="background: linear-gradient(135deg, #6c63ff, #5a52d5);">
                                            <i class="fas fa-user-shield me-1"></i>
                                            Admin
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php
                                        $daysLeft = ceil((strtotime($business['subscription_end_date']) - time()) / (60 * 60 * 24));
                                        $statusClass = $daysLeft > 7 ? 'subscription-active' : 'subscription-warning';
                                        ?>
                                        <div class="subscription-status <?php echo $statusClass; ?>">
                                            <?php if($daysLeft > 0): ?>
                                                <i class="fas fa-check-circle me-1"></i>
                                                Aktif (<?php echo $daysLeft; ?> hari lagi)
                                            <?php else: ?>
                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                Berakhir
                                            <?php endif; ?>
                                        </div>
                                    </button>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="logout.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Kembali ke Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
