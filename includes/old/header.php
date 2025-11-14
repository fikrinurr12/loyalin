<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuriCrypt - Sistem Loyalitas Rumah Makan Sate</title>
    <link rel="icon" href="\assets\images\logo-sate.png" type="image/png">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo isset($basePath) ? $basePath : ''; ?>assets/css/style.css">
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="<?php echo isset($basePath) ? $basePath : ''; ?>index.php">
                <i class="fas fa-utensils me-2"></i>SuriCrypt Loyalty
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if(isset($_SESSION['user_id'])): ?>
                    <?php if($_SESSION['role'] == 'superadmin'): ?>
                    <li class="nav-item">
                        <a class="nav-link"
                            href="<?php echo isset($basePath) ? $basePath : ''; ?>superadmin/dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link"
                            href="<?php echo isset($basePath) ? $basePath : ''; ?>superadmin/businesses.php">
                            <i class="fas fa-store me-1"></i>Kelola UMKM
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link"
                            href="<?php echo isset($basePath) ? $basePath : ''; ?>superadmin/subscriptions.php">
                            <i class="fas fa-calendar-check me-1"></i>Subscription
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo isset($basePath) ? $basePath : ''; ?>superadmin/users.php">
                            <i class="fas fa-users me-1"></i>Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link"
                            href="<?php echo isset($basePath) ? $basePath : ''; ?>superadmin/transactions.php">
                            Transaksi
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link"
                            href="<?php echo isset($basePath) ? $basePath : ''; ?>superadmin/settings.php">Settings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo isset($basePath) ? $basePath : ''; ?>logout.php">Logout</a>
                    </li>

                    <?php elseif($_SESSION['role'] == 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link"
                            href="<?php echo isset($basePath) ? $basePath : ''; ?>admin/dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link"
                            href="<?php echo isset($basePath) ? $basePath : ''; ?>admin/manage_rewards.php">Kelola
                            Hadiah</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link"
                            href="<?php echo isset($basePath) ? $basePath : ''; ?>admin/transactions.php">Transaksi</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo isset($basePath) ? $basePath : ''; ?>logout.php">Logout</a>
                    </li>

                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link"
                            href="<?php echo isset($basePath) ? $basePath : ''; ?>customer/dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link"
                            href="<?php echo isset($basePath) ? $basePath : ''; ?>customer/rewards.php">Hadiah</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link"
                            href="<?php echo isset($basePath) ? $basePath : ''; ?>customer/history.php">Riwayat</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo isset($basePath) ? $basePath : ''; ?>logout.php">Logout</a>
                    </li>
                    <?php endif; ?>

                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo isset($basePath) ? $basePath : ''; ?>login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link"
                            href="<?php echo isset($basePath) ? $basePath : ''; ?>register.php">Daftar</a>
                    </li>
                    <?php endif; ?>
                </ul>

            </div>
        </div>
    </nav>