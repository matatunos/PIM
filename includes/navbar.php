
<?php require_once __DIR__.'/lang.php'; ?>
<nav class="navbar-glass">
  <div class="navbar-container">
    <button id="hamburger-menu" title="Menú" style="display: none;">
      <span></span>
      <span></span>
      <span></span>
    </button>
    <a href="/index.php" class="navbar-brand"><i class="fas fa-th-large"></i> PIM</a>
    <ul class="navbar-menu">
      <li><a href="#" data-module="contactos"><i class="fas fa-address-book"></i> <?= t('contactos') ?></a></li>
      <li><a href="#" data-module="notas"><i class="fas fa-sticky-note"></i> <?= t('notas') ?></a></li>
      <li><a href="#" data-module="archivos"><i class="fas fa-folder-open"></i> <?= t('archivos') ?></a></li>
      <li><a href="#" data-module="calendario"><i class="fas fa-calendar-alt"></i> <?= t('calendario') ?></a></li>
      <li><a href="#" data-module="tareas"><i class="fas fa-tasks"></i> <?= t('tareas') ?></a></li>
      <li><a href="#" data-module="recordatorios"><i class="fas fa-bell"></i> <?= t('recordatorios') ?></a></li>
      <li><a href="#" data-module="busqueda"><i class="fas fa-search"></i> <?= t('busqueda') ?></a></li>
    </ul>
    <ul class="navbar-menu navbar-right">
      <li><a href="/app/auth/logout.php"><i class="fas fa-sign-out-alt"></i> <?= t('salir') ?></a></li>
      <li><a href="?lang=es"><i class="fas fa-globe"></i> ES</a></li>
      <li><a href="?lang=en"><i class="fas fa-globe"></i> EN</a></li>
    </ul>
  </div>
</nav>

<script>
// Función para actualizar visibilidad del hamburger
function updateHamburgerVisibility() {
    const hamburger = document.getElementById('hamburger-menu');
    if (!hamburger) return;
    
    const isMobile = window.innerWidth <= 768;
    console.log('Viewport width:', window.innerWidth, 'Mobile:', isMobile);
    
    if (isMobile) {
        hamburger.style.display = 'flex';
        hamburger.style.flexDirection = 'column';
        hamburger.style.visibility = 'visible';
    } else {
        hamburger.style.display = 'none';
        hamburger.style.visibility = 'hidden';
    }
}

// Ejecutar al cargar
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', updateHamburgerVisibility);
} else {
    updateHamburgerVisibility();
}

// Ejecutar después de que el layout esté completo
window.addEventListener('load', updateHamburgerVisibility);

// Actualizar cuando cambie el tamaño o orientación
window.addEventListener('resize', updateHamburgerVisibility);
window.addEventListener('orientationchange', updateHamburgerVisibility);

// También ejecutar periódicamente por si acaso
setTimeout(updateHamburgerVisibility, 100);
setTimeout(updateHamburgerVisibility, 500);
setTimeout(updateHamburgerVisibility, 1000);

// Configurar funcionalidad del hamburger
document.addEventListener('DOMContentLoaded', function() {
    const hamburger = document.getElementById('hamburger-menu');
    const sidebar = document.querySelector('.sidebar');
    
    if (hamburger && sidebar) {
        hamburger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            hamburger.classList.toggle('active');
            sidebar.classList.toggle('active');
        });
        
        // Cerrar al click fuera
        document.addEventListener('click', function(e) {
            if (!sidebar.contains(e.target) && !hamburger.contains(e.target)) {
                hamburger.classList.remove('active');
                sidebar.classList.remove('active');
            }
        });
        
        // Cerrar al click en links
        sidebar.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    hamburger.classList.remove('active');
                    sidebar.classList.remove('active');
                }
            });
        });
    }
});
</script>

