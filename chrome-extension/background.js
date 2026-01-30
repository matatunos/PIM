// Crear menú contextual
chrome.runtime.onInstalled.addListener(() => {
    chrome.contextMenus.create({
        id: 'save-page-link',
        title: 'Guardar página en PIM',
        contexts: ['page', 'link']
    });

    chrome.contextMenus.create({
        id: 'save-link',
        title: 'Guardar enlace en PIM',
        contexts: ['link']
    });
});

// Manejar clicks en menú contextual
chrome.contextMenus.onClicked.addListener(async (info, tab) => {
    const config = await chrome.storage.local.get('pimConfig');
    
    if (!config.pimConfig) {
        chrome.action.openPopup();
        return;
    }

    let url, title;

    if (info.menuItemId === 'save-link' && info.linkUrl) {
        url = info.linkUrl;
        title = info.linkText || url;
    } else {
        url = tab.url;
        title = tab.title;
    }

    // Intentar obtener el título de la página
    try {
        const response = await fetch(`${config.pimConfig.url}/app/links/index.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            credentials: 'include',
            body: new URLSearchParams({
                action: 'obtener_titulo',
                url: url
            })
        });

        if (response.ok) {
            const data = await response.json();
            if (data.titulo) {
                title = data.titulo;
            }
        }
    } catch (e) {
        console.log('No se pudo obtener el título');
    }

    // Guardar automáticamente
    guardarLink(config.pimConfig, {
        titulo: title,
        url: url,
        descripcion: '',
        categoria: 'General',
        color: '#a8dadc',
        icono: 'fa-link'
    });
});

// Función para guardar link
async function guardarLink(pimConfig, linkData) {
    try {
        const headers = {
            'Content-Type': 'application/json',
        };
        
        // Añadir token si existe
        if (pimConfig.token) {
            headers['X-PIM-Token'] = pimConfig.token;
        }
        
        const response = await fetch(`${pimConfig.url}/api/links.php`, {
            method: 'POST',
            headers: headers,
            credentials: 'include',
            body: JSON.stringify(linkData)
        });

        const data = await response.json();

        if (response.ok) {
            chrome.notifications.create({
                type: 'basic',
                title: 'Link guardado',
                message: `"${linkData.titulo}" ha sido guardado en PIM`,
                priority: 2
            });
        } else {
            chrome.notifications.create({
                type: 'basic',
                title: 'Error',
                message: data.error || 'No se pudo guardar el link',
                priority: 2
            });
        }
    } catch (error) {
        chrome.notifications.create({
            type: 'basic',
            title: 'Error de conexión',
            message: 'Verifica la configuración de tu PIM',
            priority: 2
        });
    }
}

// Responder a mensajes del content script
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
    if (request.action === 'guardarLink') {
        chrome.storage.local.get('pimConfig', (result) => {
            if (result.pimConfig) {
                guardarLink(result.pimConfig, request.linkData);
            }
        });
    }
});
