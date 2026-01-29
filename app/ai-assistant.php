<?php
/**
 * Chat con IA usando Open WebUI + RAG
 * Ruta: /app/ai-assistant.php
 * Permite consultar documentos subidos a Open WebUI
 */

require_once '../config/config.php';
require_once '../includes/auth_check.php';

$usuario_id = $_SESSION['user_id'];
$usuario_nombre = $_SESSION['username'] ?? 'Usuario';

// Obtener configuración de Open WebUI
try {
    $stmt = $pdo->prepare('SELECT clave, valor FROM configuracion_ia WHERE clave IN (?, ?, ?)');
    $stmt->execute(['openwebui_host', 'openwebui_port', 'sync_enabled']);
    $config = [];
    while ($row = $stmt->fetch()) {
        $config[$row['clave']] = $row['valor'];
    }
} catch (Exception $e) {
    $config = [];
}

$openwebui_host = $config['openwebui_host'] ?? '192.168.1.19';
$openwebui_port = $config['openwebui_port'] ?? '8080';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat con IA - PIM</title>
    <link rel="icon" type="image/png" href="/assets/img/favicon.png">
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/fonts/fontawesome/css/all.min.css">
    <style>
        .chat-wrapper {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 180px);
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .chat-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            padding: var(--spacing-md) var(--spacing-lg);
            border-bottom: 1px solid var(--border-color);
            background: #f8f9fa;
            flex-wrap: wrap;
        }
        
        .chat-header label {
            font-weight: 500;
            font-size: 13px;
            color: var(--text-secondary);
        }
        
        .chat-header select {
            padding: 6px 10px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 13px;
            background: #fff;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: var(--spacing-lg);
            background: #f9f9f9;
        }
        
        .message {
            margin-bottom: var(--spacing-lg);
            display: flex;
            gap: var(--spacing-md);
        }
        
        .message.user {
            flex-direction: row-reverse;
        }
        
        .message-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }
        
        .message.user .message-avatar {
            background: #e8d5f0;
            color: #7c4dff;
        }
        
        .message.assistant .message-avatar {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .message-content {
            max-width: 80%;
            padding: var(--spacing-md) var(--spacing-lg);
            border-radius: 12px;
            line-height: 1.5;
        }
        
        .message.user .message-content {
            background: #7c4dff;
            color: #fff;
            border-bottom-right-radius: 4px;
        }
        
        .message.assistant .message-content {
            background: #fff;
            border: 1px solid var(--border-color);
            border-bottom-left-radius: 4px;
        }
        
        .message-content pre {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: var(--spacing-md);
            border-radius: 6px;
            overflow-x: auto;
            margin: var(--spacing-sm) 0;
        }
        
        .message-content code {
            font-family: 'Fira Code', monospace;
            font-size: 13px;
        }
        
        .message-content p {
            margin-bottom: var(--spacing-sm);
        }
        
        .chat-input-container {
            display: flex;
            gap: var(--spacing-md);
            padding: var(--spacing-lg);
            border-top: 1px solid var(--border-color);
            background: #fff;
        }
        
        .chat-input {
            flex: 1;
            padding: var(--spacing-md);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            resize: none;
            font-family: inherit;
            max-height: 150px;
        }
        
        .btn-send {
            padding: var(--spacing-md) var(--spacing-lg);
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            font-weight: 500;
            transition: background 0.2s;
        }
        
        .btn-send:hover {
            background: var(--primary-dark);
        }
        
        .btn-send:disabled {
            background: #ccc;
            cursor: not-allowed;
        }        
        .btn-stop {
            padding: 10px 20px;
            background: #f44336;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            animation: pulse-stop 1s infinite;
        }
        
        .btn-stop:hover {
            background: #d32f2f;
        }
        
        @keyframes pulse-stop {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }        
        .typing-indicator {
            display: flex;
            gap: 4px;
            padding: var(--spacing-md);
        }
        
        .typing-indicator span {
            width: 8px;
            height: 8px;
            background: #999;
            border-radius: 50%;
            animation: typing 1.4s infinite ease-in-out;
        }
        
        .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
        .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }
        
        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-8px); }
        }
        
        .welcome-message {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
        }
        
        .welcome-message h3 {
            color: var(--text-primary);
            margin-bottom: var(--spacing-md);
        }
        
        .welcome-message i {
            font-size: 48px;
            color: var(--primary);
            margin-bottom: var(--spacing-lg);
            display: block;
        }
        
        /* Selector de documentos */
        .docs-selector {
            position: relative;
        }
        
        .docs-toggle {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s;
        }
        
        .docs-toggle:hover {
            border-color: var(--primary);
        }
        
        .docs-toggle.active {
            background: #e8f5e9;
            border-color: #4caf50;
            color: #2e7d32;
        }
        
        .docs-count {
            background: var(--primary);
            color: #fff;
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 4px;
        }
        
        .docs-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            width: 400px;
            max-height: 400px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 100;
            display: none;
            margin-top: 4px;
        }
        
        .docs-dropdown.show {
            display: block;
        }
        
        .docs-dropdown-header {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
            font-weight: 500;
            font-size: 13px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .docs-dropdown-header button {
            font-size: 11px;
            color: var(--primary);
            background: none;
            border: none;
            cursor: pointer;
        }
        
        .btn-select-quick {
            padding: 4px 10px;
            border: 1px solid var(--border-color) !important;
            border-radius: 4px;
            background: #f8f9fa !important;
            font-size: 12px !important;
            transition: all 0.2s;
        }
        
        .btn-select-quick:hover {
            background: var(--primary) !important;
            color: #fff !important;
            border-color: var(--primary) !important;
        }
        
        .docs-search {
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .docs-search input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 13px;
        }
        
        .docs-search input:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .docs-filters {
            display: flex;
            gap: 6px;
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            background: #f8f9fa;
        }
        
        .docs-filter-btn {
            padding: 4px 10px;
            font-size: 11px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background: #fff;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .docs-filter-btn:hover {
            border-color: var(--primary);
        }
        
        .docs-filter-btn.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }
        
        .docs-list-container {
            max-height: 280px;
            overflow-y: auto;
        }
        
        .doc-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            cursor: pointer;
            transition: background 0.15s;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .doc-item:hover {
            background: #f8f9fa;
        }
        
        .doc-item.selected {
            background: #e8f5e9;
        }
        
        .doc-item input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        
        .doc-item-info {
            flex: 1;
            min-width: 0;
        }
        
        .doc-item-name {
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .doc-item-meta {
            font-size: 11px;
            color: var(--text-secondary);
        }
        
        .doc-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f0f0;
            border-radius: 6px;
            color: #666;
        }
        
        .doc-icon.pdf { background: #ffebee; color: #c62828; }
        .doc-icon.doc { background: #e3f2fd; color: #1565c0; }
        .doc-icon.txt { background: #f3e5f5; color: #7b1fa2; }
        .doc-icon.yml { background: #fff3e0; color: #ef6c00; }
        
        .status-indicator {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            margin-left: auto;
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #4caf50;
        }
        
        .status-dot.disconnected {
            background: #f44336;
        }
        
        .rag-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 10px;
            background: #e8f5e9;
            color: #2e7d32;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 8px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="top-bar-left">
                    <h1 class="page-title"><i class="fas fa-brain"></i> Chat con IA</h1>
                </div>
                <div>
                    <?php if ($_SESSION['rol'] === 'admin'): ?>
                        <a href="/app/admin/configuracion.php#openwebui-section" class="btn btn-ghost">
                            <i class="fas fa-cog"></i> Configurar
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="content-area">
                <div class="chat-wrapper">
                    <div class="chat-header">
                        <label for="model-select"><i class="fas fa-robot"></i> Modelo:</label>
                        <select id="model-select">
                            <option value="llama3.2:3b">Llama 3.2 (3B) - Rápido</option>
                            <option value="qwen2.5:14b">Qwen 2.5 (14B) - Potente</option>
                        </select>
                        
                        <div class="docs-selector">
                            <button type="button" class="docs-toggle" id="docs-toggle">
                                <i class="fas fa-file-alt"></i>
                                <span>Documentos</span>
                                <span class="docs-count" id="docs-count" style="display:none;">0</span>
                            </button>
                            <div class="docs-dropdown" id="docs-dropdown">
                                <div class="docs-dropdown-header">
                                    <div style="display:flex;gap:8px;align-items:center;">
                                        <button type="button" id="select-all-notes" class="btn-select-quick" title="Seleccionar solo notas">
                                            📝 Notas
                                        </button>
                                        <button type="button" id="select-all-docs" class="btn-select-quick" title="Seleccionar todo">
                                            📁 Todo
                                        </button>
                                    </div>
                                    <button type="button" id="clear-docs">Limpiar</button>
                                </div>
                                <div class="docs-search">
                                    <input type="text" id="docs-search-input" placeholder="🔍 Buscar documentos...">
                                </div>
                                <div class="docs-filters">
                                    <button type="button" class="docs-filter-btn active" data-filter="all">Todos</button>
                                    <button type="button" class="docs-filter-btn" data-filter="docs">📄 Archivos</button>
                                    <button type="button" class="docs-filter-btn" data-filter="notes">📝 Notas</button>
                                </div>
                                <div class="docs-list-container" id="docs-list">
                                    <div style="padding: 20px; text-align: center; color: #999;">
                                        <i class="fas fa-spinner fa-spin"></i> Cargando...
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="status-indicator">
                            <div class="status-dot" id="status-dot"></div>
                            <span id="status-text">Conectando...</span>
                        </div>
                    </div>
                    
                    <div class="chat-messages" id="chat-messages">
                        <div class="welcome-message">
                            <i class="fas fa-comments"></i>
                            <h3>¡Hola, <?= htmlspecialchars($usuario_nombre) ?>!</h3>
                            <p>Soy tu asistente de IA. Puedo ayudarte con preguntas, código, análisis y más.</p>
                            <p style="margin-top: 15px; font-size: 13px;">
                                <i class="fas fa-lightbulb" style="color: #ff9800;"></i> 
                                <strong>Tip:</strong> Selecciona documentos para hacer preguntas sobre su contenido.
                            </p>
                            <p style="font-size: 12px; margin-top: 15px; color: #999;">
                                Powered by Open WebUI + Ollama
                            </p>
                        </div>
                    </div>
                    
                    <div class="chat-input-container">
                        <textarea 
                            id="chat-input" 
                            class="chat-input" 
                            placeholder="Escribe tu mensaje... (selecciona documentos arriba para consultar su contenido)"
                            rows="1"></textarea>
                        <button id="btn-stop" class="btn-stop" style="display:none;">
                            <i class="fas fa-stop"></i>
                            Parar
                        </button>
                        <button id="btn-send" class="btn-send">
                            <i class="fas fa-paper-plane"></i>
                            Enviar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    const API_PROXY = '/api/openwebui-proxy.php';
    
    let conversationHistory = [];
    let isGenerating = false;
    let availableFiles = [];
    let selectedFiles = [];
    let currentAbortController = null;
    
    const chatMessages = document.getElementById('chat-messages');
    const chatInput = document.getElementById('chat-input');
    const btnSend = document.getElementById('btn-send');
    const btnStop = document.getElementById('btn-stop');
    const modelSelect = document.getElementById('model-select');
    const statusDot = document.getElementById('status-dot');
    const statusText = document.getElementById('status-text');
    const docsToggle = document.getElementById('docs-toggle');
    const docsDropdown = document.getElementById('docs-dropdown');
    const docsList = document.getElementById('docs-list');
    const docsCount = document.getElementById('docs-count');
    const docsSearchInput = document.getElementById('docs-search-input');
    
    let currentFilter = 'all';
    let searchQuery = '';
    
    // Cargar archivos disponibles y seleccionar solo NOTAS por defecto
    async function loadFiles() {
        try {
            const response = await fetch(`${API_PROXY}?endpoint=files`);
            availableFiles = await response.json();
            
            // Seleccionar solo notas por defecto (más ligero para RAG)
            selectedFiles = availableFiles.filter(f => isNote(f)).map(f => f.id);
            
            renderFilesList();
            updateDocsUI();
            updateStatus(true);
        } catch (error) {
            console.error('Error cargando archivos:', error);
            docsList.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">Error cargando documentos</div>';
            updateStatus(false);
        }
    }
    
    function getFileIcon(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        if (['pdf'].includes(ext)) return 'pdf';
        if (['doc', 'docx'].includes(ext)) return 'doc';
        if (['txt', 'md'].includes(ext)) return 'txt';
        if (['yml', 'yaml', 'json'].includes(ext)) return 'yml';
        return '';
    }
    
    function isNote(file) {
        const name = file.meta?.name || file.filename || '';
        // Notas: archivos .txt, sin extensión, o con prefijos [Paperless], [BookStack], etc.
        const hasNotePrefix = name.startsWith('[') || name.includes('Paperless') || name.includes('BookStack');
        const isTxtFile = name.endsWith('.txt') || name.endsWith('.md');
        const noExtension = !name.includes('.') && name.length > 0;
        return hasNotePrefix || isTxtFile || noExtension;
    }
    
    function isDocument(file) {
        const name = file.meta?.name || file.filename || '';
        const ext = name.split('.').pop().toLowerCase();
        return ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'yml', 'yaml', 'json'].includes(ext);
    }
    
    function filterFiles() {
        return availableFiles.filter(file => {
            const name = (file.meta?.name || file.filename || '').toLowerCase();
            
            // Filtro por búsqueda
            if (searchQuery && !name.includes(searchQuery.toLowerCase())) {
                return false;
            }
            
            // Filtro por tipo
            if (currentFilter === 'notes' && !isNote(file)) {
                return false;
            }
            if (currentFilter === 'docs' && !isDocument(file)) {
                return false;
            }
            
            return true;
        });
    }
    
    function renderFilesList() {
        const filteredFiles = filterFiles();
        
        if (filteredFiles.length === 0) {
            if (searchQuery || currentFilter !== 'all') {
                docsList.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;"><i class="fas fa-search"></i><br>No se encontraron resultados</div>';
            } else {
                docsList.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;"><i class="fas fa-folder-open"></i><br>No hay documentos sincronizados</div>';
            }
            return;
        }
        
        docsList.innerHTML = filteredFiles.map(file => {
            const name = file.meta?.name || file.filename || 'Sin nombre';
            const size = file.meta?.size ? formatSize(file.meta.size) : '';
            const iconClass = isNote(file) ? 'note' : getFileIcon(name);
            const isSelected = selectedFiles.includes(file.id);
            const noteIndicator = isNote(file) ? '<span style="font-size:10px;color:#7c4dff;">📝</span> ' : '';
            
            return `
                <label class="doc-item ${isSelected ? 'selected' : ''}" data-id="${file.id}">
                    <input type="checkbox" ${isSelected ? 'checked' : ''}>
                    <div class="doc-icon ${iconClass}" style="${isNote(file) ? 'background:#f3e5f5;color:#7b1fa2;' : ''}">
                        <i class="fas ${isNote(file) ? 'fa-sticky-note' : 'fa-file' + (iconClass === 'pdf' ? '-pdf' : '')}"></i>
                    </div>
                    <div class="doc-item-info">
                        <div class="doc-item-name" title="${name}">${noteIndicator}${name}</div>
                        <div class="doc-item-meta">${size}</div>
                    </div>
                </label>
            `;
        }).join('');
        
        // Event listeners para checkboxes
        docsList.querySelectorAll('.doc-item').forEach(item => {
            item.addEventListener('click', (e) => {
                if (e.target.tagName === 'INPUT') return;
                const checkbox = item.querySelector('input');
                checkbox.checked = !checkbox.checked;
                toggleFile(item.dataset.id, checkbox.checked);
            });
            
            item.querySelector('input').addEventListener('change', (e) => {
                toggleFile(item.dataset.id, e.target.checked);
            });
        });
    }
    
    // Búsqueda
    docsSearchInput.addEventListener('input', (e) => {
        searchQuery = e.target.value;
        renderFilesList();
    });
    
    // Filtros
    document.querySelectorAll('.docs-filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.docs-filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentFilter = btn.dataset.filter;
            renderFilesList();
        });
    });
    
    function toggleFile(fileId, selected) {
        if (selected) {
            if (!selectedFiles.includes(fileId)) {
                selectedFiles.push(fileId);
            }
        } else {
            selectedFiles = selectedFiles.filter(id => id !== fileId);
        }
        updateDocsUI();
    }
    
    function updateDocsUI() {
        const count = selectedFiles.length;
        docsCount.textContent = count;
        docsCount.style.display = count > 0 ? 'inline' : 'none';
        docsToggle.classList.toggle('active', count > 0);
        
        // Actualizar visual de items
        docsList.querySelectorAll('.doc-item').forEach(item => {
            const isSelected = selectedFiles.includes(item.dataset.id);
            item.classList.toggle('selected', isSelected);
            item.querySelector('input').checked = isSelected;
        });
        
        // Actualizar checkbox "seleccionar todos"
        updateSelectAllState();
    }
    
    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }
    
    function updateStatus(connected) {
        statusDot.classList.toggle('disconnected', !connected);
        statusText.textContent = connected ? 'Conectado' : 'Desconectado';
    }
    
    // Toggle dropdown
    docsToggle.addEventListener('click', () => {
        docsDropdown.classList.toggle('show');
    });
    
    // Cerrar dropdown al hacer clic fuera
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.docs-selector')) {
            docsDropdown.classList.remove('show');
        }
    });
    
    // Limpiar selección
    document.getElementById('clear-docs').addEventListener('click', () => {
        selectedFiles = [];
        updateDocsUI();
    });
    
    // Seleccionar solo notas
    document.getElementById('select-all-notes').addEventListener('click', () => {
        // Seleccionar solo archivos que son notas
        const notesOnly = availableFiles.filter(f => isNote(f));
        selectedFiles = notesOnly.map(f => f.id);
        updateDocsUI();
    });
    
    // Seleccionar todos
    document.getElementById('select-all-docs').addEventListener('click', () => {
        // Seleccionar todos los archivos
        selectedFiles = availableFiles.map(f => f.id);
        updateDocsUI();
    });
    
    // Actualizar estado visual
    function updateSelectAllState() {
        // Ya no hay checkbox, solo botones
    }
    
    // Auto-resize textarea
    chatInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 150) + 'px';
    });
    
    // Enter para enviar
    chatInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });
    
    btnSend.addEventListener('click', sendMessage);
    
    async function sendMessage() {
        const message = chatInput.value.trim();
        if (!message || isGenerating) return;
        
        // Limpiar bienvenida
        const welcome = chatMessages.querySelector('.welcome-message');
        if (welcome) welcome.remove();
        
        // Badge de RAG si hay archivos seleccionados
        let userMessageExtra = '';
        if (selectedFiles.length > 0) {
            const fileNames = selectedFiles.map(id => {
                const file = availableFiles.find(f => f.id === id);
                return file?.meta?.name || 'documento';
            }).slice(0, 2).join(', ');
            const moreCount = selectedFiles.length > 2 ? ` +${selectedFiles.length - 2}` : '';
            userMessageExtra = `<span class="rag-badge"><i class="fas fa-file-alt"></i> ${fileNames}${moreCount}</span>`;
        }
        
        // Agregar mensaje del usuario
        addMessage('user', message, userMessageExtra);
        chatInput.value = '';
        chatInput.style.height = 'auto';
        
        // Agregar a historial
        conversationHistory.push({ role: 'user', content: message });
        
        // Indicador de escritura
        const typingDiv = document.createElement('div');
        typingDiv.className = 'message assistant';
        typingDiv.id = 'typing-indicator';
        typingDiv.innerHTML = `
            <div class="message-avatar"><i class="fas fa-robot"></i></div>
            <div class="message-content">
                <div class="typing-indicator">
                    <span></span><span></span><span></span>
                </div>
            </div>
        `;
        chatMessages.appendChild(typingDiv);
        scrollToBottom();
        
        isGenerating = true;
        btnSend.disabled = true;
        btnSend.style.display = 'none';
        btnStop.style.display = 'flex';
        
        // Crear AbortController para poder cancelar
        currentAbortController = new AbortController();
        
        try {
            const payload = {
                model: modelSelect.value,
                messages: conversationHistory,
                stream: true
            };
            
            // Añadir archivos si hay seleccionados
            if (selectedFiles.length > 0) {
                payload.files = selectedFiles.map(id => ({ type: 'file', id }));
            }
            
            const response = await fetch(`${API_PROXY}?endpoint=chat`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
                signal: currentAbortController.signal
            });
            
            document.getElementById('typing-indicator')?.remove();
            
            if (!response.ok) {
                throw new Error(`Error ${response.status}: ${response.statusText}`);
            }
            
            const assistantDiv = document.createElement('div');
            assistantDiv.className = 'message assistant';
            assistantDiv.innerHTML = `
                <div class="message-avatar"><i class="fas fa-robot"></i></div>
                <div class="message-content"></div>
            `;
            chatMessages.appendChild(assistantDiv);
            const contentDiv = assistantDiv.querySelector('.message-content');
            
            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let fullResponse = '';
            
            while (true) {
                const { done, value } = await reader.read();
                if (done) break;
                
                const chunk = decoder.decode(value);
                const lines = chunk.split('\n').filter(line => line.trim());
                
                for (const line of lines) {
                    // Manejar formato SSE de Open WebUI
                    let jsonStr = line;
                    if (line.startsWith('data: ')) {
                        jsonStr = line.slice(6);
                    }
                    if (jsonStr === '[DONE]') continue;
                    
                    try {
                        const json = JSON.parse(jsonStr);
                        // Formato Open WebUI
                        if (json.choices?.[0]?.delta?.content) {
                            fullResponse += json.choices[0].delta.content;
                            contentDiv.innerHTML = formatMessage(fullResponse);
                            scrollToBottom();
                        }
                        // Formato Ollama directo
                        if (json.message?.content) {
                            fullResponse += json.message.content;
                            contentDiv.innerHTML = formatMessage(fullResponse);
                            scrollToBottom();
                        }
                    } catch (e) {
                        // Ignorar líneas no JSON
                    }
                }
            }
            
            if (fullResponse) {
                conversationHistory.push({ role: 'assistant', content: fullResponse });
            }
            
        } catch (error) {
            document.getElementById('typing-indicator')?.remove();
            
            if (error.name === 'AbortError') {
                // Cancelado por el usuario
                addMessage('assistant', '⏹️ *Respuesta interrumpida*');
            } else {
                console.error('Error:', error);
                addMessage('assistant', `❌ Error: ${error.message}. Verifica la conexión con Open WebUI.`);
                updateStatus(false);
            }
        } finally {
            isGenerating = false;
            btnSend.disabled = false;
            btnSend.style.display = 'flex';
            btnStop.style.display = 'none';
            currentAbortController = null;
            chatInput.focus();
        }
    }
    
    // Botón de Stop
    btnStop.addEventListener('click', () => {
        if (currentAbortController) {
            currentAbortController.abort();
        }
    });
    
    function addMessage(role, content, extra = '') {
        const div = document.createElement('div');
        div.className = `message ${role}`;
        
        const avatarIcon = role === 'user' ? 'fa-user' : 'fa-robot';
        div.innerHTML = `
            <div class="message-avatar"><i class="fas ${avatarIcon}"></i></div>
            <div class="message-content">${formatMessage(content)}${extra}</div>
        `;
        
        chatMessages.appendChild(div);
        scrollToBottom();
    }
    
    function formatMessage(text) {
        let html = text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
        
        html = html.replace(/```(\w*)\n([\s\S]*?)```/g, '<pre><code>$2</code></pre>');
        html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
        html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/\*([^*]+)\*/g, '<em>$1</em>');
        html = html.replace(/\n/g, '<br>');
        
        return html;
    }
    
    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    // Inicializar
    loadFiles();
    </script>
</body>
</html>
