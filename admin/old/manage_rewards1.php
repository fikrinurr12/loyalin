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
            $query = "INSERT INTO rewards (name, points_required, description, status) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("siss", $name, $points, $description, $status);
            
            if ($stmt->execute()) {
                $successMsg = 'Hadiah baru berhasil ditambahkan.';
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
            $query = "UPDATE rewards SET name = ?, points_required = ?, description = ?, status = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sissi", $name, $points, $description, $status, $id);
            
            if ($stmt->execute()) {
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
        
        // Check if reward is being used in any redemptions
        $checkQuery = "SELECT COUNT(*) as count FROM redemptions WHERE reward_id = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("i", $id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $redemptionCount = $checkResult->fetch_assoc()['count'];
        $checkStmt->close();
        
        if ($redemptionCount > 0) {
            $errorMsg = 'Hadiah ini tidak dapat dihapus karena sudah digunakan dalam penukaran.';
        } else {
            $query = "DELETE FROM rewards WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $successMsg = 'Hadiah berhasil dihapus.';
            } else {
                $errorMsg = 'Terjadi kesalahan saat menghapus hadiah.';
            }
            
            $stmt->close();
        }
    }
}

// Get all rewards
$query = "SELECT * FROM rewards ORDER BY points_required ASC";
$result = $conn->query($query);

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Kelola Hadiah</h2>
            <p>Tambah, edit, atau hapus hadiah yang dapat ditukarkan oleh pelanggan.</p>
        </div>
        <div class="col-md-4 text-md-end">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRewardModal">
                <i class="fas fa-plus-circle me-2"></i>Tambah Hadiah
            </button>
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

    <!-- Rewards Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Daftar Hadiah</h5>
            <div class="input-group" style="max-width: 300px;">
                <input type="text" id="rewardSearch" class="form-control" placeholder="Cari hadiah...">
                <button class="btn btn-outline-secondary" type="button">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover" id="rewardsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama Hadiah</th>
                        <th>Poin Dibutuhkan</th>
                        <th>Deskripsi</th>
                        <th>Status</th>
                        <th>Tanggal Dibuat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result->num_rows > 0): ?>
                    <?php while($reward = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $reward['id']; ?></td>
                        <td><?php echo $reward['name']; ?></td>
                        <td><?php echo $reward['points_required']; ?> poin</td>
                        <td><?php echo $reward['description'] ?: '-'; ?></td>
                        <td>
                            <?php if($reward['status'] == 'active'): ?>
                            <span class="badge bg-success">Aktif</span>
                            <?php else: ?>
                            <span class="badge bg-danger">Tidak Aktif</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d M Y H:i', strtotime($reward['created_at'])); ?></td>
                        <td>
                            <button class="btn btn-sm btn-warning edit-reward" data-id="<?php echo $reward['id']; ?>"
                                data-name="<?php echo $reward['name']; ?>"
                                data-points="<?php echo $reward['points_required']; ?>"
                                data-description="<?php echo $reward['description']; ?>"
                                data-status="<?php echo $reward['status']; ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger delete-reward" data-id="<?php echo $reward['id']; ?>"
                                data-name="<?php echo $reward['name']; ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center">Belum ada hadiah yang tersedia.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Reward Modal -->
<div class="modal fade" id="addRewardModal" tabindex="-1" aria-labelledby="addRewardModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addRewardModalLabel">Tambah Hadiah Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nama Hadiah</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="points_required" class="form-label">Poin yang Dibutuhkan</label>
                        <input type="number" class="form-control" id="points_required" name="points_required" min="1"
                            required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="status" id="status_active" value="active"
                                checked>
                            <label class="form-check-label" for="status_active">
                                Aktif
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="status" id="status_inactive"
                                value="inactive">
                            <label class="form-check-label" for="status_inactive">
                                Tidak Aktif
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" name="add_reward">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Reward Modal -->
<div class="modal fade" id="editRewardModal" tabindex="-1" aria-labelledby="editRewardModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editRewardModalLabel">Edit Hadiah</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
                        <input type="number" class="form-control" id="edit_points_required" name="points_required"
                            min="1" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="status" id="edit_status_active"
                                value="active">
                            <label class="form-check-label" for="edit_status_active">
                                Aktif
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="status" id="edit_status_inactive"
                                value="inactive">
                            <label class="form-check-label" for="edit_status_inactive">
                                Tidak Aktif
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" name="update_reward">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Reward Modal -->
<div class="modal fade" id="deleteRewardModal" tabindex="-1" aria-labelledby="deleteRewardModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteRewardModalLabel">Hapus Hadiah</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="modal-body">
                    <input type="hidden" id="delete_reward_id" name="reward_id">
                    <p>Apakah Anda yakin ingin menghapus hadiah "<span id="delete_reward_name"></span>"?</p>
                    <p class="text-danger">Perhatian: Hadiah yang sudah digunakan dalam penukaran tidak dapat dihapus.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger" name="delete_reward">Hapus</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Search functionality
document.getElementById('rewardSearch').addEventListener('keyup', function() {
    const searchText = this.value.toLowerCase();
    const table = document.getElementById('rewardsTable');
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

// Edit reward modal
document.querySelectorAll('.edit-reward').forEach(button => {
    button.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const name = this.getAttribute('data-name');
        const points = this.getAttribute('data-points');
        const description = this.getAttribute('data-description');
        const status = this.getAttribute('data-status');

        document.getElementById('edit_reward_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_points_required').value = points;
        document.getElementById('edit_description').value = description;

        if (status === 'active') {
            document.getElementById('edit_status_active').checked = true;
        } else {
            document.getElementById('edit_status_inactive').checked = true;
        }

        const editModal = new bootstrap.Modal(document.getElementById('editRewardModal'));
        editModal.show();
    });
});

// Delete reward modal
document.querySelectorAll('.delete-reward').forEach(button => {
    button.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const name = this.getAttribute('data-name');

        document.getElementById('delete_reward_id').value = id;
        document.getElementById('delete_reward_name').textContent = name;

        const deleteModal = new bootstrap.Modal(document.getElementById('deleteRewardModal'));
        deleteModal.show();
    });
});
</script>

<?php
// Include footer
include '../includes/footer.php';
?>