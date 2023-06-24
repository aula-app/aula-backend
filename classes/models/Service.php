<?php
// only process script if variable $allowed_include is set to 1, otherwise exit
// this prevents direct call of this script

if ($allowed_include==1){

}else {
  exit;
}



class Service {

    private $db;

    public function __construct($db, $crypt, $syslog) {
        // db = database class, crypt = crypt class
        $this->db = $db;
        $this->crypt = $crypt;
        //$this->syslog = new Systemlog ($db);
        $this->syslog = $syslog;
        $this->converters = new Converters ($db);
    }// end function

    protected function buildCacheHash ($key) {
        return md5 ($key);
      }


    public function getServiceHashId($service_id) {
      /* returns hash_id of a service for a integer id
      */
      $service_id = $this->converters->checkServiceId($service_id); // checks id and converts id to db id if necessary (when hash id was passed)

      $stmt = $this->db->query('SELECT hash_id FROM '.$this->db->au_media.' WHERE id = :id');
      $this->db->bind(':id', $service_id); // bind service id
      $services = $this->db->resultSet();
      if (count($services)<1){
        return "0,0"; // nothing found, return 0 code
      }else {
        return "1,".$services[0]['hash_id']; // return hash id
      }
    }// end function

    public function getServiceStatus ($service_id) {
      /* returns the consent status for this text for a specific user
      */
      $service_id = $this->converters->checkServiceId($service_id); // checks id and converts id to db id if necessary (when hash id was passed)

      $stmt = $this->db->query('SELECT status FROM '.$this->db->au_services.' WHERE id = :service_id');
      $this->db->bind(':service_id', $service_id); // bind media id

      $services = $this->db->resultSet();
      if (count($services)<1){
        // no consent found
        $returnvalue['success'] = true; // set return value to false
        $returnvalue['error_code'] = 0; // no error code
        $returnvalue ['data'] = 0; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      }else {
        // consent found
        $returnvalue['success'] = true; // set return value to false
        $returnvalue['error_code'] = 0; // no error code
        $returnvalue ['data'] = $services [0]['status']; // returned data
        $returnvalue ['count'] = 1; // returned count of datasets

        return $returnvalue;
      }
    }// end function

    public function archiveService ($service_id, $updater_id=0){
      /* sets the status of a service to 4 = archived
      accepts db id and hash id
      updater_id is the id of the user that did the update
      */
      $service_id = $this->converters->checkServiceId($service_id); // checks id and converts id to db id if necessary (when hash id was passed)

      return $this->setServiceStatus($service_id, 4, $updater_id);

    }

    public function activateService ($service_id, $updater_id=0){
      /* sets the status of a service to 1 = active
      accepts db id and hash id
      updater_id is the id of the user that did the update
      */
      $service_id = $this->converters->checkServiceId($service_id); // checks id and converts id to db id if necessary (when hash id was passed)

      return $this->setServiceStatus($service_id, 1, $updater_id);

    }

    public function deactivateService ($service_id, $updater_id){
      /* sets the status of a service to 0 = inactive
      accepts db id and hash id
      updater_id is the id of the user that did the update
      */
      $service_id = $this->converters->checkServiceId($service_id); // checks id and converts id to db id if necessary (when hash id was passed)

      return $this->setServiceStatus($service_id, 0, $updater_id);
    }

    public function setServiceToReview ($service_id, $updater_id){
      /* sets the status of a service to 5 = to review
      accepts db id and hash id
      updater_id is the id of the user that did the update
      */
      $service_id = $this->converters->checkServiceId($service_id); // checks id and converts id to db id if necessary (when hash id was passed)

      return $this->setServiceStatus($service_id, 5, $updater_id);

    }

    public function getServiceBaseData ($service_id) {
      /* returns media base data for a specified db id */
      $service_id = $this->converters->checkServiceId($service_id); // checks id and converts id to db id if necessary (when hash id was passed)

      $stmt = $this->db->query('SELECT * FROM '.$this->db->au_services.' WHERE id = :id');
      $this->db->bind(':id', $service_id); // bind service id
      $services = $this->db->resultSet();
      if (count($services)<1){
        $returnvalue['success'] = false; // set return value to false
        $returnvalue['error_code'] = 2; //  error code
        $returnvalue ['data'] = 1; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue; // nothing found, return 0 code
      }else {
        $returnvalue['success'] = true; // set return value to false
        $returnvalue['error_code'] = 0; // no error code
        $returnvalue ['data'] = $services[0]; // returned data
        $returnvalue ['count'] = 1; // returned count of datasets

        return $returnvalue; // return an array (associative) with all the data
      }
    }// end function


    public function searchInServices ($searchstring, $status=1){
      // searches for a term / string in texts and returns all texts
      $extra_where = " AND (name LIKE '%".searchstring."%' OR description_public LIKE '%".searchstring."%' OR description_internal LIKE '%".searchstring."%') ";
      $ret_value = getServices (0, 0, 3, 0, $status, $extra_where);

      return $ret_value;
    }

    public function getServices ($offset=0, $limit=0, $orderby=3, $asc=0, $status=1, $extra_where="", $last_update=0) {
      /* returns services list (associative array) with start and limit provided
      if start and limit are set to 0, then the whole list is read (without limit)
      orderby is the field (int, see switch), defaults to last_update (3)
      asc (smallint), is either ascending (1) or descending (0), defaults to descending
      status (int) 0=inactive, 1=active, 2=suspended, 3=archived, 5= in review defaults to active (1)
      last_update = date that specifies texts younger than last_update date (if set to 0, gets all texts)
      extra_where = extra parameters for where clause, synthax " AND XY=4"
      */

      // init return array
      $returnvalue ['success'] = false; // success (true) or failure (false)
      $returnvalue ['errorcode'] = 0; // error code
      $returnvalue ['data'] = false; // the actual data
      $returnvalue ['count_data'] = 0; // number of datasets

      $date_now = date('Y-m-d H:i:s');
      // init vars
      $orderby_field="";
      $asc_field ="";

      $limit_string=" LIMIT :offset , :limit ";
      $limit_active=true;

      // check if offset an limit are both set to 0, then show whole list (exclude limit clause)
      if ($offset==0 && $limit==0){
        $limit_string="";
        $limit_active=false;
      }

      if ($updater_id > 0){
        // if a creator id is set then add to where clause
        $extra_where.= " AND updater_id = ".$updater_id; // get specific texts for a updloader
      }

      if (!(intval ($last_update)==0)){
        // if a publish date is set then add to where clause
        $extra_where.= " AND last_update > \'".$last_update."\'";
      }

      switch (intval ($orderby)){
        case 0:
        $orderby_field = "status";
        break;
        case 1:
        $orderby_field = "name";
        break;
        case 2:
        $orderby_field = "created";
        break;
        case 3:
        $orderby_field = "last_update";
        break;
        case 4:
        $orderby_field = "id";
        break;


        default:
        $orderby_field = "last_update";
      }

      switch (intval ($asc)){
        case 0:
        $asc_field = "DESC";
        break;
        case 1:
        $asc_field = "ASC";
        break;
        default:
        $asc_field = "DESC";
      }
      $count_datasets = 0; // number of datasets retrieved
      $stmt = $this->db->query('SELECT * FROM '.$this->db->au_services.' WHERE status= :status '.$extra_where.' ORDER BY '.$orderby_field.' '.$asc_field.' '.$limit_string);
      if ($limit){
        // only bind if limit is set
        $this->db->bind(':offset', $offset); // bind limit
        $this->db->bind(':limit', $limit); // bind limit
      }
      $this->db->bind(':status', $status); // bind status

      $err=false;
      try {
        $services = $this->db->resultSet();


      } catch (Exception $e) {
          $err=true;
          $returnvalue['success'] = false; // set return value to false
          $returnvalue['error_code'] = 1; // database error while executing query
          $returnvalue ['data'] = false; // returned data is false
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
      }
      $count_datasets = count ($services);

      if ($count_datasets<1){
        $returnvalue['success'] = false; // set success value
        $returnvalue['error_code'] = 2; // no data found
        $returnvalue ['data'] = false; // returned data is false
        $returnvalue ['count'] = $count_datasets; // returned count of datasets

        return $returnvalue; // nothing found, return 0 code
      }else {
        $returnvalue['success'] = true; // set return value to false
        $returnvalue['error_code'] = 0; // no error code
        $returnvalue ['data'] = $services; // returned data
        $returnvalue ['count'] = $count_datasets; // returned count of datasets

        return $returnvalue; // return an array (associative) with all the data
      }
    }// end function

    private function makeBool ($val){
      // helper function that converts ints to bool ints, sanitizes values
      $val = intval ($val);
      if ($val > 1) {
        $val = 1;
      }
      if ($val < 1) {
        $val = 0;
      }
      return $val;
    }

    public function addService ($name, $url, $return_url, $type, $api_key, $api_tok, $api_secret, $status=1, $order_importance=10, $description_public="", $description_internal="", $parameter1="", $parameter2="", $parameter3="", $parameter4="", $parameter5="", $parameter6="", $updater_id=0) {
        /* adds a new service and returns insert id (service id) if successful, accepts the above parameters
        name is the shown name for the service in the frontend
        url is the https link to the service (third party)
        return_url is the https link from the service back to this system after using service
        type defines the type of service (0=authentication, 1=push notification etc.)
        api_key -> key needed for the service
        api_tok -> token needed for the service
        api_secret -> secret needed for the service
        status = status of the service (0=inactive, 1=active, 2=
        ed, 3=reported, 4=archived 5= in review)
        order_importance = order for frontend display
        description_public is the shown description for the service in the frontend
        description_internal is the shown description for the service in the backend only
        parameter 1-6 -> free definable parameters that could be needed in conjunction with the service
        updater id specifies the id of the user (i.e. admin) that added this service
        */

        //sanitize the vars
        $name = trim ($name);
        $description_internal = trim ($description_internal);
        $description_public = trim ($description_public);

        $updater_id = $this->converters->checkUserId($updater_id); // checks id and converts id to db id if necessary (when hash id was passed)

        $status = intval($status);
        $type = intval($type);

        $stmt = $this->db->query('INSERT INTO '.$this->db->au_media.' (name, description_public, description_internal, type, api_key, api_tok, api_secret, order_importance, url, return_url, parameter1, parameter2, parameter3, parameter4, parameter5, parameter6, status, hash_id, created, last_update, updater_id) VALUES (:name, :description_public, :description_internal, :type, :api_key, :api_tok, :api_secret, :order_importance, :url, :return_url, :parameter1, :parameter2, :parameter3, :parameter4, :parameter5, :parameter6, :status, :hash_id, NOW(), NOW(), :updater_id)');
        // bind all VALUES

        $this->db->bind(':name', $name);
        $this->db->bind(':description_public', $description_public);
        $this->db->bind(':description_internal', $description_internal);
        $this->db->bind(':type', $type);
        $this->db->bind(':api_key', $api_key);
        $this->db->bind(':api_tok', $api_tok);
        $this->db->bind(':api_secret', $api_secret);
        $this->db->bind(':url', $url);
        $this->db->bind(':return_url', $return_url);
        $this->db->bind(':parameter1', $parameter1);
        $this->db->bind(':parameter2', $parameter2);
        $this->db->bind(':parameter3', $parameter3);
        $this->db->bind(':parameter4', $parameter4);
        $this->db->bind(':parameter5', $parameter5);
        $this->db->bind(':parameter6', $parameter6);
        $this->db->bind(':order_importance', $order_importance);

        $this->db->bind(':status', $status);

        // generate unique hash for this idea
        $testrand = rand (100,10000000);
        $appendix = microtime(true).$testrand;
        $hash_id = md5($name.$appendix); // create hash id for this service
        $this->db->bind(':hash_id', $hash_id);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }
        if (!$err)
        {
          $insertid = intval($this->db->lastInsertId());

          $this->syslog->addSystemEvent(0, "Added new service (#".$insertid.") uploader: ".$updater_id, 0, "", 1);
          $returnvalue ['success'] = true; // set return value
          $returnvalue ['error_code'] = 0; // error code
          $returnvalue ['data'] = $insertid; // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue; // return insert id to calling script

        } else {
          $returnvalue ['success'] = false; // set return value
          $returnvalue ['error_code'] = 1; // error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue; // return 0,2 to indicate that there was an db error executing the statement
        }


    }// end function

    public function setServiceStatus($service_id, $status, $updater_id = 0) {
        /* edits a service and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         status = status of service (0=inactive, 1=active, 2=suspended, 3=reported, 4=archived 5= in review)
         updater_id is the id of the user that does the update (i.E. admin )
        */
        $service_id = $this->converters->checkServiceId($service_id); // checks id and converts id to db id if necessary (when hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->db->au_services.' SET status= :status, last_update= NOW(), updater_id= :updater_id WHERE id= :service_id');
        // bind all VALUES
        $this->db->bind(':status', $status);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':service_id', $service_id); // text that is updated

        $err=false; // set error variable to false
        $count_datasets = 0; // init row count

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }
        if (!$err)
        {
          $count_datasets = intval($this->db->rowCount());
          $this->syslog->addSystemEvent(0, "Service status changed ".$media_id." by ".$updater_id, 0, "", 1);
          $returnvalue ['success'] = true; // set return value
          $returnvalue ['error_code'] = 0; // error code
          $returnvalue ['data'] = $count_datasets; // returned data
          $returnvalue ['count'] = $count_datasets; // returned count of datasets


          return $returnvalue; // return number of affected rows to calling script
        } else {
          $returnvalue ['success'] = false; // set return value
          $returnvalue ['error_code'] = 1; // error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue; // return 0,2 to indicate that there was an db error executing the statement
        }
    }// end function

    public function setServiceContent($service_id, $name, $description_internal, $description_public, $updater_id = 0) {
        /* edits a service and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         name, description_internal, description_public = meta data of the service
         updater_id is the id of the user that does the update (i.E. admin )
        */
        $service_id = $this->converters->checkServiceId($service_id); // checks id and converts id to db id if necessary (when hash id was passed)

        $name = trim ($name);

        $stmt = $this->db->query('UPDATE '.$this->db->au_services.' SET name= :name, description_internal= :description_internal, description_public= :description_public,  last_update= NOW(), updater_id= :updater_id WHERE id= :service_id');
        // bind all VALUES
        $this->db->bind(':name', $name);
        $this->db->bind(':description', $description);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':service_id', $service_id); // service that is updated

        $err=false; // set error variable to false
        $count_datasets = 0; // init row count

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }
        if (!$err)
        {
          $count_datasets = intval($this->db->rowCount());
          $this->syslog->addSystemEvent(0, "Service content changed ".$text_id." by ".$updater_id, 0, "", 1);
          $returnvalue ['success'] = true; // set return value
          $returnvalue ['error_code'] = 0; // error code
          $returnvalue ['data'] = $count_datasets; // returned data
          $returnvalue ['count'] = $count_datasets; // returned count of datasets


          return $returnvalue; // return number of affected rows to calling script
        } else {
          $returnvalue ['success'] = false; // set return value
          $returnvalue ['error_code'] = 1; // error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
        }
    }// end function


    public function deleteMedia ($service_id, $updater_id=0) {
        /* deletes services, accepts id (hash (varchar) or db id (int))

        */
        $service_id = $this->converters->checkMediaId($service_id); // checks id and converts id to db  id if necessary (when hash id was passed)

        $stmt = $this->db->query('DELETE FROM '.$this->db->au_services.' WHERE id = :id');
        $this->db->bind (':id', $service_id);

        $err=false;
        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }
        if (!$err)
        {
          $count_datasets = intval($this->db->rowCount());
          $this->syslog->addSystemEvent(0, "Service deleted, id=".$service_id." by ".$updater_id, 0, "", 1);
          $returnvalue ['success'] = true; // set return value
          $returnvalue ['error_code'] = 0; // error code
          $returnvalue ['data'] =  $count_datasets; // returned data
          $returnvalue ['count'] = $count_datasets; // returned count of datasets


          return $returnvalue; // return number of affected rows to calling script
        } else {
          $returnvalue ['success'] = false; // set return value
          $returnvalue ['error_code'] = 1; // error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets


          return $returnvalue; // return success = false and error code = 1 to indicate that there was an db error executing the statement
        }

    }// end function

} // end class
?>
