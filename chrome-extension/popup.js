// Elementos del DOM
const configView = document.getElementById('configView');
const saveView = document.getElementById('saveView');
const linkForm = document.getElementById('linkForm');
const configBtn = document.getElementById('configBtn');
const saveConfigBtn = document.getElementById('saveConfigBtn');
const pimUrlInput = document.getElementById('pimUrl');
const loading = document.getElementById('loading');
const success = document.getElementById('success');
const error = document.getElementById('error');
const errorMessage = document.getElementById('errorMessage');

let selectedColor = '#a8dadc';
let selectedIcon = 'fa-link';

// Inicializar
document.addEventListener('DOMContentLoaded', () => {
    cargarConfiguracion();
    setupColorPicker();
    setupIconPicker();
});

// Cargar configuración
async function cargarConfiguracion() {
    const config = await chrome.storage.local.get('pimConfig');
    
    if (!config.pimConfig || !config.pimConfig.url) {
        mostrarVista('configView');
    } else {
        mostrarVista('saveView');
        cargarDatosPagina();
        cargarCategorias();
    }
}

// Guardar configuración
saveConfigBtn.addEventListener('click', async () => {
    const url = pimUrlInput.value.trim();
    
    if (!url) {
        mostrarError('Por favor ingresa una URL');
        return;
    }
    
    if (!isValidUrl(url)) {
        mostrarError('URL inválida');
        return;
    }
    
    await chrome.storage.local.set({
        pimConfig: { url: url }
    });
    
    mostrarVista('saveView');
    cargarDatosPagina();
    cargarCategorias();
});

// Botón configurar
configBtn.addEventListener('click', async () => {
    const config = await chrome.storage.local.get('pimConfig');
    pimUrlInput.value = config.pimConfig?.url || '';
    mostrarVista('configView');
});

// Cargar datos de la página actual
async function cargarDatosPagina() {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    
    document.getElementById('url').value = tab.url;
    document.getElementById('titulo').value = tab.title;
    document.getElementById('titulo').focus();
}

// Cargar categorías
async function cargarCategorias() {
    const config = await chrome.storage.local.get('pimConfig');
    if (!config.pimConfig) return;
    
    try {
        const response = await fetch(`${config.pimConfig.url}/api/links.php?action=get_categories`, {
            method: 'GET',
            credentials: 'include'
        });
        
        if (response.ok) {
            const data = await response.json();
            const datalist = document.getElementById('categoriasList');
            datalist.innerHTML = '';
            
            data.categorias?.forEach(cat => {
                const option = document.createElement('option');
                option.value = cat;
                datalist.appendChild(option);
            });
        }
    } catch (e) {
        console.log('No se pudieron cargar categorías');
    }
}

// Setup color picker
function setupColorPicker() {
    document.querySelectorAll('.color-option').forEach(option => {
        option.addEventListener('click', (e) => {
            document.querySelectorAll('.color-option').forEach(o => o.classList.remove('selected'));
            e.target.classList.add('selected');
            selectedColor = e.target.dataset.color;
            document.getElementById('color').value = selectedColor;
        });
    });
    
    // Seleccionar el primero por defecto
    document.querySelector('.color-option').classList.add('selected');
}

// Setup icon picker
function setupIconPicker() {
    document.querySelectorAll('.icon-option').forEach(option => {
        option.addEventListener('click', (e) => {
            document.querySelectorAll('.icon-option').forEach(o => o.classList.remove('selected'));
            e.target.classList.add('selected');
            selectedIcon = e.target.dataset.icon;
            document.getElementById('icono').value = selectedIcon;
        });
    });
}

// Enviar formulario
linkForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const config = await chrome.storage.local.get('pimConfig');
    if (!config.pimConfig) {
        mostrarError('Configuración no encontrada');
        return;
    }
    
    const formData = {
        titulo: document.getElementById('titulo').value.trim(),
        url: document.getElementById('url').value.trim(),
        descripcion: document.getElementById('descripcion').value.trim(),
        categoria: document.getElementById('categoria').value.trim() || 'General',
        color: document.getElementById('color').value,
        icono: document.getElementById('icono').value
    };
    
    if (!formData.titulo || !formData.url) {
        mostrarError('Título y URL son requeridos');
        return;
    }
    
    mostrarLoading(true);
    
    try {
        const response = await fetch(`${config.pimConfig.url}/api/links.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (response.ok) {
            mostrarLoading(false);
            mostrarSuccess();
            setTimeout(() => {
                window.close();
            }, 1500);
        } else {
            mostrarError(data.error || 'Error al guardar el link');
        }
    } catch (err) {
        mostrarError('Error de conexión: ' + err.message);
    }
    
    mostrarLoading(false);
});

// Funciones auxiliares
function mostrarVista(vistaId) {
    configView.classList.add('hidden');
    saveView.classList.add('hidden');
    document.getElementById(vistaId).classList.remove('hidden');
}

function mostrarLoading(visible) {
    if (visible) {
        linkForm.classList.add('hidden');
        loading.classList.remove('hidden');
    } else {
        linkForm.classList.remove('hidden');
        loading.classList.add('hidden');
    }
}

function mostrarSuccess() {
    linkForm.classList.add('hidden');
    error.classList.add('hidden');
    success.classList.remove('hidden');
}

function mostrarError(mensaje) {
    errorMessage.textContent = mensaje;
    error.classList.remove('hidden');
    success.classList.add('hidden');
}

function isValidUrl(string) {
    try {
        new URL(string);
        return true;
    } catch (_) {
        return false;
    }
}
