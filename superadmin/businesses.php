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

// Handle business deletion
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $businessId = (int)$_GET['delete'];
    
    // Don't allow deletion if business has users
    $checkQuery = "SELECT COUNT(*) as count FROM users WHERE business_id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("i", $businessId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $count = $checkResult->fetch_assoc()['count'];
    
    if ($count > 0) {
        $errorMsg = "Tidak dapat menghapus UMKM karena masih memiliki {$count} user terdaftar.";
    } else {
        $deleteQuery = "DELETE FROM businesses WHERE id = ?";
        $deleteStmt = $conn->prepare($deleteQuery);
        $deleteStmt->bind_param("i", $businessId);
        
        if ($deleteStmt->execute()) {
            $successMsg = "UMKM berhasil dihapus.";
            logActivity($conn, null, $_SESSION['user_id'], 'DELETE_BUSINESS', "Deleted business ID: {$businessId}");
        } else {
            $errorMsg = "Gagal menghapus UMKM.";
        }
        $deleteStmt->close();
    }
    $checkStmt->close();
}

// Handle status toggle
if (isset($_GET['toggle_status']) && !empty($_GET['toggle_status'])) {
    $businessId = (int)$_GET['toggle_status'];
    
    $toggleQuery = "UPDATE businesses SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?";
    $toggleStmt = $conn->prepare($toggleQuery);
    $toggleStmt->bind_param("i", $businessId);
    
    if ($toggleStmt->execute()) {
        $successMsg = "Status UMKM berhasil diubah.";
        logActivity($conn, null, $_SESSION['user_id'], 'TOGGLE_BUSINESS_STATUS', "Toggled business ID: {$businessId}");
    } else {
        $errorMsg = "Gagal mengubah status UMKM.";
    }
    $toggleStmt->close();
}

// Get all businesses with statistics
$query = "
    SELECT 
        b.*,
        COUNT(DISTINCT u.id) as total_users,
        COUNT(DISTINCT CASE WHEN u.role = 'admin' THEN u.id END) as total_admins,
        COUNT(DISTINCT CASE WHEN u.role = 'customer' THEN u.id END) as total_customers,
        COALESCE(SUM(t.amount), 0) as total_revenue,
        COALESCE(SUM(t.points_earned), 0) as total_points_issued,
        DATEDIFF(b.subscription_end_date, CURDATE()) as days_left
    FROM businesses b
    LEFT JOIN users u ON b.id = u.business_id
    LEFT JOIN transactions t ON b.id = t.business_id
    GROUP BY b.id
    ORDER BY b.created_at DESC
";
$result = $conn->query($query);

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-store me-2"></i>Kelola UMKM</h2>
            <p>Manajemen semua UMKM yang terdaftar di platform</p>
        </div>
        <div class="col-md-4 text-md-end">
            <a href="business_add.php" class="btn btn-primary">
                <i class="fas fa-plus-circle me-2"></i>Tambah UMKM Baru
            </a>
        </div>
    </div>

    <?php if(isset($successMsg)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo $successMsg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if(isset($errorMsg)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $errorMsg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-store fa-3x text-primary mb-2"></i>
                    <h3><?php echo $result->num_rows; ?></h3>
                    <p class="text-muted mb-0">Total UMKM</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-check-circle fa-3x text-success mb-2"></i>
                    <h3>
                        <?php 
                        $activeQuery = "SELECT COUNT(*) as count FROM businesses WHERE status = 'active' AND subscription_status = 'active'";
                        $activeResult = $conn->query($activeQuery);
                        echo $activeResult->fetch_assoc()['count'];
                        ?>
                    </h3>
                    <p class="text-muted mb-0">UMKM Aktif</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-users fa-3x text-info mb-2"></i>
                    <h3>
                        <?php 
                        $usersQuery = "SELECT COUNT(*) as count FROM users WHERE role = 'customer'";
                        $usersResult = $conn->query($usersQuery);
                        echo $usersResult->fetch_assoc()['count'];
                        ?>
                    </h3>
                    <p class="text-muted mb-0">Total Pelanggan</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-exclamation-triangle fa-3x text-warning mb-2"></i>
                    <h3>
                        <?php 
                        $expiredQuery = "SELECT COUNT(*) as count FROM businesses WHERE subscription_end_date < CURDATE()";
                        $expiredResult = $conn->query($expiredQuery);
                        echo $expiredResult->fetch_assoc()['count'];
                        ?>
                    </h3>
                    <p class="text-muted mb-0">Subscription Kadaluarsa</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Businesses Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Daftar UMKM</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>UMKM</th>
                        <th>Pemilik</th>
                        <th>Kontak</th>
                        <th>Users</th>
                        <th>Revenue</th>
                        <th>Subscription</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result->num_rows > 0): ?>
                    <?php while($business = $result->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <?php if($business['logo_path'] && file_exists('../' . $business['logo_path'])): ?>
                                    <img src="../<?php echo $business['logo_path']; ?>" alt="Logo" 
                                        style="width: 40px; height: 40px; object-fit: cover; border-radius: 50%; margin-right: 10px;">
                                <?php else: ?>
                                    <div style="width: 40px; height: 40px; border-radius: 50%; margin-right: 10px;
                                        background: linear-gradient(135deg, <?php echo $business['primary_color']; ?>, <?php echo $business['accent_color']; ?>);
                                        display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-store text-white"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <strong><?php echo htmlspecialchars($business['business_name']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo $business['business_slug']; ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($business['owner_name'] ?: '-'); ?>
                        </td>
                        <td>
                            <small>
                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($business['phone'] ?: '-'); ?><br>
                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($business['email'] ?: '-'); ?>
                            </small>
                        </td>
                        <td>
                            <span class="badge bg-primary"><?php echo $business['total_admins']; ?> Admin</span>
                            <span class="badge bg-info"><?php echo $business['total_customers']; ?> Customer</span>
                        </td>
                        <td>
                            Rp <?php echo number_format($business['total_revenue'], 0, ',', '.'); ?>
                            <br>
                            <small class="text-muted"><?php echo number_format($business['total_points_issued']); ?> poin</small>
                        </td>
                        <td>
                            <?php 
                            $daysLeft = $business['days_left'];
                            if ($daysLeft < 0) {
                                echo '<span class="badge bg-danger">Kadaluarsa</span>';
                            } elseif ($daysLeft <= 7) {
                                echo '<span class="badge bg-warning text-dark">' . $daysLeft . ' hari lagi</span>';
                            } else {
                                echo '<span class="badge bg-success">' . $daysLeft . ' hari lagi</span>';
                            }
                            ?>
                            <br>
                            <small class="text-muted"><?php echo formatDate($business['subscription_end_date'], 'd M Y'); ?></small>
                        </td>
                        <td>
                            <?php if($business['status'] == 'active' && $business['subscription_status'] == 'active'): ?>
                                <span class="badge bg-success">Aktif</span>
                            <?php elseif($business['subscription_status'] == 'suspended'): ?>
                                <span class="badge bg-warning">Ditangguhkan</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="business_edit.php?id=<?php echo $business['id']; ?>" 
                                    class="btn btn-outline-primary" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="subscriptions.php?business_id=<?php echo $business['id']; ?>" 
                                    class="btn btn-outline-info" title="Subscription">
                                    <i class="fas fa-calendar-alt"></i>
                                </a>
                                <a href="?toggle_status=<?php echo $business['id']; ?>" 
                                    class="btn btn-outline-warning" 
                                    onclick="return confirm('Yakin ingin mengubah status UMKM ini?')" title="Toggle Status">
                                    <i class="fas fa-power-off"></i>
                                </a>
                                <a href="?delete=<?php echo $business['id']; ?>" 
                                    class="btn btn-outline-danger" 
                                    onclick="return confirm('Yakin ingin menghapus UMKM ini? Data tidak dapat dikembalikan!')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center">Belum ada UMKM terdaftar</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>
