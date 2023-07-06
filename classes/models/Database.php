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
        // Load the database configuration from db config
        global $baseClassDir;
        global $baseConfigDir;

        $config = parse_ini_file($baseConfigDir.'db_config.ini');
        //base config
        $this->host = $config['host'];
        $this->user = $config['user'];
        $this->pass = $config['pass'];
        $this->dbname = $config['dbname'];
        //table names
        $this->au_ideas = $config['au_ideas'];
        $this->au_groups = $config['au_groups'];
        $this->au_rooms = $config['au_rooms'];
        $this->au_votes = $config['au_votes'];
        $this->au_likes = $config['au_likes'];
        $this->au_topics = $config['au_topics'];
        $this->au_consent = $config['au_consent'];
        $this->au_services = $config['au_services'];
        $this->au_delegation = $config['au_delegation'];
        $this->au_media = $config['au_media'];
        $this->au_messages = $config['au_messages'];
        $this->au_comments = $config['au_comments'];
        $this->au_commands = $config['au_commands'];
        $this->au_commands = $config['au_systemlog'];
        $this->au_texts = $config['au_texts'];
        $this->au_reported = $config['au_reported'];
        $this->au_categories = $config['au_categories'];
        $this->au_activitylog = $config['au_activitylog'];
        $this->au_users_basedata = $config['au_users_basedata'];

        $this->au_rel_user_user = $config['au_rel_user_user'];
        $this->au_rel_rooms_users = $config['au_rel_rooms_users'];
        $this->au_rel_topics_ideas = $config['au_rel_topics_ideas'];
        $this->au_rel_topics_media = $config['au_rel_topics_media'];
        $this->au_rel_groups_users = $config['au_rel_groups_users'];
        $this->au_rel_categories_ideas = $config['au_rel_categories_ideas'];
        $this->au_rel_users_messages = $config['au_rel_users_messages'];
        $this->au_rel_users_services = $config['au_rel_users_services'];






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
