<!-- Sidebar -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="/" class="brand-link">
        <img src="/assets/img/logo.png" alt="Logo" class="brand-image img-circle elevation-3">
        <span class="brand-text font-weight-light">Sistema Gestión</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar user panel -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
                <img src="/assets/img/user-profile.jpg" class="img-circle elevation-2" alt="User Image">
            </div>
            <div class="info">
                <a href="#" class="d-block"><?= $_SESSION['user_name'] ?? 'Usuario' ?></a>
            </div>
        </div>

        <!-- Sidebar Menu Dinámico -->
        <nav class="mt-2">
            <?php include __DIR__ . '/menu-items.php'; ?>
        </nav>
    </div>
</aside>