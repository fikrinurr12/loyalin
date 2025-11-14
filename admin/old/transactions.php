<?php
// ... (sama seperti sebelumnya sampai line 36) ...
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

$queryBusiness = "SELECT points_ratio FROM businesses WHERE id = ?";
$stmtBusiness = $conn->prepare($queryBusiness);
$stmtBusiness->bind_param("i", $businessId);
$stmtBusiness->execute();
$resultBusiness = $stmtBusiness->get_result();
$businessData = $resultBusiness->fetch_assoc();
$pointsRatio = $businessData['points_ratio'] ?? 10000;
$stmtBusiness->close();

$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_transaction'])) {
    $phoneNumber = isset($_POST['phone']) ? sanitize($conn, $_POST['phone']) : '';
    $amount = isset($_POST['amount']) ? (float)sanitize($conn, $_POST['amount']) : 0;
    
    if (!empty($phoneNumber)) {
        if (substr($phoneNumber, 0, 1) == '0') {
            $phoneNumber = '62' . substr($phoneNumber, 1);
        } elseif (substr($phoneNumber, 0, 2) != '62') {
            $phoneNumber = '62' . $phoneNumber;
        }
    }
    
    if (empty($phoneNumber)) {
        $errorMsg = "Nomor HP tidak boleh kosong.";
    } elseif (!is_numeric($amount) || $amount <= 0) {
        $errorMsg = "Jumlah transaksi harus berupa angka positif.";
    } else {
        $queryUser = "SELECT id, name, total_points FROM users WHERE phone = ? AND role = 'customer' AND business_id = ?";
        $stmtUser = $conn->prepare($queryUser);
        $stmtUser->bind_param("si", $phoneNumber, $businessId);
        $stmtUser->execute();
        $resultUser = $stmtUser->get_result();
        
        if ($resultUser->num_rows > 0) {
            $user = $resultUser->fetch_assoc();
            $userId = $user['id'];
            $userName = $user['name'];
            $currentPoints = $user['total_points'];
            
            $pointsEarned = floor($amount / $pointsRatio);
            $newTotalPoints = $currentPoints + $pointsEarned;
            
            $conn->begin_transaction();
            
            try {
                $queryTransaction = "INSERT INTO transactions (business_id, user_id, amount, points_earned) VALUES (?, ?, ?, ?)";
                $stmtTransaction = $conn->prepare($queryTransaction);
                $stmtTransaction->bind_param("iidi", $businessId, $userId, $amount, $pointsEarned);
                $stmtTransaction->execute();
                $stmtTransaction->close();
                
                $queryUpdatePoints = "UPDATE users SET total_points = ? WHERE id = ?";
                $stmtUpdatePoints = $conn->prepare($queryUpdatePoints);
                $stmtUpdatePoints->bind_param("ii", $newTotalPoints, $userId);
                $stmtUpdatePoints->execute();
                $stmtUpdatePoints->close();
                
                logActivity($conn, $businessId, $_SESSION['user_id'], 'ADD_TRANSACTION', 
                    "Added transaction for {$userName}: Rp {$amount}, earned {$pointsEarned} points");
                
                $conn->commit();
                
                $successMsg = "Transaksi berhasil! <strong>{$userName}</strong> mendapat <strong>+{$pointsEarned} poin</strong>. Total: <strong>{$newTotalPoints} poin</strong>";
            } catch (Exception $e) {
                $conn->rollback();
                $errorMsg = "Terjadi kesalahan saat menambahkan transaksi: " . $e->getMessage();
            }
        } else {
            $errorMsg = "Nomor HP <strong>{$phoneNumber}</strong> tidak terdaftar sebagai pelanggan di <strong>" . 
                       htmlspecialchars($businessName) . "</strong>. Pastikan customer sudah daftar di UMKM ini.";
        }
        
        $stmtUser->close();
    }
}

$queryAllTransactions = "
    SELECT t.*, u.name as customer_name, u.phone as customer_phone 
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE t.business_id = ?
    ORDER BY t.transaction_date DESC
";
$stmtTransactions = $conn->prepare($queryAllTransactions);
$stmtTransactions->bind_param("i", $businessId);
$stmtTransactions->execute();
$resultAllTransactions = $stmtTransactions->get_result();

include '../includes/header.php';
?>

<div class="container py-4">
    <!-- Business Info Banner -->
    <div class="alert alert-info mb-4" style="background: linear-gradient(135deg, #154c79, #1565a0); border: none; color: white;">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <i class="fas fa-receipt fa-2x me-3"></i>
                <div>
                    <h5 class="mb-1">Transaksi - <?php echo htmlspecialchars($businessName); ?></h5>
                    <small>Anda hanya dapat menambah dan melihat transaksi untuk UMKM ini</small>
                </div>
            </div>
            <div class="text-end">
                <h6 class="mb-0">Rasio Poin:</h6>
                <strong>Rp <?php echo number_format($pointsRatio, 0, ',', '.'); ?> = 1 poin</strong>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Manajemen Transaksi</h2>
            <p>Kelola transaksi dan poin pelanggan <?php echo htmlspecialchars($businessName); ?></p>
        </div>
        <div class="col-md-4 text-md-end">
            <a href="points_setting.php" class="btn btn-outline-primary me-2">
                <i class="fas fa-cog me-1"></i>Atur Poin
            </a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                <i class="fas fa-plus-circle me-2"></i>Tambah Transaksi
            </button>
        </div>
    </div>

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

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Riwayat Transaksi</h5>
            <div class="input-group" style="max-width: 300px;">
                <input type="text" id="searchTransaction" class="form-control" placeholder="Cari pelanggan...">
                <button class="btn btn-outline-secondary" type="button">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover" id="transactionsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tanggal</th>
                        <th>Pelanggan</th>
                        <th>Nomor HP</th>
                        <th>Jumlah</th>
                        <th>Poin Didapat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($resultAllTransactions->num_rows > 0): ?>
                    <?php while($transaction = $resultAllTransactions->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $transaction['id']; ?></td>
                        <td><?php echo date('d M Y H:i', strtotime($transaction['transaction_date'])); ?></td>
                        <td><?php echo htmlspecialchars($transaction['customer_name']); ?></td>
                        <td><?php echo $transaction['customer_phone']; ?></td>
                        <td><strong>Rp <?php echo number_format($transaction['amount'], 0, ',', '.'); ?></strong></td>
                        <td><span class="badge bg-success">+<?php echo $transaction['points_earned']; ?> poin</span></td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">
                            <div class="py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted mb-0">Belum ada transaksi untuk <?php echo htmlspecialchars($businessName); ?></p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ✅ MODAL DENGAN BUTTON CEK NOMOR -->
<div class="modal fade" id="addTransactionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Transaksi Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="transactionForm">
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
// Search functionality
document.getElementById('searchTransaction').addEventListener('keyup', function() {
    const searchText = this.value.toLowerCase();
    const table = document.getElementById('transactionsTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

    for (let i = 0; i < rows.length; i++) {
        const rowText = rows[i].textContent.toLowerCase();
        rows[i].style.display = rowText.includes(searchText) ? '' : 'none';
    }
});

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
$stmtTransactions->close();
include '../includes/footer.php';
?>
