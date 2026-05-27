<?php
define("SERVER",  $_ENV['SERVER']);
define("DBASE",   $_ENV['DATABASE']);
define("USER",  $_ENV['USER']);
define("PWORD",   $_ENV['PWORD']);
define("CHARSET", $_ENV['CHARSET']);

class Connection {

    public function connect(): PDO {
        $dsn = "mysql:host=" . $_ENV['SERVER'] . ";dbname=" . $_ENV['DATABASE'] . ";charset=" . $_ENV['CHARSET'];
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            return new PDO($dsn, $_ENV['USER'], $_ENV['PWORD'], $options);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()]);
            exit;
        }
    }
}