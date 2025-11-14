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

// Initialize messages
$successMsg = '';
$errorMsg = '';

// Process redemption status update if submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $redemptionId = (int)sanitize($conn, $_POST['redemption_id']);
    $newStatus = sanitize($conn, $_POST['status']);
    
    // Update redemption status
    $query = "UPDATE redemptions SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $newStatus, $redemptionId);
    
    if ($stmt->execute()) {
        $successMsg = 'Status penukaran berhasil diperbarui.';
    } else {
        $errorMsg = 'Terjadi kesalahan saat memperbarui status penukaran.';
    }
    
    $stmt->close();
}

// Process redemption deletion if submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_redemption'])) {
    $redemptionId = (int)sanitize($conn, $_POST['redemption_id']);
    
    // Get redemption info for points return
    $queryInfo = "SELECT user_id, points_used FROM redemptions WHERE id = ?";
    $stmtInfo = $conn->prepare($queryInfo);
    $stmtInfo->bind_param("i", $redemptionId);
    $stmtInfo->execute();
    $resultInfo = $stmtInfo->get_result();
    $redemptionInfo = $resultInfo->fetch_assoc();
    $stmtInfo->close();
    
    if ($redemptionInfo) {
        $userId = $redemptionInfo['user_id'];
        $pointsUsed = $redemptionInfo['points_used'];
        
        $conn->begin_transaction();
        
        try {
            // Delete redemption
            $queryDelete = "DELETE FROM redemptions WHERE id = ?";
            $stmtDelete = $conn->prepare($queryDelete);
            $stmtDelete->bind_param("i", $redemptionId);
            $stmtDelete->execute();
            $stmtDelete->close();
            
            // Return points to user
            $queryUpdatePoints = "UPDATE users SET total_points = total_points + ? WHERE id = ?";
            $stmtUpdatePoints = $conn->prepare($queryUpdatePoints);
            $stmtUpdatePoints->bind_param("ii", $pointsUsed, $userId);
            $stmtUpdatePoints->execute();
            $stmtUpdatePoints->close();
            
            $conn->commit();
            $successMsg = 'Penukaran berhasil dihapus dan poin dikembalikan ke pelanggan.';
        } catch (Exception $e) {
            $conn->rollback();
            $errorMsg = 'Terjadi kesalahan saat menghapus penukaran: ' . $e->getMessage();
        }
    } else {
        $errorMsg = 'Data penukaran tidak ditemukan.';
    }
}

// Get date filter parameters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Base query
$baseQuery = "
    SELECT r.*, u.name as customer_name, u.phone as customer_phone, rw.name as reward_name
    FROM redemptions r
    JOIN users u ON r.user_id = u.id
    JOIN rewards rw ON r.reward_id = rw.id
    WHERE r.redemption_date BETWEEN ? AND ?
";

// Add status filter if not 'all'
if ($statusFilter != 'all') {
    $baseQuery .= " AND r.status = ?";
}

// Complete the query
$baseQuery .= " ORDER BY r.redemption_date DESC";

// Prepare and execute query
$stmt = $conn->prepare($baseQuery);

if ($statusFilter != 'all') {
    $endDateWithTime = $endDate . ' 23:59:59';
    $stmt->bind_param("sss", $startDate, $endDateWithTime, $statusFilter);
} else {
    $endDateWithTime = $endDate . ' 23:59:59';
    $stmt->bind_param("ss", $startDate, $endDateWithTime);
}

$stmt->execute();
$result = $stmt->get_result();
$redemptions = [];

while ($row = $result->fetch_assoc()) {
    $redemptions[] = $row;
}

$stmt->close();

// Get redemption statistics
$queryStats = "
    SELECT 
        COUNT(*) as total_redemptions,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = 'redeemed' THEN 1 ELSE 0 END) as redeemed_count,
        SUM(points_used) as total_points_used
    FROM redemptions
";
$resultStats = $conn->query($queryStats);
$stats = $resultStats->fetch_assoc();

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Manajemen Penukaran</h2>
            <p>Lihat dan kelola semua penukaran hadiah dari pelanggan.</p>
        </div>
        <div class="col-md-4 text-md-end">
            <a href="dashboard.php" class="btn btn-primary">
                <i class="fas fa-arrow-left me-2"></i>Kembali ke Dashboard
            </a>
        </div>
    </div>

    <!-- Messages -->
    <?php if($successMsg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo $successMsg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if($errorMsg): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $errorMsg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <i class="fas fa-gift"></i>
                <div class="stats-title">Total Penukaran</div>
                <div class="stats-value"><?php echo $stats['total_redemptions'] ?: 0; ?></div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <i class="fas fa-hourglass-half"></i>
                <div class="stats-title">Menunggu</div>
                <div class="stats-value"><?php echo $stats['pending_count'] ?: 0; ?></div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <i class="fas fa-check-circle"></i>
                <div class="stats-title">Selesai</div>
                <div class="stats-value"><?php echo $stats['redeemed_count'] ?: 0; ?></div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <i class="fas fa-coins"></i>
                <div class="stats-title">Total Poin Digunakan</div>
                <div class="stats-value"><?php echo $stats['total_points_used'] ?: 0; ?></div>
            </div>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Filter Penukaran</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row g-3">
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Tanggal Mulai</label>
                    <input type="date" class="form-control" id="start_date" name="start_date"
                        value="<?php echo $startDate; ?>">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">Tanggal Akhir</label>
                    <input type="date" class="form-control" id="end_date" name="end_date"
                        value="<?php echo $endDate; ?>">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="all" <?php echo $statusFilter == 'all' ? 'selected' : ''; ?>>Semua Status
                        </option>
                        <option value="pending" <?php echo $statusFilter == 'pending' ? 'selected' : ''; ?>>Menunggu
                        </option>
                        <option value="redeemed" <?php echo $statusFilter == 'redeemed' ? 'selected' : ''; ?>>Selesai
                        </option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i>Terapkan Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Redemptions Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Daftar Penukaran</h5>
            <div class="input-group" style="max-width: 300px;">
                <input type="text" id="redemptionSearch" class="form-control" placeholder="Cari penukaran...">
                <button class="btn btn-outline-secondary" type="button">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover" id="redemptionsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tanggal</th>
                        <th>Pelanggan</th>
                        <th>Nomor HP</th>
                        <th>Hadiah</th>
                        <th>Poin</th>
                        <th>Kode</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($redemptions) > 0): ?>
                    <?php foreach($redemptions as $redemption): ?>
                    <tr>
                        <td><?php echo $redemption['id']; ?></td>
                        <td><?php echo date('d M Y H:i', strtotime($redemption['redemption_date'])); ?></td>
                        <td><?php echo $redemption['customer_name']; ?></td>
                        <td><?php echo $redemption['customer_phone']; ?></td>
                        <td><?php echo $redemption['reward_name']; ?></td>
                        <td><?php echo $redemption['points_used']; ?> poin</td>
                        <td><span class="redemption-code"><?php echo $redemption['redemption_code']; ?></span></td>
                        <td>
                            <?php if($redemption['status'] == 'pending'): ?>
                            <span class="badge bg-warning">Menunggu</span>
                            <?php else: ?>
                            <span class="badge bg-success">Selesai</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary update-redemption"
                                data-id="<?php echo $redemption['id']; ?>"
                                data-status="<?php echo $redemption['status']; ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger delete-redemption"
                                data-id="<?php echo $redemption['id']; ?>"
                                data-code="<?php echo $redemption['redemption_code']; ?>">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center">Tidak ada data penukaran dalam rentang waktu yang dipilih.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Update Redemption Status Modal -->
<div class="modal fade" id="updateRedemptionModal" tabindex="-1" aria-labelledby="updateRedemptionModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateRedemptionModalLabel">Update Status Penukaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="modal-body">
                    <input type="hidden" id="update_redemption_id" name="redemption_id">
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="update_status" name="status">
                            <option value="pending">Menunggu</option>
                            <option value="redeemed">Selesai</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" name="update_status">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Redemption Modal -->
<div class="modal fade" id="deleteRedemptionModal" tabindex="-1" aria-labelledby="deleteRedemptionModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteRedemptionModalLabel">Hapus Penukaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="modal-body">
                    <input type="hidden" id="delete_redemption_id" name="redemption_id">
                    <p>Apakah Anda yakin ingin menghapus penukaran dengan kode <strong><span
                                id="delete_redemption_code"></span></strong>?</p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Poin yang digunakan akan dikembalikan ke akun pelanggan.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger" name="delete_redemption">Hapus</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Search functionality
document.getElementById('redemptionSearch').addEventListener('keyup', function() {
    const searchText = this.value.toLowerCase();
    const table = document.getElementById('redemptionsTable');
    const rows = table.getElementsByTagName('tr');

    for (let i = 1; i < rows.length; i++) {
        const rowText = rows[i].textContent.toLowerCase();
        if (rowText.includes(searchText)) {
            rows[i].style.display = '';
        } else {
            rows[i].style.display = 'none';
        }
    }
});

// Update redemption modal
document.querySelectorAll('.update-redemption').forEach(button => {
    button.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const status = this.getAttribute('data-status');

        document.getElementById('update_redemption_id').value = id;
        document.getElementById('update_status').value = status;

        const updateModal = new bootstrap.Modal(document.getElementById('updateRedemptionModal'));
        updateModal.show();
    });
});

// Delete redemption modal
document.querySelectorAll('.delete-redemption').forEach(button => {
    button.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const code = this.getAttribute('data-code');

        document.getElementById('delete_redemption_id').value = id;
        document.getElementById('delete_redemption_code').textContent = code;

        const deleteModal = new bootstrap.Modal(document.getElementById('deleteRedemptionModal'));
        deleteModal.show();
    });
});
</script>

<?php
// Include footer
include '../includes/footer.php';
?>