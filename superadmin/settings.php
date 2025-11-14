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

// Initialize messages
$successMsg = '';
$errorMsg = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Update points ratio setting
    if (isset($_POST['update_points_ratio'])) {
        $pointsRatio = (int)sanitize($conn, $_POST['points_ratio']);
        
        if ($pointsRatio <= 0) {
            $errorMsg = 'Nilai rasio poin harus lebih dari 0.';
        } else {
            if (updateSetting($conn, 'points_ratio', $pointsRatio)) {
                $successMsg = 'Rasio poin berhasil diperbarui.';
            } else {
                $errorMsg = 'Terjadi kesalahan saat memperbarui rasio poin.';
            }
        }
    }
    
    // Update site settings
    if (isset($_POST['update_site_settings'])) {
        $siteName = sanitize($conn, $_POST['site_name']);
        $restoAddress = sanitize($conn, $_POST['resto_address']);
        $restoPhone = sanitize($conn, $_POST['resto_phone']);
        $restoEmail = sanitize($conn, $_POST['resto_email']);
        
        $conn->begin_transaction();
        
        try {
            updateSetting($conn, 'site_name', $siteName);
            updateSetting($conn, 'resto_address', $restoAddress);
            updateSetting($conn, 'resto_phone', $restoPhone);
            updateSetting($conn, 'resto_email', $restoEmail);
            
            $conn->commit();
            $successMsg = 'Pengaturan situs berhasil diperbarui.';
        } catch (Exception $e) {
            $conn->rollback();
            $errorMsg = 'Terjadi kesalahan saat memperbarui pengaturan situs.';
        }
    }
    
    // Reset system data
    if (isset($_POST['reset_system_data'])) {
        $resetType = sanitize($conn, $_POST['reset_type']);
        $confirmText = sanitize($conn, $_POST['confirm_text']);
        
        if ($confirmText != 'RESET') {
            $errorMsg = 'Teks konfirmasi tidak sesuai. Data tidak direset.';
        } else {
            $conn->begin_transaction();
            
            try {
                switch ($resetType) {
                    case 'transactions':
                        $conn->query("DELETE FROM transactions");
                        $conn->query("ALTER TABLE transactions AUTO_INCREMENT = 1");
                        $conn->query("UPDATE users SET total_points = 0 WHERE role = 'customer'");
                        $successMsg = 'Semua data transaksi berhasil direset.';
                        break;
                        
                    case 'redemptions':
                        $conn->query("DELETE FROM redemptions");
                        $conn->query("ALTER TABLE redemptions AUTO_INCREMENT = 1");
                        $successMsg = 'Semua data penukaran berhasil direset.';
                        break;
                        
                    case 'rewards':
                        // Check if rewards are being used
                        $result = $conn->query("SELECT COUNT(*) as count FROM redemptions");
                        $redemptionCount = $result->fetch_assoc()['count'];
                        
                        if ($redemptionCount > 0) {
                            throw new Exception('Tidak dapat mereset hadiah karena masih ada data penukaran.');
                        }
                        
                        $conn->query("DELETE FROM rewards");
                        $conn->query("ALTER TABLE rewards AUTO_INCREMENT = 1");
                        $successMsg = 'Semua data hadiah berhasil direset.';
                        break;
                        
                    case 'customers':
                        // Check if customers have transactions or redemptions
                        $result = $conn->query("SELECT COUNT(*) as count FROM transactions");
                        $transactionCount = $result->fetch_assoc()['count'];
                        
                        $result = $conn->query("SELECT COUNT(*) as count FROM redemptions");
                        $redemptionCount = $result->fetch_assoc()['count'];
                        
                        if ($transactionCount > 0 || $redemptionCount > 0) {
                            throw new Exception('Tidak dapat mereset pelanggan karena masih ada data transaksi atau penukaran.');
                        }
                        
                        $conn->query("DELETE FROM users WHERE role = 'customer'");
                        $successMsg = 'Semua data pelanggan berhasil direset.';
                        break;
                        
                    case 'all':
                        $conn->query("DELETE FROM transactions");
                        $conn->query("ALTER TABLE transactions AUTO_INCREMENT = 1");
                        $conn->query("DELETE FROM redemptions");
                        $conn->query("ALTER TABLE redemptions AUTO_INCREMENT = 1");
                        $conn->query("DELETE FROM rewards");
                        $conn->query("ALTER TABLE rewards AUTO_INCREMENT = 1");
                        $conn->query("DELETE FROM users WHERE role = 'customer'");
                        $conn->query("UPDATE users SET total_points = 0");
                        
                        // Insert default rewards
                        $conn->query("INSERT INTO rewards (name, points_required, description, status) VALUES 
                            ('1 Tusuk Sate', 25, 'Gratis 1 tusuk sate pilihan', 'active'),
                            ('Es Teh Manis', 50, 'Gratis 1 gelas es teh manis', 'active'),
                            ('Sate Paket Kecil', 100, 'Gratis 5 tusuk sate dengan nasi dan sambal', 'active')");
                            
                        $successMsg = 'Sistem berhasil direset ke kondisi awal.';
                        break;
                        
                    default:
                        throw new Exception('Tipe reset tidak valid.');
                }
                
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                $errorMsg = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    }
}

// Get current settings
$querySettings = "SELECT * FROM settings";
$resultSettings = $conn->query($querySettings);
$settings = [];
while($row = $resultSettings->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Pengaturan Sistem</h2>
            <p>Kelola pengaturan dan data sistem loyalitas.</p>
        </div>
        <div class="col-md-4 text-md-end">
            <a href="dashboard.php" class="btn btn-primary">
                <i class="fas fa-arrow-left me-2"></i>Kembali ke Dashboard
            </a>
        </div>
    </div>

    <!-- Messages -->
    <?php if($successMsg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo $successMsg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if($errorMsg): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $errorMsg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- Points Ratio Settings -->
    <div class="settings-section">
        <h4><i class="fas fa-coins me-2"></i>Pengaturan Poin</h4>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row align-items-end">
            <div class="col-md-6 mb-3">
                <label for="points_ratio" class="form-label">Rasio Poin (Rp per 1 poin)</label>
                <input type="number" class="form-control" id="points_ratio" name="points_ratio"
                    value="<?php echo $settings['points_ratio'] ?? 10000; ?>" min="1" required>
                <div class="form-text">
                    Contoh: Jika diatur 10.000, maka setiap transaksi Rp 10.000 akan mendapatkan 1 poin.
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <button type="submit" name="update_points_ratio" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Simpan Pengaturan Poin
                </button>
            </div>
        </form>
    </div>

    <!-- Site Settings -->
    <div class="settings-section">
        <h4><i class="fas fa-store me-2"></i>Pengaturan Restoran</h4>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="site_name" class="form-label">Nama Situs</label>
                    <input type="text" class="form-control" id="site_name" name="site_name"
                        value="<?php echo $settings['site_name'] ?? 'SuriCrypt Loyalty - Rumah Makan Sate'; ?>"
                        required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="resto_address" class="form-label">Alamat Restoran</label>
                    <input type="text" class="form-control" id="resto_address" name="resto_address"
                        value="<?php echo $settings['resto_address'] ?? ''; ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="resto_phone" class="form-label">Nomor Telepon</label>
                    <input type="text" class="form-control" id="resto_phone" name="resto_phone"
                        value="<?php echo $settings['resto_phone'] ?? ''; ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="resto_email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="resto_email" name="resto_email"
                        value="<?php echo $settings['resto_email'] ?? ''; ?>" required>
                </div>
                <div class="col-12 text-end">
                    <button type="submit" name="update_site_settings" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Simpan Pengaturan Restoran
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- System Reset -->
    <div class="settings-section">
        <h4 class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Reset Data Sistem</h4>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-circle me-2"></i>
            <strong>Perhatian!</strong> Tindakan reset tidak dapat dibatalkan. Pastikan Anda memiliki cadangan data jika
            diperlukan.
        </div>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="resetForm"
            onsubmit="return confirmReset()">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="reset_type" class="form-label">Pilih Data yang Akan Direset</label>
                    <select class="form-select" id="reset_type" name="reset_type" required>
                        <option value="" selected disabled>-- Pilih tipe reset --</option>
                        <option value="transactions">Reset Semua Transaksi</option>
                        <option value="redemptions">Reset Semua Penukaran</option>
                        <option value="rewards">Reset Semua Hadiah</option>
                        <option value="customers">Reset Semua Pelanggan</option>
                        <option value="all">Reset Seluruh Sistem</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="confirm_text" class="form-label">Ketik "RESET" untuk Konfirmasi</label>
                    <input type="text" class="form-control" id="confirm_text" name="confirm_text" required>
                </div>
                <div class="col-12 text-end">
                    <button type="submit" name="reset_system_data" class="btn btn-danger">
                        <i class="fas fa-trash-alt me-2"></i>Reset Data
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function confirmReset() {
    const resetType = document.getElementById('reset_type').value;
    const confirmText = document.getElementById('confirm_text').value;

    if (confirmText !== 'RESET') {
        alert('Silakan ketik "RESET" untuk konfirmasi.');
        return false;
    }

    let message = '';

    switch (resetType) {
        case 'transactions':
            message = 'Anda akan menghapus SEMUA data transaksi dan mengatur ulang poin pelanggan menjadi 0.';
            break;
        case 'redemptions':
            message = 'Anda akan menghapus SEMUA data penukaran hadiah.';
            break;
        case 'rewards':
            message = 'Anda akan menghapus SEMUA data hadiah.';
            break;
        case 'customers':
            message = 'Anda akan menghapus SEMUA data pelanggan.';
            break;
        case 'all':
            message = 'Anda akan mereset SELURUH sistem ke kondisi awal. Semua data akan dihapus.';
            break;
    }

    return confirm(message + '\n\nTindakan ini tidak dapat dibatalkan. Lanjutkan?');
}
</script>

<?php
// Include footer
include '../includes/footer.php';
?>