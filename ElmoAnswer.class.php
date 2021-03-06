<?php

require_once 'lib/classes/SimpleORMap.class.php';

define('ELMOANSWER_DB_TABLE', 'elmo_tasks_answers');

class ElmoAnswer extends SimpleORMap {

	protected $restored_data;

    function __construct($task_id = null, $user_id = null){
		$this->db_table = 'elmo_tasks_answers';
		parent::__construct(array($task_id, $user_id));
	}


	function restore(){
		parent::restore();
		$this->restored_data = $this->toArray();
	}


	function store () {
		$old_data = $this->restored_data;
		foreach(array('answer','notes','feedback','answer_allowed','chdate_answer','chdate_notes','chdate_feedback','answer_is_visible') as $field){
		    if ($this->getValue($field) === null) {
		       $this->setValue($field, '');
		    }
		}
		$ret = parent::store();
		$chdate = array();
		foreach(array('answer','notes','feedback') as $field){
			if($old_data[$field] != $this->getValue($field)) $chdate[] = 'chdate_' . $field . '=UNIX_TIMESTAMP()';
		}
		if(count($chdate)){
			$db = new DB_Seminar();
			if ($where_query = $this->getWhereQuery()){
				$db->query("UPDATE {$this->db_table} SET " . join(', ', $chdate) . " WHERE ". join(" AND ", $where_query));
				$this->restore();
			}
		}
		return count($chdate) + $ret;
	}
}
?>
