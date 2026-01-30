<?php
namespace PIM\DAV\Auth;

use Sabre\DAV\Auth\Backend\AbstractBasic;

class Backend extends AbstractBasic {
    protected $pdo;
    
    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
        $this->realm = 'PIM CalDAV/CardDAV';
    }
    
    protected function validateUserPass($username, $password) {
        $stmt = $this->pdo->prepare('SELECT id, password FROM usuarios WHERE username = ? AND activo = 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            return true;
        }
        
        return false;
    }
}
