<?php
require_once '../config/config.php';
require_once '../includes/auth_check.php';

$doc = $_GET['doc'] ?? '';

// Lista blanca de documentos permitidos
$allowed_docs = [
    'WEBHOOKS_AUTOMATIZACIONES.md',
    'PERFORMANCE.md',
    'OPENWEBUI_INTEGRATION.md',
    'CALDAV_CARDDAV.md',
    'DOCKER.md',
    'manual-usuario.md',
    'INDICE_DOCUMENTACION.md'
];

if (!in_array($doc, $allowed_docs)) {
    header('Location: /index.php');
    exit;
}

$file_path = __DIR__ . '/' . $doc;
if (!file_exists($file_path)) {
    die('Documento no encontrado');
}

$markdown_content = file_get_contents($file_path);
$doc_title = pathinfo($doc, PATHINFO_FILENAME);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($doc_title) ?> - PIM Docs</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/fonts/fontawesome/css/all.min.css">
    <style>
        .doc-viewer {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            background: var(--bg-primary);
            min-height: 100vh;
        }
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }
        .doc-content {
            line-height: 1.8;
            color: var(--text-primary);
        }
        .doc-content h1 {
            color: var(--text-primary);
            margin-top: 2rem;
            margin-bottom: 1rem;
            font-size: 2rem;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 0.5rem;
        }
        .doc-content h2 {
            color: var(--text-primary);
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
            font-size: 1.5rem;
        }
        .doc-content h3 {
            color: var(--text-secondary);
            margin-top: 1rem;
            margin-bottom: 0.5rem;
            font-size: 1.25rem;
        }
        .doc-content code {
            background: var(--bg-secondary);
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            color: #e83e8c;
        }
        .doc-content pre {
            background: var(--bg-secondary);
            padding: 1rem;
            border-radius: 5px;
            overflow-x: auto;
            border-left: 4px solid var(--primary);
        }
        .doc-content pre code {
            background: none;
            padding: 0;
            color: var(--text-primary);
        }
        .doc-content a {
            color: var(--primary);
            text-decoration: none;
        }
        .doc-content a:hover {
            text-decoration: underline;
        }
        .doc-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }
        .doc-content table th,
        .doc-content table td {
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            text-align: left;
        }
        .doc-content table th {
            background: var(--bg-secondary);
            font-weight: 600;
        }
        .doc-content blockquote {
            border-left: 4px solid var(--primary);
            padding-left: 1rem;
            margin: 1rem 0;
            color: var(--text-secondary);
            font-style: italic;
        }
        .doc-content ul, .doc-content ol {
            margin: 0.5rem 0 0.5rem 2rem;
        }
        .doc-content li {
            margin: 0.25rem 0;
        }
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--primary);
            color: white;
            border-radius: 5px;
            text-decoration: none;
            transition: background 0.3s;
        }
        .btn-back:hover {
            background: var(--primary-dark);
            color: white;
        }
    </style>
</head>
<body>
    <div class="doc-viewer">
        <div class="doc-header">
            <h1><i class="fas fa-book"></i> <?= htmlspecialchars($doc_title) ?></h1>
            <a href="javascript:history.back()" class="btn-back">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
        
        <div class="doc-content" id="markdown-content">
            <!-- El contenido se renderiza aquí con marked.js -->
        </div>
    </div>

    <script src="/assets/js/marked.min.js"></script>
    <script>
        const markdownContent = <?= json_encode($markdown_content) ?>;
        document.getElementById('markdown-content').innerHTML = marked.parse(markdownContent);
    </script>
</body>
</html>
