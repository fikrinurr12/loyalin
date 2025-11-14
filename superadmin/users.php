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

// Get all businesses for dropdown
$businessesQuery = "SELECT id, business_name FROM businesses WHERE status = 'active' ORDER BY business_name ASC";
$businessesResult = $conn->query($businessesQuery);

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new user
    if (isset($_POST['add_user'])) {
        $businessId = (int)$_POST['business_id'];
        $name = sanitize($conn, $_POST['name']);
        $phone = sanitize($conn, $_POST['phone']);
        $password = $_POST['password'];
        $role = sanitize($conn, $_POST['role']);
        
        // Format phone number
        $phone = formatPhoneNumber($phone);
        
        // Validate
        if (empty($businessId)) {
            $errorMsg = 'Silakan pilih UMKM.';
        } elseif (empty($name)) {
            $errorMsg = 'Nama tidak boleh kosong.';
        } elseif (empty($phone)) {
            $errorMsg = 'Nomor HP tidak boleh kosong.';
        } elseif (strlen($password) < 8) {
            $errorMsg = 'Password harus minimal 8 karakter.';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $errorMsg = 'Password harus memiliki minimal 1 huruf kapital.';
        } elseif (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
            $errorMsg = 'Password harus memiliki minimal 1 simbol.';
        } else {
            // Check if phone already exists in this business
            if (userExistsInBusiness($conn, $phone, $businessId)) {
                $errorMsg = 'Nomor HP sudah terdaftar di UMKM ini.';
            } else {
                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert user
                $insertQuery = "INSERT INTO users (business_id, name, phone, password, role) VALUES (?, ?, ?, ?, ?)";
                $insertStmt = $conn->prepare($insertQuery);
                $insertStmt->bind_param("issss", $businessId, $name, $phone, $hashedPassword, $role);
                
                if ($insertStmt->execute()) {
                    $userId = $insertStmt->insert_id;
                    
                    // Add to user_business_access if customer
                    if ($role == 'customer') {
                        $accessQuery = "INSERT INTO user_business_access (user_phone, business_id, user_id) VALUES (?, ?, ?)";
                        $accessStmt = $conn->prepare($accessQuery);
                        $accessStmt->bind_param("sii", $phone, $businessId, $userId);
                        $accessStmt->execute();
                        $accessStmt->close();
                    }
                    
                    // Log activity
                    logActivity($conn, $businessId, $_SESSION['user_id'], 'CREATE_USER', "Created user: {$name} ({$role}) in business ID: {$businessId}");
                    
                    $successMsg = 'Pengguna baru berhasil ditambahkan.';
                } else {
                    $errorMsg = 'Terjadi kesalahan saat menambahkan pengguna.';
                }
                
                $insertStmt->close();
            }
        }
    }
    
    // Edit user
    if (isset($_POST['edit_user'])) {
        $userId = (int)sanitize($conn, $_POST['user_id']);
        $businessId = (int)$_POST['business_id'];
        $name = sanitize($conn, $_POST['name']);
        $phone = sanitize($conn, $_POST['phone']);
        $role = sanitize($conn, $_POST['role']);
        $newPassword = $_POST['new_password'];
        
        // Format phone number
        $phone = formatPhoneNumber($phone);
        
        // Validate
        if (empty($name)) {
            $errorMsg = 'Nama tidak boleh kosong.';
        } elseif (empty($phone)) {
            $errorMsg = 'Nomor HP tidak boleh kosong.';
        } else {
            // Check if phone already exists in this business (exclude current user)
            $checkQuery = "SELECT * FROM users WHERE phone = ? AND business_id = ? AND id != ?";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param("sii", $phone, $businessId, $userId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $errorMsg = 'Nomor HP sudah digunakan oleh pengguna lain di UMKM ini.';
            } else {
                // If new password is provided, validate and hash it
                if (!empty($newPassword)) {
                    if (strlen($newPassword) < 8) {
                        $errorMsg = 'Password baru harus minimal 8 karakter.';
                    } elseif (!preg_match('/[A-Z]/', $newPassword)) {
                        $errorMsg = 'Password baru harus memiliki minimal 1 huruf kapital.';
                    } elseif (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $newPassword)) {
                        $errorMsg = 'Password baru harus memiliki minimal 1 simbol.';
                    } else {
                        // Hash new password
                        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                        
                        // Update user with new password
                        $updateQuery = "UPDATE users SET business_id = ?, name = ?, phone = ?, password = ?, role = ? WHERE id = ?";
                        $updateStmt = $conn->prepare($updateQuery);
                        $updateStmt->bind_param("issssi", $businessId, $name, $phone, $hashedPassword, $role, $userId);
                    }
                } else {
                    // Update user without changing password
                    $updateQuery = "UPDATE users SET business_id = ?, name = ?, phone = ?, role = ? WHERE id = ?";
                    $updateStmt = $conn->prepare($updateQuery);
                    $updateStmt->bind_param("isssi", $businessId, $name, $phone, $role, $userId);
                }
                
                if (empty($errorMsg) && $updateStmt->execute()) {
                    logActivity($conn, $businessId, $_SESSION['user_id'], 'UPDATE_USER', "Updated user ID: {$userId}");
                    $successMsg = 'Pengguna berhasil diperbarui.';
                } else {
                    if (empty($errorMsg)) {
                        $errorMsg = 'Terjadi kesalahan saat memperbarui pengguna.';
                    }
                }
                
                $updateStmt->close();
            }
            
            $checkStmt->close();
        }
    }
    
    // Delete user
    if (isset($_POST['delete_user'])) {
        $userId = (int)sanitize($conn, $_POST['user_id']);
        
        // Get user info
        $userQuery = "SELECT role, business_id FROM users WHERE id = ?";
        $userStmt = $conn->prepare($userQuery);
        $userStmt->bind_param("i", $userId);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        $userInfo = $userResult->fetch_assoc();
        $userStmt->close();
        
        if ($userInfo['role'] == 'superadmin' && $userId == $_SESSION['user_id']) {
            $errorMsg = 'Anda tidak dapat menghapus akun yang sedang Anda gunakan.';
        } else {
            $deleteQuery = "DELETE FROM users WHERE id = ?";
            $deleteStmt = $conn->prepare($deleteQuery);
            $deleteStmt->bind_param("i", $userId);
            
            if ($deleteStmt->execute()) {
                logActivity($conn, $userInfo['business_id'], $_SESSION['user_id'], 'DELETE_USER', "Deleted user ID: {$userId}");
                $successMsg = 'Pengguna berhasil dihapus.';
            } else {
                $errorMsg = 'Terjadi kesalahan saat menghapus pengguna.';
            }
            
            $deleteStmt->close();
        }
    }
}

// Get all users with business info
$query = "
    SELECT u.*, b.business_name
    FROM users u
    LEFT JOIN businesses b ON u.business_id = b.id
    ORDER BY b.business_name, FIELD(u.role, 'superadmin', 'admin', 'customer'), u.name
";
$result = $conn->query($query);

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-users me-2"></i>Manajemen Pengguna</h2>
            <p>Kelola semua pengguna sistem loyalitas multi-UMKM.</p>
        </div>
        <div class="col-md-4 text-md-end">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-user-plus me-2"></i>Tambah Pengguna
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

    <!-- Users Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Daftar Pengguna</h5>
            <div class="input-group" style="max-width: 300px;">
                <input type="text" id="userSearch" class="form-control" placeholder="Cari pengguna...">
                <button class="btn btn-outline-secondary" type="button">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover" id="usersTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>UMKM</th>
                        <th>Nama</th>
                        <th>Nomor HP</th>
                        <th>Role</th>
                        <th>Poin</th>
                        <th>Tanggal Dibuat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result->num_rows > 0): ?>
                    <?php while($user = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td>
                            <small><?php echo htmlspecialchars($user['business_name'] ?: '-'); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo $user['phone']; ?></td>
                        <td>
                            <?php 
                            $badgeClass = '';
                            $roleText = '';
                            switch($user['role']) {
                                case 'superadmin':
                                    $badgeClass = 'bg-danger';
                                    $roleText = 'Superadmin';
                                    break;
                                case 'admin':
                                    $badgeClass = 'bg-primary';
                                    $roleText = 'Admin';
                                    break;
                                default:
                                    $badgeClass = 'bg-success';
                                    $roleText = 'Customer';
                            }
                            ?>
                            <span class="badge <?php echo $badgeClass; ?>"><?php echo $roleText; ?></span>
                        </td>
                        <td>
                            <?php if($user['role'] == 'customer'): ?>
                            <?php echo number_format($user['total_points']); ?> poin
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                        <td><?php echo formatDate($user['created_at'], 'd M Y H:i'); ?></td>
                        <td>
                            <button class="btn btn-sm btn-warning edit-user" data-id="<?php echo $user['id']; ?>"
                                data-business="<?php echo $user['business_id']; ?>"
                                data-name="<?php echo htmlspecialchars($user['name']); ?>"
                                data-phone="<?php echo $user['phone']; ?>" data-role="<?php echo $user['role']; ?>">
                                <i class="fas fa-edit"></i>
                            </button>

                            <?php if($user['id'] != $_SESSION['user_id']): ?>
                            <button class="btn btn-sm btn-danger delete-user" data-id="<?php echo $user['id']; ?>"
                                data-name="<?php echo htmlspecialchars($user['name']); ?>">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center">Tidak ada data pengguna.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Pengguna Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="business_id" class="form-label">UMKM <span class="text-danger">*</span></label>
                        <select class="form-select" id="business_id" name="business_id" required>
                            <option value="">Pilih UMKM</option>
                            <?php 
                            $businessesResult->data_seek(0);
                            while($business = $businessesResult->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $business['id']; ?>">
                                <?php echo htmlspecialchars($business['business_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label">Nomor HP <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">+62</span>
                            <input type="text" class="form-control" id="phone" name="phone" placeholder="8xxxxxxxxxx"
                                required>
                        </div>
                        <div class="form-text">Masukkan nomor tanpa awalan 0 atau +62</div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                        <div class="password-container">
                            <input type="password" class="form-control" id="password" name="password" required>
                            <span class="password-toggle" onclick="togglePassword('password')">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                        <div id="password-strength" class="mt-2"></div>
                        <div class="form-text">Minimal 8 karakter, 1 huruf kapital, dan 1 simbol</div>
                    </div>

                    <div class="mb-3">
                        <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="customer">Customer</option>
                            <option value="admin">Admin</option>
                            <option value="superadmin">Superadmin</option>
                        </select>
                        <div class="form-text">
                            <strong>Customer:</strong> Pelanggan UMKM<br>
                            <strong>Admin:</strong> Admin UMKM (kelola transaksi, rewards)<br>
                            <strong>Superadmin:</strong> Admin platform (kelola semua UMKM)
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="add_user" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Pengguna</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="modal-body">
                    <input type="hidden" id="edit_user_id" name="user_id">

                    <div class="mb-3">
                        <label for="edit_business_id" class="form-label">UMKM</label>
                        <select class="form-select" id="edit_business_id" name="business_id" required>
                            <option value="">Pilih UMKM</option>
                            <?php 
                            $businessesResult->data_seek(0);
                            while($business = $businessesResult->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $business['id']; ?>">
                                <?php echo htmlspecialchars($business['business_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_phone" class="form-label">Nomor HP</label>
                        <div class="input-group">
                            <span class="input-group-text">+62</span>
                            <input type="text" class="form-control" id="edit_phone" name="phone"
                                placeholder="8xxxxxxxxxx" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_role" class="form-label">Role</label>
                        <select class="form-select" id="edit_role" name="role" required>
                            <option value="customer">Customer</option>
                            <option value="admin">Admin</option>
                            <option value="superadmin">Superadmin</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label">Password Baru (Kosongkan jika tidak diubah)</label>
                        <div class="password-container">
                            <input type="password" class="form-control" id="new_password" name="new_password">
                            <span class="password-toggle" onclick="togglePassword('new_password')">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                        <div class="form-text">Minimal 8 karakter, 1 huruf kapital, dan 1 simbol</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="edit_user" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Hapus Pengguna</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="modal-body">
                    <input type="hidden" id="delete_user_id" name="user_id">
                    <p>Apakah Anda yakin ingin menghapus pengguna <strong><span id="delete_user_name"></span></strong>?
                    </p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Aksi ini tidak dapat dibatalkan!
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="delete_user" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Hapus
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Search functionality
document.getElementById('userSearch').addEventListener('keyup', function() {
    const searchText = this.value.toLowerCase();
    const table = document.getElementById('usersTable');
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

// Edit user modal
document.querySelectorAll('.edit-user').forEach(button => {
    button.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const businessId = this.getAttribute('data-business');
        const name = this.getAttribute('data-name');
        const phone = this.getAttribute('data-phone').replace('62', '');
        const role = this.getAttribute('data-role');

        document.getElementById('edit_user_id').value = id;
        document.getElementById('edit_business_id').value = businessId;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_phone').value = phone;
        document.getElementById('edit_role').value = role;
        document.getElementById('new_password').value = '';

        const editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
        editModal.show();
    });
});

// Delete user modal
document.querySelectorAll('.delete-user').forEach(button => {
    button.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const name = this.getAttribute('data-name');

        document.getElementById('delete_user_id').value = id;
        document.getElementById('delete_user_name').textContent = name;

        const deleteModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
        deleteModal.show();
    });
});

// Password validation
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const strengthDiv = document.getElementById('password-strength');
    strengthDiv.innerHTML = '';

    const minLength = password.length >= 8;
    const hasCapital = /[A-Z]/.test(password);
    const hasSymbol = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);

    if (minLength) {
        strengthDiv.innerHTML += '<div class="text-success">✓ Minimal 8 karakter</div>';
    } else {
        strengthDiv.innerHTML += '<div class="text-danger">✗ Minimal 8 karakter</div>';
    }

    if (hasCapital) {
        strengthDiv.innerHTML += '<div class="text-success">✓ Memiliki huruf kapital</div>';
    } else {
        strengthDiv.innerHTML += '<div class="text-danger">✗ Memiliki huruf kapital</div>';
    }

    if (hasSymbol) {
        strengthDiv.innerHTML += '<div class="text-success">✓ Memiliki simbol</div>';
    } else {
        strengthDiv.innerHTML += '<div class="text-danger">✗ Memiliki simbol</div>';
    }
});
</script>

<?php
// Include footer
include '../includes/footer.php';
?>