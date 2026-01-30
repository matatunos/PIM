<?php
/**
 * Servidor CalDAV/CardDAV para PIM
 * 
 * Endpoints:
 * - /dav/calendars/{username}/default/
 * - /dav/addressbooks/{username}/contacts/
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/dav/AuthBackend.php';
require_once __DIR__ . '/../includes/dav/PrincipalBackend.php';
require_once __DIR__ . '/../includes/dav/CalendarBackend.php';
require_once __DIR__ . '/../includes/dav/CardDAVBackend.php';

use Sabre\DAV;
use Sabre\CalDAV;
use Sabre\CardDAV;
use Sabre\DAVACL;

// Crear backend de autenticación personalizado
$authBackend = new PIM\DAV\Auth\Backend($pdo);

// Crear backend de principal (usuarios)
$principalBackend = new PIM\DAV\PrincipalBackend($pdo);

// Crear backend de calendario
$calendarBackend = new PIM\DAV\CalendarBackend($pdo);

// Crear backend de libreta de direcciones
$carddavBackend = new PIM\DAV\CardDAVBackend($pdo);

// Construir árbol de directorios
$tree = [
    new DAVACL\PrincipalCollection($principalBackend),
    new CalDAV\CalendarRoot($principalBackend, $calendarBackend),
    new CardDAV\AddressBookRoot($principalBackend, $carddavBackend),
];

// Configurar servidor
$server = new DAV\Server($tree);
$server->setBaseUri('/dav/');

// Plugins
$server->addPlugin(new DAV\Auth\Plugin($authBackend));
$server->addPlugin(new DAVACL\Plugin());
$server->addPlugin(new CalDAV\Plugin());
$server->addPlugin(new CardDAV\Plugin());
$server->addPlugin(new DAV\Browser\Plugin());
$server->addPlugin(new CalDAV\Schedule\Plugin());
$server->addPlugin(new DAV\Sync\Plugin());

// Procesar solicitud
$server->exec();
