<?php
// Include database configuration
require_once '../config/db.php';

// Define base path for includes
$basePath = '../';

// Start session
session_start();

// Check if user is logged in and is customer
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header('Location: ../login.php');
    exit;
}

// Get user ID
$userId = $_SESSION['user_id'];

// Get all transactions for this user
$queryTransactions = "
    SELECT * FROM transactions 
    WHERE user_id = ? 
    ORDER BY transaction_date DESC
";
$stmtTransactions = $conn->prepare($queryTransactions);
$stmtTransactions->bind_param("i", $userId);
$stmtTransactions->execute();
$resultTransactions = $stmtTransactions->get_result();
$stmtTransactions->close();

// Get all redemptions for this user
$queryRedemptions = "
    SELECT r.*, rw.name as reward_name, rw.points_required
    FROM redemptions r
    JOIN rewards rw ON r.reward_id = rw.id
    WHERE r.user_id = ?
    ORDER BY r.redemption_date DESC
";
$stmtRedemptions = $conn->prepare($queryRedemptions);
$stmtRedemptions->bind_param("i", $userId);
$stmtRedemptions->execute();
$resultRedemptions = $stmtRedemptions->get_result();
$stmtRedemptions->close();

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <h2>Riwayat Aktivitas</h2>
    <p>Lihat riwayat transaksi dan penukaran hadiah Anda.</p>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mb-4" id="activityTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="transactions-tab" data-bs-toggle="tab" data-bs-target="#transactions"
                type="button" role="tab" aria-controls="transactions" aria-selected="true">
                <i class="fas fa-shopping-cart me-2"></i>Transaksi
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="redemptions-tab" data-bs-toggle="tab" data-bs-target="#redemptions"
                type="button" role="tab" aria-controls="redemptions" aria-selected="false">
                <i class="fas fa-gift me-2"></i>Penukaran Hadiah
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="activityTabContent">
        <!-- Transactions Tab -->
        <div class="tab-pane fade show active" id="transactions" role="tabpanel" aria-labelledby="transactions-tab">
            <div class="card">
                <div class="card-header">
                    <h5>Riwayat Transaksi</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Jumlah</th>
                                <th>Poin Didapat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($resultTransactions->num_rows > 0): ?>
                            <?php 
                                $totalSpent = 0;
                                $totalPoints = 0;
                                while($transaction = $resultTransactions->fetch_assoc()): 
                                    $totalSpent += $transaction['amount'];
                                    $totalPoints += $transaction['points_earned'];
                                ?>
                            <tr>
                                <td><?php echo date('d M Y H:i', strtotime($transaction['transaction_date'])); ?></td>
                                <td>Rp <?php echo number_format($transaction['amount'], 0, ',', '.'); ?></td>
                                <td><?php echo $transaction['points_earned']; ?> poin</td>
                            </tr>
                            <?php endwhile; ?>
                            <tr class="table-secondary">
                                <td><strong>Total</strong></td>
                                <td><strong>Rp <?php echo number_format($totalSpent, 0, ',', '.'); ?></strong></td>
                                <td><strong><?php echo $totalPoints; ?> poin</strong></td>
                            </tr>
                            <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center">Belum ada transaksi</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Redemptions Tab -->
        <div class="tab-pane fade" id="redemptions" role="tabpanel" aria-labelledby="redemptions-tab">
            <div class="card">
                <div class="card-header">
                    <h5>Riwayat Penukaran Hadiah</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Hadiah</th>
                                <th>Poin Digunakan</th>
                                <th>Kode Penukaran</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($resultRedemptions->num_rows > 0): ?>
                            <?php 
                                $totalPointsUsed = 0;
                                while($redemption = $resultRedemptions->fetch_assoc()): 
                                    $totalPointsUsed += $redemption['points_used'];
                                ?>
                            <tr>
                                <td><?php echo date('d M Y H:i', strtotime($redemption['redemption_date'])); ?></td>
                                <td><?php echo $redemption['reward_name']; ?></td>
                                <td><?php echo $redemption['points_used']; ?> poin</td>
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
                            <tr class="table-secondary">
                                <td colspan="2"><strong>Total Poin Digunakan</strong></td>
                                <td colspan="3"><strong><?php echo $totalPointsUsed; ?> poin</strong></td>
                            </tr>
                            <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">Belum ada penukaran hadiah</td>
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