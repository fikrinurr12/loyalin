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

// Filter by business if specified
$filterBusinessId = isset($_GET['business_id']) ? (int)$_GET['business_id'] : null;

// Process payment addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_payment'])) {
    $businessId = (int)$_POST['business_id'];
    $amount = (float)$_POST['amount'];
    $paymentDate = sanitize($conn, $_POST['payment_date']);
    $paymentMethod = sanitize($conn, $_POST['payment_method']);
    $periodMonths = (int)$_POST['period_months'];
    $notes = sanitize($conn, $_POST['notes']);
    
    // Calculate period dates
    $periodStart = date('Y-m-d');
    $periodEnd = date('Y-m-d', strtotime("+{$periodMonths} months"));
    
    // Insert payment record
    $query = "
        INSERT INTO subscription_payments (
            business_id, amount, payment_date, payment_method,
            period_start, period_end, status, notes, verified_by, verified_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'paid', ?, ?, NOW())
    ";
    
    $stmt = $conn->prepare($query);
    $userId = $_SESSION['user_id'];
    $stmt->bind_param("idsssssi", $businessId, $amount, $paymentDate, $paymentMethod, $periodStart, $periodEnd, $notes, $userId);
    
    if ($stmt->execute()) {
        // Update business subscription
        $updateQuery = "
            UPDATE businesses SET
                subscription_status = 'active',
                subscription_end_date = ?
            WHERE id = ?
        ";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("si", $periodEnd, $businessId);
        $updateStmt->execute();
        $updateStmt->close();
        
        logActivity($conn, null, $_SESSION['user_id'], 'ADD_PAYMENT', "Added payment for business ID: {$businessId}, amount: {$amount}");
        $successMsg = "Pembayaran berhasil dicatat dan subscription diperpanjang!";
    } else {
        $errorMsg = "Gagal mencatat pembayaran.";
    }
    
    $stmt->close();
}

// Get all businesses for filter
$businessesQuery = "SELECT id, business_name FROM businesses ORDER BY business_name ASC";
$businessesResult = $conn->query($businessesQuery);

// Get payments with filter
$query = "
    SELECT 
        sp.*,
        b.business_name,
        u.name as verified_by_name
    FROM subscription_payments sp
    JOIN businesses b ON sp.business_id = b.id
    LEFT JOIN users u ON sp.verified_by = u.id
";

if ($filterBusinessId) {
    $query .= " WHERE sp.business_id = {$filterBusinessId}";
}

$query .= " ORDER BY sp.created_at DESC";
$result = $conn->query($query);

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-calendar-check me-2"></i>Kelola Subscription</h2>
            <p>Manajemen pembayaran subscription UMKM</p>
        </div>
        <div class="col-md-4 text-md-end">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                <i class="fas fa-plus-circle me-2"></i>Catat Pembayaran
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

    <!-- Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row g-3">
                <div class="col-md-4">
                    <label for="business_id" class="form-label">Filter by UMKM</label>
                    <select class="form-select" id="business_id" name="business_id" onchange="this.form.submit()">
                        <option value="">Semua UMKM</option>
                        <?php 
                        $businessesResult->data_seek(0);
                        while($b = $businessesResult->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $b['id']; ?>" <?php echo $filterBusinessId == $b['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($b['business_name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <?php if($filterBusinessId): ?>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <a href="subscriptions.php" class="btn btn-secondary d-block">Clear Filter</a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">History Pembayaran</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>UMKM</th>
                        <th>Jumlah</th>
                        <th>Tanggal Bayar</th>
                        <th>Metode</th>
                        <th>Periode</th>
                        <th>Status</th>
                        <th>Diverifikasi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result->num_rows > 0): ?>
                    <?php while($payment = $result->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($payment['business_name']); ?></strong></td>
                        <td><?php echo formatCurrency($payment['amount']); ?></td>
                        <td><?php echo formatDate($payment['payment_date'], 'd M Y'); ?></td>
                        <td><?php echo htmlspecialchars($payment['payment_method'] ?: '-'); ?></td>
                        <td>
                            <small>
                                <?php echo formatDate($payment['period_start'], 'd M Y'); ?><br>
                                s/d <?php echo formatDate($payment['period_end'], 'd M Y'); ?>
                            </small>
                        </td>
                        <td>
                            <?php if($payment['status'] == 'paid'): ?>
                                <span class="badge bg-success">Paid</span>
                            <?php elseif($payment['status'] == 'pending'): ?>
                                <span class="badge bg-warning">Pending</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Failed</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small>
                                <?php echo htmlspecialchars($payment['verified_by_name'] ?: '-'); ?><br>
                                <?php if($payment['verified_at']): ?>
                                    <?php echo formatDate($payment['verified_at'], 'd M Y H:i'); ?>
                                <?php endif; ?>
                            </small>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center">Belum ada pembayaran tercatat</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Payment Modal -->
<div class="modal fade" id="addPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Catat Pembayaran Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_business_id" class="form-label">UMKM <span class="text-danger">*</span></label>
                        <select class="form-select" id="modal_business_id" name="business_id" required>
                            <option value="">Pilih UMKM</option>
                            <?php 
                            $businessesResult->data_seek(0);
                            while($b = $businessesResult->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['business_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="amount" class="form-label">Jumlah (Rp) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="amount" name="amount" min="0" step="1000" required>
                    </div>

                    <div class="mb-3">
                        <label for="payment_date" class="form-label">Tanggal Pembayaran <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="payment_date" name="payment_date" 
                            value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Metode Pembayaran</label>
                        <select class="form-select" id="payment_method" name="payment_method">
                            <option value="Transfer Bank">Transfer Bank</option>
                            <option value="Cash">Cash</option>
                            <option value="E-Wallet">E-Wallet</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="period_months" class="form-label">Durasi Perpanjangan (bulan) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="period_months" name="period_months" 
                            value="1" min="1" max="12" required>
                        <div class="form-text">Subscription akan diperpanjang selama X bulan dari hari ini</div>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">Catatan</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="add_payment" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Simpan Pembayaran
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>
