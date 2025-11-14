<?php
// Include database configuration
require_once '../config/db.php';
$basePath = '../';
session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

$businessId = $_SESSION['business_id'];
$businessName = $_SESSION['business_name'];

// Get points ratio for modal display
$queryBusiness = "SELECT points_ratio FROM businesses WHERE id = ?";
$stmtBusiness = $conn->prepare($queryBusiness);
$stmtBusiness->bind_param("i", $businessId);
$stmtBusiness->execute();
$resultBusiness = $stmtBusiness->get_result();
$businessData = $resultBusiness->fetch_assoc();
$pointsRatio = $businessData['points_ratio'] ?? 10000;
$stmtBusiness->close();

// Get total customers FOR THIS BUSINESS ONLY
$queryCustomers = "SELECT COUNT(*) as total FROM users WHERE role = 'customer' AND business_id = ?";
$stmtCustomers = $conn->prepare($queryCustomers);
$stmtCustomers->bind_param("i", $businessId);
$stmtCustomers->execute();
$totalCustomers = $stmtCustomers->get_result()->fetch_assoc()['total'];
$stmtCustomers->close();

// Get total transactions FOR THIS BUSINESS ONLY
$queryTransactions = "SELECT COUNT(*) as total, SUM(amount) as total_amount FROM transactions WHERE business_id = ?";
$stmtTransactions = $conn->prepare($queryTransactions);
$stmtTransactions->bind_param("i", $businessId);
$stmtTransactions->execute();
$transactionStats = $stmtTransactions->get_result()->fetch_assoc();
$stmtTransactions->close();

// Get total points issued FOR THIS BUSINESS ONLY
$queryPoints = "SELECT SUM(points_earned) as total FROM transactions WHERE business_id = ?";
$stmtPoints = $conn->prepare($queryPoints);
$stmtPoints->bind_param("i", $businessId);
$stmtPoints->execute();
$totalPoints = $stmtPoints->get_result()->fetch_assoc()['total'] ?: 0;
$stmtPoints->close();

// Get total redemptions FOR THIS BUSINESS ONLY
$queryRedemptions = "SELECT COUNT(*) as total FROM redemptions WHERE business_id = ?";
$stmtRedemptions = $conn->prepare($queryRedemptions);
$stmtRedemptions->bind_param("i", $businessId);
$stmtRedemptions->execute();
$totalRedemptions = $stmtRedemptions->get_result()->fetch_assoc()['total'];
$stmtRedemptions->close();

// Get pending redemptions FOR THIS BUSINESS ONLY
$queryPendingRedemptions = "SELECT COUNT(*) as total FROM redemptions WHERE status = 'pending' AND business_id = ?";
$stmtPendingRedemptions = $conn->prepare($queryPendingRedemptions);
$stmtPendingRedemptions->bind_param("i", $businessId);
$stmtPendingRedemptions->execute();
$pendingRedemptions = $stmtPendingRedemptions->get_result()->fetch_assoc()['total'];
$stmtPendingRedemptions->close();

// Get recent transactions FOR THIS BUSINESS ONLY
$queryRecentTransactions = "
    SELECT t.*, u.name as customer_name, u.phone as customer_phone 
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE t.business_id = ?
    ORDER BY t.transaction_date DESC
    LIMIT 5
";
$stmtRecentTransactions = $conn->prepare($queryRecentTransactions);
$stmtRecentTransactions->bind_param("i", $businessId);
$stmtRecentTransactions->execute();
$resultRecentTransactions = $stmtRecentTransactions->get_result();

// Get recent redemptions FOR THIS BUSINESS ONLY
$queryRecentRedemptions = "
    SELECT r.*, u.name as customer_name, u.phone as customer_phone, rw.name as reward_name
    FROM redemptions r
    JOIN users u ON r.user_id = u.id
    JOIN rewards rw ON r.reward_id = rw.id
    WHERE r.business_id = ?
    ORDER BY r.redemption_date DESC
    LIMIT 5
";
$stmtRecentRedemptions = $conn->prepare($queryRecentRedemptions);
$stmtRecentRedemptions->bind_param("i", $businessId);
$stmtRecentRedemptions->execute();
$resultRecentRedemptions = $stmtRecentRedemptions->get_result();

// Process redemption code verification if submitted
$redemptionMessage = '';
$redemptionError = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['redemption_code'])) {
    $redemptionCode = sanitize($conn, $_POST['redemption_code']);
    
    // Check if redemption code exists FOR THIS BUSINESS ONLY
    $query = "
        SELECT r.*, u.name as customer_name, u.phone as customer_phone, rw.name as reward_name, rw.points_required
        FROM redemptions r
        JOIN users u ON r.user_id = u.id
        JOIN rewards rw ON r.reward_id = rw.id
        WHERE r.redemption_code = ? AND r.status = 'pending' AND r.business_id = ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $redemptionCode, $businessId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $redemption = $result->fetch_assoc();
        
        $updateQuery = "UPDATE redemptions SET status = 'claimed', claimed_at = NOW() WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("i", $redemption['id']);
        
        if ($updateStmt->execute()) {
            logActivity($conn, $businessId, $_SESSION['user_id'], 'REDEEM_REWARD', "Verified redemption code: {$redemptionCode}");
            
            $redemptionMessage = "Kode penukaran berhasil diverifikasi! Pelanggan: {$redemption['customer_name']}, Hadiah: {$redemption['reward_name']}";
        } else {
            $redemptionError = "Terjadi kesalahan saat memverifikasi kode penukaran.";
        }
        
        $updateStmt->close();
    } else {
        $redemptionError = "Kode penukaran tidak valid atau sudah digunakan untuk UMKM ini.";
    }
    
    $stmt->close();
}

include '../includes/header.php';
?>

<div class="container py-4">
    <!-- Business Info Banner -->
    <div class="alert alert-info mb-4" style="background: linear-gradient(135deg, #154c79, #1565a0); border: none; color: white;">
        <div class="d-flex align-items-center">
            <i class="fas fa-store fa-2x me-3"></i>
            <div>
                <h5 class="mb-1">Dashboard Admin - <?php echo htmlspecialchars($businessName); ?></h5>
                <small>Anda hanya dapat melihat dan mengelola data untuk UMKM ini</small>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Dashboard Admin</h2>
            <p>Selamat datang, <?php echo $_SESSION['name']; ?>!</p>
        </div>
        <div class="col-md-4 text-md-end">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                <i class="fas fa-plus-circle me-2"></i>Tambah Transaksi
            </button>
        </div>
    </div>

    <!-- Redemption Code Verification -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-qrcode me-2"></i>Verifikasi Kode Penukaran</h5>
        </div>
        <div class="card-body">
            <?php if($redemptionMessage): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $redemptionMessage; ?>
            </div>
            <?php endif; ?>

            <?php if($redemptionError): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $redemptionError; ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="row align-items-end">
                    <div class="col-md-8">
                        <label for="redemption_code" class="form-label">Masukkan Kode Penukaran</label>
                        <input type="text" class="form-control form-control-lg" id="redemption_code" 
                            name="redemption_code" placeholder="Contoh: RDM123456" required>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-success btn-lg w-100">
                            <i class="fas fa-check me-2"></i>Verifikasi
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">Total Pelanggan</h6>
                            <h2><?php echo $totalCustomers; ?></h2>
                        </div>
                        <div class="stats-icon bg-primary">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">Total Transaksi</h6>
                            <h2><?php echo $transactionStats['total'] ?: 0; ?></h2>
                            <small class="text-muted">Rp <?php echo number_format($transactionStats['total_amount'] ?: 0, 0, ',', '.'); ?></small>
                        </div>
                        <div class="stats-icon bg-success">
                            <i class="fas fa-receipt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">Total Poin Diberikan</h6>
                            <h2><?php echo number_format($totalPoints); ?></h2>
                        </div>
                        <div class="stats-icon bg-warning">
                            <i class="fas fa-coins"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">Penukaran Pending</h6>
                            <h2><?php echo $pendingRedemptions; ?></h2>
                            <small class="text-muted">dari <?php echo $totalRedemptions; ?> total</small>
                        </div>
                        <div class="stats-icon bg-info">
                            <i class="fas fa-gift"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Transaksi Terbaru</h5>
                </div>
                <div class="card-body">
                    <?php if($resultRecentTransactions->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Pelanggan</th>
                                    <th>Jumlah</th>
                                    <th>Poin</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($transaction = $resultRecentTransactions->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($transaction['transaction_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['customer_name']); ?></td>
                                    <td>Rp <?php echo number_format($transaction['amount'], 0, ',', '.'); ?></td>
                                    <td><span class="badge bg-success">+<?php echo $transaction['points_earned']; ?></span></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-center text-muted">Belum ada transaksi</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-gift me-2"></i>Penukaran Terbaru</h5>
                </div>
                <div class="card-body">
                    <?php if($resultRecentRedemptions->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Pelanggan</th>
                                    <th>Hadiah</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($redemption = $resultRecentRedemptions->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($redemption['redemption_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($redemption['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($redemption['reward_name']); ?></td>
                                    <td>
                                        <?php if($redemption['status'] == 'pending'): ?>
                                        <span class="badge bg-warning">Pending</span>
                                        <?php else: ?>
                                        <span class="badge bg-success">Claimed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-center text-muted">Belum ada penukaran</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ✅ MODAL SAMA DENGAN TRANSACTIONS.PHP - DENGAN BUTTON CEK NOMOR! -->
<div class="modal fade" id="addTransactionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Transaksi Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="transactions.php" id="transactionForm">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Rasio Poin <?php echo htmlspecialchars($businessName); ?>:</strong> 
                            Rp <?php echo number_format($pointsRatio, 0, ',', '.'); ?> = 1 poin
                        </small>
                    </div>

                    <!-- ✅ NOMOR HP dengan BUTTON CEK -->
                    <div class="mb-3">
                        <label for="phone" class="form-label">Nomor HP Pelanggan</label>
                        <div class="input-group">
                            <span class="input-group-text">+62</span>
                            <input type="text" class="form-control" id="phone" name="phone" 
                                placeholder="8xxxxxxxxxx" required>
                            <button class="btn btn-outline-primary" type="button" id="checkPhoneBtn">
                                <i class="fas fa-search me-1"></i>Cek Nomor
                            </button>
                        </div>
                        <div class="form-text">Masukkan nomor tanpa awalan 0 atau +62</div>
                    </div>

                    <!-- ✅ HASIL CEK NOMOR -->
                    <div id="customerCheckResult" class="mb-3" style="display: none;">
                        <!-- Will be filled by JavaScript -->
                    </div>

                    <div class="mb-3">
                        <label for="amount" class="form-label">Jumlah Transaksi (Rp)</label>
                        <input type="number" class="form-control form-control-lg" id="amount" name="amount" 
                            placeholder="50000" min="1" step="1" required>
                    </div>

                    <div class="alert alert-secondary">
                        <h6 class="mb-2">Simulasi Poin:</h6>
                        <div id="pointsPreview" class="text-center">
                            <span class="text-muted">Masukkan jumlah transaksi untuk melihat poin...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="add_transaction" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save me-2"></i>Simpan Transaksi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Points preview calculator
const pointsRatio = <?php echo $pointsRatio; ?>;

document.getElementById('amount').addEventListener('input', function() {
    const amount = parseInt(this.value) || 0;
    const points = Math.floor(amount / pointsRatio);
    const preview = document.getElementById('pointsPreview');
    
    if (amount > 0) {
        preview.innerHTML = `
            <div class="d-flex align-items-center justify-content-center gap-3">
                <div>
                    <strong class="text-primary" style="font-size: 1.2rem;">Rp ${amount.toLocaleString('id-ID')}</strong>
                </div>
                <i class="fas fa-arrow-right text-muted"></i>
                <div>
                    <span class="badge bg-success" style="font-size: 1.1rem;">+${points} poin</span>
                </div>
            </div>
        `;
    } else {
        preview.innerHTML = '<span class="text-muted">Masukkan jumlah transaksi...</span>';
    }
});

// Format phone number input
document.getElementById('phone').addEventListener('input', function() {
    let phone = this.value.replace(/\D/g, '');
    if (phone.startsWith('0')) phone = phone.substring(1);
    if (phone.startsWith('62')) phone = phone.substring(2);
    this.value = phone;
});

// ✅ CEK NOMOR HP BUTTON
document.getElementById('checkPhoneBtn').addEventListener('click', function() {
    const phone = document.getElementById('phone').value.trim();
    const resultDiv = document.getElementById('customerCheckResult');
    const btn = this;
    
    if (!phone) {
        resultDiv.innerHTML = `
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Masukkan nomor HP dulu!
            </div>
        `;
        resultDiv.style.display = 'block';
        return;
    }
    
    // Show loading
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Mengecek...';
    resultDiv.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Mengecek nomor...</div>';
    resultDiv.style.display = 'block';
    
    // AJAX request
    fetch('check_customer.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'phone=' + encodeURIComponent(phone)
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'found') {
            // ✅ CUSTOMER DITEMUKAN
            resultDiv.innerHTML = `
                <div class="alert alert-success">
                    <h6 class="alert-heading mb-2">
                        <i class="fas fa-check-circle me-2"></i>Customer Ditemukan!
                    </h6>
                    <div class="row">
                        <div class="col-6"><strong>Nama:</strong></div>
                        <div class="col-6">${data.customer.name}</div>
                    </div>
                    <div class="row">
                        <div class="col-6"><strong>HP:</strong></div>
                        <div class="col-6">${data.customer.phone}</div>
                    </div>
                    <div class="row">
                        <div class="col-6"><strong>Total Poin:</strong></div>
                        <div class="col-6"><span class="badge bg-primary">${data.customer.total_points} poin</span></div>
                    </div>
                </div>
            `;
            document.getElementById('submitBtn').disabled = false;
        } else {
            // ❌ CUSTOMER TIDAK DITEMUKAN
            const registerLink = data.in_other_business ? '' : 
                '<div class="mt-2"><a href="../register.php" target="_blank" class="btn btn-sm btn-primary"><i class="fas fa-user-plus me-1"></i>Daftarkan Customer Baru</a></div>';
            
            resultDiv.innerHTML = `
                <div class="alert alert-danger">
                    <h6 class="alert-heading">
                        <i class="fas fa-times-circle me-2"></i>Customer Tidak Ditemukan
                    </h6>
                    <p class="mb-0">${data.message}</p>
                    ${registerLink}
                </div>
            `;
            document.getElementById('submitBtn').disabled = true;
        }
    })
    .catch(error => {
        resultDiv.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Terjadi kesalahan saat mengecek nomor
            </div>
        `;
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-search me-1"></i>Cek Nomor';
    });
});

// Reset form saat modal dibuka
document.getElementById('addTransactionModal').addEventListener('show.bs.modal', function() {
    document.getElementById('transactionForm').reset();
    document.getElementById('customerCheckResult').style.display = 'none';
    document.getElementById('submitBtn').disabled = false;
});
</script>

<?php
$stmtRecentTransactions->close();
$stmtRecentRedemptions->close();
include '../includes/footer.php';
?>
