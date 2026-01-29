
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
// Emergency script to ensure hamburger is visible on mobile
(function() {
    function ensureHamburgerVisible() {
        const hamburger = document.getElementById('hamburger-menu');
        if (!hamburger) return;
        
        // Force visible on mobile (iPhone 13 Pro Max is 430px)
        const isMobile = window.innerWidth <= 768;
        
        if (isMobile) {
            // Force it to be visible
            hamburger.style.setProperty('display', 'flex', 'important');
            hamburger.style.setProperty('visibility', 'visible', 'important');
            hamburger.style.setProperty('opacity', '1', 'important');
            hamburger.style.setProperty('flex-direction', 'column', 'important');
        }
    }
    
    // Run immediately
    ensureHamburgerVisible();
    
    // Run after DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ensureHamburgerVisible);
    }
    
    // Run on window load
    window.addEventListener('load', ensureHamburgerVisible);
})();
</script>

<script>
// Setup hamburger menu functionality
document.addEventListener('DOMContentLoaded', function() {
    const hamburger = document.getElementById('hamburger-menu');
    const sidebar = document.querySelector('.sidebar');
    
    if (!hamburger || !sidebar) return;
    
    // Click handler
    hamburger.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        hamburger.classList.toggle('active');
        sidebar.classList.toggle('active');
    });
    
    // Close on click outside
    document.addEventListener('click', function(e) {
        if (!sidebar.contains(e.target) && !hamburger.contains(e.target)) {
            hamburger.classList.remove('active');
            sidebar.classList.remove('active');
        }
    });
    
    // Close on link click
    sidebar.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', function() {
            hamburger.classList.remove('active');
            sidebar.classList.remove('active');
        });
    });
});
</script>

