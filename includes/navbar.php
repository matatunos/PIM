
<?php require_once __DIR__.'/lang.php'; ?>
<nav class="navbar-glass">
  <div class="navbar-container">
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
