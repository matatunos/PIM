<aside class="sidebar">
    <div class="sidebar-header">
        <a href="/index.php" class="sidebar-logo">
            <img src="/assets/img/logo-48.png" alt="PIM Logo" class="sidebar-logo-icon" style="width: 40px; height: 40px;">
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
            <div class="nav-item-group">
                <a href="/app/tareas/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/tareas/') !== false ? 'active' : '' ?>">
                    <i class="fas fa-tasks"></i>
                    Tareas
                </a>
                <div class="nav-sublinks" style="display: <?= strpos($_SERVER['PHP_SELF'], '/tareas/') !== false ? 'block' : 'none' ?>;">
                    <a href="/app/tareas/index.php" class="nav-sublink <?= $_SERVER['REQUEST_URI'] === '/app/tareas/index.php' ? 'active' : '' ?>">
                        <i class="fas fa-list"></i> Lista
                    </a>
                    <a href="/app/tareas/kanban.php" class="nav-sublink <?= strpos($_SERVER['REQUEST_URI'], 'kanban') !== false ? 'active' : '' ?>">
                        <i class="fas fa-th"></i> Kanban
                    </a>
                </div>
            </div>
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
            <a href="/app/admin/backups.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/admin/backups') !== false ? 'active' : '' ?>">
                <i class="fas fa-database"></i>
                Backups
            </a>
            <a href="/app/admin/papelera.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/admin/papelera') !== false ? 'active' : '' ?>">
                <i class="fas fa-trash"></i>
                Papelera
            </a>
            <a href="/app/admin/archivos.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/admin/archivos') !== false ? 'active' : '' ?>">
                <i class="fas fa-folder-cog"></i>
                Gestión de Archivos
            </a>
            <a href="/app/admin/auditoria.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/admin/auditoria') !== false ? 'active' : '' ?>">
                <i class="fas fa-shield-alt"></i>
                Auditoría y Logs
            </a>
            <a href="/app/admin/configuracion.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/admin/configuracion') !== false ? 'active' : '' ?>">
                <i class="fas fa-cogs"></i>
                Configuración
            </a>
        </div>
        <?php endif; ?>
    </nav>
    
    <div class="sidebar-footer">
        <div class="user-profile-menu" id="userProfileMenu">
            <div class="user-profile" id="userProfileToggle">
                <div class="user-avatar">
                    <?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 2)) ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($_SESSION['nombre_completo'] ?? $_SESSION['username'] ?? 'Usuario') ?></div>
                    <div class="user-role"><?= htmlspecialchars($_SESSION['rol'] ?? 'user') ?></div>
                </div>
                <i class="fas fa-chevron-up" style="color: var(--text-secondary); transition: all 0.3s ease;"></i>
            </div>
            
            <div class="user-dropdown" id="userDropdown">
                <a href="/app/perfil/index.php" class="dropdown-item" style="display: flex; align-items: center; gap: var(--spacing-md); padding: 0.75rem var(--spacing-md); text-decoration: none; color: var(--text-secondary); cursor: pointer; transition: all var(--transition-fast);">
                    <i class="fas fa-user-cog"></i>
                    <span>Mi Perfil</span>
                </a>
                <div class="dropdown-item" id="changePasswordBtn">
                    <i class="fas fa-key"></i>
                    <span>Cambiar Contraseña</span>
                </div>
                <div class="dropdown-item" id="downloadExtensionBtn">
                    <i class="fas fa-download"></i>
                    <span>Descargar Extensión Chrome</span>
                </div>
                <div class="dropdown-divider"></div>
                <div class="dropdown-item dropdown-logout" onclick="window.location.href='/app/auth/logout.php'">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Cerrar Sesión</span>
                </div>
            </div>
        </div>
    </div>
</aside>

<script>
    // User Profile Menu Toggle
    document.addEventListener('DOMContentLoaded', function() {
        // Tareas submenu - mostrar si estamos en /tareas/
        const tareaSublinks = document.querySelector('.nav-sublinks');
        if (tareaSublinks && window.location.pathname.includes('/tareas/')) {
            tareaSublinks.style.display = 'block';
        }
        
        const profileToggle = document.getElementById('userProfileToggle');
        const dropdown = document.getElementById('userDropdown');
        const profileMenu = document.getElementById('userProfileMenu');
        const changePasswordBtn = document.getElementById('changePasswordBtn');
        const downloadExtensionBtn = document.getElementById('downloadExtensionBtn');
        
        if (!profileToggle || !dropdown) return;
        
        // Toggle dropdown
        profileToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            profileToggle.classList.toggle('active');
            dropdown.classList.toggle('active');
        });
        
        // Close dropdown when clicking elsewhere
        document.addEventListener('click', function(e) {
            if (!profileMenu.contains(e.target)) {
                profileToggle.classList.remove('active');
                dropdown.classList.remove('active');
            }
        });
        
        // Change password button
        if (changePasswordBtn) {
            changePasswordBtn.addEventListener('click', function() {
                window.location.href = '/app/perfil/cambiar-contrasena.php';
            });
        }
        
        // Download extension button
        if (downloadExtensionBtn) {
            downloadExtensionBtn.addEventListener('click', function() {
                // Crear un blob con la extensión
                const extensionPath = '/chrome-extension';
                
                // Crear un modal de descarga
                const modal = document.createElement('div');
                modal.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0,0,0,0.5);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 2000;
                `;
                
                const content = document.createElement('div');
                content.style.cssText = `
                    background: white;
                    padding: 2rem;
                    border-radius: 8px;
                    max-width: 500px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                `;
                
                content.innerHTML = `
                    <h3 style="margin-bottom: 1rem; color: #2c3e50;">📥 Descargar Extensión Chrome</h3>
                    <p style="margin-bottom: 1rem; color: #7f8c8d; line-height: 1.6;">
                        Para descargar la extensión de PIM:
                    </p>
                    <ol style="margin-bottom: 1.5rem; color: #2c3e50; padding-left: 1.5rem;">
                        <li style="margin-bottom: 0.5rem;">Descarga el archivo ZIP con la extensión</li>
                        <li style="margin-bottom: 0.5rem;">Abre <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px;">chrome://extensions/</code></li>
                        <li style="margin-bottom: 0.5rem;">Activa "Modo de desarrollador"</li>
                        <li style="margin-bottom: 0.5rem;">Click en "Cargar extensión sin empaquetar"</li>
                        <li>Selecciona la carpeta de la extensión</li>
                    </ol>
                    <div style="display: flex; gap: 1rem;">
                        <button id="downloadZipBtn" style="
                            flex: 1;
                            padding: 0.75rem;
                            background: #a8dadc;
                            color: white;
                            border: none;
                            border-radius: 6px;
                            cursor: pointer;
                            font-weight: 600;
                            font-size: 0.95rem;
                            transition: all 0.3s ease;
                        " onmouseover="this.style.background='#80cfd4'" onmouseout="this.style.background='#a8dadc'">
                            <i class="fas fa-download"></i> Descargar ZIP
                        </button>
                        <button id="closeModalBtn" style="
                            flex: 1;
                            padding: 0.75rem;
                            background: #ecf0f1;
                            color: #2c3e50;
                            border: none;
                            border-radius: 6px;
                            cursor: pointer;
                            font-weight: 600;
                            font-size: 0.95rem;
                            transition: all 0.3s ease;
                        " onmouseover="this.style.background='#d5dbdb'" onmouseout="this.style.background='#ecf0f1'">
                            Cerrar
                        </button>
                    </div>
                `;
                
                modal.appendChild(content);
                document.body.appendChild(modal);
                
                document.getElementById('closeModalBtn').addEventListener('click', function() {
                    modal.remove();
                });
                
                document.getElementById('downloadZipBtn').addEventListener('click', function() {
                    // Crear descarga del ZIP
                    const link = document.createElement('a');
                    link.href = '/api/download-extension.php';
                    link.download = 'pim-chrome-extension.zip';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    setTimeout(() => {
                        modal.remove();
                    }, 500);
                });
                
                // Cerrar al hacer click fuera
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        modal.remove();
                    }
                });
            });
        }
        
        // Cerrar dropdown al hacer clic en cualquier item
        const dropdownItems = document.querySelectorAll('.user-dropdown .dropdown-item');
        dropdownItems.forEach(item => {
            item.addEventListener('click', function() {
                profileToggle.classList.remove('active');
                dropdown.classList.remove('active');
            });
        });
    });
</script>