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

// ✅ Check if business_id is set
if(!isset($_SESSION['business_id'])) {
    header('Location: ../select_business.php');
    exit;
}

$businessId = $_SESSION['business_id'];
$userId = $_SESSION['user_id'];

// ✅ Check if business subscription is active
if (!isBusinessSubscriptionActive($conn, $businessId)) {
    session_destroy();
    header('Location: ../login.php?error=subscription_expired');
    exit;
}

// Get user information (business-scoped)
$query = "SELECT * FROM users WHERE id = ? AND business_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $userId, $businessId);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();
$stmt->close();

// ✅ FIXED: Get rewards ONLY for THIS BUSINESS!
$queryRewards = "SELECT * FROM rewards WHERE status = 'active' AND business_id = ? ORDER BY points_required ASC";
$stmtRewards = $conn->prepare($queryRewards);
$stmtRewards->bind_param("i", $businessId);
$stmtRewards->execute();
$resultRewards = $stmtRewards->get_result();
$stmtRewards->close();

// Process reward redemption if requested
$redemptionMessage = '';
$redemptionError = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['redeem_reward'])) {
    $rewardId = sanitize($conn, $_POST['reward_id']);
    
    // ✅ FIXED: Get reward information (business-scoped)
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
                // ✅ FIXED: Insert redemption record with business_id
                $queryInsert = "
                    INSERT INTO redemptions (business_id, user_id, reward_id, points_used, redemption_code) 
                    VALUES (?, ?, ?, ?, ?)
                ";
                $stmtInsert = $conn->prepare($queryInsert);
                $stmtInsert->bind_param("iiiis", $businessId, $userId, $rewardId, $reward['points_required'], $redemptionCode);
                $stmtInsert->execute();
                $stmtInsert->close();
                
                // ✅ FIXED: Update user points with business_id
                $newPoints = $customer['total_points'] - $reward['points_required'];
                $queryUpdate = "UPDATE users SET total_points = ? WHERE id = ? AND business_id = ?";
                $stmtUpdate = $conn->prepare($queryUpdate);
                $stmtUpdate->bind_param("iii", $newPoints, $userId, $businessId);
                $stmtUpdate->execute();
                $stmtUpdate->close();
                
                // Commit transaction
                $conn->commit();
                
                // ✅ Log activity with business_id
                logActivity($conn, $businessId, $userId, 'REDEEM_REWARD', "Redeemed {$reward['name']} for {$reward['points_required']} points");
                
                // Update customer variable for display
                $customer['total_points'] = $newPoints;
                
                // Set success message
                $redemptionMessage = "Selamat! Anda berhasil menukarkan {$reward['points_required']} poin untuk {$reward['name']}. 
                                    Kode penukaran Anda: <span class='redemption-code'>{$redemptionCode}</span>. 
                                    Tunjukkan kode ini kepada admin untuk mendapatkan hadiah Anda.";
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $redemptionError = "Terjadi kesalahan saat menukarkan poin. Silakan coba lagi.";
            }
            
        } else {
            $redemptionError = "Poin Anda tidak mencukupi untuk menukarkan hadiah ini. Anda memerlukan {$reward['points_required']} poin.";
        }
        
    } else {
        $redemptionError = "Hadiah tidak ditemukan atau tidak tersedia untuk UMKM ini.";
    }
    
    $stmtReward->close();
}

include '../includes/header.php';
?>

<div class="container py-4">
    <h2>Tukar Poin dengan Hadiah</h2>
    <p>Poin Anda: <strong><?php echo number_format($customer['total_points']); ?> poin</strong></p>

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

    <div class="row">
        <?php if($resultRewards->num_rows > 0): ?>
            <?php while($reward = $resultRewards->fetch_assoc()): ?>
            <div class="col-md-4 mb-4">
                <div class="card reward-card">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($reward['name']); ?></h5>
                        <p class="card-text"><?php echo htmlspecialchars($reward['description']); ?></p>
                        <p class="points-required">
                            <i class="fas fa-coins"></i> 
                            <?php echo number_format($reward['points_required']); ?> poin
                        </p>
                        
                        <?php if($customer['total_points'] >= $reward['points_required']): ?>
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <input type="hidden" name="reward_id" value="<?php echo $reward['id']; ?>">
                            <button type="submit" name="redeem_reward" class="btn btn-primary btn-block">
                                <i class="fas fa-gift"></i> Tukar Sekarang
                            </button>
                        </form>
                        <?php else: ?>
                        <button class="btn btn-secondary btn-block" disabled>
                            <i class="fas fa-lock"></i> Poin Tidak Cukup
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    Belum ada hadiah yang tersedia untuk UMKM ini saat ini.
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.reward-card {
    height: 100%;
    border: 2px solid #e0e0e0;
    transition: all 0.3s;
}

.reward-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.points-required {
    font-size: 1.2rem;
    font-weight: bold;
    color: #28a745;
    margin: 15px 0;
}

.redemption-code {
    font-size: 1.5rem;
    font-weight: bold;
    color: #007bff;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 5px;
    display: inline-block;
}
</style>

<?php include '../includes/footer.php'; ?>
