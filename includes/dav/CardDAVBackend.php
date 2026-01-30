<?php
namespace PIM\DAV;

use Sabre\CardDAV\Backend\AbstractBackend;
use Sabre\VObject;

class CardDAVBackend extends AbstractBackend {
    protected $pdo;
    
    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    public function getAddressBooksForUser($principalUri) {
        list($prefix, $username) = explode('/', $principalUri, 2);
        
        $stmt = $this->pdo->prepare('SELECT id FROM usuarios WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$user) {
            return [];
        }
        
        return [[
            'id' => $user['id'],
            'uri' => 'contacts',
            'principaluri' => $principalUri,
            '{DAV:}displayname' => 'Contacts',
            '{http://calendarserver.org/ns/}getctag' => time(),
            '{http://sabredav.org/ns}sync-token' => time(),
        ]];
    }
    
    public function updateAddressBook($addressBookId, \Sabre\DAV\PropPatch $propPatch) {
        return true;
    }
    
    public function createAddressBook($principalUri, $url, array $properties) {
        return false;
    }
    
    public function deleteAddressBook($addressBookId) {
        return false;
    }
    
    public function getCards($addressBookId) {
        $stmt = $this->pdo->prepare('
            SELECT id, nombre, email, telefono, empresa, cargo, direccion, notas,
                   UNIX_TIMESTAMP(fecha_modificacion) as lastmodified
            FROM contactos 
            WHERE usuario_id = ? AND papelera = 0
        ');
        $stmt->execute([$addressBookId]);
        
        $cards = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $vcf = $this->generateVCF($row);
            $cards[] = [
                'id' => $row['id'],
                'uri' => $row['id'] . '.vcf',
                'lastmodified' => $row['lastmodified'],
                'etag' => '"' . md5($row['lastmodified']) . '"',
                'size' => strlen($vcf),
                'carddata' => $vcf,
            ];
        }
        
        return $cards;
    }
    
    public function getCard($addressBookId, $cardUri) {
        $contactId = (int)str_replace('.vcf', '', $cardUri);
        
        $stmt = $this->pdo->prepare('
            SELECT id, nombre, email, telefono, empresa, cargo, direccion, notas,
                   UNIX_TIMESTAMP(fecha_modificacion) as lastmodified
            FROM contactos 
            WHERE id = ? AND usuario_id = ? AND papelera = 0
        ');
        $stmt->execute([$contactId, $addressBookId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$row) {
            return false;
        }
        
        $vcf = $this->generateVCF($row);
        
        return [
            'id' => $row['id'],
            'uri' => $cardUri,
            'lastmodified' => $row['lastmodified'],
            'etag' => '"' . md5($row['lastmodified']) . '"',
            'size' => strlen($vcf),
            'carddata' => $vcf,
        ];
    }
    
    public function getMultipleCards($addressBookId, array $uris) {
        $cards = [];
        foreach ($uris as $uri) {
            $card = $this->getCard($addressBookId, $uri);
            if ($card) {
                $cards[] = $card;
            }
        }
        return $cards;
    }
    
    public function createCard($addressBookId, $cardUri, $cardData) {
        $vcard = VObject\Reader::read($cardData);
        
        $nombre = isset($vcard->FN) ? (string)$vcard->FN : '';
        $email = isset($vcard->EMAIL) ? (string)$vcard->EMAIL : '';
        $telefono = isset($vcard->TEL) ? (string)$vcard->TEL : '';
        
        $empresa = '';
        $cargo = '';
        if (isset($vcard->ORG)) {
            $org = $vcard->ORG->getParts();
            $empresa = $org[0] ?? '';
            $cargo = $org[1] ?? '';
        }
        
        if (isset($vcard->TITLE)) {
            $cargo = (string)$vcard->TITLE;
        }
        
        $direccion = isset($vcard->ADR) ? (string)$vcard->ADR : '';
        $notas = isset($vcard->NOTE) ? (string)$vcard->NOTE : '';
        
        $stmt = $this->pdo->prepare('
            INSERT INTO contactos (usuario_id, nombre, email, telefono, empresa, cargo, direccion, notas)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $addressBookId, $nombre, $email, $telefono, $empresa, $cargo, $direccion, $notas
        ]);
        
        return '"' . md5(time()) . '"';
    }
    
    public function updateCard($addressBookId, $cardUri, $cardData) {
        $contactId = (int)str_replace('.vcf', '', $cardUri);
        
        $vcard = VObject\Reader::read($cardData);
        
        $nombre = isset($vcard->FN) ? (string)$vcard->FN : '';
        $email = isset($vcard->EMAIL) ? (string)$vcard->EMAIL : '';
        $telefono = isset($vcard->TEL) ? (string)$vcard->TEL : '';
        
        $empresa = '';
        $cargo = '';
        if (isset($vcard->ORG)) {
            $org = $vcard->ORG->getParts();
            $empresa = $org[0] ?? '';
            $cargo = $org[1] ?? '';
        }
        
        if (isset($vcard->TITLE)) {
            $cargo = (string)$vcard->TITLE;
        }
        
        $direccion = isset($vcard->ADR) ? (string)$vcard->ADR : '';
        $notas = isset($vcard->NOTE) ? (string)$vcard->NOTE : '';
        
        $stmt = $this->pdo->prepare('
            UPDATE contactos 
            SET nombre = ?, email = ?, telefono = ?, empresa = ?, cargo = ?, direccion = ?, notas = ?
            WHERE id = ? AND usuario_id = ?
        ');
        $stmt->execute([
            $nombre, $email, $telefono, $empresa, $cargo, $direccion, $notas,
            $contactId, $addressBookId
        ]);
        
        return '"' . md5(time()) . '"';
    }
    
    public function deleteCard($addressBookId, $cardUri) {
        $contactId = (int)str_replace('.vcf', '', $cardUri);
        
        $stmt = $this->pdo->prepare('UPDATE contactos SET papelera = 1 WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$contactId, $addressBookId]);
    }
    
    protected function generateVCF($contact) {
        $vcard = new VObject\Component\VCard();
        
        $vcard->add('FN', $contact['nombre']);
        $vcard->add('UID', 'pim-contact-' . $contact['id'] . '@pim.local');
        
        if (!empty($contact['email'])) {
            $vcard->add('EMAIL', $contact['email'], ['TYPE' => 'WORK']);
        }
        
        if (!empty($contact['telefono'])) {
            $vcard->add('TEL', $contact['telefono'], ['TYPE' => ['WORK', 'VOICE']]);
        }
        
        if (!empty($contact['empresa'])) {
            $vcard->add('ORG', [$contact['empresa'], $contact['cargo']]);
        }
        
        if (!empty($contact['cargo'])) {
            $vcard->add('TITLE', $contact['cargo']);
        }
        
        if (!empty($contact['direccion'])) {
            $vcard->add('ADR', ['', '', $contact['direccion'], '', '', '', ''], ['TYPE' => 'WORK']);
        }
        
        if (!empty($contact['notas'])) {
            $vcard->add('NOTE', $contact['notas']);
        }
        
        return $vcard->serialize();
    }
}
