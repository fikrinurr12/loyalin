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
    <title>Loyalin - Platform Loyalitas UMKM Indonesia</title>
    <link rel="icon" href="../uploads/logos/logo-Loyalin.png"
        type="image/png">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo isset($basePath) ? $basePath : ''; ?>assets/css/style.css">

    <style>
    /* Loyalin Branding */
    .navbar {
        background: linear-gradient(135deg, #154c79, #003d5c) !important;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
    }

    .navbar-brand {
        font-weight: 700;
        font-size: 1.5rem;
        display: flex;
        align-items: center;
    }

    .navbar-brand img {
        height: 40px;
        margin-right: 10px;
    }

    .navbar-brand .brand-text {
        display: flex;
        flex-direction: column;
        line-height: 1.2;
    }

    .navbar-brand .brand-name {
        font-size: 1.5rem;
        font-weight: 700;
    }

    .navbar-brand .brand-tagline {
        font-size: 0.65rem;
        opacity: 0.8;
        font-weight: 400;
    }

    .nav-link {
        font-weight: 500;
        transition: all 0.3s;
    }

    .nav-link:hover {
        color: #c5d900 !important;
        transform: translateY(-2px);
    }

    .business-indicator {
        background: rgba(197, 217, 0, 0.2);
        border-left: 3px solid #c5d900;
        padding: 8px 15px;
        margin-right: 15px;
        border-radius: 5px;
    }

    .business-indicator small {
        opacity: 0.8;
    }

    @media (max-width: 991px) {
        .navbar-brand .brand-tagline {
            display: none;
        }
    }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="<?php echo isset($basePath) ? $basePath : ''; ?>index.php">
                <img src="assets/images/logo-Loyalin.png" alt="Loyalin">
                <div class="brand-text">
                    <span class="brand-name">Loyalin</span>
                    <span class="brand-tagline">Bikin Pelanggan Balik Lagi</span>
                </div>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if(isset($_SESSION['user_id'])): ?>

                    <!-- Business Indicator (for logged in users) -->
                    <?php if(isset($_SESSION['business_name'])): ?>
                    <li class="nav-item d-flex align-items-center me-3">
                        <div class="business-indicator">
                            <small class="d-block">UMKM Aktif:</small>
                            <strong><?php echo htmlspecialchars($_SESSION['business_name']); ?></strong>
                        </div>
                    </li>
                    <?php endif; ?>

                    <?php if($_SESSION['role'] == 'superadmin'): ?>
                    <li class="nav-item">
                        <a class="nav-link"
                            href="<?php echo isset($basePath) ? $basePath : ''; ?>superadmin/dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link"
                            href="<?php echo isset($basePath) ? $basePath : ''; ?>superadmin/businesses.php">
                            <i class="fas fa-store me-1"></i>Kelola UMKM</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo isset($basePath) ? $basePath : ''; ?>superadmin/users.php">
                            <i class="fas fa-users me-1"></i>Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link"
                            href="<?php echo isset($basePath) ? $basePath : ''; ?>superadmin/subscriptions.php">
                            <i class="fas fa-calendar-check me-1"></i>Subscription</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link"
                            href="<?php echo isset($basePath) ? $basePath : ''; ?>superadmin/transactions.php">
                            <i class="fas fa-exchange-alt me-1"></i>Transaksi</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link"
                            href="<?php echo isset($basePath) ? $basePath : ''; ?>superadmin/settings.php">
                            <i class="fas fa-cog me-1"></i>Settings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-warning"
                            href="<?php echo isset($basePath) ? $basePath : ''; ?>logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i>Logout</a>
                    </li>

                    <?php elseif($_SESSION['role'] == 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo isset($basePath) ? $basePath : ''; ?>admin/dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link"
                            href="<?php echo isset($basePath) ? $basePath : ''; ?>admin/manage_rewards.php">
                            <i class="fas fa-gift me-1"></i>Kelola Hadiah</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link"
                            href="<?php echo isset($basePath) ? $basePath : ''; ?>admin/transactions.php">
                            <i class="fas fa-receipt me-1"></i>Transaksi</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link"
                            href="<?php echo isset($basePath) ? $basePath : ''; ?>admin/points_setting.php">
                            <i class="fas fa-coins me-1"></i>Atur Poin</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-warning"
                            href="<?php echo isset($basePath) ? $basePath : ''; ?>logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i>Logout</a>
                    </li>

                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link"
                            href="<?php echo isset($basePath) ? $basePath : ''; ?>customer/dashboard.php">
                            <i class="fas fa-home me-1"></i>Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo isset($basePath) ? $basePath : ''; ?>customer/rewards.php">
                            <i class="fas fa-gift me-1"></i>Hadiah</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo isset($basePath) ? $basePath : ''; ?>customer/history.php">
                            <i class="fas fa-history me-1"></i>Riwayat</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo isset($basePath) ? $basePath : ''; ?>select_business.php">
                            <i class="fas fa-exchange-alt me-1"></i>Ganti UMKM</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-warning"
                            href="<?php echo isset($basePath) ? $basePath : ''; ?>logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i>Logout</a>
                    </li>
                    <?php endif; ?>

                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo isset($basePath) ? $basePath : ''; ?>login.php">
                            <i class="fas fa-sign-in-alt me-1"></i>Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-outline-light btn-sm ms-2"
                            href="<?php echo isset($basePath) ? $basePath : ''; ?>register.php">
                            <i class="fas fa-user-plus me-1"></i>Daftar</a>
                    </li>
                    <?php endif; ?>
                </ul>

            </div>
        </div>
    </nav>