<?php
namespace PIM\DAV;

use Sabre\CalDAV\Backend\AbstractBackend;
use Sabre\CalDAV\Plugin;
use Sabre\VObject;

class CalendarBackend extends AbstractBackend {
    protected $pdo;
    
    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    public function getCalendarsForUser($principalUri) {
        list($prefix, $username) = explode('/', $principalUri, 2);
        
        $stmt = $this->pdo->prepare('SELECT id FROM usuarios WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$user) {
            return [];
        }
        
        return [[
            'id' => [$user['id'], 'default'],
            'uri' => 'default',
            'principaluri' => $principalUri,
            '{DAV:}displayname' => 'Default Calendar',
            '{http://apple.com/ns/ical/}calendar-color' => '#a8dadc',
            '{http://calendarserver.org/ns/}getctag' => time(),
            '{http://sabredav.org/ns}sync-token' => time(),
            'supported-calendar-component-set' => new Plugin\SupportedCalendarComponentSet(['VEVENT']),
        ]];
    }
    
    public function createCalendar($principalUri, $calendarUri, array $properties) {
        return false;
    }
    
    public function updateCalendar($calendarId, \Sabre\DAV\PropPatch $propPatch) {
        return true;
    }
    
    public function deleteCalendar($calendarId) {
        return false;
    }
    
    public function getCalendarObjects($calendarId) {
        list($userId, $calName) = $calendarId;
        
        $stmt = $this->pdo->prepare('
            SELECT id, titulo, descripcion, fecha_inicio, fecha_fin, 
                   hora_inicio, hora_fin, ubicacion, todo_el_dia,
                   UNIX_TIMESTAMP(fecha_modificacion) as lastmodified
            FROM eventos 
            WHERE usuario_id = ? AND papelera = 0
        ');
        $stmt->execute([$userId]);
        
        $objects = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $objects[] = [
                'id' => $row['id'],
                'uri' => $row['id'] . '.ics',
                'lastmodified' => $row['lastmodified'],
                'etag' => '"' . md5($row['lastmodified']) . '"',
                'calendarid' => $calendarId,
                'size' => strlen($this->generateICS($row)),
                'component' => 'VEVENT',
            ];
        }
        
        return $objects;
    }
    
    public function getCalendarObject($calendarId, $objectUri) {
        list($userId, $calName) = $calendarId;
        $eventId = (int)str_replace('.ics', '', $objectUri);
        
        $stmt = $this->pdo->prepare('
            SELECT id, titulo, descripcion, fecha_inicio, fecha_fin, 
                   hora_inicio, hora_fin, ubicacion, todo_el_dia,
                   UNIX_TIMESTAMP(fecha_modificacion) as lastmodified
            FROM eventos 
            WHERE id = ? AND usuario_id = ? AND papelera = 0
        ');
        $stmt->execute([$eventId, $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$row) {
            return null;
        }
        
        $ics = $this->generateICS($row);
        
        return [
            'id' => $row['id'],
            'uri' => $objectUri,
            'lastmodified' => $row['lastmodified'],
            'etag' => '"' . md5($row['lastmodified']) . '"',
            'calendarid' => $calendarId,
            'size' => strlen($ics),
            'calendardata' => $ics,
        ];
    }
    
    public function getMultipleCalendarObjects($calendarId, array $uris) {
        $objects = [];
        foreach ($uris as $uri) {
            $obj = $this->getCalendarObject($calendarId, $uri);
            if ($obj) {
                $objects[] = $obj;
            }
        }
        return $objects;
    }
    
    public function createCalendarObject($calendarId, $objectUri, $calendarData) {
        list($userId, $calName) = $calendarId;
        
        $vcal = VObject\Reader::read($calendarData);
        $vevent = $vcal->VEVENT;
        
        if (!$vevent) {
            return false;
        }
        
        $titulo = (string)$vevent->SUMMARY;
        $descripcion = isset($vevent->DESCRIPTION) ? (string)$vevent->DESCRIPTION : '';
        $ubicacion = isset($vevent->LOCATION) ? (string)$vevent->LOCATION : '';
        
        $dtstart = $vevent->DTSTART->getDateTime();
        $dtend = isset($vevent->DTEND) ? $vevent->DTEND->getDateTime() : $dtstart;
        
        $todo_el_dia = !$vevent->DTSTART->hasTime() ? 1 : 0;
        
        $fecha_inicio = $dtstart->format('Y-m-d');
        $fecha_fin = $dtend->format('Y-m-d');
        $hora_inicio = $todo_el_dia ? null : $dtstart->format('H:i:s');
        $hora_fin = $todo_el_dia ? null : $dtend->format('H:i:s');
        
        $stmt = $this->pdo->prepare('
            INSERT INTO eventos (usuario_id, titulo, descripcion, fecha_inicio, fecha_fin, 
                                 hora_inicio, hora_fin, ubicacion, todo_el_dia, color)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $userId, $titulo, $descripcion, $fecha_inicio, $fecha_fin,
            $hora_inicio, $hora_fin, $ubicacion, $todo_el_dia, '#a8dadc'
        ]);
        
        return '"' . md5(time()) . '"';
    }
    
    public function updateCalendarObject($calendarId, $objectUri, $calendarData) {
        list($userId, $calName) = $calendarId;
        $eventId = (int)str_replace('.ics', '', $objectUri);
        
        $vcal = VObject\Reader::read($calendarData);
        $vevent = $vcal->VEVENT;
        
        if (!$vevent) {
            return false;
        }
        
        $titulo = (string)$vevent->SUMMARY;
        $descripcion = isset($vevent->DESCRIPTION) ? (string)$vevent->DESCRIPTION : '';
        $ubicacion = isset($vevent->LOCATION) ? (string)$vevent->LOCATION : '';
        
        $dtstart = $vevent->DTSTART->getDateTime();
        $dtend = isset($vevent->DTEND) ? $vevent->DTEND->getDateTime() : $dtstart;
        
        $todo_el_dia = !$vevent->DTSTART->hasTime() ? 1 : 0;
        
        $fecha_inicio = $dtstart->format('Y-m-d');
        $fecha_fin = $dtend->format('Y-m-d');
        $hora_inicio = $todo_el_dia ? null : $dtstart->format('H:i:s');
        $hora_fin = $todo_el_dia ? null : $dtend->format('H:i:s');
        
        $stmt = $this->pdo->prepare('
            UPDATE eventos 
            SET titulo = ?, descripcion = ?, fecha_inicio = ?, fecha_fin = ?,
                hora_inicio = ?, hora_fin = ?, ubicacion = ?, todo_el_dia = ?
            WHERE id = ? AND usuario_id = ?
        ');
        $stmt->execute([
            $titulo, $descripcion, $fecha_inicio, $fecha_fin,
            $hora_inicio, $hora_fin, $ubicacion, $todo_el_dia, $eventId, $userId
        ]);
        
        return '"' . md5(time()) . '"';
    }
    
    public function deleteCalendarObject($calendarId, $objectUri) {
        list($userId, $calName) = $calendarId;
        $eventId = (int)str_replace('.ics', '', $objectUri);
        
        $stmt = $this->pdo->prepare('UPDATE eventos SET papelera = 1 WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$eventId, $userId]);
    }
    
    protected function generateICS($event) {
        $vcalendar = new VObject\Component\VCalendar();
        
        $vevent = $vcalendar->add('VEVENT', [
            'SUMMARY' => $event['titulo'],
            'UID' => 'pim-event-' . $event['id'] . '@pim.local',
        ]);
        
        if (!empty($event['descripcion'])) {
            $vevent->add('DESCRIPTION', $event['descripcion']);
        }
        
        if (!empty($event['ubicacion'])) {
            $vevent->add('LOCATION', $event['ubicacion']);
        }
        
        if ($event['todo_el_dia']) {
            $vevent->add('DTSTART', $event['fecha_inicio'], ['VALUE' => 'DATE']);
            $vevent->add('DTEND', $event['fecha_fin'], ['VALUE' => 'DATE']);
        } else {
            $dtstart = $event['fecha_inicio'] . ' ' . ($event['hora_inicio'] ?: '00:00:00');
            $dtend = $event['fecha_fin'] . ' ' . ($event['hora_fin'] ?: '23:59:59');
            $vevent->add('DTSTART', new \DateTime($dtstart));
            $vevent->add('DTEND', new \DateTime($dtend));
        }
        
        return $vcalendar->serialize();
    }
}
