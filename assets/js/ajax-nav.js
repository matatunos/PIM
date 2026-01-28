// Navegación AJAX para módulos principales

document.addEventListener('DOMContentLoaded', function() {
  console.log('[AJAX NAV] Script cargado');
  document.querySelectorAll('.navbar-menu a[data-module]').forEach(function(link) {
    link.addEventListener('click', function(e) {
      e.preventDefault();
      const module = link.getAttribute('data-module');
      console.log(`[AJAX NAV] Click en módulo: ${module}`);
      const mainContent = document.getElementById('main-content');
      if (!mainContent) {
        console.warn('[AJAX NAV] No se encontró #main-content');
        return;
      }
      mainContent.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-3">Cargando...</p></div>';
      fetch(`/app/${module}/index.php`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(res => res.text())
        .then(html => {
          // Extraer solo el contenido principal
          const temp = document.createElement('div');
          temp.innerHTML = html;
          let content = temp.querySelector('.container');
          if (content) {
            mainContent.innerHTML = content.outerHTML;
            console.log(`[AJAX NAV] Módulo ${module} cargado correctamente.`);
          } else {
            mainContent.innerHTML = html;
            console.warn(`[AJAX NAV] No se encontró .container en el módulo ${module}`);
          }
        })
        .catch((err) => {
          mainContent.innerHTML = '<div class="alert alert-danger">Error al cargar el módulo.</div>';
          console.error(`[AJAX NAV] Error al cargar el módulo ${module}:`, err);
        });
    });
  });
});
