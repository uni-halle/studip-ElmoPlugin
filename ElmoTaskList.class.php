<?
// +---------------------------------------------------------------------------+
// This file is part of Stud.IP
// StudipLitList.class.php
//
//
// Copyright (c) 2003 André Noack <noack@data-quest.de>
// Suchi & Berg GmbH <info@data-quest.de>
// +---------------------------------------------------------------------------+
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or any later version.
// +---------------------------------------------------------------------------+
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
// +---------------------------------------------------------------------------+
require_once("lib/classes/TreeAbstract.class.php");
require_once "ElmoTask.class.php";
require_once "ElmoAnswer.class.php";

/**
* class to handle the
*
* This class provides
*
* @access	public
* @author	André Noack <noack@data-quest.de>
* @version	$Id: StudipLitList.class.php 8008 2007-08-10 14:03:59Z anoack $
* @package
*/
class ElmoTaskList extends TreeAbstract {

	var $issue_id;

	/**
	* constructor
	*
	* do not use directly, call &TreeAbstract::GetInstance("ElmoTaskList", array('issue_id'))
	* @access private
	*/
	function ElmoTaskList($args) {
		$this->issue_id = $args['issue_id'];
		$this->is_admin = $args['is_admin'];
		$this->current_user_id = $args['current_user_id'];
		parent::TreeAbstract(); //calling the baseclass constructor
	}

	/**
	* initializes the tree
	*
	* @access public
	*/
	function init(){
		parent::init();
		$rs = new DB_Seminar();
		$rs->query("SELECT input,title,seminar_id,is_visible,visible_from,answer_visible_default,elmo_themen.chdate FROM themen LEFT JOIN elmo_themen USING(issue_id) WHERE themen.issue_id='{$this->issue_id}'");
		$rs->next_record();
		$this->root_name = $this->title = $rs->f('title');
		$this->input = $rs->f('input');
		$this->seminar_id = $rs->f('seminar_id');
		$this->is_visible = $rs->f('is_visible') && $rs->f('visible_from') < time();
		$this->answer_visible_default = $rs->f('answer_visible_default');
		$this->tree_data['root']['chdate'] = $rs->f('chdate');
		$rs->query("SELECT * FROM elmo_tasks WHERE issue_id='{$this->issue_id}' ORDER BY priority");
		while ($rs->next_record()){
			$task_ids[] =  $rs->f("task_id");
			$this->tree_data[$rs->f("task_id")] = array("description" => $rs->f("description"),
			"task_completion" => $rs->f("task_completion"),
			"enable_answerfield" => $rs->f("enable_answerfield"),
			"chdate" => $rs->f("chdate")
			);
			$this->storeItem($rs->f("task_id"), "root", $rs->f("title"), $rs->f("priority"));
		}
		if (is_array($task_ids)){
			$sem = Seminar::GetInstance($this->seminar_id);
			foreach($sem->getMembers('autor') as $user_id => $user_data){
				foreach($task_ids as $task_id){
					$this->tree_data[$task_id . '-' . $user_id]["user_id"] = $user_id;
					$this->tree_data[$task_id . '-' . $user_id]["username"] = $user_data['username'];
					$rs->query("SELECT * FROM elmo_tasks_answers WHERE task_id = '$task_id' AND user_id = '$user_id'");
					while ($rs->next_record()){
						$this->tree_data[$task_id . '-' . $user_id]["notes"] = $rs->f("notes");
						$this->tree_data[$task_id . '-' . $user_id]["answer"] = $rs->f("answer");
						$this->tree_data[$task_id . '-' . $user_id]["feedback"] = $rs->f("feedback");
						$this->tree_data[$task_id . '-' . $user_id]["chdate_answer"] = $rs->f("chdate_answer");
						$this->tree_data[$task_id . '-' . $user_id]["chdate_feedback"] = $rs->f("chdate_feedback");
						$this->tree_data[$task_id . '-' . $user_id]["chdate_notes"] = $rs->f("chdate_notes");
						$this->tree_data[$task_id . '-' . $user_id]["answer_allowed"] = $rs->f("answer_allowed");
						$this->tree_data[$task_id . '-' . $user_id]["answer_is_visible"] = $rs->f("answer_is_visible");
					}
					if($this->answer_visible_default) {
						$this->tree_data[$task_id . '-' . $user_id]["answer_is_visible"] = $this->answer_visible_default;
					}
					if($this->tree_data[$task_id . '-' . $user_id]["answer_is_visible"] || $this->is_admin || $this->tree_data[$task_id . '-' . $user_id]["user_id"] == $this->current_user_id){
						$this->storeItem($task_id . '-' . $user_id, $task_id, $user_data['fullname'], 1);
					}
				}
			}
		}
	}

	function updateInput($input){
		$db = new DB_Seminar();
		$db->queryf("UPDATE elmo_themen SET input='%s' WHERE issue_id='%s'", mysql_escape_string($input), $this->issue_id);
		if($db->affected_rows()) $db->queryf("UPDATE elmo_themen SET chdate=UNIX_TIMESTAMP() WHERE issue_id='%s'", $this->issue_id);
		return $db->affected_rows();
	}

	function insertTask($data,$config) {
		$task = new ElmoTask();
		$task->setData($data);
		$task->setValue('issue_id', $this->issue_id);
		$ret = $task->store();
		if($task->getValue('task_completion')){
			$this->_updateTaskTermin($task, $config);
		} else {
			$this->_deleteTaskTermin($task->getId());
		}
		return $ret;

	}

	function updateTask($data, $config){
		$task = new ElmoTask($data['task_id']);
		$task->setData($data);
		$ret = $task->store();
		if($task->getValue('task_completion')){
			$this->_updateTaskTermin($task, $config);
		} else {
			$this->_deleteTaskTermin($task->getId());
		}
		return $ret;
	}

	function _updateTaskTermin($task, $config){
		$db = new DB_Seminar();
		$db->queryf("REPLACE INTO termine (termin_id, range_id, autor_id,date,end_time,mkdate,chdate,date_typ)
					VALUES ('%s','%s','%s','%s','%s',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'%s')",
					md5('task' . $task->getId()),
					$this->seminar_id,
					$GLOBALS['user']->id,
					strtotime('00:00', $task->getValue('task_completion')),
					strtotime('23:59', $task->getValue('task_completion')),
					$config['ELMO_TASK_DATE_TYP']);
		$db->queryf("REPLACE INTO themen (issue_id,seminar_id,title,description, mkdate, chdate)
					VALUES ('%s','%s','%s','%s',UNIX_TIMESTAMP(),UNIX_TIMESTAMP())",
					md5('issue' . $task->getId()),
					$this->seminar_id,
					mysql_escape_string(_("Abgabetermin") . ' '. $config['ELMO_TASKNAME'] . ': ' . $task->getValue('title')),
					mysql_escape_string('['.$config['ELMO_TASKNAME'] . ': ' . $task->getValue('title').']' . $config['LINK'] . chr(10) . _("(Dieser Termin wird automatisch erstellt. Manuelle Änderungen gehen verloren.)")));
		$db->queryf("REPLACE INTO themen_termine (issue_id, termin_id) VALUES ('%s','%s')",
					md5('issue' . $task->getId()),md5('task' . $task->getId()));
	}

	function _deleteTaskTermin($task_id){
		$db = new DB_Seminar();
		$db->queryf("DELETE FROM termine WHERE termin_id='%s' LIMIT 1", md5('task' . $task_id));
		$db->queryf("DELETE FROM themen_termine WHERE termin_id='%s' AND issue_id='%s' LIMIT 1", md5('task' . $task_id),md5('issue' . $task_id));
		$db->queryf("DELETE FROM themen WHERE issue_id='%s' LIMIT 1", md5('issue' . $task_id));

	}

	function deleteTask($task_id){
		$this->_deleteTaskTermin($task_id);
		if($this->getNumKids($task_id)){
			foreach($this->getKids($task_id) as $kid){
				list(, $user_id) = explode('-', $kid);
				$answer = new ElmoAnswer($task_id, $user_id);
				$deleted += $answer->delete();
			}
		}
		$task = new ElmoTask($task_id);
		$deleted += $task->delete();
		return $deleted;
	}

	function updateAnswer($data){
		$answer = new ElmoAnswer($data['task_id'], $data['user_id']);
		$answer->setData($data);
		return $answer->store();
	}

	function isTaskElement($item_id){
		return $this->getValue($item_id,'parent_id') == 'root';
	}

	function isAnswerElement($item_id){
		return $this->isTaskElement($this->getValue($item_id,'parent_id'));
	}

	function isElement(){
			return false;
	}
	function getDocumentList($item_id){
		$ret = array();
		if($item_id == 'root'){
			$folder_id = md5($this->issue_id .'elmo');
		} elseif($this->isAnswerElement($item_id)){
			$folder_id = md5($item_id.'answers');
			$user_id = $this->getValue($item_id,'user_id');
		} else {
			$folder_id = $item_id;
		}
		if($user_id) $cond = "AND dokumente.user_id='$user_id'";
		$db = new DB_Seminar("SELECT dokumente.*, IF(IFNULL(dokumente.name,'')='', dokumente.filename,dokumente.name) as t_name FROM folder INNER JOIN dokumente ON folder_id=dokumente.range_id WHERE folder_id='".$folder_id."' $cond ORDER BY dokumente.chdate");
		while($db->next_record()){
			$ret[$db->f('dokument_id')] = $db->Record;
		}
		return $ret;
	}

	function getDocumentListChdate($item_id){
		$list = array_map(create_function('$a', 'return $a["chdate"];'), $this->getDocumentList($item_id));
		return count($list) ? max($list) : null;
	}
}
?>
