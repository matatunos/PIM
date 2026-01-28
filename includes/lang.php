<?php
$lang = 'es';
if (isset($_GET['lang']) && in_array($_GET['lang'], ['es','en'])) {
    $lang = $_GET['lang'];
}
$traducciones = include __DIR__ . '/../app/idiomas/' . $lang . '.php';
function t($key) {
    global $traducciones;
    return $traducciones[$key] ?? $key;
}
