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

// Check if business_id is set
if(!isset($_SESSION['business_id'])) {
    header('Location: ../select_business.php');
    exit;
}

$businessId = $_SESSION['business_id'];
$userId = $_SESSION['user_id'];

// Check if business subscription is active
if (!isBusinessSubscriptionActive($conn, $businessId)) {
    session_destroy();
    header('Location: ../login.php?error=subscription_expired');
    exit;
}

// Get business information
$business = getBusiness($conn, $businessId);

// Get customer information
$query = "SELECT * FROM users WHERE id = ? AND business_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $userId, $businessId);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();
$stmt->close();

// Get available rewards (BUSINESS-SCOPED)
$queryRewards = "SELECT * FROM rewards WHERE business_id = ? AND status = 'active' ORDER BY points_required ASC";
$stmtRewards = $conn->prepare($queryRewards);
$stmtRewards->bind_param("i", $businessId);
$stmtRewards->execute();
$resultRewards = $stmtRewards->get_result();
$stmtRewards->close();

// Get recent transactions (BUSINESS-SCOPED)
$queryTransactions = "
    SELECT * FROM transactions 
    WHERE user_id = ? AND business_id = ?
    ORDER BY transaction_date DESC 
    LIMIT 5
";
$stmtTransactions = $conn->prepare($queryTransactions);
$stmtTransactions->bind_param("ii", $userId, $businessId);
$stmtTransactions->execute();
$resultTransactions = $stmtTransactions->get_result();
$stmtTransactions->close();

// Get recent redemptions (BUSINESS-SCOPED)
$queryRedemptions = "
    SELECT r.*, rw.name as reward_name, rw.points_required
    FROM redemptions r
    JOIN rewards rw ON r.reward_id = rw.id
    WHERE r.user_id = ? AND r.business_id = ?
    ORDER BY r.redemption_date DESC
    LIMIT 5
";
$stmtRedemptions = $conn->prepare($queryRedemptions);
$stmtRedemptions->bind_param("ii", $userId, $businessId);
$stmtRedemptions->execute();
$resultRedemptions = $stmtRedemptions->get_result();
$stmtRedemptions->close();

// Process reward redemption if requested
$redemptionMessage = '';
$redemptionError = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['redeem_reward'])) {
    $rewardId = sanitize($conn, $_POST['reward_id']);
    
    // Get reward information (BUSINESS-SCOPED)
    $queryReward = "SELECT * FROM rewards WHERE id = ? AND business_id = ?";
    $stmtReward = $conn->prepare($queryReward);
    $stmtReward->bind_param("ii", $rewardId, $businessId);
    $stmtReward->execute();
    $resultReward = $stmtReward->get_result();
    
    if ($resultReward->num_rows > 0) {
        $reward = $resultReward->fetch_assoc();
        
        // Check if user has enough points
        if ($customer['total_points'] >= $reward['points_required']) {
            // Generate unique redemption code
            $redemptionCode = generateRedemptionCode();
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Insert redemption record
                $queryInsert = "
                    INSERT INTO redemptions (business_id, user_id, reward_id, points_used, redemption_code) 
                    VALUES (?, ?, ?, ?, ?)
                ";
                $stmtInsert = $conn->prepare($queryInsert);
                $stmtInsert->bind_param("iiiis", $businessId, $userId, $rewardId, $reward['points_required'], $redemptionCode);
                $stmtInsert->execute();
                $stmtInsert->close();
                
                // Update user points
                $newPoints = $customer['total_points'] - $reward['points_required'];
                $queryUpdate = "UPDATE users SET total_points = ? WHERE id = ? AND business_id = ?";
                $stmtUpdate = $conn->prepare($queryUpdate);
                $stmtUpdate->bind_param("iii", $newPoints, $userId, $businessId);
                $stmtUpdate->execute();
                $stmtUpdate->close();
                
                // Commit transaction
                $conn->commit();
                
                // Log activity
                logActivity($conn, $businessId, $userId, 'REDEEM_REWARD', "Redeemed {$reward['name']} for {$reward['points_required']} points");
                
                // Update customer variable for display
                $customer['total_points'] = $newPoints;
                
                // Set success message
                $redemptionMessage = "Selamat! Anda berhasil menukarkan {$reward['points_required']} poin untuk {$reward['name']}. 
                                    Kode penukaran Anda: <span class='redemption-code'>{$redemptionCode}</span>. 
                                    Tunjukkan kode ini kepada admin untuk mendapatkan hadiah Anda.";
                
                // Refresh redemptions list
                $stmtRedemptions = $conn->prepare($queryRedemptions);
                $stmtRedemptions->bind_param("ii", $userId, $businessId);
                $stmtRedemptions->execute();
                $resultRedemptions = $stmtRedemptions->get_result();
                $stmtRedemptions->close();
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $redemptionError = "Terjadi kesalahan saat menukarkan poin. Silakan coba lagi.";
            }
        } else {
            $redemptionError = "Poin Anda tidak cukup untuk menukarkan hadiah ini.";
        }
        
        $stmtReward->close();
    } else {
        $redemptionError = "Hadiah tidak ditemukan.";
    }
}

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <!-- Business Info Banner -->
    <div class="alert alert-info mb-4">
        <div class="d-flex align-items-center">
            <?php if($business['logo_path'] && file_exists('../' . $business['logo_path'])): ?>
            <img src="../<?php echo $business['logo_path']; ?>" alt="Logo"
                style="width: 50px; height: 50px; object-fit: cover; border-radius: 50%; margin-right: 15px;">
            <?php else: ?>
            <div style="width: 50px; height: 50px; border-radius: 50%; margin-right: 15px;
                    background: linear-gradient(135deg, <?php echo $business['primary_color']; ?>, <?php echo $business['accent_color']; ?>);
                    display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-store text-white"></i>
            </div>
            <?php endif; ?>
            <div class="flex-grow-1">
                <strong><?php echo htmlspecialchars($business['business_name']); ?></strong>
                <br>
                <small><?php echo htmlspecialchars($business['address']); ?></small>
            </div>
            <!-- <a href="../select_business.php" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-exchange-alt me-1"></i>Ganti UMKM
            </a> -->
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Dashboard Pelanggan</h2>
            <p>Selamat datang, <?php echo $customer['name']; ?>!</p>
        </div>
    </div>

    <!-- Points Card -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="points-card">
                <h4>Total Poin Anda</h4>
                <div class="points-value"><?php echo $customer['total_points']; ?></div>
                <p>Kumpulkan poin dengan melakukan transaksi di
                    <?php echo htmlspecialchars($business['business_name']); ?>.</p>
            </div>
        </div>
    </div>

    <!-- Redemption Messages -->
    <?php if($redemptionMessage): ?>
    <div class="alert alert-success" role="alert">
        <?php echo $redemptionMessage; ?>
    </div>
    <?php endif; ?>

    <?php if($redemptionError): ?>
    <div class="alert alert-danger" role="alert">
        <?php echo $redemptionError; ?>
    </div>
    <?php endif; ?>

    <!-- Available Rewards -->
    <div class="row mb-4">
        <div class="col-md-12">
            <h4 class="mb-3">Hadiah yang Tersedia</h4>
        </div>

        <?php if($resultRewards->num_rows > 0): ?>
        <?php while($reward = $resultRewards->fetch_assoc()): ?>
        <div class="col-md-4 mb-4">
            <div class="card h-100 reward-card">
                <div class="card-header text-center">
                    <?php echo $reward['name']; ?>
                </div>
                <div class="card-body text-center">
                    <h5 class="points-value"><?php echo $reward['points_required']; ?> Poin</h5>
                    <p class="card-text"><?php echo $reward['description'] ?: 'Tidak ada deskripsi'; ?></p>

                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                        <input type="hidden" name="reward_id" value="<?php echo $reward['id']; ?>">
                        <button type="submit" name="redeem_reward" class="btn btn-primary w-100"
                            <?php echo ($customer['total_points'] < $reward['points_required']) ? 'disabled' : ''; ?>>
                            <?php echo ($customer['total_points'] < $reward['points_required']) ? 'Poin Tidak Cukup' : 'Tukar Hadiah'; ?>
                        </button>
                    </form>
                </div>
                <div class="card-footer text-center">
                    <?php 
                            $pointsNeeded = max(0, $reward['points_required'] - $customer['total_points']);
                            if ($pointsNeeded > 0) {
                                echo "Butuh {$pointsNeeded} poin lagi";
                            } else {
                                echo "Anda dapat menukarkan hadiah ini!";
                            }
                        ?>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
        <?php else: ?>
        <div class="col-12">
            <div class="alert alert-info" role="alert">
                Belum ada hadiah yang tersedia. Silakan cek kembali nanti.
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Recent Transactions -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Transaksi Terbaru</h5>
                    <a href="../customer/history.php" class="btn btn-sm btn-primary">Lihat Semua</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Jumlah</th>
                                <th>Poin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($resultTransactions->num_rows > 0): ?>
                            <?php while($transaction = $resultTransactions->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('d M Y H:i', strtotime($transaction['transaction_date'])); ?></td>
                                <td>Rp <?php echo number_format($transaction['amount'], 0, ',', '.'); ?></td>
                                <td><?php echo $transaction['points_earned']; ?> poin</td>
                            </tr>
                            <?php endwhile; ?>
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

        <!-- Recent Redemptions -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Penukaran Terbaru</h5>
                    <a href="../customer/history.php" class="btn btn-sm btn-primary">Lihat Semua</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Hadiah</th>
                                <th>Kode</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($resultRedemptions->num_rows > 0): ?>
                            <?php while($redemption = $resultRedemptions->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('d M Y H:i', strtotime($redemption['redemption_date'])); ?></td>
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
                                <td colspan="4" class="text-center">Belum ada penukaran</td>
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