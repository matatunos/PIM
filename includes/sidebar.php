<aside class="sidebar">
    <div class="sidebar-header">
        <a href="/index.php" class="sidebar-logo">
            <div class="sidebar-logo-icon">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <div class="sidebar-logo-text">
                <h1>PIM</h1>
                <p>Gestor Personal</p>
            </div>
        </a>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-title">Principal</div>
            <a href="/index.php" class="nav-link <?= $_SERVER['PHP_SELF'] == '/index.php' ? 'active' : '' ?>">
                <i class="fas fa-home"></i>
                Dashboard
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">Productividad</div>
            <a href="/app/tareas/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/tareas/') !== false ? 'active' : '' ?>">
                <i class="fas fa-tasks"></i>
                Tareas
            </a>
            <a href="/app/notas/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/notas/') !== false ? 'active' : '' ?>">
                <i class="fas fa-sticky-note"></i>
                Notas
            </a>
            <a href="/app/calendario/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/calendario/') !== false ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i>
                Calendario
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">Organización</div>
            <a href="/app/contactos/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/contactos/') !== false ? 'active' : '' ?>">
                <i class="fas fa-address-book"></i>
                Contactos
            </a>
            <a href="/app/archivos/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/archivos/') !== false ? 'active' : '' ?>">
                <i class="fas fa-folder"></i>
                Archivos
            </a>
            <a href="/app/links/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/links/') !== false ? 'active' : '' ?>">
                <i class="fas fa-link"></i>
                Links
            </a>
        </div>
        
        <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
        <div class="nav-section">
            <div class="nav-section-title">Administración</div>
            <a href="/app/admin/usuarios.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/admin/usuarios') !== false ? 'active' : '' ?>">
                <i class="fas fa-users-cog"></i>
                Usuarios
            </a>
            <a href="/app/admin/archivos.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/admin/archivos') !== false ? 'active' : '' ?>">
                <i class="fas fa-folder-cog"></i>
                Gestión de Archivos
            </a>
            <a href="/app/admin/logs.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/admin/logs') !== false ? 'active' : '' ?>">
                <i class="fas fa-history"></i>
                Logs de Acceso
            </a>
        </div>
        <?php endif; ?>
    </nav>
    
    <div class="sidebar-footer">
        <a href="/app/auth/logout.php" style="text-decoration: none;">
            <div class="user-profile">
                <div class="user-avatar">
                    <?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 2)) ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($_SESSION['nombre_completo'] ?? $_SESSION['username'] ?? 'Usuario') ?></div>
                    <div class="user-role"><?= htmlspecialchars($_SESSION['rol'] ?? 'user') ?></div>
                </div>
                <i class="fas fa-sign-out-alt" style="color: var(--text-secondary);"></i>
            </div>
        </a>
    </div>
</aside>
