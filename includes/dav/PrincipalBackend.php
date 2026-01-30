<?php
namespace PIM\DAV;

use Sabre\DAVACL\PrincipalBackend\AbstractBackend;

class PrincipalBackend extends AbstractBackend {
    protected $pdo;
    
    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    public function getPrincipalsByPrefix($prefixPath) {
        $principals = [];
        
        if ($prefixPath !== 'principals') {
            return $principals;
        }
        
        $stmt = $this->pdo->query('SELECT id, username, email FROM usuarios WHERE activo = 1');
        
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $principals[] = [
                'uri' => 'principals/' . $row['username'],
                '{DAV:}displayname' => $row['username'],
                '{http://sabredav.org/ns}email-address' => $row['email'],
            ];
        }
        
        return $principals;
    }
    
    public function getPrincipalByPath($path) {
        list($prefix, $username) = explode('/', $path, 2);
        
        if ($prefix !== 'principals') {
            return null;
        }
        
        $stmt = $this->pdo->prepare('SELECT id, username, email FROM usuarios WHERE username = ? AND activo = 1');
        $stmt->execute([$username]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$row) {
            return null;
        }
        
        return [
            'uri' => 'principals/' . $row['username'],
            '{DAV:}displayname' => $row['username'],
            '{http://sabredav.org/ns}email-address' => $row['email'],
        ];
    }
    
    public function updatePrincipal($path, $mutations) {
        return true;
    }
    
    public function searchPrincipals($prefixPath, array $searchProperties, $test = 'allof') {
        return [];
    }
    
    public function getGroupMemberSet($principal) {
        return [];
    }
    
    public function getGroupMembership($principal) {
        return [];
    }
    
    public function setGroupMemberSet($principal, array $members) {
        throw new \Sabre\DAV\Exception('Setting group members is not supported');
    }
}
