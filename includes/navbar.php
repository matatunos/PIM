
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
// Mostrar/ocultar hamburger según viewport
function toggleHamburgerVisibility() {
    const hamburger = document.getElementById('hamburger-menu');
    if (!hamburger) return;
    
    if (window.innerWidth <= 768) {
        hamburger.style.display = 'flex';
    } else {
        hamburger.style.display = 'none';
        // Si está activo en desktop, cerrarlo
        hamburger.classList.remove('active');
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) sidebar.classList.remove('active');
    }
}

// Ejecutar cuando cargue el navbar
document.addEventListener('DOMContentLoaded', function() {
    toggleHamburgerVisibility();
    
    // Configurar click del hamburger
    const hamburger = document.getElementById('hamburger-menu');
    const sidebar = document.querySelector('.sidebar');
    
    if (hamburger && sidebar) {
        hamburger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            hamburger.classList.toggle('active');
            sidebar.classList.toggle('active');
        });
        
        // Cerrar sidebar al hacer click fuera
        document.addEventListener('click', function(e) {
            if (!sidebar.contains(e.target) && !hamburger.contains(e.target)) {
                hamburger.classList.remove('active');
                sidebar.classList.remove('active');
            }
        });
        
        // Cerrar sidebar al hacer click en un link
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

// Ejecutar al cambiar tamaño de ventana
window.addEventListener('resize', toggleHamburgerVisibility);
</script>

