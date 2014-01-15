<?php

require_once 'lib/classes/SimpleORMap.class.php';

define('ELMOSHOUTBOX_DB_TABLE', 'elmo_shoutbox');

class ElmoShoutbox extends SimpleORMap {

	function &GetBySeminar($seminar_id, $type = 'dozent', $as_objects = false){
		$ret = array();
		$db = new DB_Seminar();
		$query = "SELECT " . ELMOSHOUTBOX_DB_TABLE . ".*,username,CONCAT_WS(' ',Vorname,Nachname) as fullname FROM "
					. ELMOSHOUTBOX_DB_TABLE . " LEFT JOIN auth_user_md5 USING(user_id) WHERE seminar_id='$seminar_id' AND type='$type' ORDER BY chdate DESC";
		$db->query($query);
		while ($db->next_record()){
			$ret[$db->f('id')] = $db->Record;
		}
		return $ret;
	}

	function __construct($id = null){
		$this->db_table = 'elmo_shoutbox';
		parent::__construct($id);
	}

	function store () {
		foreach(array('content') as $field){
		    if ($this->getValue($field) === null) {
		       $this->setValue($field, '');
		    }
		}
		return parent::store();
	}
}
?>
