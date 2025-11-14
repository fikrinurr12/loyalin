<footer class="bg-dark text-light py-4 mt-5">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="d-flex align-items-center mb-3">
                    <img src="<?php echo isset($basePath) ? $basePath : ''; ?>assets/images/logo-loyalin.png"
                        alt="Loyalin" style="height: 40px; margin-right: 10px;">
                    <div>
                        <h5 class="mb-0" style="color: #c5d900;">Loyalin</h5>
                        <small style="opacity: 0.8;">Bikin Pelanggan Balik Lagi</small>
                    </div>
                </div>
                <p class="small mb-2">Platform Loyalitas untuk UMKM Indonesia</p>
                <p class="small text-muted mb-0">Membantu UMKM meningkatkan loyalitas pelanggan dengan sistem poin
                    digital yang mudah dan efektif.</p>
            </div>

            <div class="col-md-5 mb-3 mb-md-0">
                <h6 class="mb-3" style="color: #c5d900;">Navigasi</h6>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <a href="<?php echo isset($basePath) ? $basePath : ''; ?>index.php"
                            class="text-light text-decoration-none">
                            <i class="fas fa-home me-2"></i>Beranda
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="<?php echo isset($basePath) ? $basePath : ''; ?>login.php"
                            class="text-light text-decoration-none">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="<?php echo isset($basePath) ? $basePath : ''; ?>register.php"
                            class="text-light text-decoration-none">
                            <i class="fas fa-user-plus me-2"></i>Daftar
                        </a>
                    </li>
                </ul>
            </div>

            <!-- ✅ SECTION "DUKUNGAN" DIHAPUS! -->

            <div class="col-md-3">
                <h6 class="mb-3" style="color: #c5d900;">Ikuti Kami</h6>
                <div class="d-flex gap-3">
                    <a href="#" class="text-light" style="font-size: 1.5rem;" title="Facebook">
                        <i class="fab fa-facebook"></i>
                    </a>
                    <a href="#" class="text-light" style="font-size: 1.5rem;" title="Instagram">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="#" class="text-light" style="font-size: 1.5rem;" title="WhatsApp">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                </div>
            </div>
        </div>

        <hr class="my-4" style="border-color: rgba(197, 217, 0, 0.3);">

        <div class="row">
            <div class="col-md-6 text-center text-md-start">
                <p class="mb-0 small">&copy; <?php echo date('Y'); ?> <strong>Loyalin</strong>. All rights reserved.</p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <p class="mb-0 small">
                    Made with <i class="fas fa-heart text-danger"></i> for Indonesian UMKM
                </p>
            </div>
        </div>
    </div>
</footer>

<style>
footer a:hover {
    color: #c5d900 !important;
    transform: translateX(3px);
    transition: all 0.3s;
}

footer .fab:hover,
footer .fas:hover {
    transform: scale(1.2);
    transition: transform 0.3s;
}
</style>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JavaScript -->
<script src="<?php echo isset($basePath) ? $basePath : ''; ?>assets/js/script.js"></script>
</body>

</html>