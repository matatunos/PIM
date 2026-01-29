
<?php require_once __DIR__.'/lang.php'; ?>
<nav class="navbar-glass">
  <div class="navbar-container">
    <button id="hamburger-menu" title="Menú">
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
    
    // Usar múltiples métodos para detectar viewport
    const width1 = window.innerWidth;
    const width2 = document.documentElement.clientWidth;
    const width3 = window.visualViewport ? window.visualViewport.width : null;
    
    const actualWidth = width1 || width2 || width3 || 0;
    console.log('Viewport detection - innerWidth:', width1, 'clientWidth:', width2, 'visualViewport:', width3, 'Using:', actualWidth);
    
    const isMobile = actualWidth <= 768;
    
    if (isMobile) {
        hamburger.style.display = 'flex';
        hamburger.style.flexDirection = 'column';
        hamburger.style.visibility = 'visible';
        hamburger.style.opacity = '1';
        hamburger.style.position = 'relative';
        console.log('HAMBURGER SHOWN');
    } else {
        hamburger.style.display = 'none';
        hamburger.style.visibility = 'hidden';
        console.log('HAMBURGER HIDDEN');
    }
}

// Ejecutar inmediatamente
updateHamburgerVisibility();

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

// También ejecutar cuando el layout cambie
if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', updateHamburgerVisibility);
}

// Ejecutar periódicamente
setInterval(updateHamburgerVisibility, 500);

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
                const actualWidth = window.innerWidth || document.documentElement.clientWidth || 0;
                if (actualWidth <= 768) {
                    hamburger.classList.remove('active');
                    sidebar.classList.remove('active');
                }
            });
        });
    }
});
</script>

