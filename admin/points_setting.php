<?php
// Include database configuration
require_once '../config/db.php';

// Define base path for includes
$basePath = '../';

// Start session
session_start();

// Check if user is logged in and is admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

// ✅ CRITICAL: Get business_id from session
$businessId = $_SESSION['business_id'];
$businessName = $_SESSION['business_name'];

// Initialize messages
$successMsg = '';
$errorMsg = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_points_ratio'])) {
    $pointsRatio = (int)sanitize($conn, $_POST['points_ratio']);
    
    // Validate input
    if ($pointsRatio <= 0) {
        $errorMsg = "Rasio poin harus berupa angka positif.";
    } else {
        // ✅ FIX: Update points_ratio di tabel businesses untuk UMKM ini aja!
        $query = "UPDATE businesses SET points_ratio = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $pointsRatio, $businessId);
        
        if ($stmt->execute()) {
            // Log activity
            logActivity($conn, $businessId, $_SESSION['user_id'], 'UPDATE_POINTS_RATIO', 
                "Updated points ratio to: 1 poin = Rp {$pointsRatio}");
            
            $successMsg = "Rasio poin untuk " . htmlspecialchars($businessName) . 
                         " berhasil diperbarui menjadi: Rp " . number_format($pointsRatio, 0, ',', '.') . " = 1 poin";
        } else {
            $errorMsg = "Terjadi kesalahan saat memperbarui rasio poin.";
        }
        
        $stmt->close();
    }
}

// ✅ FIX: Get points_ratio dari tabel businesses untuk UMKM ini aja!
$query = "SELECT points_ratio FROM businesses WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $businessId);
$stmt->execute();
$result = $stmt->get_result();
$business = $result->fetch_assoc();
$pointsRatio = $business['points_ratio'] ?? 10000;
$stmt->close();

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <!-- Business Info Banner -->
    <div class="alert alert-info mb-4" style="background: linear-gradient(135deg, #154c79, #1565a0); border: none; color: white;">
        <div class="d-flex align-items-center">
            <i class="fas fa-coins fa-2x me-3"></i>
            <div>
                <h5 class="mb-1">Pengaturan Poin - <?php echo htmlspecialchars($businessName); ?></h5>
                <small>Rasio poin yang Anda atur hanya berlaku untuk UMKM ini</small>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Pengaturan Poin</h2>
            <p>Atur rasio transaksi terhadap poin yang didapat pelanggan <strong><?php echo htmlspecialchars($businessName); ?></strong>.</p>
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
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if($errorMsg): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $errorMsg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Current Points Ratio -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Rasio Poin Saat Ini</h5>
        </div>
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h3 class="text-primary mb-0">Rp <?php echo number_format($pointsRatio, 0, ',', '.'); ?> = 1 Poin</h3>
                    <p class="text-muted mb-0">Setiap pembelian Rp <?php echo number_format($pointsRatio, 0, ',', '.'); ?> akan mendapat 1 poin</p>
                </div>
                <div class="col-md-6 text-end">
                    <div class="p-3 rounded" style="background-color: #f8f9fa;">
                        <h6 class="text-muted mb-2">Contoh:</h6>
                        <div class="mb-1">
                            <strong>Transaksi Rp <?php echo number_format($pointsRatio * 5, 0, ',', '.'); ?></strong>
                            <i class="fas fa-arrow-right mx-2 text-muted"></i>
                            <span class="badge bg-success">+5 poin</span>
                        </div>
                        <div>
                            <strong>Transaksi Rp <?php echo number_format($pointsRatio * 10, 0, ',', '.'); ?></strong>
                            <i class="fas fa-arrow-right mx-2 text-muted"></i>
                            <span class="badge bg-success">+10 poin</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Points Ratio Form -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Ubah Rasio Poin</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="mb-3">
                    <label for="points_ratio" class="form-label">
                        Rasio Poin Baru (Rp per 1 poin)
                    </label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text">Rp</span>
                        <input type="number" class="form-control" id="points_ratio" name="points_ratio" 
                            value="<?php echo $pointsRatio; ?>" min="1" step="1" required>
                        <span class="input-group-text">= 1 Poin</span>
                    </div>
                    <div class="form-text">
                        <i class="fas fa-info-circle me-1"></i>
                        Masukkan jumlah rupiah yang diperlukan untuk mendapatkan 1 poin
                    </div>
                </div>

                <div class="alert alert-warning mb-3">
                    <h6 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Perhatian!</h6>
                    <ul class="mb-0">
                        <li>Perubahan ini <strong>HANYA berlaku untuk <?php echo htmlspecialchars($businessName); ?></strong></li>
                        <li>UMKM lain tidak terpengaruh</li>
                        <li>Transaksi yang sudah ada tidak berubah</li>
                        <li>Hanya transaksi baru yang menggunakan rasio poin yang baru</li>
                    </ul>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <h6 class="mb-3">Rekomendasi Rasio:</h6>
                        <div class="d-flex flex-column gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm text-start" 
                                onclick="document.getElementById('points_ratio').value = 5000">
                                <strong>Rp 5.000 = 1 poin</strong> <span class="text-muted">(untuk UMKM kecil)</span>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm text-start" 
                                onclick="document.getElementById('points_ratio').value = 10000">
                                <strong>Rp 10.000 = 1 poin</strong> <span class="text-muted">(standar)</span>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm text-start" 
                                onclick="document.getElementById('points_ratio').value = 20000">
                                <strong>Rp 20.000 = 1 poin</strong> <span class="text-muted">(untuk premium)</span>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="mb-3">Tips:</h6>
                        <ul class="small text-muted">
                            <li>Rasio lebih kecil = pelanggan dapat poin lebih cepat</li>
                            <li>Rasio lebih besar = lebih eksklusif</li>
                            <li>Sesuaikan dengan rata-rata transaksi Anda</li>
                            <li>Pertimbangkan margin keuntungan</li>
                        </ul>
                    </div>
                </div>

                <hr>

                <div class="text-end">
                    <button type="submit" name="update_points_ratio" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i>Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Examples -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Kalkulator Poin</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Jumlah Transaksi (Rp)</label>
                    <input type="number" class="form-control" id="calc_amount" placeholder="Contoh: 50000">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Poin yang Didapat</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="calc_points" readonly>
                        <span class="input-group-text">poin</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Calculator
const pointsRatio = <?php echo $pointsRatio; ?>;

document.getElementById('calc_amount').addEventListener('input', function() {
    const amount = parseInt(this.value) || 0;
    const points = Math.floor(amount / pointsRatio);
    document.getElementById('calc_points').value = points;
});

// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const ratio = parseInt(document.getElementById('points_ratio').value);
    
    if (ratio < 1000) {
        if (!confirm('Rasio poin sangat kecil (< Rp 1.000). Yakin ingin melanjutkan?')) {
            e.preventDefault();
        }
    }
    
    if (ratio > 100000) {
        if (!confirm('Rasio poin sangat besar (> Rp 100.000). Yakin ingin melanjutkan?')) {
            e.preventDefault();
        }
    }
});
</script>

<?php
// Include footer
include '../includes/footer.php';
?>
