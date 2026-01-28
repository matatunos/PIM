<?php
/**
 * Función para mostrar archivos vinculados a una entidad
 * @param $tipo: 'tarea', 'nota' o 'evento'
 * @param $id: ID de la entidad
 */
function mostrarArchivosVinculados($tipo, $id) {
    global $pdo;
    
    $tabla_relacion = 'archivo_' . $tipo;
    $campo_id = $tipo . '_id';
    
    $stmt = $pdo->prepare("SELECT a.* FROM archivos a 
                          INNER JOIN $tabla_relacion ar ON a.id = ar.archivo_id 
                          WHERE ar.$campo_id = ? ORDER BY a.nombre_original");
    $stmt->execute([$id]);
    $archivos = $stmt->fetchAll();
    
    if (empty($archivos)) {
        return '';
    }
    
    $html = '<div style="margin-top: var(--spacing-lg); padding-top: var(--spacing-lg); border-top: 1px solid var(--gray-200);">
        <h4 style="margin-bottom: var(--spacing-md); display: flex; align-items: center; gap: var(--spacing-sm);">
            <i class="fas fa-paperclip"></i>
            Archivos adjuntos (' . count($archivos) . ')
        </h4>
        <div style="display: flex; gap: var(--spacing-sm); flex-wrap: wrap;">';
    
    foreach ($archivos as $archivo) {
        $icon = 'fa-file';
        if (strpos($archivo['tipo_mime'], 'image/') === 0) $icon = 'fa-image';
        elseif (strpos($archivo['tipo_mime'], 'video/') === 0) $icon = 'fa-video';
        elseif (strpos($archivo['tipo_mime'], 'audio/') === 0) $icon = 'fa-music';
        
        $html .= '<a href="/app/archivos/index.php?download=' . $archivo['id'] . '" 
                 class="btn btn-sm" style="display: flex; align-items: center; gap: var(--spacing-xs);">
            <i class="fas ' . $icon . '"></i>
            ' . htmlspecialchars(strlen($archivo['nombre_original']) > 20 ? substr($archivo['nombre_original'], 0, 17) . '...' : $archivo['nombre_original']) . '
        </a>';
    }
    
    $html .= '</div></div>';
    
    return $html;
}

/**
 * Función para mostrar el modal de seleccionar archivos
 * @param $tipo: 'tarea', 'nota' o 'evento'
 * @param $id: ID de la entidad
 */
function mostrarModalArchivos($tipo, $id) {
    $modalId = 'modalArchivos' . ucfirst($tipo) . $id;
    
    return '
    <div id="' . $modalId . '" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <h3 style="margin-bottom: var(--spacing-lg);">
                <i class="fas fa-link"></i>
                Vincular Archivos
            </h3>
            
            <div id="lista-archivos-' . $tipo . '-' . $id . '" style="max-height: 400px; overflow-y: auto; border: 1px solid var(--gray-200); border-radius: var(--radius-md); padding: var(--spacing-md);">
                <p style="text-align: center; color: var(--text-muted);">Cargando archivos...</p>
            </div>
            
            <div style="display: flex; gap: var(--spacing-md); margin-top: var(--spacing-lg);">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById(\'' . $modalId . '\').classList.remove(\'active\')" style="flex: 1;">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
    
    <script>
        document.getElementById(\'' . $modalId . '\').addEventListener(\'click\', function(e) {
            if (e.target === this) this.classList.remove(\'active\');
        });
    </script>';
}
?>
