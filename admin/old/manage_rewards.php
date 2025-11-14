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

// ✅ CRITICAL: Get business_id from session
$businessId = $_SESSION['business_id'];
$businessName = $_SESSION['business_name'];

// Initialize messages
$successMsg = '';
$errorMsg = '';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new reward
    if (isset($_POST['add_reward'])) {
        $name = sanitize($conn, $_POST['name']);
        $points = (int)sanitize($conn, $_POST['points_required']);
        $description = sanitize($conn, $_POST['description']);
        $status = sanitize($conn, $_POST['status']);
        
        if (empty($name)) {
            $errorMsg = 'Nama hadiah tidak boleh kosong.';
        } elseif ($points <= 0) {
            $errorMsg = 'Poin yang dibutuhkan harus lebih dari 0.';
        } else {
            // ✅ FIX: Add business_id to INSERT
            $query = "INSERT INTO rewards (business_id, name, points_required, description, status) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("isiss", $businessId, $name, $points, $description, $status);
            
            if ($stmt->execute()) {
                logActivity($conn, $businessId, $_SESSION['user_id'], 'CREATE_REWARD', "Created reward: {$name}");
                $successMsg = 'Hadiah baru berhasil ditambahkan untuk ' . htmlspecialchars($businessName);
            } else {
                $errorMsg = 'Terjadi kesalahan saat menambahkan hadiah.';
            }
            
            $stmt->close();
        }
    }
    
    // Update existing reward
    if (isset($_POST['update_reward'])) {
        $id = (int)sanitize($conn, $_POST['reward_id']);
        $name = sanitize($conn, $_POST['name']);
        $points = (int)sanitize($conn, $_POST['points_required']);
        $description = sanitize($conn, $_POST['description']);
        $status = sanitize($conn, $_POST['status']);
        
        if (empty($name)) {
            $errorMsg = 'Nama hadiah tidak boleh kosong.';
        } elseif ($points <= 0) {
            $errorMsg = 'Poin yang dibutuhkan harus lebih dari 0.';
        } else {
            // ✅ FIX: Add business_id filter to UPDATE
            $query = "UPDATE rewards SET name = ?, points_required = ?, description = ?, status = ? WHERE id = ? AND business_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sissii", $name, $points, $description, $status, $id, $businessId);
            
            if ($stmt->execute()) {
                logActivity($conn, $businessId, $_SESSION['user_id'], 'UPDATE_REWARD', "Updated reward ID: {$id}");
                $successMsg = 'Hadiah berhasil diperbarui.';
            } else {
                $errorMsg = 'Terjadi kesalahan saat memperbarui hadiah.';
            }
            
            $stmt->close();
        }
    }
    
    // Delete reward
    if (isset($_POST['delete_reward'])) {
        $id = (int)sanitize($conn, $_POST['reward_id']);
        
        // Check if reward has been redeemed
        $checkQuery = "SELECT COUNT(*) as count FROM redemptions WHERE reward_id = ? AND business_id = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("ii", $id, $businessId);
        $checkStmt->execute();
        $redemptionCount = $checkStmt->get_result()->fetch_assoc()['count'];
        $checkStmt->close();
        
        if ($redemptionCount > 0) {
            $errorMsg = 'Hadiah ini tidak dapat dihapus karena sudah pernah ditukar.';
        } else {
            // ✅ FIX: Add business_id filter to DELETE
            $query = "DELETE FROM rewards WHERE id = ? AND business_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $id, $businessId);
            
            if ($stmt->execute()) {
                logActivity($conn, $businessId, $_SESSION['user_id'], 'DELETE_REWARD', "Deleted reward ID: {$id}");
                $successMsg = 'Hadiah berhasil dihapus.';
            } else {
                $errorMsg = 'Terjadi kesalahan saat menghapus hadiah.';
            }
            
            $stmt->close();
        }
    }
}

// ✅ FIX: Get rewards for THIS BUSINESS ONLY
$query = "SELECT * FROM rewards WHERE business_id = ? ORDER BY points_required ASC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $businessId);
$stmt->execute();
$result = $stmt->get_result();

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <!-- Business Info Banner -->
    <div class="alert alert-info mb-4" style="background: linear-gradient(135deg, #154c79, #1565a0); border: none; color: white;">
        <div class="d-flex align-items-center">
            <i class="fas fa-gift fa-2x me-3"></i>
            <div>
                <h5 class="mb-1">Kelola Hadiah - <?php echo htmlspecialchars($businessName); ?></h5>
                <small>Hadiah yang Anda kelola hanya untuk UMKM ini</small>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Kelola Hadiah</h2>
            <p>Atur hadiah yang tersedia untuk pelanggan <?php echo htmlspecialchars($businessName); ?></p>
        </div>
        <div class="col-md-4 text-md-end">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRewardModal">
                <i class="fas fa-plus-circle me-2"></i>Tambah Hadiah Baru
            </button>
        </div>
    </div>

    <!-- Messages -->
    <?php if($successMsg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo $successMsg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if($errorMsg): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $errorMsg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Rewards Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Daftar Hadiah</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama Hadiah</th>
                        <th>Poin</th>
                        <th>Deskripsi</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result->num_rows > 0): ?>
                    <?php while($reward = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $reward['id']; ?></td>
                        <td><?php echo htmlspecialchars($reward['name']); ?></td>
                        <td><span class="badge bg-primary"><?php echo $reward['points_required']; ?> poin</span></td>
                        <td><?php echo htmlspecialchars($reward['description']); ?></td>
                        <td>
                            <?php if($reward['status'] == 'active'): ?>
                            <span class="badge bg-success">Aktif</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Tidak Aktif</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-warning edit-reward"
                                data-id="<?php echo $reward['id']; ?>"
                                data-name="<?php echo htmlspecialchars($reward['name']); ?>"
                                data-points="<?php echo $reward['points_required']; ?>"
                                data-description="<?php echo htmlspecialchars($reward['description']); ?>"
                                data-status="<?php echo $reward['status']; ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger delete-reward"
                                data-id="<?php echo $reward['id']; ?>"
                                data-name="<?php echo htmlspecialchars($reward['name']); ?>">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">Belum ada hadiah untuk <?php echo htmlspecialchars($businessName); ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Reward Modal -->
<div class="modal fade" id="addRewardModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Hadiah Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nama Hadiah</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                        <small class="text-muted">Contoh: "Gratis 1 Mangkok Bubur" atau "Diskon 50%"</small>
                    </div>
                    <div class="mb-3">
                        <label for="points_required" class="form-label">Poin yang Dibutuhkan</label>
                        <input type="number" class="form-control" id="points_required" name="points_required" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active">Aktif</option>
                            <option value="inactive">Tidak Aktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="add_reward" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Reward Modal -->
<div class="modal fade" id="editRewardModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Hadiah</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="modal-body">
                    <input type="hidden" id="edit_reward_id" name="reward_id">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Nama Hadiah</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_points_required" class="form-label">Poin yang Dibutuhkan</label>
                        <input type="number" class="form-control" id="edit_points_required" name="points_required" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_status" class="form-label">Status</label>
                        <select class="form-select" id="edit_status" name="status">
                            <option value="active">Aktif</option>
                            <option value="inactive">Tidak Aktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="update_reward" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Reward Modal -->
<div class="modal fade" id="deleteRewardModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Hapus Hadiah</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="modal-body">
                    <input type="hidden" id="delete_reward_id" name="reward_id">
                    <p>Apakah Anda yakin ingin menghapus hadiah <strong><span id="delete_reward_name"></span></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Hadiah yang sudah pernah ditukar tidak dapat dihapus.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="delete_reward" class="btn btn-danger">Hapus</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Edit reward modal
document.querySelectorAll('.edit-reward').forEach(button => {
    button.addEventListener('click', function() {
        document.getElementById('edit_reward_id').value = this.getAttribute('data-id');
        document.getElementById('edit_name').value = this.getAttribute('data-name');
        document.getElementById('edit_points_required').value = this.getAttribute('data-points');
        document.getElementById('edit_description').value = this.getAttribute('data-description');
        document.getElementById('edit_status').value = this.getAttribute('data-status');
        
        const editModal = new bootstrap.Modal(document.getElementById('editRewardModal'));
        editModal.show();
    });
});

// Delete reward modal
document.querySelectorAll('.delete-reward').forEach(button => {
    button.addEventListener('click', function() {
        document.getElementById('delete_reward_id').value = this.getAttribute('data-id');
        document.getElementById('delete_reward_name').textContent = this.getAttribute('data-name');
        
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteRewardModal'));
        deleteModal.show();
    });
});
</script>

<?php
$stmt->close();
include '../includes/footer.php';
?>
