<?php
// Include database configuration
require_once 'config/db.php';

// Define base path for includes
$basePath = '';

// Start session
session_start();

// Check if already logged in
if(isset($_SESSION['user_id'])) {
    // Redirect based on role
    if($_SESSION['role'] == 'superadmin') {
        header('Location: superadmin/dashboard.php');
    } else if($_SESSION['role'] == 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: customer/dashboard.php');
    }
    exit;
}

// Initialize variables
$errorMsg = '';
$phone = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $phone = sanitize($conn, $_POST['phone']);
    $password = $_POST['password'];
    
    // Format phone number
    if (substr($phone, 0, 1) == '0') {
        $phone = '62' . substr($phone, 1);
    } elseif (substr($phone, 0, 2) != '62') {
        $phone = '62' . $phone;
    }
    
    // Check if user exists
    $query = "SELECT * FROM users WHERE phone = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // For default admin login with plaintext password (special case)
        if($user['role'] == 'admin' && $user['phone'] == '628123456789' && $password == 'Admin#123') {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['phone'] = $user['phone'];
            $_SESSION['role'] = $user['role'];
            
            // Redirect to admin dashboard
            header('Location: admin/dashboard.php');
            exit;
        }
        // For superadmin login with plaintext password (special case)
        elseif($user['role'] == 'superadmin' && $user['phone'] == '6282123456789' && $password == 'Super#123') {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['phone'] = $user['phone'];
            $_SESSION['role'] = $user['role'];
            
            // Redirect to superadmin dashboard
            header('Location: superadmin/dashboard.php');
            exit;
        }
        // Normal password verification for all users
        elseif (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['phone'] = $user['phone'];
            $_SESSION['role'] = $user['role'];
            
            // Redirect based on role
            if ($user['role'] == 'superadmin') {
                header('Location: superadmin/dashboard.php');
            } elseif ($user['role'] == 'admin') {
                header('Location: admin/dashboard.php');
            } else {
                header('Location: customer/dashboard.php');
            }
            exit;
        } else {
            $errorMsg = 'Password yang Anda masukkan salah.';
        }
    } else {
        $errorMsg = 'Nomor HP tidak terdaftar.';
    }
    
    $stmt->close();
}

// Get site name from settings
$siteName = getSetting($conn, 'site_name', 'SuriCrypt Loyalty - Rumah Makan Sate');

// Include header without navbar
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo $siteName; ?></title>
    <link rel="icon" href="\assets\images\logo-sate.png" type="image/png">


    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo isset($basePath) ? $basePath : ''; ?>assets/css/style.css">
</head>

<body>
    <!-- Background Image with Overlay -->
    <div class="auth-bg"></div>
    <div class="auth-overlay"></div>

    <div class="container login-container">
        <div class="card">
            <div class="card-header text-center">
                <div class="auth-logo">
                    <img src="assets/images/logo-sate.png" alt="Logo Rumah Makan Sate">
                </div>
                <h3>Login ke Sistem Loyalitas</h3>
            </div>
            <div class="card-body auth-form">
                <?php if($errorMsg): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $errorMsg; ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="mb-4">
                        <label for="phone" class="form-label">Nomor HP</label>
                        <div class="input-group">
                            <span class="input-group-text">+62</span>
                            <input type="text" class="form-control" id="phone" name="phone" placeholder="8xxxxxxxxxx"
                                value="<?php echo htmlspecialchars(str_replace('62', '', $phone)); ?>" required
                                onkeyup="formatPhoneNumber(this)">
                        </div>
                        <div class="form-text">Masukkan nomor tanpa awalan 0 atau +62, contoh: 8123456789</div>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="password-container">
                            <input type="password" class="form-control" id="password" name="password" required>
                            <span class="password-toggle" onclick="togglePassword('password')">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mb-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </button>
                    </div>
                </form>

                <div class="text-center">
                    <p>Belum punya akun? <a href="register.php" class="text-primary fw-bold">Daftar Sekarang</a></p>
                </div>
            </div>
        </div>

        <!-- Store Info -->
        <div class="text-center mt-4 text-white">
            <h5><?php echo $siteName; ?></h5>
            <p><?php echo getSetting($conn, 'resto_address', 'Jl. Sate Lezat No. 123, Jakarta'); ?></p>
            <p>Telp: <?php echo getSetting($conn, 'resto_phone', '021-1234567'); ?></p>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script src="<?php echo isset($basePath) ? $basePath : ''; ?>assets/js/script.js"></script>
</body>

</html>