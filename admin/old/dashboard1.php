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

// Get total customers
$queryCustomers = "SELECT COUNT(*) as total FROM users WHERE role = 'customer'";
$resultCustomers = $conn->query($queryCustomers);
$totalCustomers = $resultCustomers->fetch_assoc()['total'];

// Get total transactions
$queryTransactions = "SELECT COUNT(*) as total, SUM(amount) as total_amount FROM transactions";
$resultTransactions = $conn->query($queryTransactions);
$transactionStats = $resultTransactions->fetch_assoc();

// Get total points issued
$queryPoints = "SELECT SUM(points_earned) as total FROM transactions";
$resultPoints = $conn->query($queryPoints);
$totalPoints = $resultPoints->fetch_assoc()['total'] ?: 0;

// Get total redemptions
$queryRedemptions = "SELECT COUNT(*) as total FROM redemptions";
$resultRedemptions = $conn->query($queryRedemptions);
$totalRedemptions = $resultRedemptions->fetch_assoc()['total'];

// Get pending redemptions
$queryPendingRedemptions = "SELECT COUNT(*) as total FROM redemptions WHERE status = 'pending'";
$resultPendingRedemptions = $conn->query($queryPendingRedemptions);
$pendingRedemptions = $resultPendingRedemptions->fetch_assoc()['total'];

// Get recent transactions
$queryRecentTransactions = "
    SELECT t.*, u.name as customer_name, u.phone as customer_phone 
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    ORDER BY t.transaction_date DESC
    LIMIT 5
";
$resultRecentTransactions = $conn->query($queryRecentTransactions);

// Get recent redemptions
$queryRecentRedemptions = "
    SELECT r.*, u.name as customer_name, u.phone as customer_phone, rw.name as reward_name
    FROM redemptions r
    JOIN users u ON r.user_id = u.id
    JOIN rewards rw ON r.reward_id = rw.id
    ORDER BY r.redemption_date DESC
    LIMIT 5
";
$resultRecentRedemptions = $conn->query($queryRecentRedemptions);

// Process redemption code verification if submitted
$redemptionMessage = '';
$redemptionError = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['redemption_code'])) {
    $redemptionCode = sanitize($conn, $_POST['redemption_code']);
    
    // Check if redemption code exists and is pending
    $query = "
        SELECT r.*, u.name as customer_name, u.phone as customer_phone, rw.name as reward_name, rw.points_required
        FROM redemptions r
        JOIN users u ON r.user_id = u.id
        JOIN rewards rw ON r.reward_id = rw.id
        WHERE r.redemption_code = ? AND r.status = 'pending'
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $redemptionCode);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $redemption = $result->fetch_assoc();
        
        // Update redemption status to redeemed
        $updateQuery = "UPDATE redemptions SET status = 'redeemed' WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("i", $redemption['id']);
        
        if ($updateStmt->execute()) {
            $redemptionMessage = 'Penukaran berhasil diverifikasi! ' . 
                                $redemption['customer_name'] . ' (' . $redemption['customer_phone'] . ') ' .
                                'mendapatkan ' . $redemption['reward_name'] . ' dengan menukarkan ' . 
                                $redemption['points_used'] . ' poin.';
        } else {
            $redemptionError = 'Terjadi kesalahan saat memverifikasi penukaran.';
        }
        
        $updateStmt->close();
    } else {
        $redemptionError = 'Kode penukaran tidak valid atau sudah digunakan.';
    }
    
    $stmt->close();
}

// Get points ratio
$pointsRatio = getSetting($conn, 'points_ratio', 10000);

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2>Dashboard Admin</h2>
            <p>Selamat datang, <?php echo $_SESSION['name']; ?>!</p>
        </div>
        <div class="col-md-6 text-md-end">
            <div class="btn-group mb-2 me-2">
                <a href="../admin/transactions.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle me-2"></i>Tambah Transaksi
                </a>
                <a href="../admin/manage_rewards.php" class="btn btn-outline-primary">
                    <i class="fas fa-gift me-2"></i>Kelola Hadiah
                </a>
            </div>
            <a href="../admin/points_setting.php" class="btn btn-secondary mb-2">
                <i class="fas fa-cog me-2"></i>Atur Poin
            </a>
        </div>
    </div>

    <!-- Points Ratio Info -->
    <div class="alert alert-primary mb-4">
        <div class="d-flex align-items-center">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle fa-2x me-3"></i>
            </div>
            <div class="flex-grow-1">
                <h5 class="alert-heading mb-1">Pengaturan Poin Saat Ini</h5>
                <p class="mb-0">Setiap transaksi <strong>Rp
                        <?php echo number_format($pointsRatio, 0, ',', '.'); ?></strong> akan mendapatkan <strong>1
                        poin</strong>.
                    <a href="points_setting.php" class="alert-link">Ubah pengaturan</a>
                </p>
            </div>
        </div>
    </div>

    <!-- Redemption Code Verification -->
    <div class="row mb-4">
        <div class="col-lg-8 col-md-12">
            <div class="card h-100">
                <div class="card-header">
                    <h5>Verifikasi Kode Penukaran</h5>
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

                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row g-3">
                        <div class="col-md-8">
                            <input type="text" class="form-control form-control-lg" name="redemption_code"
                                placeholder="Masukkan kode penukaran (SATE-XXXX)" required>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary btn-lg w-100">Verifikasi</button>
                        </div>
                    </form>
                    <div class="mt-3">
                        <p class="text-muted mb-0">
                            <i class="fas fa-lightbulb me-2"></i>
                            Kode penukaran diberikan kepada pelanggan saat mereka menukar poin mereka dengan hadiah.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-12">
            <div class="card text-center h-100">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="fas fa-hourglass-half me-2"></i>Penukaran Menunggu
                    </h5>
                </div>
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <h1 class="display-1 mb-3 fw-bold text-warning"><?php echo $pendingRedemptions; ?></h1>
                    <p class="mb-3">penukaran yang belum diproses</p>
                    <?php if($pendingRedemptions > 0): ?>
                    <a href="#pendingRedemptions" class="btn btn-warning">
                        <i class="fas fa-arrow-down me-2"></i>Lihat Detail
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <i class="fas fa-users"></i>
                <div class="stats-title">Total Pelanggan</div>
                <div class="stats-value"><?php echo $totalCustomers; ?></div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <i class="fas fa-shopping-cart"></i>
                <div class="stats-title">Total Transaksi</div>
                <div class="stats-value"><?php echo $transactionStats['total'] ?: 0; ?></div>
                <div class="mt-2 text-muted">
                    Rp <?php echo number_format($transactionStats['total_amount'] ?: 0, 0, ',', '.'); ?>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <i class="fas fa-coins"></i>
                <div class="stats-title">Total Poin</div>
                <div class="stats-value"><?php echo $totalPoints; ?></div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <i class="fas fa-gift"></i>
                <div class="stats-title">Total Penukaran</div>
                <div class="stats-value"><?php echo $totalRedemptions; ?></div>
            </div>
        </div>
    </div>

    <!-- Recent Transactions and Redemptions -->
    <div class="row">
        <!-- Recent Transactions -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Transaksi Terbaru</h5>
                    <a href="../admin/transactions.php" class="btn btn-sm btn-primary">Lihat Semua</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Pelanggan</th>
                                <th>Jumlah</th>
                                <th>Poin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($resultRecentTransactions->num_rows > 0): ?>
                            <?php while($transaction = $resultRecentTransactions->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($transaction['transaction_date'])); ?></td>
                                <td><?php echo $transaction['customer_name']; ?></td>
                                <td>Rp <?php echo number_format($transaction['amount'], 0, ',', '.'); ?></td>
                                <td><span class="badge bg-primary"><?php echo $transaction['points_earned']; ?>
                                        poin</span></td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">Belum ada transaksi</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Redemptions -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0" id="pendingRedemptions">Penukaran Terbaru</h5>
                    <a href="../admin/manage_rewards.php" class="btn btn-sm btn-primary">Kelola Hadiah</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Pelanggan</th>
                                <th>Hadiah</th>
                                <th>Kode</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($resultRecentRedemptions->num_rows > 0): ?>
                            <?php while($redemption = $resultRecentRedemptions->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($redemption['redemption_date'])); ?></td>
                                <td><?php echo $redemption['customer_name']; ?></td>
                                <td><?php echo $redemption['reward_name']; ?></td>
                                <td><span class="redemption-code"><?php echo $redemption['redemption_code']; ?></span>
                                </td>
                                <td>
                                    <?php if($redemption['status'] == 'pending'): ?>
                                    <span class="badge bg-warning">Menunggu</span>
                                    <?php else: ?>
                                    <span class="badge bg-success">Ditukar</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">Belum ada penukaran</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>