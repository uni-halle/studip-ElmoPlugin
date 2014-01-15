<?php

require_once 'lib/classes/SimpleORMap.class.php';

define('ELMOTASK_DB_TABLE', 'elmo_tasks');

class ElmoTask extends SimpleORMap {

	function &GetByIssue($issue_id){
		$ret = array();
		$db = new DB_Seminar();
		$query = "SELECT " . ELMOTASK_DB_TABLE . ".* FROM "
					. ELMOTASK_DB_TABLE . " WHERE issue_id='$issue_id' ORDER BY priority";
		$db->query($query);
		while ($db->next_record()){
			$ret[$db->f('task_id')] = $db->Record;
		}
		return $ret;
	}

	function __construct($id = null){
		$this->db_table = 'elmo_tasks';
		parent::__construct($id);
	}

	function store () {
		foreach(array('description') as $field){
		    if ($this->getValue($field) === null) {
		       $this->setValue($field, '');
		    }
		}
		return parent::store();
	}

	function getSeminarId(){
		$db = new DB_Seminar();
		$db->queryf("SELECT seminar_id FROM themen WHERE issue_id='%s'" , $this->getValue('issue_id'));
		$db->next_record();
		return $db->f(0);
	}
}
?>
