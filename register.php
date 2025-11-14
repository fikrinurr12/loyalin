<?php
// Include database configuration
require_once 'config/db.php';

// Define base path for includes
$basePath = '';

// Start session
session_start();

// Check if already logged in
if(isset($_SESSION['user_id']) && isset($_SESSION['business_id'])) {
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

// Get all active businesses
$activeBusinesses = getAllActiveBusinesses($conn);

if (count($activeBusinesses) == 0) {
    $errorMsg = 'Maaf, saat ini belum ada UMKM yang aktif. Silakan coba lagi nanti.';
}

// Initialize variables
$errorMsg = '';
$successMsg = '';
$name = '';
$phone = '';
$businessId = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize($conn, $_POST['name']);
    $phone = sanitize($conn, $_POST['phone']);
    $password = $_POST['password'];
    $businessId = (int)$_POST['business_id'];
    
    // Validate inputs
    if (empty($name)) {
        $errorMsg = 'Nama tidak boleh kosong.';
    }
    elseif (empty($phone)) {
        $errorMsg = 'Nomor HP tidak boleh kosong.';
    }
    elseif (empty($businessId)) {
        $errorMsg = 'Silakan pilih UMKM.';
    }
    elseif (strlen($password) < 8) {
        $errorMsg = 'Password harus minimal 8 karakter.';
    }
    elseif (!preg_match('/[A-Z]/', $password)) {
        $errorMsg = 'Password harus memiliki minimal 1 huruf kapital.';
    }
    elseif (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
        $errorMsg = 'Password harus memiliki minimal 1 simbol (!@#$%^&*()_+-=[]{};\':"\\|,.<>/?).';
    }
    else {
        // Format phone number
        $phone = formatPhoneNumber($phone);
        
        // Check if business exists and is active
        $business = getBusiness($conn, $businessId);
        if (!$business || $business['status'] != 'active') {
            $errorMsg = 'UMKM yang dipilih tidak valid.';
        }
        // Check if business subscription is active
        elseif (!isBusinessSubscriptionActive($conn, $businessId)) {
            $errorMsg = 'Maaf, UMKM ini sedang tidak aktif. Silakan pilih UMKM lain atau hubungi administrator.';
        }
        // Check if phone number already exists in this business
        elseif (userExistsInBusiness($conn, $phone, $businessId)) {
            $errorMsg = 'Nomor HP sudah terdaftar di UMKM ini. Silakan login atau pilih UMKM lain.';
        }
        else {
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $query = "INSERT INTO users (business_id, name, phone, password, role) VALUES (?, ?, ?, ?, 'customer')";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("isss", $businessId, $name, $phone, $hashedPassword);
            
            if ($stmt->execute()) {
                $userId = $stmt->insert_id;
                
                // Add to user_business_access table
                $accessQuery = "INSERT INTO user_business_access (user_phone, business_id, user_id) VALUES (?, ?, ?)";
                $accessStmt = $conn->prepare($accessQuery);
                $accessStmt->bind_param("sii", $phone, $businessId, $userId);
                $accessStmt->execute();
                $accessStmt->close();
                
                // Log activity
                logActivity($conn, $businessId, $userId, 'REGISTER', 'New customer registered');
                
                $successMsg = 'Pendaftaran berhasil! Anda sekarang terdaftar di ' . htmlspecialchars($business['business_name']) . '. Silakan login.';
                
                // Clear form fields
                $name = '';
                $phone = '';
                $businessId = '';
            } else {
                $errorMsg = 'Terjadi kesalahan saat mendaftar. Silakan coba lagi.';
            }
            
            $stmt->close();
        }
    }
}

// Get site name from settings
$siteName = getSetting($conn, 'site_name', 'SuriCrypt Loyalty Platform');
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - Loyalin</title>
    <link rel="icon" href="assets/images/logo-loyalin.png" type="image/png">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo isset($basePath) ? $basePath : ''; ?>assets/css/style.css">
    
    <style>
        /* Loyalin Branding */
        .auth-bg { background: linear-gradient(135deg, #154c79 0%, #003d5c 100%); }
        .card-header { background: linear-gradient(135deg, #154c79, #003d5c); color: white; border: none; }
        .btn-primary { background: linear-gradient(135deg, #154c79, #1565a0); border: none; transition: all 0.3s; }
        .btn-primary:hover { background: linear-gradient(135deg, #003d5c, #154c79); transform: translateY(-2px); box-shadow: 0 5px 15px rgba(21, 76, 121, 0.3); }
        .text-primary { color: #154c79 !important; }
        a.text-primary:hover { color: #c5d900 !important; }
        .form-control:focus { border-color: #c5d900; box-shadow: 0 0 0 0.2rem rgba(197, 217, 0, 0.25); }
        .input-group-text { background-color: #154c79; color: white; border-color: #154c79; }
        
        /* Business Card Selection */
        .business-select-card {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }
        
        .business-select-card:hover {
            border-color: var(--primary-color);
            background-color: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .business-select-card input[type="radio"] {
            display: none;
        }
        
        .business-select-card input[type="radio"]:checked + label {
            border-color: var(--primary-color);
            background-color: rgba(230, 57, 70, 0.1);
        }
        
        .business-select-label {
            margin: 0;
            cursor: pointer;
            width: 100%;
        }
        
        /* Scroll container for businesses */
        .business-list-container {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 5px;
            margin-bottom: 15px;
        }
        
        /* Custom scrollbar */
        .business-list-container::-webkit-scrollbar {
            width: 8px;
        }
        
        .business-list-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .business-list-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        
        .business-list-container::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        /* Search box styling */
        .business-search-box {
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
            padding-bottom: 10px;
        }
        
        .business-counter {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
        }
        
        /* No results message */
        .no-results {
            text-align: center;
            padding: 30px;
            color: #999;
        }
    </style>
</head>

<body>
    <!-- Background Image with Overlay -->
    <div class="auth-bg"></div>
    <div class="auth-overlay"></div>

    <div class="container register-container">
        <div class="card">
            <div class="card-header text-center">
                <div class="auth-logo">
                    <img src="assets/images/logo-loyalin.png" alt="Loyalin Logo" style="max-width: 200px;">
                </div>
                <h3>Daftar Akun Baru</h3>
                <p class="text-muted small mb-0">Bergabung dengan Loyalin - Platform Loyalitas UMKM</p>
            </div>
            <div class="card-body auth-form">
                <?php if($errorMsg): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $errorMsg; ?>
                </div>
                <?php endif; ?>

                <?php if($successMsg): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $successMsg; ?>
                    <div class="mt-2">
                        <a href="login.php" class="btn btn-primary btn-sm">Login Sekarang</a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if(count($activeBusinesses) > 0): ?>
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>"
                    onsubmit="return validateRegistrationForm()">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-store me-2"></i>Pilih UMKM
                        </label>
                        <p class="text-muted small">Pilih UMKM tempat Anda ingin bergabung:</p>
                        
                        <!-- Search Box -->
                        <div class="business-search-box">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" id="businessSearch" 
                                    placeholder="Cari UMKM berdasarkan nama atau lokasi...">
                            </div>
                            <div class="business-counter">
                                Menampilkan <span id="visibleCount"><?php echo count($activeBusinesses); ?></span> 
                                dari <span id="totalCount"><?php echo count($activeBusinesses); ?></span> UMKM
                            </div>
                        </div>
                        
                        <!-- Business List with Scroll -->
                        <div class="business-list-container" id="businessList">
                            <?php foreach($activeBusinesses as $business): ?>
                            <div class="business-select-card" data-business-name="<?php echo strtolower($business['business_name']); ?>" 
                                data-business-address="<?php echo strtolower($business['address']); ?>">
                                <input type="radio" name="business_id" id="business_<?php echo $business['id']; ?>" 
                                    value="<?php echo $business['id']; ?>" <?php echo $businessId == $business['id'] ? 'checked' : ''; ?> required>
                                <label class="business-select-label" for="business_<?php echo $business['id']; ?>">
                                    <div class="d-flex align-items-center">
                                        <?php if($business['logo_path'] && file_exists($business['logo_path'])): ?>
                                            <img src="<?php echo $business['logo_path']; ?>" alt="Logo" 
                                                style="width: 50px; height: 50px; object-fit: cover; border-radius: 50%; margin-right: 15px;">
                                        <?php else: ?>
                                            <div style="width: 50px; height: 50px; border-radius: 50%; margin-right: 15px; 
                                                background: linear-gradient(135deg, <?php echo $business['primary_color']; ?>, <?php echo $business['accent_color']; ?>); 
                                                display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-store text-white"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="flex-grow-1">
                                            <div class="fw-bold"><?php echo htmlspecialchars($business['business_name']); ?></div>
                                            <div class="text-muted small">
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                <?php echo htmlspecialchars(substr($business['address'], 0, 50)); ?><?php echo strlen($business['address']) > 50 ? '...' : ''; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Selected indicator -->
                                        <div class="ms-2">
                                            <i class="fas fa-check-circle text-success" style="font-size: 1.2rem; display: none;"></i>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- No results message -->
                        <div id="noResults" class="no-results" style="display: none;">
                            <i class="fas fa-search fa-3x mb-3" style="color: #ddd;"></i>
                            <p>Tidak ada UMKM yang ditemukan</p>
                            <small class="text-muted">Coba kata kunci yang berbeda</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" id="name" name="name"
                            value="<?php echo htmlspecialchars($name); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label">Nomor HP</label>
                        <div class="input-group">
                            <span class="input-group-text">+62</span>
                            <input type="text" class="form-control" id="phone" name="phone" placeholder="8xxxxxxxxxx"
                                value="<?php echo htmlspecialchars(str_replace('62', '', $phone)); ?>" required
                                onkeyup="formatPhoneNumberInput(this)">
                        </div>
                        <div class="form-text">Masukkan nomor tanpa awalan 0 atau +62, contoh: 8123456789</div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="password-container">
                            <input type="password" class="form-control" id="password" name="password" required>
                            <span class="password-toggle" onclick="togglePassword('password')">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                        <div id="password-strength-feedback" class="mt-2"></div>
                        <div class="form-text">
                            Password harus memiliki minimal 8 karakter, 1 huruf kapital, dan 1 simbol
                            (!@#$%^&*()_+-=[]{};':"\\|,.<>/?)
                        </div>
                    </div>

                    <div class="d-grid gap-2 mb-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-user-plus me-2"></i>Daftar
                        </button>
                    </div>
                </form>
                <?php endif; ?>

                <div class="text-center">
                    <p>Sudah punya akun? <a href="login.php" class="text-primary fw-bold">Login</a></p>
                </div>
            </div>
        </div>

        <!-- Platform Info -->
        <div class="text-center mt-4 text-white">
            <h4><strong>Loyalin</strong></h4>
            <p class="mb-1 opacity-75"><em>"Bikin Pelanggan Balik Lagi"</em></p>
            <p class="small mb-0 opacity-50">Platform Loyalitas untuk UMKM Indonesia</p>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script src="<?php echo isset($basePath) ? $basePath : ''; ?>assets/js/script.js"></script>

    <script>
    // Format phone number input
    function formatPhoneNumberInput(input) {
        let phoneNumber = input.value.replace(/\D/g, '');
        if (phoneNumber.startsWith('0')) {
            phoneNumber = phoneNumber.substring(1);
        }
        if (phoneNumber.startsWith('62')) {
            phoneNumber = phoneNumber.substring(2);
        }
        input.value = phoneNumber;
    }
    
    // Update password strength on input
    document.addEventListener('DOMContentLoaded', function() {
        const passwordField = document.getElementById('password');
        if (passwordField) {
            passwordField.addEventListener('input', function() {
                updatePasswordStrength(this.value);
            });
        }
        
        // Add click handler for business cards
        document.querySelectorAll('.business-select-card').forEach(card => {
            card.addEventListener('click', function() {
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
                
                // Update visual indicator
                updateSelectedIndicator();
            });
        });
        
        // Add change handler for radio buttons
        document.querySelectorAll('input[name="business_id"]').forEach(radio => {
            radio.addEventListener('change', function() {
                updateSelectedIndicator();
            });
        });
        
        // Search functionality
        const searchBox = document.getElementById('businessSearch');
        if (searchBox) {
            searchBox.addEventListener('keyup', function() {
                filterBusinesses(this.value.toLowerCase());
            });
        }
        
        // Update selected indicator on load
        updateSelectedIndicator();
    });
    
    // Update selected indicator
    function updateSelectedIndicator() {
        // Hide all check icons
        document.querySelectorAll('.business-select-card .fa-check-circle').forEach(icon => {
            icon.style.display = 'none';
        });
        
        // Show check icon for selected card
        const selectedRadio = document.querySelector('input[name="business_id"]:checked');
        if (selectedRadio) {
            const selectedCard = selectedRadio.closest('.business-select-card');
            const checkIcon = selectedCard.querySelector('.fa-check-circle');
            if (checkIcon) {
                checkIcon.style.display = 'inline-block';
            }
        }
    }
    
    // Filter businesses based on search
    function filterBusinesses(searchText) {
        const cards = document.querySelectorAll('.business-select-card');
        const businessList = document.getElementById('businessList');
        const noResults = document.getElementById('noResults');
        let visibleCount = 0;
        const totalCount = cards.length;
        
        cards.forEach(card => {
            const businessName = card.getAttribute('data-business-name');
            const businessAddress = card.getAttribute('data-business-address');
            
            // Check if search text matches name or address
            if (businessName.includes(searchText) || businessAddress.includes(searchText)) {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        // Update counter
        document.getElementById('visibleCount').textContent = visibleCount;
        document.getElementById('totalCount').textContent = totalCount;
        
        // Show/hide no results message
        if (visibleCount === 0) {
            businessList.style.display = 'none';
            noResults.style.display = 'block';
        } else {
            businessList.style.display = 'block';
            noResults.style.display = 'none';
        }
    }

    // Update password strength indicator
    function updatePasswordStrength(password) {
        const strengthFeedback = document.getElementById('password-strength-feedback');
        if (!strengthFeedback) return;

        strengthFeedback.innerHTML = '';

        const minLength = password.length >= 8;
        const hasCapital = /[A-Z]/.test(password);
        const hasSymbol = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);

        if (!minLength) {
            const item = document.createElement('div');
            item.textContent = '✖ Password harus minimal 8 karakter';
            item.className = 'text-danger';
            strengthFeedback.appendChild(item);
        } else {
            const item = document.createElement('div');
            item.textContent = '✓ Password minimal 8 karakter';
            item.className = 'text-success';
            strengthFeedback.appendChild(item);
        }

        if (!hasCapital) {
            const item = document.createElement('div');
            item.textContent = '✖ Password harus memiliki minimal 1 huruf kapital';
            item.className = 'text-danger';
            strengthFeedback.appendChild(item);
        } else {
            const item = document.createElement('div');
            item.textContent = '✓ Password memiliki huruf kapital';
            item.className = 'text-success';
            strengthFeedback.appendChild(item);
        }

        if (!hasSymbol) {
            const item = document.createElement('div');
            item.textContent = '✖ Password harus memiliki minimal 1 simbol';
            item.className = 'text-danger';
            strengthFeedback.appendChild(item);
        } else {
            const item = document.createElement('div');
            item.textContent = '✓ Password memiliki simbol';
            item.className = 'text-success';
            strengthFeedback.appendChild(item);
        }
    }

    // Validate registration form
    function validateRegistrationForm() {
        const businessId = document.querySelector('input[name="business_id"]:checked');
        const name = document.getElementById('name').value.trim();
        const phone = document.getElementById('phone').value.trim();
        const password = document.getElementById('password').value;

        if (!businessId) {
            alert('Silakan pilih UMKM');
            return false;
        }

        if (name === '') {
            alert('Nama tidak boleh kosong');
            return false;
        }

        if (phone === '') {
            alert('Nomor HP tidak boleh kosong');
            return false;
        }

        const hasMinLength = password.length >= 8;
        const hasCapital = /[A-Z]/.test(password);
        const hasSymbol = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);

        if (!hasMinLength || !hasCapital || !hasSymbol) {
            alert('Password tidak memenuhi persyaratan');
            return false;
        }

        return true;
    }
    </script>
</body>

</html>
