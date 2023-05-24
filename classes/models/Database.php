<?php
require_once ("./base_config.php");

    class Database {

    private $host;
    private $user;
    private $pass;
    private $dbname;
    private $dbh;
    private $error;
    private $stmt;

    public function __construct() {
        // Load the database configuration from a file
        global $baseClassDir;
        global $baseConfigDir;

        $config = parse_ini_file($baseConfigDir.'db_config.ini');
        //$file = file_get_contents($baseConfigDir.'db_config.ini', true);
        //print_r ($config);
        //echo ("file: ".$file);
        $this->host = $config['host'];
        $this->user = $config['user'];
        $this->pass = $config['pass'];
        $this->dbname = $config['dbname'];
        //echo ($baseConfigDir."Setting up database...".$config['host']);

        // Set up a PDO connection
        $dsn = "mysql:host=$this->host;dbname=$this->dbname";
        $options = array(
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        );
        try {
            $this->dbh = new PDO($dsn, $this->user, $this->pass, $options);
        } catch(PDOException $e) {
            $this->error = $e->getMessage();
            echo ("<br><br>ERROR occured: ".$e->getMessage());
        }
    }

    public function query($query) {
        //echo ("<br><br>QUERY:".$query);
        $this->stmt = $this->dbh->prepare($query);
        return $this->stmt;
    }

    public function bind($param, $value, $type = null) {
        if (is_null($type)) {
            switch (true) {
                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = PDO::PARAM_NULL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
            }
        }
        $this->stmt->bindValue($param, $value, $type);
    }

    public function execute() {
        return $this->stmt->execute();
    }

    public function resultSet() {
        $this->execute();
        return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function single() {
        $this->execute();
        return $this->stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function rowCount() {
        return $this->stmt->rowCount();
    }

    public function lastInsertId() {
        return $this->dbh->lastInsertId();
    }

    public function beginTransaction() {
        return $this->dbh->beginTransaction();
    }

    public function endTransaction() {
        return $this->dbh->commit();
    }

    public function cancelTransaction() {
        return $this->dbh->rollBack();
    }

    public function debugDumpParams() {
        return $this->stmt->debugDumpParams();
    }
}
?>
