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
  
  // Iniciar sistema de notificaciones
  initNotificationPoller();
});

// ==========================================
// Sistema de polling de notificaciones
// ==========================================

function initNotificationPoller() {
  console.log('[NOTIFICATIONS] Iniciando poll de notificaciones');
  
  // Poll cada 30 segundos
  setInterval(checkNotifications, 30000);
  
  // Verificar al inicio también
  checkNotifications();
}

function checkNotifications() {
  // Solo si hay sesión activa
  fetch('/api/notificaciones.php?action=obtener')
    .then(response => {
      if (response.status === 401) {
        console.log('[NOTIFICATIONS] Sesión no activa, omitiendo poll');
        return null;
      }
      return response.json();
    })
    .then(data => {
      if (!data || !data.notificaciones) return;
      
      console.log(`[NOTIFICATIONS] ${data.cantidad} notificaciones pendientes`);
      
      data.notificaciones.forEach(notif => {
        showNotification(notif);
        
        // Marcar como visto
        fetch('/api/notificaciones.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: `action=marcar_visto&id=${notif.id_notif}`
        });
      });
    })
    .catch(err => console.error('[NOTIFICATIONS] Error al obtener notificaciones:', err));
}

function showNotification(notif) {
  // Determinar tipo de notificación
  let type = 'info';
  let actionText = null;
  let onAction = null;
  
  if (notif.tipo === 'evento') {
    type = 'info';
    actionText = 'Ver evento';
    onAction = () => window.location.hash = '#calendario';
  } else if (notif.tipo === 'tarea') {
    type = 'warning';
    actionText = 'Ver tarea';
    onAction = () => window.location.hash = '#tareas';
  }
  
  // Mostrar toast
  if (typeof toast !== 'undefined') {
    toast.show({
      title: notif.titulo,
      message: notif.descripcion,
      type: type,
      duration: 6000,
      actionText: actionText,
      onAction: onAction
    });
  }
}
