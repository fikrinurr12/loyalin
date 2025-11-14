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
$customerPhone = $_SESSION['phone'];

// Get business information
$queryBusiness = "SELECT * FROM businesses WHERE id = ?";
$stmtBusiness = $conn->prepare($queryBusiness);
$stmtBusiness->bind_param("i", $businessId);
$stmtBusiness->execute();
$business = $stmtBusiness->get_result()->fetch_assoc();
$stmtBusiness->close();

// Get customer information
$query = "SELECT * FROM users WHERE id = ? AND business_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $userId, $businessId);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();
$stmt->close();

// ✅ GET ALL BUSINESSES WHERE CUSTOMER IS REGISTERED (for switcher) - FIXED!
$queryAllBusinesses = "
    SELECT DISTINCT b.id, b.business_name, u.id as user_id, u.total_points
    FROM users u
    JOIN businesses b ON u.business_id = b.id
    WHERE u.phone = ? AND u.role = 'customer'
    ORDER BY b.business_name ASC
";
$stmtAllBusinesses = $conn->prepare($queryAllBusinesses);
$stmtAllBusinesses->bind_param("s", $customerPhone);
$stmtAllBusinesses->execute();
$allBusinesses = $stmtAllBusinesses->get_result();
$businessCount = $allBusinesses->num_rows;
$stmtAllBusinesses->close();

// Get available rewards (BUSINESS-SCOPED)
$queryRewards = "SELECT * FROM rewards WHERE business_id = ? AND is_active = 1 ORDER BY points_required ASC";
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

// ✅ CHECK FOR SWITCH SUCCESS MESSAGE
$switchMessage = '';
if(isset($_SESSION['switch_success'])) {
    $switchMessage = $_SESSION['switch_success'];
    unset($_SESSION['switch_success']);
}

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
            // ✅ Generate RANDOM redemption code (no parameter needed!)
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
                                    Kode penukaran Anda: <strong>{$redemptionCode}</strong>. 
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

    <!-- ✅ BUSINESS SWITCHER COMPONENT - Only show if customer in multiple businesses -->
    <?php if($businessCount > 1): ?>
    <div class="business-switcher mb-3">
        <div class="alert d-flex align-items-center justify-content-between"
            style="background: linear-gradient(135deg, #e3f2fd, #f3e5f5); border: 1px solid #2196f3;">
            <div class="d-flex align-items-center">
                <i class="fas fa-store me-2 text-primary"></i>
                <div>
                    <strong>UMKM Aktif:</strong> <?php echo htmlspecialchars($business['business_name']); ?>
                    <br>
                    <small class="text-muted">Anda terdaftar di <?php echo $businessCount; ?> UMKM</small>
                </div>
            </div>
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#switchBusinessModal">
                <i class="fas fa-exchange-alt me-1"></i>Ganti UMKM
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- ✅ SWITCH SUCCESS MESSAGE -->
    <?php if($switchMessage): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo $switchMessage; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Business Info Banner -->
    <div class="alert alert-info mb-4">
        <div class="d-flex align-items-center">
            <?php if(isset($business['logo_path']) && !empty($business['logo_path']) && file_exists('../' . $business['logo_path'])): ?>
            <img src="../<?php echo $business['logo_path']; ?>" alt="Logo"
                style="width: 50px; height: 50px; object-fit: cover; border-radius: 50%; margin-right: 15px;">
            <?php else: ?>
            <div style="width: 50px; height: 50px; border-radius: 50%; margin-right: 15px;
                    background: linear-gradient(135deg, #154c79, #1565a0);
                    display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-store text-white"></i>
            </div>
            <?php endif; ?>
            <div class="flex-grow-1">
                <strong><?php echo htmlspecialchars($business['business_name']); ?></strong>
                <p class="mb-0 small">Sistem Loyalitas Pelanggan</p>
            </div>
        </div>
    </div>

    <!-- Customer Welcome Section -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Selamat Datang, <?php echo htmlspecialchars($customer['name']); ?>!</h2>
            <p>Total poin Anda saat ini</p>
        </div>
        <div class="col-md-4 text-md-end">
            <div class="points-display p-3 bg-primary text-white rounded">
                <i class="fas fa-coins fa-2x mb-2"></i>
                <h3 class="mb-0"><?php echo number_format($customer['total_points']); ?> Poin</h3>
            </div>
        </div>
    </div>

    <!-- Redemption Messages -->
    <?php if($redemptionMessage): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo $redemptionMessage; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if($redemptionError): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $redemptionError; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Available Rewards -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-gift me-2"></i>Hadiah yang Tersedia</h5>
        </div>
        <div class="card-body">
            <?php if($resultRewards->num_rows > 0): ?>
            <div class="row">
                <?php 
                $resultRewards->data_seek(0);
                while($reward = $resultRewards->fetch_assoc()): 
                ?>
                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="card-title"><?php echo htmlspecialchars($reward['name']); ?></h6>
                            <p class="card-text"><?php echo htmlspecialchars($reward['description']); ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-primary"><?php echo $reward['points_required']; ?> poin</span>
                                <?php if($customer['total_points'] >= $reward['points_required']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="reward_id" value="<?php echo $reward['id']; ?>">
                                    <button type="submit" name="redeem_reward" class="btn btn-sm btn-success">
                                        Tukar
                                    </button>
                                </form>
                                <?php else: ?>
                                <button class="btn btn-sm btn-secondary" disabled>
                                    Poin Kurang
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <p class="text-center text-muted">Belum ada hadiah yang tersedia</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Activity History -->
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Riwayat Transaksi</h5>
                </div>
                <div class="card-body">
                    <?php if($resultTransactions->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Jumlah</th>
                                    <th>Poin</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($transaction = $resultTransactions->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($transaction['transaction_date'])); ?></td>
                                    <td>Rp <?php echo number_format($transaction['amount'], 0, ',', '.'); ?></td>
                                    <td><span
                                            class="badge bg-success">+<?php echo $transaction['points_earned']; ?></span>
                                    </td>
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
                    <h5 class="mb-0"><i class="fas fa-gift me-2"></i>Riwayat Penukaran</h5>
                </div>
                <div class="card-body">
                    <?php if($resultRedemptions->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Hadiah</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($redemption = $resultRedemptions->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($redemption['redemption_date'])); ?></td>
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

<!-- ✅ SWITCH BUSINESS MODAL -->
<?php if($businessCount > 1): ?>
<div class="modal fade" id="switchBusinessModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Pilih UMKM</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Pilih UMKM yang ingin Anda akses:</p>

                <?php
                // Reset result pointer and query again for modal
                $stmtAllBusinesses = $conn->prepare($queryAllBusinesses);
                $stmtAllBusinesses->bind_param("s", $customerPhone);
                $stmtAllBusinesses->execute();
                $allBusinesses = $stmtAllBusinesses->get_result();
                
                while($biz = $allBusinesses->fetch_assoc()):
                    $isActive = ($biz['id'] == $businessId);
                ?>
                <div class="card mb-2 <?php echo $isActive ? 'border-primary' : ''; ?>">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0"><?php echo htmlspecialchars($biz['business_name']); ?></h6>
                                <small class="text-muted"><?php echo number_format($biz['total_points']); ?>
                                    poin</small>
                                <?php if($isActive): ?>
                                <br><small class="text-primary"><i class="fas fa-check-circle me-1"></i>Aktif
                                    sekarang</small>
                                <?php endif; ?>
                            </div>
                            <?php if(!$isActive): ?>
                            <form method="POST" action="switch_business.php" style="display: inline;">
                                <input type="hidden" name="switch_to_business_id" value="<?php echo $biz['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="fas fa-arrow-right me-1"></i>Pilih
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php 
                endwhile;
                $stmtAllBusinesses->close();
                ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.business-switcher .alert {
    margin-bottom: 0;
}

.points-display {
    text-align: center;
}
</style>

<?php
// Include footer
include '../includes/footer.php';
?>