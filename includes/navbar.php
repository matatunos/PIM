
<?php require_once __DIR__.'/lang.php'; ?>
<nav class="navbar-glass">
  <div class="navbar-container">
    <button id="hamburger-menu" class="hamburger-menu" title="Menú" style="display: none; flex-direction: column; background: none; border: none; cursor: pointer; padding: 8px; gap: 6px; margin-right: 12px;">
      <span style="width: 24px; height: 3px; background-color: var(--text-primary); border-radius: 2px; display: block;"></span>
      <span style="width: 24px; height: 3px; background-color: var(--text-primary); border-radius: 2px; display: block;"></span>
      <span style="width: 24px; height: 3px; background-color: var(--text-primary); border-radius: 2px; display: block;"></span>
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
// Mostrar hamburger en mobile
function mostrarHamburguerMobile() {
    const hamburger = document.getElementById('hamburger-menu');
    if (window.innerWidth <= 768) {
        hamburger.style.display = 'flex';
    } else {
        hamburger.style.display = 'none';
    }
}

// Ejecutar al cargar
mostrarHamburguerMobile();

// Ejecutar al cambiar tamaño
window.addEventListener('resize', mostrarHamburguerMobile);
</script>
