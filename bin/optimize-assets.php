#!/usr/bin/env php
<?php
/**
 * Minificador de assets (CSS/JS)
 * Combina y minifica archivos para mejorar rendimiento
 */

class AssetOptimizer {
    private $baseDir;
    private $cssFiles = [];
    private $jsFiles = [];
    private $outputDir;
    
    public function __construct($baseDir) {
        $this->baseDir = rtrim($baseDir, '/');
        $this->outputDir = $this->baseDir . '/assets/dist';
        
        // Definir archivos CSS
        $this->cssFiles = [
            $this->baseDir . '/assets/css/bootstrap.min.css',
            $this->baseDir . '/assets/css/styles.css',
            $this->baseDir . '/assets/css/2fa-fix.css'
        ];
        
        // Definir archivos JS
        $this->jsFiles = [
            $this->baseDir . '/assets/js/ajax-nav.js',
            $this->baseDir . '/assets/js/hamburger.js',
            $this->baseDir . '/assets/js/notifications.js',
            $this->baseDir . '/assets/js/marked.min.js'
        ];
    }
    
    /**
     * Ejecutar optimización
     */
    public function optimize() {
        echo "🚀 Optimizando assets...\n\n";
        
        // Crear directorio de salida
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
        
        // Optimizar CSS
        $this->optimizeCSS();
        
        // Optimizar JS
        $this->optimizeJS();
        
        // Generar manifiesto
        $this->generateManifest();
        
        echo "\n✅ Optimización completada\n";
        echo "   Archivos generados en: assets/dist/\n";
    }
    
    /**
     * Optimizar archivos CSS
     */
    private function optimizeCSS() {
        echo "📦 Minificando CSS...\n";
        
        $combined = '';
        $totalSize = 0;
        
        foreach ($this->cssFiles as $file) {
            if (!file_exists($file)) {
                echo "   ⚠️  No encontrado: " . basename($file) . "\n";
                continue;
            }
            
            $size = filesize($file);
            $totalSize += $size;
            $content = file_get_contents($file);
            $combined .= "/* " . basename($file) . " */\n" . $content . "\n\n";
            
            echo "   ✓ " . basename($file) . " (" . $this->formatBytes($size) . ")\n";
        }
        
        // Minificar
        $minified = $this->minifyCSS($combined);
        $minifiedSize = strlen($minified);
        
        // Guardar
        $output = $this->outputDir . '/app.min.css';
        file_put_contents($output, $minified);
        
        $reduction = round((1 - $minifiedSize / $totalSize) * 100, 2);
        echo "   📊 Original: " . $this->formatBytes($totalSize) . "\n";
        echo "   📊 Minificado: " . $this->formatBytes($minifiedSize) . " (-{$reduction}%)\n\n";
    }
    
    /**
     * Optimizar archivos JS
     */
    private function optimizeJS() {
        echo "📦 Minificando JS...\n";
        
        $combined = '';
        $totalSize = 0;
        
        foreach ($this->jsFiles as $file) {
            if (!file_exists($file)) {
                echo "   ⚠️  No encontrado: " . basename($file) . "\n";
                continue;
            }
            
            $size = filesize($file);
            $totalSize += $size;
            $content = file_get_contents($file);
            $combined .= "/* " . basename($file) . " */\n" . $content . "\n\n";
            
            echo "   ✓ " . basename($file) . " (" . $this->formatBytes($size) . ")\n";
        }
        
        // Minificar
        $minified = $this->minifyJS($combined);
        $minifiedSize = strlen($minified);
        
        // Guardar
        $output = $this->outputDir . '/app.min.js';
        file_put_contents($output, $minified);
        
        $reduction = round((1 - $minifiedSize / $totalSize) * 100, 2);
        echo "   📊 Original: " . $this->formatBytes($totalSize) . "\n";
        echo "   📊 Minificado: " . $this->formatBytes($minifiedSize) . " (-{$reduction}%)\n\n";
    }
    
    /**
     * Minificar CSS
     */
    private function minifyCSS($css) {
        // Eliminar comentarios
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Eliminar espacios en blanco
        $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/\s*([:;,{}])\s*/', '$1', $css);
        
        return trim($css);
    }
    
    /**
     * Minificar JS (simple)
     */
    private function minifyJS($js) {
        // Eliminar comentarios de línea
        $js = preg_replace('/\/\/[^\n]*/', '', $js);
        
        // Eliminar comentarios de bloque
        $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);
        
        // Eliminar espacios extra
        $js = preg_replace('/\s+/', ' ', $js);
        
        // Eliminar espacios alrededor de operadores
        $js = preg_replace('/\s*([=+\-*\/<>!&|(){}\[\];,:])\s*/', '$1', $js);
        
        return trim($js);
    }
    
    /**
     * Generar manifiesto con hashes
     */
    private function generateManifest() {
        echo "📝 Generando manifiesto...\n";
        
        $manifest = [
            'generated' => date('Y-m-d H:i:s'),
            'files' => []
        ];
        
        $files = [
            'app.min.css' => $this->outputDir . '/app.min.css',
            'app.min.js' => $this->outputDir . '/app.min.js'
        ];
        
        foreach ($files as $name => $path) {
            if (file_exists($path)) {
                $hash = md5_file($path);
                $size = filesize($path);
                
                $manifest['files'][$name] = [
                    'hash' => $hash,
                    'size' => $size,
                    'url' => 'assets/dist/' . $name . '?v=' . substr($hash, 0, 8)
                ];
                
                echo "   ✓ $name (v" . substr($hash, 0, 8) . ")\n";
            }
        }
        
        $manifestFile = $this->outputDir . '/manifest.json';
        file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT));
    }
    
    /**
     * Formatear bytes
     */
    private function formatBytes($bytes) {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}

// Ejecutar
if (php_sapi_name() === 'cli') {
    $baseDir = dirname(__DIR__);
    $optimizer = new AssetOptimizer($baseDir);
    $optimizer->optimize();
} else {
    die('Este script debe ejecutarse desde CLI');
}
