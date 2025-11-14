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

// Get business ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: businesses.php');
    exit;
}

$businessId = (int)$_GET['id'];

// Get business data
$business = getBusiness($conn, $businessId);
if (!$business) {
    $_SESSION['error'] = 'UMKM tidak ditemukan.';
    header('Location: businesses.php');
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
    
    // Validate
    if (empty($businessName)) {
        $errorMsg = 'Nama UMKM tidak boleh kosong.';
    } elseif ($pointsRatio < 1000) {
        $errorMsg = 'Ratio poin minimal 1000.';
    } else {
        // Handle logo upload
        $logoPath = $business['logo_path'];
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] == UPLOAD_ERR_OK) {
            $uploadResult = handleFileUpload($_FILES['logo'], '../uploads/logos/', ['image/jpeg', 'image/png', 'image/jpg']);
            if ($uploadResult['success']) {
                // Delete old logo
                if ($logoPath && file_exists('../' . $logoPath)) {
                    unlink('../' . $logoPath);
                }
                $logoPath = 'uploads/logos/' . $uploadResult['filename'];
            }
        }
        
        // Update business
        $query = "
            UPDATE businesses SET
                business_name = ?, owner_name = ?, phone = ?, email = ?, address = ?,
                logo_path = ?, points_ratio = ?, primary_color = ?, secondary_color = ?, accent_color = ?,
                monthly_fee = ?
            WHERE id = ?
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            "ssssssisssdi",
            $businessName, $ownerName, $phone, $email, $address,
            $logoPath, $pointsRatio, $primaryColor, $secondaryColor, $accentColor,
            $monthlyFee, $businessId
        );
        
        if ($stmt->execute()) {
            logActivity($conn, null, $_SESSION['user_id'], 'UPDATE_BUSINESS', "Updated business: {$businessName} (ID: {$businessId})");
            $successMsg = "Data UMKM berhasil diperbarui!";
            // Refresh business data
            $business = getBusiness($conn, $businessId);
        } else {
            $errorMsg = 'Gagal memperbarui data UMKM.';
        }
        
        $stmt->close();
    }
}

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2><i class="fas fa-edit me-2"></i>Edit UMKM</h2>
            <p>Edit informasi <?php echo htmlspecialchars($business['business_name']); ?></p>
        </div>
    </div>

    <?php if($errorMsg): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $errorMsg; ?>
    </div>
    <?php endif; ?>

    <?php if($successMsg): ?>
    <div class="alert alert-success" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo $successMsg; ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $businessId; ?>" enctype="multipart/form-data">
                <!-- Business Information -->
                <h6 class="mb-3"><i class="fas fa-store me-2"></i>Data Bisnis</h6>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="business_name" class="form-label">Nama UMKM</label>
                        <input type="text" class="form-control" id="business_name" name="business_name" 
                            value="<?php echo htmlspecialchars($business['business_name']); ?>" required>
                        <div class="form-text">Slug: <?php echo $business['business_slug']; ?></div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="owner_name" class="form-label">Nama Pemilik</label>
                        <input type="text" class="form-control" id="owner_name" name="owner_name" 
                            value="<?php echo htmlspecialchars($business['owner_name']); ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="phone" class="form-label">Nomor HP</label>
                        <input type="text" class="form-control" id="phone" name="phone" 
                            value="<?php echo htmlspecialchars($business['phone']); ?>">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" 
                            value="<?php echo htmlspecialchars($business['email']); ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="address" class="form-label">Alamat</label>
                    <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($business['address']); ?></textarea>
                </div>

                <div class="mb-3">
                    <label for="logo" class="form-label">Logo UMKM</label>
                    <?php if($business['logo_path'] && file_exists('../' . $business['logo_path'])): ?>
                    <div class="mb-2">
                        <img src="../<?php echo $business['logo_path']; ?>" alt="Logo" 
                            style="width: 100px; height: 100px; object-fit: cover; border-radius: 10px;">
                    </div>
                    <?php endif; ?>
                    <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                    <div class="form-text">Upload logo baru untuk mengganti. Format: JPG, PNG. Max 2MB.</div>
                </div>

                <hr class="my-4">

                <!-- Points Settings -->
                <h6 class="mb-3"><i class="fas fa-coins me-2"></i>Pengaturan Poin</h6>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="points_ratio" class="form-label">Ratio Poin</label>
                        <input type="number" class="form-control" id="points_ratio" name="points_ratio" 
                            value="<?php echo $business['points_ratio']; ?>" min="1000" required>
                        <div class="form-text">Setiap Rp X = 1 poin</div>
                    </div>
                </div>

                <hr class="my-4">

                <!-- Branding -->
                <h6 class="mb-3"><i class="fas fa-palette me-2"></i>Branding</h6>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="primary_color" class="form-label">Warna Utama</label>
                        <input type="color" class="form-control form-control-color" id="primary_color" name="primary_color" 
                            value="<?php echo $business['primary_color']; ?>">
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="secondary_color" class="form-label">Warna Sekunder</label>
                        <input type="color" class="form-control form-control-color" id="secondary_color" name="secondary_color" 
                            value="<?php echo $business['secondary_color']; ?>">
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="accent_color" class="form-label">Warna Aksen</label>
                        <input type="color" class="form-control form-control-color" id="accent_color" name="accent_color" 
                            value="<?php echo $business['accent_color']; ?>">
                    </div>
                </div>

                <hr class="my-4">

                <!-- Subscription Info (Read Only) -->
                <h6 class="mb-3"><i class="fas fa-calendar-alt me-2"></i>Subscription Info</h6>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Status</label>
                        <div>
                            <?php if($business['subscription_status'] == 'active'): ?>
                                <span class="badge bg-success">Aktif</span>
                            <?php elseif($business['subscription_status'] == 'suspended'): ?>
                                <span class="badge bg-warning">Ditangguhkan</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Nonaktif</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tanggal Berakhir</label>
                        <div><?php echo formatDate($business['subscription_end_date'], 'd M Y'); ?></div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="monthly_fee" class="form-label">Biaya Bulanan (Rp)</label>
                        <input type="number" class="form-control" id="monthly_fee" name="monthly_fee" 
                            value="<?php echo $business['monthly_fee']; ?>" min="0" step="1000">
                    </div>
                </div>

                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Untuk perpanjang subscription, gunakan menu <a href="subscriptions.php?business_id=<?php echo $businessId; ?>">Kelola Subscription</a>
                </div>

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Simpan Perubahan
                    </button>
                    <a href="businesses.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Kembali
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
