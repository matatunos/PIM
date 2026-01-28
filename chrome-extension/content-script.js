// Content script para inyectar funcionalidad en las páginas web

// Detectar cuando el usuario quiera guardar un link
document.addEventListener('contextmenu', (e) => {
    // Se maneja desde el background.js con los menús contextuales
}, true);

// Escuchar un atajo de teclado (Ctrl+Shift+L para guardar el link actual)
document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'L') {
        e.preventDefault();
        
        // Enviar mensaje al background script
        chrome.runtime.sendMessage({
            action: 'guardarLink',
            linkData: {
                titulo: document.title,
                url: window.location.href,
                descripcion: '',
                categoria: 'General',
                color: '#a8dadc',
                icono: 'fa-link'
            }
        });
    }
});

// Función para preparar link en el portapapeles si se copia
document.addEventListener('copy', () => {
    // Esto es opcional, puede usarse para detectar cuando se copia un enlace
});
