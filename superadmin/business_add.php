<?php
// Include database configuration
require_once '../config/db.php';

// Define base path for includes
$basePath = '../';

// Start session
session_start();

// Check if user is logged in and is superadmin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'superadmin') {
    header('Location: ../login.php');
    exit;
}

$errorMsg = '';
$successMsg = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $businessName = sanitize($conn, $_POST['business_name']);
    $ownerName = sanitize($conn, $_POST['owner_name']);
    $phone = sanitize($conn, $_POST['phone']);
    $email = sanitize($conn, $_POST['email']);
    $address = sanitize($conn, $_POST['address']);
    $pointsRatio = (int)$_POST['points_ratio'];
    $primaryColor = sanitize($conn, $_POST['primary_color']);
    $secondaryColor = sanitize($conn, $_POST['secondary_color']);
    $accentColor = sanitize($conn, $_POST['accent_color']);
    $monthlyFee = (float)$_POST['monthly_fee'];
    $subscriptionMonths = (int)$_POST['subscription_months'];
    
    // Validate
    if (empty($businessName)) {
        $errorMsg = 'Nama UMKM tidak boleh kosong.';
    } elseif ($pointsRatio < 1000) {
        $errorMsg = 'Ratio poin minimal 1000.';
    } else {
        // Generate slug
        $slug = generateBusinessSlug($conn, $businessName);
        
        // Handle logo upload
        $logoPath = null;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] == UPLOAD_ERR_OK) {
            $uploadResult = handleFileUpload($_FILES['logo'], '../uploads/logos/', ['image/jpeg', 'image/png', 'image/jpg']);
            if ($uploadResult['success']) {
                $logoPath = 'uploads/logos/' . $uploadResult['filename'];
            } else {
                $errorMsg = 'Gagal upload logo: ' . $uploadResult['error'];
            }
        }
        
        if (empty($errorMsg)) {
            // Calculate subscription dates
            $subscriptionStart = date('Y-m-d');
            $subscriptionEnd = date('Y-m-d', strtotime("+{$subscriptionMonths} months"));
            
            // Insert business
            $query = "
                INSERT INTO businesses (
                    business_name, business_slug, owner_name, phone, email, address,
                    logo_path, points_ratio, primary_color, secondary_color, accent_color,
                    monthly_fee, subscription_status, subscription_start_date, subscription_end_date,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, 'active')
            ";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param(
                "sssssssississss",
                $businessName, $slug, $ownerName, $phone, $email, $address,
                $logoPath, $pointsRatio, $primaryColor, $secondaryColor, $accentColor,
                $monthlyFee, $subscriptionStart, $subscriptionEnd
            );
            
            if ($stmt->execute()) {
                $businessId = $stmt->insert_id;
                
                // Log activity
                logActivity($conn, null, $_SESSION['user_id'], 'CREATE_BUSINESS', "Created business: {$businessName} (ID: {$businessId})");
                
                $_SESSION['success'] = "UMKM '{$businessName}' berhasil ditambahkan!";
                header('Location: businesses.php');
                exit;
            } else {
                $errorMsg = 'Gagal menambahkan UMKM. Silakan coba lagi.';
            }
            
            $stmt->close();
        }
    }
}

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2><i class="fas fa-plus-circle me-2"></i>Tambah UMKM Baru</h2>
            <p>Daftarkan UMKM baru ke platform</p>
        </div>
    </div>

    <?php if($errorMsg): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $errorMsg; ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Informasi UMKM</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
                <!-- Business Information -->
                <h6 class="mb-3"><i class="fas fa-store me-2"></i>Data Bisnis</h6>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="business_name" class="form-label">Nama UMKM <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="business_name" name="business_name" 
                            value="<?php echo htmlspecialchars($_POST['business_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="owner_name" class="form-label">Nama Pemilik</label>
                        <input type="text" class="form-control" id="owner_name" name="owner_name" 
                            value="<?php echo htmlspecialchars($_POST['owner_name'] ?? ''); ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="phone" class="form-label">Nomor HP</label>
                        <input type="text" class="form-control" id="phone" name="phone" 
                            value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" 
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="address" class="form-label">Alamat</label>
                    <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                </div>

                <div class="mb-3">
                    <label for="logo" class="form-label">Logo UMKM</label>
                    <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                    <div class="form-text">Format: JPG, PNG. Max 2MB.</div>
                </div>

                <hr class="my-4">

                <!-- Points Settings -->
                <h6 class="mb-3"><i class="fas fa-coins me-2"></i>Pengaturan Poin</h6>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="points_ratio" class="form-label">Ratio Poin <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="points_ratio" name="points_ratio" 
                            value="<?php echo $_POST['points_ratio'] ?? 10000; ?>" min="1000" required>
                        <div class="form-text">Setiap Rp X = 1 poin. Contoh: 10000 berarti Rp 10.000 = 1 poin</div>
                    </div>
                </div>

                <hr class="my-4">

                <!-- Branding -->
                <h6 class="mb-3"><i class="fas fa-palette me-2"></i>Branding</h6>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="primary_color" class="form-label">Warna Utama</label>
                        <input type="color" class="form-control form-control-color" id="primary_color" name="primary_color" 
                            value="<?php echo $_POST['primary_color'] ?? '#e63946'; ?>">
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="secondary_color" class="form-label">Warna Sekunder</label>
                        <input type="color" class="form-control form-control-color" id="secondary_color" name="secondary_color" 
                            value="<?php echo $_POST['secondary_color'] ?? '#f1faee'; ?>">
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="accent_color" class="form-label">Warna Aksen</label>
                        <input type="color" class="form-control form-control-color" id="accent_color" name="accent_color" 
                            value="<?php echo $_POST['accent_color'] ?? '#f8a963'; ?>">
                    </div>
                </div>

                <hr class="my-4">

                <!-- Subscription -->
                <h6 class="mb-3"><i class="fas fa-calendar-alt me-2"></i>Subscription</h6>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="monthly_fee" class="form-label">Biaya Bulanan (Rp)</label>
                        <input type="number" class="form-control" id="monthly_fee" name="monthly_fee" 
                            value="<?php echo $_POST['monthly_fee'] ?? 50000; ?>" min="0" step="1000">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="subscription_months" class="form-label">Durasi Langganan Awal (bulan)</label>
                        <input type="number" class="form-control" id="subscription_months" name="subscription_months" 
                            value="<?php echo $_POST['subscription_months'] ?? 1; ?>" min="1" max="12">
                    </div>
                </div>

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Simpan UMKM
                    </button>
                    <a href="businesses.php" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>
