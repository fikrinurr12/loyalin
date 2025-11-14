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

// Get statistics
// Total users by role
$queryUsers = "
    SELECT role, COUNT(*) as total 
    FROM users 
    GROUP BY role
";
$resultUsers = $conn->query($queryUsers);
$userStats = [];
while($row = $resultUsers->fetch_assoc()) {
    $userStats[$row['role']] = $row['total'];
}

// Total transactions
$queryTransactions = "SELECT COUNT(*) as total, SUM(amount) as total_amount FROM transactions";
$resultTransactions = $conn->query($queryTransactions);
$transactionStats = $resultTransactions->fetch_assoc();

// Total points
$queryPoints = "SELECT SUM(points_earned) as total_earned, SUM(points_used) as total_used 
                FROM (
                    SELECT 0 as points_used, points_earned 
                    FROM transactions
                    UNION ALL
                    SELECT points_used, 0 as points_earned 
                    FROM redemptions
                ) as points_data";
$resultPoints = $conn->query($queryPoints);
$pointsStats = $resultPoints->fetch_assoc();

// Total redemptions
$queryRedemptions = "SELECT COUNT(*) as total FROM redemptions";
$resultRedemptions = $conn->query($queryRedemptions);
$redemptionsTotal = $resultRedemptions->fetch_assoc()['total'];

// Recent activities (combined transactions and redemptions)
$queryActivities = "
    SELECT 'transaction' as type, t.transaction_date as date, u.name, u.phone, t.amount, t.points_earned, NULL as reward_name, NULL as redemption_code
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    UNION
    SELECT 'redemption' as type, r.redemption_date as date, u.name, u.phone, NULL as amount, NULL as points_earned, rw.name as reward_name, r.redemption_code
    FROM redemptions r
    JOIN users u ON r.user_id = u.id
    JOIN rewards rw ON r.reward_id = rw.id
    ORDER BY date DESC
    LIMIT 10
";
$resultActivities = $conn->query($queryActivities);

// Current settings
$querySettings = "SELECT * FROM settings ORDER BY setting_key";
$resultSettings = $conn->query($querySettings);
$settings = [];
while($row = $resultSettings->fetch_assoc()) {
    $settings[$row['setting_key']] = $row;
}

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Dashboard Superadmin</h2>
            <p>Selamat datang, <?php echo $_SESSION['name']; ?>!</p>
            <p>Ini adalah panel kontrol utama untuk mengelola seluruh sistem.</p>
        </div>
        <div class="col-md-4 text-md-end">
            <div class="d-inline-flex gap-2">
                <a href="settings.php" class="btn btn-primary">
                    <i class="fas fa-cog me-2"></i>Pengaturan Sistem
                </a>
                <a href="users.php" class="btn btn-outline-primary">
                    <i class="fas fa-users me-2"></i>Kelola Pengguna
                </a>
            </div>
        </div>

    </div>

    <!-- Stats Overview -->
    <div class="row mb-4">
        <div class="col-md-3 mb-4">
            <div class="stats-card h-100">
                <i class="fas fa-users"></i>
                <div class="stats-title">Total Pengguna</div>
                <div class="stats-value">
                    <?php echo array_sum($userStats); ?>
                </div>
                <div class="mt-2">
                    <?php if(isset($userStats['superadmin'])): ?>
                    <span class="badge role-badge superadmin">
                        Superadmin: <?php echo $userStats['superadmin']; ?>
                    </span>
                    <?php endif; ?>

                    <?php if(isset($userStats['admin'])): ?>
                    <span class="badge role-badge admin">
                        Admin: <?php echo $userStats['admin']; ?>
                    </span>
                    <?php endif; ?>

                    <?php if(isset($userStats['customer'])): ?>
                    <span class="badge role-badge customer">
                        Customer: <?php echo $userStats['customer']; ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="stats-card h-100">
                <i class="fas fa-shopping-cart"></i>
                <div class="stats-title">Total Transaksi</div>
                <div class="stats-value">
                    <?php echo $transactionStats['total']; ?>
                </div>
                <div class="mt-2">
                    Rp <?php echo number_format($transactionStats['total_amount'] ?? 0, 0, ',', '.'); ?>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="stats-card h-100">
                <i class="fas fa-coins"></i>
                <div class="stats-title">Total Poin</div>
                <div class="stats-value">
                    <?php echo $pointsStats['total_earned'] ?? 0; ?>
                </div>
                <div class="mt-2">
                    <span class="badge bg-primary">
                        Diperoleh: <?php echo $pointsStats['total_earned'] ?? 0; ?>
                    </span>
                    <span class="badge bg-warning">
                        Digunakan: <?php echo $pointsStats['total_used'] ?? 0; ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="stats-card h-100">
                <i class="fas fa-gift"></i>
                <div class="stats-title">Total Penukaran</div>
                <div class="stats-value">
                    <?php echo $redemptionsTotal; ?>
                </div>
                <div class="mt-2">
                    <a href="redemptions.php" class="btn btn-sm btn-outline-primary">Lihat Detail</a>
                </div>
            </div>
        </div>
    </div>

    <!-- System Settings -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Pengaturan Sistem</h5>
                    <a href="settings.php" class="btn btn-sm btn-primary">Edit Pengaturan</a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="fw-bold">Rasio Poin:</label>
                                <p>Rp
                                    <?php echo number_format($settings['points_ratio']['setting_value'] ?? 10000, 0, ',', '.'); ?>
                                    = 1 poin</p>
                            </div>
                            <div class="mb-3">
                                <label class="fw-bold">Nama Situs:</label>
                                <p><?php echo $settings['site_name']['setting_value'] ?? 'SuriCrypt Loyalty'; ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="fw-bold">Alamat Resto:</label>
                                <p><?php echo $settings['resto_address']['setting_value'] ?? '-'; ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="fw-bold">Kontak:</label>
                                <p>
                                    Telp: <?php echo $settings['resto_phone']['setting_value'] ?? '-'; ?><br>
                                    Email: <?php echo $settings['resto_email']['setting_value'] ?? '-'; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activities -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Aktivitas Terbaru</h5>
                    <div>
                        <a href="transactions.php" class="btn btn-sm btn-outline-primary me-2">Semua Transaksi</a>
                        <a href="redemptions.php" class="btn btn-sm btn-outline-primary">Semua Penukaran</a>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Pelanggan</th>
                                <th>Nomor HP</th>
                                <th>Tipe</th>
                                <th>Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($resultActivities->num_rows > 0): ?>
                            <?php while($activity = $resultActivities->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('d M Y H:i', strtotime($activity['date'])); ?></td>
                                <td><?php echo $activity['name']; ?></td>
                                <td><?php echo $activity['phone']; ?></td>
                                <td>
                                    <?php if($activity['type'] == 'transaction'): ?>
                                    <span class="badge bg-primary">Transaksi</span>
                                    <?php else: ?>
                                    <span class="badge bg-success">Penukaran</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($activity['type'] == 'transaction'): ?>
                                    Rp <?php echo number_format($activity['amount'], 0, ',', '.'); ?>
                                    (<?php echo $activity['points_earned']; ?> poin)
                                    <?php else: ?>
                                    <?php echo $activity['reward_name']; ?>
                                    <span
                                        class="ms-2 redemption-code"><?php echo $activity['redemption_code']; ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">Belum ada aktivitas</td>
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