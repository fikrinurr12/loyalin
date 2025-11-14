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

// Get user information
$userId = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();
$stmt->close();

// Get all available rewards
$queryRewards = "SELECT * FROM rewards WHERE status = 'active' ORDER BY points_required ASC";
$resultRewards = $conn->query($queryRewards);

// Process reward redemption if requested
$redemptionMessage = '';
$redemptionError = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['redeem_reward'])) {
    $rewardId = sanitize($conn, $_POST['reward_id']);
    
    // Get reward information
    $queryReward = "SELECT * FROM rewards WHERE id = ?";
    $stmtReward = $conn->prepare($queryReward);
    $stmtReward->bind_param("i", $rewardId);
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
                    INSERT INTO redemptions (user_id, reward_id, points_used, redemption_code) 
                    VALUES (?, ?, ?, ?)
                ";
                $stmtInsert = $conn->prepare($queryInsert);
                $stmtInsert->bind_param("iiis", $userId, $rewardId, $reward['points_required'], $redemptionCode);
                $stmtInsert->execute();
                $stmtInsert->close();
                
                // Update user points
                $newPoints = $customer['total_points'] - $reward['points_required'];
                $queryUpdate = "UPDATE users SET total_points = ? WHERE id = ?";
                $stmtUpdate = $conn->prepare($queryUpdate);
                $stmtUpdate->bind_param("ii", $newPoints, $userId);
                $stmtUpdate->execute();
                $stmtUpdate->close();
                
                // Commit transaction
                $conn->commit();
                
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
            $redemptionError = "Poin Anda tidak cukup untuk menukarkan hadiah ini.";
        }
        
        $stmtReward->close();
    } else {
        $redemptionError = "Hadiah tidak ditemukan.";
    }
}

// Get recent redemptions
$queryRecentRedemptions = "
    SELECT r.*, rw.name as reward_name, rw.points_required
    FROM redemptions r
    JOIN rewards rw ON r.reward_id = rw.id
    WHERE r.user_id = ? AND r.status = 'pending'
    ORDER BY r.redemption_date DESC
    LIMIT 5
";
$stmtRecentRedemptions = $conn->prepare($queryRecentRedemptions);
$stmtRecentRedemptions->bind_param("i", $userId);
$stmtRecentRedemptions->execute();
$resultRecentRedemptions = $stmtRecentRedemptions->get_result();
$stmtRecentRedemptions->close();

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2>Tukar Poin dengan Hadiah</h2>
            <p>Tukarkan poin yang telah Anda kumpulkan dengan berbagai hadiah menarik.</p>
        </div>
    </div>

    <!-- Points Card -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="points-card">
                <h4>Total Poin Anda</h4>
                <div class="points-value"><?php echo $customer['total_points']; ?></div>
                <p>Kumpulkan poin dengan melakukan transaksi di rumah makan kami.</p>
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

    <!-- Pending Redemptions -->
    <?php if($resultRecentRedemptions->num_rows > 0): ?>
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>Penukaran yang Belum Diproses</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Hadiah</th>
                                    <th>Poin</th>
                                    <th>Kode Penukaran</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($redemption = $resultRecentRedemptions->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d M Y H:i', strtotime($redemption['redemption_date'])); ?></td>
                                    <td><?php echo $redemption['reward_name']; ?></td>
                                    <td><?php echo $redemption['points_used']; ?> poin</td>
                                    <td><span
                                            class="redemption-code"><?php echo $redemption['redemption_code']; ?></span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        Tunjukkan kode penukaran kepada admin untuk mendapatkan hadiah Anda.
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Available Rewards -->
    <div class="row">
        <div class="col-md-12 mb-4">
            <h4>Daftar Hadiah</h4>
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
</div>

<?php
// Include footer
include '../includes/footer.php';
?>