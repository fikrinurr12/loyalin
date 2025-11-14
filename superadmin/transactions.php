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

// Process transaction deletion if submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_transaction'])) {
    $transactionId = (int)sanitize($conn, $_POST['transaction_id']);
    
    // Get transaction info for points adjustment
    $queryInfo = "SELECT user_id, points_earned FROM transactions WHERE id = ?";
    $stmtInfo = $conn->prepare($queryInfo);
    $stmtInfo->bind_param("i", $transactionId);
    $stmtInfo->execute();
    $resultInfo = $stmtInfo->get_result();
    $transactionInfo = $resultInfo->fetch_assoc();
    $stmtInfo->close();
    
    if ($transactionInfo) {
        $userId = $transactionInfo['user_id'];
        $pointsEarned = $transactionInfo['points_earned'];
        
        $conn->begin_transaction();
        
        try {
            // Delete transaction
            $queryDelete = "DELETE FROM transactions WHERE id = ?";
            $stmtDelete = $conn->prepare($queryDelete);
            $stmtDelete->bind_param("i", $transactionId);
            $stmtDelete->execute();
            $stmtDelete->close();
            
            // Update user's points (subtract the points earned from this transaction)
            $queryUpdatePoints = "UPDATE users SET total_points = total_points - ? WHERE id = ?";
            $stmtUpdatePoints = $conn->prepare($queryUpdatePoints);
            $stmtUpdatePoints->bind_param("ii", $pointsEarned, $userId);
            $stmtUpdatePoints->execute();
            $stmtUpdatePoints->close();
            
            $conn->commit();
            $successMsg = 'Transaksi berhasil dihapus dan poin pelanggan telah disesuaikan.';
        } catch (Exception $e) {
            $conn->rollback();
            $errorMsg = 'Terjadi kesalahan saat menghapus transaksi: ' . $e->getMessage();
        }
    } else {
        $errorMsg = 'Data transaksi tidak ditemukan.';
    }
}

// Get date filter parameters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$customerFilter = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;

// Base query
$baseQuery = "
    SELECT t.*, u.name as customer_name, u.phone as customer_phone 
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE t.transaction_date BETWEEN ? AND ?
";

// Add customer filter if specified
if ($customerFilter > 0) {
    $baseQuery .= " AND t.user_id = ?";
}

// Complete the query
$baseQuery .= " ORDER BY t.transaction_date DESC";

// Prepare and execute query
$stmt = $conn->prepare($baseQuery);

if ($customerFilter > 0) {
    $endDateWithTime = $endDate . ' 23:59:59';
    $stmt->bind_param("ssi", $startDate, $endDateWithTime, $customerFilter);
} else {
    $endDateWithTime = $endDate . ' 23:59:59';
    $stmt->bind_param("ss", $startDate, $endDateWithTime);
}

$stmt->execute();
$result = $stmt->get_result();
$transactions = [];

while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}

$stmt->close();

// Get transaction statistics
$queryStats = "
    SELECT 
        COUNT(*) as total_transactions,
        SUM(amount) as total_amount,
        SUM(points_earned) as total_points,
        AVG(amount) as average_amount,
        MAX(amount) as highest_amount
    FROM transactions
";
$resultStats = $conn->query($queryStats);
$stats = $resultStats->fetch_assoc();

// Get all customers for filter dropdown
$queryCustomers = "SELECT id, name, phone FROM users WHERE role = 'customer' ORDER BY name";
$resultCustomers = $conn->query($queryCustomers);
$customers = [];

while ($row = $resultCustomers->fetch_assoc()) {
    $customers[] = $row;
}

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Manajemen Transaksi</h2>
            <p>Lihat dan kelola semua transaksi pelanggan dalam sistem.</p>
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
                <i class="fas fa-receipt"></i>
                <div class="stats-title">Total Transaksi</div>
                <div class="stats-value"><?php echo $stats['total_transactions'] ?: 0; ?></div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <i class="fas fa-money-bill-wave"></i>
                <div class="stats-title">Total Nominal</div>
                <div class="stats-value">Rp <?php echo number_format($stats['total_amount'] ?: 0, 0, ',', '.'); ?></div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <i class="fas fa-coins"></i>
                <div class="stats-title">Total Poin</div>
                <div class="stats-value"><?php echo $stats['total_points'] ?: 0; ?></div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <i class="fas fa-chart-line"></i>
                <div class="stats-title">Rata-rata Transaksi</div>
                <div class="stats-value">Rp <?php echo number_format($stats['average_amount'] ?: 0, 0, ',', '.'); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Filter Transaksi</h5>
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
                    <label for="customer_id" class="form-label">Pelanggan</label>
                    <select class="form-select" id="customer_id" name="customer_id">
                        <option value="0">Semua Pelanggan</option>
                        <?php foreach($customers as $customer): ?>
                        <option value="<?php echo $customer['id']; ?>"
                            <?php echo ($customerFilter == $customer['id']) ? 'selected' : ''; ?>>
                            <?php echo $customer['name'] . ' (' . $customer['phone'] . ')'; ?>
                        </option>
                        <?php endforeach; ?>
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

    <!-- Transactions Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Daftar Transaksi</h5>
            <div class="input-group" style="max-width: 300px;">
                <input type="text" id="transactionSearch" class="form-control" placeholder="Cari transaksi...">
                <button class="btn btn-outline-secondary" type="button">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover" id="transactionsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tanggal</th>
                        <th>Pelanggan</th>
                        <th>Nomor HP</th>
                        <th>Jumlah (Rp)</th>
                        <th>Poin Didapat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($transactions) > 0): ?>
                    <?php foreach($transactions as $transaction): ?>
                    <tr>
                        <td><?php echo $transaction['id']; ?></td>
                        <td><?php echo date('d M Y H:i', strtotime($transaction['transaction_date'])); ?></td>
                        <td><?php echo $transaction['customer_name']; ?></td>
                        <td><?php echo $transaction['customer_phone']; ?></td>
                        <td><?php echo number_format($transaction['amount'], 0, ',', '.'); ?></td>
                        <td><?php echo $transaction['points_earned']; ?> poin</td>
                        <td>
                            <button class="btn btn-sm btn-danger delete-transaction"
                                data-id="<?php echo $transaction['id']; ?>"
                                data-amount="<?php echo number_format($transaction['amount'], 0, ',', '.'); ?>"
                                data-name="<?php echo $transaction['customer_name']; ?>">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center">Tidak ada data transaksi dalam rentang waktu yang dipilih.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Delete Transaction Modal -->
<div class="modal fade" id="deleteTransactionModal" tabindex="-1" aria-labelledby="deleteTransactionModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteTransactionModalLabel">Hapus Transaksi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="modal-body">
                    <input type="hidden" id="delete_transaction_id" name="transaction_id">
                    <p>Apakah Anda yakin ingin menghapus transaksi <strong>Rp <span
                                id="delete_transaction_amount"></span></strong> milik <strong><span
                                id="delete_transaction_name"></span></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Perhatian!</strong> Poin yang didapatkan pelanggan dari transaksi ini juga akan
                        dikurangi.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger" name="delete_transaction">Hapus</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Search functionality
document.getElementById('transactionSearch').addEventListener('keyup', function() {
    const searchText = this.value.toLowerCase();
    const table = document.getElementById('transactionsTable');
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

// Delete transaction modal
document.querySelectorAll('.delete-transaction').forEach(button => {
    button.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const amount = this.getAttribute('data-amount');
        const name = this.getAttribute('data-name');

        document.getElementById('delete_transaction_id').value = id;
        document.getElementById('delete_transaction_amount').textContent = amount;
        document.getElementById('delete_transaction_name').textContent = name;

        const deleteModal = new bootstrap.Modal(document.getElementById('deleteTransactionModal'));
        deleteModal.show();
    });
});
</script>

<?php
// Include footer
include '../includes/footer.php';
?>