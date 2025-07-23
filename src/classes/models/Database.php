<?php
    require_once(__DIR__ . '/../../../config/instances_config.php');
    # db provides helper functions and db connection for the system
    class Database {

    private $host;
    private $user;
    private $pass;
    private $dbname;
    private $dbh;
    private $error;
    private $stmt;
    public $code;

    public string $au_ideas = 'au_ideas';
    public string $au_groups = 'au_groups';
    public string $au_rooms = 'au_rooms';
    public string $au_votes = 'au_votes';
    public string $au_likes = 'au_likes';
    public string $au_topics = 'au_topics';
    public string $au_consent = 'au_consent';
    public string $au_services = 'au_services';
    public string $au_delegation = 'au_delegation';
    public string $au_media = 'au_media';
    public string $au_messages = 'au_messages';
    public string $au_comments = 'au_comments';
    public string $au_commands = 'au_commands';
    public string $au_systemlog = 'au_systemlog';
    public string $au_texts = 'au_texts';
    public string $au_reported = 'au_reported';
    public string $au_categories = 'au_categories';
    public string $au_activitylog = 'au_activitylog';
    public string $au_users_basedata = 'au_users_basedata';
    public string $au_users_settings = 'au_users_settings';
    public string $au_user_levels = 'au_user_levels';
    public string $au_system_current_state = 'au_system_current_state';
    public string $au_system_global_config = 'au_system_global_config';
    public string $au_phases_global_config = 'au_phases_global_config';
    public string $au_rel_user_user = 'au_rel_user_user';
    public string $au_rel_rooms_users = 'au_rel_rooms_users';
    public string $au_rel_topics_ideas = 'au_rel_topics_ideas';
    public string $au_rel_topics_media = 'au_rel_topics_media';
    public string $au_rel_groups_users = 'au_rel_groups_users';
    public string $au_rel_categories_ideas = 'au_rel_categories_ideas';
    public string $au_rel_categories_rooms = 'au_rel_categories_rooms';
    public string $au_rel_users_messages = 'au_rel_users_messages';
    public string $au_rel_users_services = 'au_rel_users_services';
    public string $au_userlevel_methods = 'au_userlevel_methods';

    public function __construct($code) {
        // Load the database configuration from db config
        global $instances;

        //base config
        $this->host = $instances[$code]['host'];
        $this->user = $instances[$code]['user'];
        $this->pass = $instances[$code]['pass'];
        $this->dbname = $instances[$code]['dbname'];
        $this->code = $code;

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
            //echo ("ERROR occured: ".$e->getMessage());
        }
        
    }

    public function query($query) {
        # prepares a statement 
        $this->stmt = $this->dbh->prepare($query);
        return $this->stmt;
    }

    public function bind($param, $value, $type = null) {
        if (str_starts_with($param, ':')) {
            # provides binding functionality
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
    }

    public function bindAll($keyvalues) {
        foreach ($keyvalues as $key => $value) {
            $this->bind($key, $value);
        }
    }

    public function execute() {
        # executes a query
        return $this->stmt->execute();
    }

    public function resultSet() {
        # returns a result set 
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

    public function getHost() {
        return $this->host;
    }

    public function getUser() {
        return $this->user;
    }

    public function getPass() {
        return $this->pass;
    }

    public function getDbname() {
        return $this->dbname;
    }
}
