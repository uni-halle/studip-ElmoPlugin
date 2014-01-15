<?php
require_once "ElmoTaskList.class.php";
require_once "lib/classes/TreeView.class.php";
require_once "lib/datei.inc.php";
if(!function_exists('parse_msg_array_to_string')){
	function parse_msg_array_to_string($msg, $class = "blank", $colspan = 2, $add_row = true, $small = true){
		ob_start();
		parse_msg_array($msg, $class, $colspan, $add_row, $small);
		$out = ob_get_contents();
		ob_end_clean();
		return $out;
	}
}
class ElmoTaskListView extends TreeView {

	var $mode;
	var $edit_item_id;
	var $msg = array();
	var $config = array();
	var $admin = false;

	function ElmoTaskListView($issue_id, $plugin){
		$this->config = $plugin->config;
		$this->plugin = $plugin;
		$this->use_aging = true;
		$this->admin = $GLOBALS['perm']->have_studip_perm('tutor', $this->plugin->getId());
		parent::TreeView("ElmoTaskList", array('issue_id' => $issue_id, 'is_admin' => $this->admin, 'current_user_id' =>$GLOBALS['user']->id)); //calling the baseclass constructor
	}

	function handleOpenRanges(){
		$this->open_ranges['root'] = true;
		if(!isset($_REQUEST['foo'])){
			foreach((array)$this->tree->getKids('root') as $task){
				$this->open_items[$task] = true;
				$this->open_ranges[$task] = true;
			}
		}
        $close_range = Request::getArray('close_range');
        if (!empty($close_range)){
            if (Request::get('close_range') == 'root'){
                $this->open_ranges = null;
                $this->open_items = null;
            } else {
                $kidskids = $this->tree->getKidsKids($close_range);
                $kidskids[] = $close_range;
                $num_kidskids = count($kidskids);
                for ($i = 0; $i < $num_kidskids; ++$i){
                    if ($this->open_ranges[$kidskids[$i]]){
                        unset($this->open_ranges[$kidskids[$i]]);
                    }
                    if ($this->open_items[$kidskids[$i]]){
                        unset($this->open_items[$kidskids[$i]]);
                    }
                }
            }
            $this->anchor = $close_range;
        }
        $open_range = Request::getArray('open_range');
        if ($open_range){
            $kidskids = $this->tree->getKidsKids($open_range);
            $kidskids[] = $open_range;
            $num_kidskids = count($kidskids);
            for ($i = 0; $i < $num_kidskids; ++$i){
                if (!$this->open_ranges[$kidskids[$i]]){
                    $this->open_ranges[$kidskids[$i]] = true;
                }
            }
            $this->anchor = $open_range;
        }

        if (Request::get('close_item') || Request::get('open_item')){
            $toggle_item = (Request::get('close_item')) ? Request::get('close_item') : Request::get('open_item');
            if (!$this->open_items[$toggle_item]){
                $this->open_items[$toggle_item] = true;
                $this->open_ranges[$toggle_item] = true;
            } else {
                unset($this->open_items[$toggle_item]);
            }
            $this->anchor = $toggle_item;
        }
        if (Request::get('item_id'))
            $this->anchor = Request::get('item_id');

	}

	function getAgingColor($item_id){
		$timecolor = "#BBBBBB";
		if($item_id == 'root'){
			$last_visit = PluginVisits::GetVisit($this->plugin, $this->tree->issue_id, 'issue', $GLOBALS['user']->id, 'current');
			$chdate = $this->tree->getValue($item_id, 'chdate');
		}
		if($this->tree->isTaskElement($item_id)){
			$last_visit = PluginVisits::GetVisit($this->plugin, $item_id, 'task', $GLOBALS['user']->id, 'current');
			$chdate = max(array($this->tree->getValue($item_id, 'chdate'), $this->tree->getDocumentListChdate($item_id)));
		}
		if($this->tree->isAnswerElement($item_id) && ($this->admin || $this->tree->getValue($item_id, 'user_id') == $GLOBALS['user']->id || $this->tree->getValue($item_id,'answer_is_visible')) ){
			$last_visit = PluginVisits::GetVisit($this->plugin, md5($item_id), 'answer', $GLOBALS['user']->id, 'current');
			if($this->admin){
				$chdate = max(array($this->tree->getValue($item_id, 'chdate_answer'),$this->tree->getValue($item_id, 'chdate_notes'), $this->tree->getDocumentListChdate($item_id)));
			} elseif($this->tree->getValue($item_id, 'user_id') == $GLOBALS['user']->id) {
				$chdate = $this->tree->getValue($item_id, 'chdate_feedback');
			} else {
				$chdate = max(array($this->tree->getValue($item_id, 'chdate_answer'),$this->tree->getValue($item_id, 'chdate_notes'), $this->tree->getDocumentListChdate($item_id)));
			}
		}
		if(isset($last_visit) && $last_visit < $chdate){
			$timecolor = "#FF0000";
		}
		return $timecolor;
	}

	function parseCommand(){
		if ($_REQUEST['mode'])
			$this->mode = $_REQUEST['mode'];
		if ($_REQUEST['elmocmd']){
			$exec_func = "execCommand" . $_REQUEST['elmocmd'];
			if (method_exists($this,$exec_func)){
				if ($this->$exec_func()){
					$this->tree->init();
				}
			}
		}
	}

	function execCommandUpload(){
		$folder_id = $this->checkFolder($_REQUEST['item_id']);
		if($folder_id){
			ob_end_clean();
			header("Location: " . UrlHelper::getUrl("folder.php?cmd=tree&open={$folder_id}#anker"));
			page_close();
			die();
		} else {
			$this->msg[$_REQUEST['item_id']][] = array('error', _("Der Dateiordner wurde nicht gefunden!"));
		}
	}

	function execCommandCancel(){
		$item_id = $_REQUEST['item_id'];
		$this->mode = "";
		$this->anchor = $item_id;
		return false;
	}

	function execCommandEditItem(){
		$item_id = $_REQUEST['item_id'];
		$this->mode = "EditItem";
		$this->anchor = $item_id;
		$this->edit_item_id = $item_id;
		$this->open_items[$item_id] = true;
		return false;
	}

	function execCommandNewItem(){
		if($_REQUEST['item_id'] == 'root' && $this->admin){
			$new_item_id = md5(uniqid("elmo",1));
			$this->tree->tree_data[$new_item_id] = array(
			'chdate' => time(),
			"description" => '',
			"task_completion" => 0,
			'visibility' => 1,
			'enable_answerfield' => 1
			);
			$this->tree->storeItem($new_item_id, 'root', _("Neu"),$this->tree->getMaxPriority('root') + 1);
			$this->anchor = $new_item_id;
			$this->edit_item_id = $new_item_id;
			$this->open_ranges['root'] = true;
			$this->open_items[$new_item_id] = true;
			$this->msg[$new_item_id][] = array("info", _("Dieser neue Eintrag wurde noch nicht gespeichert."));
			$this->mode = "NewItem";
			return false;
		}
	}

	function execCommandInsertItem(){
		$item_id = $_REQUEST['item_id'];
		$parent_id = $_REQUEST['parent_id'];
		$user_id = $GLOBALS['auth']->auth['uid'];
		if ($this->mode != "NewItem"){
			if ($item_id == 'root' && isset($_REQUEST['edit_input']) && ($this->admin)){
				$affected_rows = $this->tree->updateInput(stripslashes($_REQUEST['edit_input']));
				if ($affected_rows){
					$this->msg[$item_id][] = array("msg", _("Daten wurden ge&auml;ndert."));
				} else {
					$this->msg[$item_id][] = array("info", _("Keine Ver&auml;nderungen vorgenommen."));
				}
				if (!$this->tree->getNumKids($item_id)) {
				    $task_id = md5(uniqid("elmo_task",1));
				    $semester = Semester::findbyTimestamp(Seminar::getInstance($this->tree->seminar_id)->getSemesterStartTime());
				    $this->tree->insertTask(array('task_id' => $task_id,
				                                  'enable_answerfield' => 1,
				                                  'priority' => 0,
				                                  'description' => '',
				                                  'title' => 'Aufgabe 1',
				                                  'task_completion' => strtotime('23:59', $semester['vorles_ende'])),
				                            array_merge($this->config, array('LINK' => $GLOBALS['ABSOLUTE_URI_STUDIP'] . $this->getSelf("open_item=$task_id"))));
				}
			} else if ($this->tree->isTaskElement($item_id) && isset($_REQUEST['task_title']) && ($this->admin)){
				$task_completion = 0;
				if(checkdate((int)$_REQUEST['task_completion_month'], (int)$_REQUEST['task_completion_day'], (int)$_REQUEST['task_completion_year'])){
					$task_completion = mktime(23, 59, 59,(int)$_REQUEST['task_completion_month'],(int)$_REQUEST['task_completion_day'],(int)$_REQUEST['task_completion_year']);
				} else {
					$this->msg[$item_id][] = array("error", _("Bitte geben Sie ein gültiges Datum an!"));
					$this->tree->tree_data[$item_id]['description'] = stripslashes($_REQUEST['task_description']);
					$this->tree->tree_data[$item_id]['task_completion'] = 0;
					$this->tree->tree_data[$item_id]['enable_answerfield'] = (int)$_REQUEST['task_enable_answerfield'];
					$this->tree->tree_data[$item_id]['name'] = stripslashes($_REQUEST['task_title']);
					$this->mode = "EditItem";
					$this->anchor = $item_id;
					$this->edit_item_id = $item_id;
					return false;
				}
				$affected_rows = $this->tree->updateTask(array('task_id' => $item_id, 'enable_answerfield' => (int)$_REQUEST['task_enable_answerfield'], 'description' => stripslashes($_REQUEST['task_description']),'title' => stripslashes($_REQUEST['task_title']), 'task_completion' => $task_completion), array_merge($this->config, array('LINK' => $GLOBALS['ABSOLUTE_URI_STUDIP'] . $this->getSelf("open_item=$item_id"))));
				if ($affected_rows){
					$this->msg[$item_id][] = array("msg", _("Daten wurden ge&auml;ndert."));
				} else {
					$this->msg[$item_id][] = array("info" , _("Keine Ver&auml;nderungen vorgenommen."));
				}
			} else if ($this->tree->isAnswerElement($item_id) && ($this->admin || $this->tree->getValue($item_id, 'user_id') == $GLOBALS['user']->id)){
				if($this->admin){
					if(checkdate((int)$_REQUEST['task_answer_allowed_month'], (int)$_REQUEST['task_answer_allowed_day'], (int)$_REQUEST['task_answer_allowed_year'])){
						$task_answer_allowed = mktime(23, 59, 59,(int)$_REQUEST['task_answer_allowed_month'],(int)$_REQUEST['task_answer_allowed_day'],(int)$_REQUEST['task_answer_allowed_year']);
					}
					$affected_rows = $this->tree->updateAnswer(array('task_id' => $this->tree->getValue($item_id, 'parent_id'), 'user_id' => $this->tree->getValue($item_id, 'user_id'), 'feedback' => stripslashes($_REQUEST['task_feedback']), 'answer_is_visible' => (int)$_REQUEST['task_answer_is_visible'], 'answer_allowed' => (int)$task_answer_allowed));
				} else {
					$affected_rows = $this->tree->updateAnswer(array('task_id' => $this->tree->getValue($item_id, 'parent_id'), 'user_id' => $this->tree->getValue($item_id, 'user_id'), 'answer' => stripslashes($_REQUEST['task_answer']),'notes' => stripslashes($_REQUEST['task_notes'])));
				}
				if ($affected_rows){
					$this->msg[$item_id][] = array("msg", _("Daten wurden ge&auml;ndert."));
				} else {
					$this->msg[$item_id][] = array("info" , _("Keine Ver&auml;nderungen vorgenommen."));
				}
			}
		} else {
			$priority = $this->tree->getMaxPriority($parent_id) + 1;
			$task_completion = 0;
			if(checkdate((int)$_REQUEST['task_completion_month'], (int)$_REQUEST['task_completion_day'], (int)$_REQUEST['task_completion_year'])){
				$task_completion = mktime(23, 59, 59,(int)$_REQUEST['task_completion_month'],(int)$_REQUEST['task_completion_day'],(int)$_REQUEST['task_completion_year']);
			} else {
				$this->msg[$item_id][] = array("error", _("Bitte geben Sie ein gültiges Datum an!"));
				$this->tree->tree_data[$item_id] = array(
					'chdate' => time(),
					"description" => stripslashes($_REQUEST['task_description']),
					"task_completion" => 0,
					'visibility' => 1,
					'enable_answerfield' => (int)$_REQUEST['task_enable_answerfield']
					);
				$this->tree->storeItem($item_id, 'root', stripslashes($_REQUEST['task_title']), $priority);
				$this->anchor = $item_id;
				$this->edit_item_id = $item_id;
				$this->open_ranges['root'] = true;
				$this->open_items[$item_id] = true;
				$this->msg[$item_id][] = array("info", _("Dieser neue Eintrag wurde noch nicht gespeichert."));
				$this->mode = "NewItem";
				return false;
			}
			$affected_rows = $this->tree->insertTask(array('task_id' => $item_id,'enable_answerfield' => (int)$_REQUEST['task_enable_answerfield'], 'priority' => $priority, 'description' => stripslashes($_REQUEST['task_description']),'title' => stripslashes($_REQUEST['task_title']), 'task_completion' => $task_completion), array_merge($this->config, array('LINK' => $GLOBALS['ABSOLUTE_URI_STUDIP'] . $this->getSelf("open_item=$item_id"))));
			if ($affected_rows){
				$this->mode = "";
				$this->anchor = $item_id;
				$this->open_items[$item_id] = true;
				$this->open_ranges[$item_id] = true;
				$this->msg[$item_id] = "msg§" . _("Der Eintrag wurde neu eingef&uuml;gt.");
			}
		}
		$this->mode = "";
		$this->anchor = $item_id;
		$this->open_items[$item_id] = true;
		$this->tree->init();
		$this->checkFolder($item_id);
		if($this->tree->getNumKidsKids($item_id)){
			foreach($this->tree->getKidsKids($item_id) as $kid){
				$this->checkFolder($kid);
			}
		}
		return false;
	}

	function execCommandOrderItem(){
		$direction = $_REQUEST['direction'];
		$item_id = $_REQUEST['item_id'];
		if(!$this->tree->isTaskElement($item_id )){
			return false;
		}
		$items_to_order = $this->tree->getKids($this->tree->tree_data[$item_id]['parent_id']);
		if (!$items_to_order){
			return false;
		}
		for ($i = 0; $i < count($items_to_order); ++$i){
			if ($item_id == $items_to_order[$i])
				break;
		}
		if ($direction == "up" && isset($items_to_order[$i-1])){
			$items_to_order[$i] = $items_to_order[$i-1];
			$items_to_order[$i-1] = $item_id;
		} elseif (isset($items_to_order[$i+1])){
			$items_to_order[$i] = $items_to_order[$i+1];
			$items_to_order[$i+1] = $item_id;
		}
		for ($i = 0; $i < count($items_to_order); ++$i){
			$this->tree->updateTask(array('task_id' => $items_to_order[$i], 'priority' => $i), $this->config);
			$this->tree->tree_data[$items_to_order[$i]]['priority'] = $i;
			$this->checkFolder($items_to_order[$i]);
		}
		$this->mode = "";
		$this->msg[$item_id][] = array("msg" , (($direction == "up") ? _("Element wurde um eine Position nach oben verschoben.") : _("Element wurde um eine Position nach unten verschoben.")));
		return true;
	}

	function execCommandOpenAnswers(){
		$item_id = $_REQUEST['item_id'];
		if(!$this->tree->isTaskElement($item_id)){
			return false;
		}
		foreach($this->tree->getKids($item_id) as $kid) $this->open_items[$kid] = true;
	}

	function execCommandCloseAnswers(){
		$item_id = $_REQUEST['item_id'];
		if(!$this->tree->isTaskElement($item_id)){
			return false;
		}
		foreach($this->tree->getKids($item_id) as $kid) unset($this->open_items[$kid]);
	}

	function execCommandAssertDelete(){
		$item_id = $_REQUEST['item_id'];
		if(!$this->tree->isTaskElement($item_id)){
			return false;
		}
		$this->mode = "AssertDeleteItem";
		$this->msg[$item_id][] = array("info", _("Sie beabsichtigen diese Aufgabe inklusive aller Antworten zu l&ouml;schen.")
						. sprintf(_("Es werden möglicherweise %s Antworten gel&ouml;scht (Dokumente bleiben erhalten)!"),count($this->tree->getKidsKids($item_id)))
						. "<br>" . _("Wollen Sie diese Aufgabe wirklich l&ouml;schen?") . "<br>"
						. "<a href=\"" . $this->getSelf("cmd=DeleteItem&item_id=$item_id") . "\">"
						. "<img " .makeButton("ja2","src") . tooltip(_("löschen"))
						. " border=\"0\"></a>&nbsp;"
						. "<a href=\"" . $this->getSelf("cmd=Cancel&item_id=$item_id") . "\">"
						. "<img " .makeButton("nein","src") . tooltip(_("abbrechen"))
						. " border=\"0\"></a>");
		return false;
	}

	function execCommandDeleteItem(){
		$item_id = $_REQUEST['item_id'];
		if(!$this->tree->isTaskElement($item_id)){
			return false;
		}
		$deleted = 0;
		$item_name = $this->tree->tree_data[$item_id]['name'];
		$this->anchor = $this->tree->tree_data[$item_id]['parent_id'];
		if ($this->mode == "AssertDeleteItem"){
			$deleted = $this->tree->deleteTask($item_id);
			if ($deleted){
				$this->msg[$this->anchor][] = array("msg", sprintf(_("Die Aufgabe <b>%s</b> und alle Antworten (insgesamt %s) wurden gel&ouml;scht. "),htmlReady($item_name),$deleted-1));
			} else {
				$this->msg[$this->anchor][] = array("error", _("Fehler, der Eintrag konnte nicht gel&ouml;scht werden!"));
			}
		}
		$this->mode = "";
		$this->open_items[$this->anchor] = true;
		return true;
	}

	function checkFolder($item_id){
		$db = new DB_Seminar();
		$db->queryf("INSERT IGNORE INTO folder (folder_id,range_id,user_id,name,description,permission,mkdate,chdate)
							VALUES('%s','%s','%s','%s','%s','%s',UNIX_TIMESTAMP(),UNIX_TIMESTAMP())",
							md5($this->tree->seminar_id  . 'elmo'),
							md5($this->tree->seminar_id  . 'top_folder'),
							$GLOBALS['user']->id,
							mysql_escape_string(chr(160) . $this->config['ELMO_DISPLAYNAME'] ? $this->config['ELMO_DISPLAYNAME'] : _("Elearning Modul")),
							mysql_escape_string('Ablage für alle Dokumente zum Modul. Dieser Ordner wurde automatisch erstellt.'),5);
		$db->queryf("UPDATE folder SET name='%s', permission='5' WHERE folder_id='%s'", mysql_escape_string(chr(160) . $this->config['ELMO_DISPLAYNAME'] ? $this->config['ELMO_DISPLAYNAME'] : _("Elearning Modul")), md5($this->tree->seminar_id  . 'elmo'));
		$db->queryf("INSERT IGNORE INTO folder (folder_id,range_id,user_id,name,description,mkdate,chdate)
							VALUES('%s','%s','%s','%s','%s',UNIX_TIMESTAMP(),UNIX_TIMESTAMP())",
							md5($this->tree->issue_id .'elmo'),
							md5($this->tree->seminar_id  . 'elmo'),
							$GLOBALS['user']->id,
							mysql_escape_string(chr(160) . 'Thema: ' . $this->tree->title),
							mysql_escape_string('Ablage für alle Dokumente zum Thema. Dieser Ordner wurde automatisch erstellt.'));
		$db->queryf("UPDATE folder SET name='%s', permission='%s' WHERE folder_id='%s'", mysql_escape_string(chr(160) . 'Thema: ' . $this->tree->title), ($this->tree->is_visible ? '5' : '0') , md5($this->tree->issue_id .'elmo'));
		$folder_id = ($item_id == 'root' ? md5($this->tree->issue_id .'elmo') : $item_id);
		if($item_id != 'root'){
			if($this->tree->isTaskElement($item_id)){
				$db->queryf("INSERT IGNORE INTO folder (folder_id,range_id,user_id,name,description,mkdate,chdate)
							VALUES('%s','%s','%s','%s','%s',UNIX_TIMESTAMP(),UNIX_TIMESTAMP())",
							$folder_id,
							md5($this->tree->issue_id .'elmo'),
							$GLOBALS['user']->id,
							mysql_escape_string(sprintf('%02d ', $this->tree->getValue($item_id,'priority')) . $this->config['ELMO_TASKNAME']. ': ' . $this->tree->getValue($item_id, 'name')),
							mysql_escape_string('Dieser Ordner wurde automatisch erstellt.'));
				$db->queryf("UPDATE folder SET name='%s', permission='5' WHERE folder_id='%s'",
						mysql_escape_string(sprintf('%02d ', $this->tree->getValue($item_id,'priority')) . $this->config['ELMO_TASKNAME']. ': ' . $this->tree->getValue($item_id, 'name')),
						$folder_id);
			}
			if($this->tree->isAnswerElement($item_id)){
				$folder_id = md5($item_id.'answers');
				$parent_folder_id = $this->tree->getValue($item_id, 'parent_id');
				if($this->tree->getValue($item_id, 'user_id') == $GLOBALS['user']->id){
					$db->queryf("INSERT IGNORE INTO folder (folder_id,range_id,user_id,name,description,mkdate,chdate)
							VALUES('%s','%s','%s','%s','%s',UNIX_TIMESTAMP(),UNIX_TIMESTAMP())",
							$folder_id,
							md5($this->tree->issue_id .'elmo'),
							$GLOBALS['user']->id,
							mysql_escape_string(sprintf('%02d'.chr(160), $this->tree->getValue($parent_folder_id,'priority')) . $this->config['ELMO_ANSWERNAME']. ': ' . $this->tree->getValue($parent_folder_id, 'name')),
							mysql_escape_string('Dieser Ordner wurde automatisch erstellt.'));
				}
				$db->queryf("UPDATE folder SET name='%s', permission='%s' WHERE folder_id='%s'",
						mysql_escape_string(sprintf('%02d'.chr(160), $this->tree->getValue($parent_folder_id,'priority')) . $this->config['ELMO_ANSWERNAME']. ': ' . $this->tree->getValue($parent_folder_id, 'name')),
						($this->tree->getValue($item_id, 'answer_is_visible') ? '7' : '3'), $folder_id);
			}
		}
		return $folder_id;
	}

	function getDocumentLink($dokument_id){
		$ret = '';
			$db = new DB_Seminar("SELECT dokumente.*, IF(IFNULL(name,'')='', filename,name) AS t_name FROM dokumente WHERE dokument_id='".$dokument_id."'");
			if($db->next_record()){
				$titel = '<a href="' . GetDownloadLink($db->f('dokument_id'), $db->f('filename'), 0,'force') . '">'
				. GetFileIcon(getFileExtension($db->f('filename')), true) .'&nbsp;'. htmlReady($db->f("t_name"));
				$titel .= '</a>';
				if (($db->f("filesize") /1024 / 1024) >= 1) $titel .= "&nbsp;&nbsp;(".round ($db->f("filesize") / 1024 / 1024)." MB)";
				else $titel .= "&nbsp;&nbsp;(".round ($db->f("filesize") / 1024)." kB)";
				$titel .= "&nbsp;&nbsp;".strftime("%x-%X",$db->f("chdate"))."";
				if ($db->f("protected")==1) $titel .= "&nbsp;<img src=\"".$GLOBALS['ASSETS_URL']."images/icons/16/blue/exclaim.png\" align=\"absbottom\" border=\"0\" ".tooltip(_("Diese Datei ist urheberrechtlich geschützt!")).">";
				if ($db->f("url")!="")	$titel .= "&nbsp;<img src=\"".$GLOBALS['ASSETS_URL']."images/icons/16/blue/link-extern.png\" align=\"absbottom\" border=\"0\" ".tooltip(_("Diese Datei wird von einem externen Server geladen!")).">";
				if ($this->admin) $titel .= "&nbsp;&nbsp;<a href=\"".UrlHelper::getLink("folder.php?open={$dokument_id}_fd_")."\"><img src=\"".$GLOBALS['ASSETS_URL']."images/icons/16/blue/trash.png\" align=\"absbottom\" border=\"0\" ".tooltip(_("Diese Datei löschen."))."></a>";
				$ret .= '<div>'.$titel.'</div>';
			}
		return $ret;
	}

	function getDocumentListContent($item_id,$user_id = ''){
		$ret = "";
		$docs = $this->tree->getDocumentList($item_id);
		if(count($docs)){
			$ret .= '<ul style="padding:0px;margin:0px;">';
			foreach($docs as $doc_id => $doc){
				$ret .= '<li style="list-style-type:none;">'.$this->getDocumentLink($doc_id).'</li>';
			}
			$ret .= '</ul>';
		}
		return $ret;
	}

	function getItemContent($item_id){
		$edit_content = false;
		if ($item_id == $this->edit_item_id){
			$edit_content = $this->getEditItemContent();
		}
		if (!$edit_content && $this->tree->isAnswerElement($item_id)) {
			if(class_exists('Avatar')){
				$user_pic = Avatar::getAvatar($this->tree->getValue($item_id,'user_id'))->getUrl(Avatar::MEDIUM);
			} elseif(function_exists('get_user_pic_url')){
				$user_pic = get_user_pic_url($this->tree->getValue($item_id,'user_id'));
			} elseif($GLOBALS['ABSOLUTE_PATH_USER_PIC']) {
				$user_pic = file_exists($GLOBALS['ABSOLUTE_PATH_USER_PIC']. '/'.$this->tree->getValue($item_id,'user_id').'.jpg')
				? $GLOBALS['ABSOLUTE_URI_STUDIP'].$GLOBALS['USER_PIC_PATH'].'/'.$this->tree->getValue($item_id,'user_id').'.jpg'
				: $GLOBALS['ABSOLUTE_URI_STUDIP'].$GLOBALS['USER_PIC_PATH'].'/nobody.jpg';
			}
			$content .= '<a href="'.UrlHelper::getLink('about.php?username='.$this->tree->getValue($item_id,'username')).'" title="persönliche Homepage des Teilnehmers aufrufen"><img align="right" hspace="5" src="'.$user_pic.'" width="80" border="0"></a>';
		}
		if ($edit_content) {
		    $content .= "\n<form name=\"item_form\" action=\"" . $this->getSelf("cmd=InsertItem&item_id={$this->edit_item_id}") . "\" method=\"POST\">";
		    $content .= "\n<input type=\"HIDDEN\" name=\"parent_id\" value=\"{$this->tree->tree_data[$this->edit_item_id]['parent_id']}\">";
		}
		$content .= "\n<table width=\"90%\" cellpadding=\"2\" cellspacing=\"0\" align=\"center\" style=\"font-size:10pt\">";
		$content .= $this->getItemMessage($item_id);
		if (!$edit_content){
			if ($item_id == "root"){
				$content .= "\n<tr><td class=\"steel1\" align=\"left\">
				<div style=\"margin-bottom:5px; font-size: 10pt;\">";
				if($this->tree->input){
					$content .= chr(10).formatReady($this->tree->input);
					$content .= '<div align="left" style="font-size:75%;margin-top:5px;">('
						. _("Letzte &Auml;nderung:") . strftime(" %d.%m.%Y %H:%M", $this->tree->getValue($item_id,'chdate'))
						. ')</div>';
				} else {
					$content .= chr(10)._("Es wurden noch keine Daten eingegeben");
				}
				$content .= "\n</div>";
				$content .= $this->getDocumentListContent($item_id);
				$content .= "</td></tr>";
			} elseif ($this->tree->isTaskElement($item_id)) {
					$content .= "\n<tr><td class=\"steel1\" align=\"left\">";
					/*
					if($this->tree->getValue($item_id,'task_completion')){
						$content .= "<div style=\"margin-bottom:5px;\"><b>"._("Zu erledigen bis:").strftime(" %d.%m.%Y", $this->tree->getValue($item_id,'task_completion'))."</b></div>";
					}
					*/
					$content .= "<div style=\"margin-bottom:5px;font-size: 10pt;\">";
					$content .= formatReady($this->tree->getValue($item_id, 'description'));
					if($this->tree->getValue($item_id, 'description')) $content .= '<div align="left" style="font-size:75%;margin-top:5px;">('
						. _("Letzte &Auml;nderung:") . strftime(" %d.%m.%Y %H:%M", $this->tree->getValue($item_id,'chdate'))
					. ')</div>';
					$content .= "\n</div>";
					$content .= $this->getDocumentListContent($item_id);
					$content .= "</td></tr>";

			} elseif($this->tree->getValue($item_id, 'user_id') == $GLOBALS['user']->id || $this->admin || $this->tree->getValue($item_id, 'answer_is_visible')) {
				$answerfield = $this->tree->getValue($this->tree->getValue($item_id,'parent_id'), 'enable_answerfield');
				$content .= "\n<tr><td class=\"steel1\" valign=\"top\" align=\"left\"><div style=\"margin-left:1em\">";
				//$content .= "<div style=\"margin-bottom:5px;\"><b>"._("Antwort:")."</b></div>";
				if($answerfield){
					$content .= "<div style=\"margin-bottom:5px;margin-left:5px;margin-right:5px;font-size: 10pt;\">";
					$content .=  formatReady($this->tree->getValue($item_id, 'answer')).'&nbsp;';
					if($this->tree->getValue($item_id, 'answer') && $this->tree->getValue($item_id,'chdate_answer')){
						$content .= '<div align="left" style="font-size:75%;margin-top:5px;">('
						. _("Letzte &Auml;nderung:") . strftime(" %d.%m.%Y %H:%M", $this->tree->getValue($item_id,'chdate_answer'))
						. ')</div>';
					}
					$content .= '</div>';
				}
				$content .= $this->getDocumentListContent($item_id);
				$content .= "<div style=\"margin-bottom:5px;margin-top:5px;\"><b>"._("Anmerkungen der/des Studierenden:")."</b></div>";
				$content .= "<div style=\"margin-bottom:5px;margin-left:5px;margin-right:5px;font-size: 10pt;\">";
				$content .=  formatReady($this->tree->getValue($item_id, 'notes')).'&nbsp;';
				if($this->tree->getValue($item_id, 'notes') && $this->tree->getValue($item_id,'chdate_notes')){
					$content .= '<div align="left" style="font-size:75%;margin-top:5px;">('
					. _("Letzte &Auml;nderung:") . strftime(" %d.%m.%Y %H:%M", $this->tree->getValue($item_id,'chdate_notes'))
					. ')</div>';
				}
				$content .= '</div>';
				$content .= "<div style=\"margin-bottom:5px;\"><b>"._("Rückmeldung der/des Lehrenden:")."</b></div>";
				$content .= "<div style=\"margin-left:5px;margin-right:5px;font-size: 10pt;\">";
				$content .=  formatReady($this->tree->getValue($item_id, 'feedback')).'&nbsp;';
				if($this->tree->getValue($item_id, 'feedback') && $this->tree->getValue($item_id,'chdate_feedback')){
					$content .= '<div align="left" style="font-size:75%;margin-top:5px;">('
					. _("Letzte &Auml;nderung:") . strftime(" %d.%m.%Y %H:%M", $this->tree->getValue($item_id,'chdate_feedback'))
					. ')</div>';
				}
				$content .= '</div>';

				$content .= "</div></td></tr>";
			}
		} else {
			$content .= "\n<tr><td class=\"steel1\" align=\"left\">$edit_content</td></tr>";
		}

		$content .= "</table>";
		if ($edit_content) {
		    $content .= '</form>';
		} else {
			$content .= "\n<table width=\"90%\" cellpadding=\"2\" cellspacing=\"2\" align=\"center\" style=\"font-size:10pt\">";
			$content .= "\n<tr><td align=\"center\">&nbsp;</td></tr>";
			$content .= "\n<tr><td align=\"center\">";
			if ($item_id == "root" && $this->admin){
				$content .= "<a href=\"" . $this->getSelf("cmd=EditItem&item_id=$item_id") . "\">"
					. "<img " .makeButton("bearbeiten","src") . tooltip(_("Inhalte bearbeiten"))
					. " border=\"0\"></a>&nbsp;";
				$content .= "<a href=\"" . $this->getSelf("cmd=upload&item_id=$item_id") . "\">"
					. "<img " .makeButton("dateihochladen","src") . tooltip(_("Datei hochladen"))
					. " border=\"0\"></a>&nbsp;";
				$content .= "<a href=\"" . $this->getSelf("cmd=NewItem&item_id=$item_id") . "\">"
				. "<img " .makeButton("hinzufuegen","src") . tooltip(_("Eine neue Aufgabe hinzufügen."))
				. " border=\"0\"></a>&nbsp;";
				$content .= "<span style=\"padding-left:20px;\"><a target=\"_blank\" href=\"" . PluginEngine::getLink($this->plugin,null,'printview/'.$this->tree->issue_id) . "\">"
					. "<img " .makeButton("export","src"). tooltip(_("Druckansicht öffnen"))
					. "></a></span>";
			} else if ($this->mode != "NewItem"){
				if ($this->tree->isTaskElement($item_id) && $this->admin){
					$content .= "<a href=\"" . $this->getSelf("cmd=EditItem&item_id=$item_id") . "\">"
					. "<img " .makeButton("bearbeiten","src") . tooltip(_("Dieses Element bearbeiten"))
					. " border=\"0\"></a>&nbsp;";
					$content .= "<a href=\"" . $this->getSelf("cmd=upload&item_id=$item_id") . "\">"
					. "<img " .makeButton("dateihochladen","src") . tooltip(_("Datei hochladen"))
					. " border=\"0\"></a>&nbsp;";
					$content .= "<a href=\"" . $this->getSelf("cmd=assertDelete&item_id=$item_id") . "\">"
					. "<img " .makeButton("loeschen","src") . tooltip(_("Dieses Element löschen"))
					. " border=\"0\"></a>&nbsp;";
				} elseif($this->tree->getValue($item_id, 'user_id') == $GLOBALS['user']->id || $this->admin) {
					if($this->admin || $this->tree->getValue($item_id, 'answer_allowed') > time() || $this->tree->getValue($this->tree->getValue($item_id,'parent_id'), 'task_completion') > time()){
						$content .= "<a href=\"" . $this->getSelf("cmd=EditItem&item_id=$item_id") . "\">"
						. "<img " .makeButton("bearbeiten","src") . tooltip(_("Dieses Element bearbeiten"))
						. " border=\"0\"></a>&nbsp;";
						if(!$this->admin){
							$content .= "<a href=\"" . $this->getSelf("cmd=upload&item_id=$item_id") . "\">"
							. "<img " .makeButton("dateihochladen","src") . tooltip(_("Datei hochladen"))
							. " border=\"0\"></a>&nbsp;";
						}
					} else {
						$content .= _("Die Bearbeitung dieser Aufgabe ist nicht mehr möglich!");
					}
				}
			}
			$content .= "</td></tr></table>";
			if ($this->mode != "NewItem" && $this->tree->isTaskElement($item_id) ){
				$content .= "\n<table width=\"100%\" cellpadding=\"2\" cellspacing=\"2\" align=\"center\" style=\"font-size:10pt\">";
				$content .= "\n<tr><td class=\"steelgraulight\" align=\"center\">";
				if(!$this->hasOpenAnswers($item_id)){
					$content .= '<a href="'.$this->getSelf('cmd=openAnswers&item_id='.$item_id).'">
					<img src="'.$GLOBALS['PLUGIN_ASSETS_URL'].'images/open_all.png" '. tooltip(_("Alle Antworten aufklappen")).' border="0">
					</a>';
				} else {
					$content .= '<a href="'.$this->getSelf('cmd=closeAnswers&item_id='.$item_id).'">
					<img src="'.$GLOBALS['PLUGIN_ASSETS_URL'].'images/close_all.png" '. tooltip(_("Alle Antworten zuklappen")).' border="0">
					</a>';
				}
				$content .= "\n</td></tr></table>";
			}
		}
		return $content;
	}

	function hasOpenAnswers($item_id){
		if(!$this->tree->isTaskElement($item_id )){
			return false;
		}
		if(!$this->tree->getNumKids($item_id)) return false;
		foreach($this->tree->getKids($item_id) as $kid) if(!$this->open_items[$kid]) return false;
		return true;
	}

	function getItemHead($item_id){
		$head = "";
		$head .= "&nbsp;<a class=\"tree\" href=\"";
		$head .= ($this->open_items[$item_id])? $this->getSelf("close_item={$item_id}") . "\"" . tooltip(_("Dieses Element schließen"),true) . "><b>"
											: $this->getSelf("open_item={$item_id}") . "\"" . tooltip(_("Dieses Element öffnen"),true) . ">";
		if($item_id == 'root'){
			$name = $this->config['ELMO_INPUTNAME'];
			if(!$this->tree->is_visible) $name .= ' (unsichtbar)';
		} elseif($this->tree->isTaskElement($item_id) ){
			$name = $this->config['ELMO_TASKNAME'];
		} else {
			$name = $this->config['ELMO_ANSWERNAME'];
		}
		$name .= ': ' . $this->tree->getValue($item_id,'name');

		$head .= htmlReady(my_substr($name,0,$this->max_cols));
		$head .= ($this->open_items[$item_id]) ? "</b></a>" : "</a>";
		if ($this->tree->isTaskElement($item_id) && $item_id != $this->start_item_id && $item_id != $this->edit_item_id){
			if($this->tree->getValue($item_id,'task_completion')){
				$head .= "</td><td align=\"left\" valign=\"bottom\" class=\"printhead\" nowrap>" ._("Zu erledigen bis:").strftime(" %d.%m.%Y ", $this->tree->getValue($item_id,'task_completion'));
			}
			$head .= "</td><td align=\"right\" valign=\"bottom\" class=\"printhead\" nowrap>";
			if (!$this->tree->isFirstKid($item_id)){
				$head .= "<a href=\"". $this->getSelf("cmd=OrderItem&direction=up&item_id=$item_id") .
				"\"><img class=\"text-top\" src=\"".$GLOBALS['PLUGIN_ASSETS_URL']."images/icons/16/yellow/arr_2up.png\" " .
				tooltip(_("Element nach oben verschieben")) ."></a>";
			}
			if (!$this->tree->isLastKid($item_id)){
				$head .= "<a href=\"". $this->getSelf("cmd=OrderItem&direction=down&item_id=$item_id") .
				"\"><img class=\"text-top\" src=\"".$GLOBALS['PLUGIN_ASSETS_URL']."images/icons/16/yellow/arr_2down.png\"   " .
				tooltip(_("Element nach unten verschieben")) . "></a>";
			}
			$head .= "&nbsp;";
		}
		if($this->tree->isAnswerElement($item_id) && ($this->admin || $this->tree->getValue($item_id, 'user_id') == $GLOBALS['user']->id) || $this->tree->getValue($item_id,'answer_is_visible')){
			$head .= "</td><td align=\"left\" valign=\"bottom\" class=\"printhead\" nowrap>";
			$head .= "<table cellpadding=\"0\" cellspacing=\"2\" border=\"0\"><tr>";
			$head .= "<td nowrap><div style=\"width:100px\">";
			$link = "<a class=\"tree\" href=\"".($this->open_items[$item_id]? $this->getSelf("close_item={$item_id}") : $this->getSelf("open_item={$item_id}")) . "\">";
			$latest_document = $this->tree->getDocumentListChdate($item_id);
			if($this->tree->getValue($item_id, 'answer') || $latest_document){
				$head .= $link;
				$head .= '<img align="absmiddle" src="'.$this->icon_path.'icon_accept.gif" border="0"'.tooltip(_("Studentische Antwort liegt vor")) . '>';
				$head .= '<span style="font-size:9px;"> '.strftime("%x&nbsp;%R", ($latest_document > $this->tree->getValue($item_id, 'chdate_answer') ? $latest_document : $this->tree->getValue($item_id, 'chdate_answer'))).' </span>';
				$head .= '</a>';
			} else {
				$head .= '<img src="'.$GLOBALS['ASSETS_URL'].'images/blank.gif" height="16" width="16">';
			}
			$head .= "</div></td><td nowrap><div style=\"width:100px\">";
			if($this->tree->getValue($item_id, 'notes')){
				$head .= $link;
				$head .= '<img align="absmiddle" src="'.$this->icon_path.'page_script.gif" border="0"'.tooltip(_("Studentische Anfrage liegt vor")) . '>';
				$head .= '<span style="font-size:9px;"> '.strftime("%x&nbsp;%R", $this->tree->getValue($item_id, 'chdate_notes')).' </span>';
				$head .= '</a>';
			} else {
				$head .= '<img src="'.$GLOBALS['ASSETS_URL'].'images/blank.gif" height="16" width="16">';
			}
			$head .= "</div></td><td nowrap><div style=\"width:100px\">";
			if($this->tree->getValue($item_id, 'feedback')){
				$head .= $link;
				$head .= '<img align="absmiddle" src="'.$this->icon_path.'page_tick.gif" border="0"'.tooltip(_("Rückmeldung erfolgt")) . '>';
				$head .= '<span style="font-size:9px;"> '.strftime("%x&nbsp;%R", $this->tree->getValue($item_id, 'chdate_feedback')).' </span>';
				$head .= '</a>';
			} else {
				$head .= '<img src="'.$GLOBALS['ASSETS_URL'].'images/blank.gif" height="16" width="16">';
			}
			$head .= "</div></td><td nowrap>";
			if($this->tree->getValue($item_id, 'answer_allowed') > 0){
				$head .= $link;
				$head .= '<img align="absmiddle" src="'.$this->icon_path.'page_edit.gif" border="0"'.tooltip(_("Erweiterte Bearbeitungszeit bis ") . strftime('%x', $this->tree->getValue($item_id, 'answer_allowed'))) . '>';
				$head .= '</a>';
			} else {
				$head .= '<img src="'.$GLOBALS['ASSETS_URL'].'images/blank.gif" height="16" width="16">';
			}
			$head .="</td></tr></table>";
		}
		return $head;
	}

	function getItemHeadPics($item_id){
		$head = $this->getItemHeadFrontPic($item_id);
		$head .= "\n<td  class=\"printhead\" nowrap  align=\"left\" valign=\"bottom\">";
		if ($item_id == "root"){
			$head .= "<img src=\"".$GLOBALS['PLUGIN_ASSETS_URL']."images/icons/16/black/learnmodule.png";
			$head .= "\" border=\"0\">";
		} elseif($this->tree->isTaskElement($item_id)) {
			$head .= "<img src=\"".$GLOBALS['PLUGIN_ASSETS_URL']."images/icons/16/black/edit.png";
			$head .= "\" border=\"0\">";
		} elseif($this->tree->isAnswerElement($item_id)) {
			$head .= "<img src=\"".$GLOBALS['PLUGIN_ASSETS_URL']."images/icons/16/black/";
			$head .= $this->tree->getValue($item_id,'answer_is_visible') ? "visibility-visible.png" : "visibility-invisible.png";
			$head .= "\" align=\"absmiddle\" border=\"0\" " . tooltip($this->tree->getValue($item_id,'answer_is_visible') ?  _("Bearbeitung sichtbar") :  _("Bearbeitung unsichtbar")) . ">";
			if(class_exists('Avatar')){
				$user_pic = Avatar::getAvatar($this->tree->getValue($item_id,'user_id'))->getUrl(Avatar::SMALL);
			} elseif(function_exists('get_user_pic_url')){
				$user_pic = get_user_pic_url($this->tree->getValue($item_id,'user_id'));
			} elseif($GLOBALS['ABSOLUTE_PATH_USER_PIC']) {
				$user_pic = file_exists($GLOBALS['ABSOLUTE_PATH_USER_PIC']. '/'.$this->tree->getValue($item_id,'user_id').'.jpg')
				? $GLOBALS['ABSOLUTE_URI_STUDIP'].$GLOBALS['USER_PIC_PATH'].'/'.$this->tree->getValue($item_id,'user_id').'.jpg'
				: $GLOBALS['ABSOLUTE_URI_STUDIP'].$GLOBALS['USER_PIC_PATH'].'/nobody.jpg';
			}
			$head .= "<img src=\"".$user_pic;
			$head .= "\" border=\"0\" hspace=\"2\" height=\"20\" align=\"absmiddle\">";
		}
	return $head . "</td>";
	}

	function getEditItemContent(){
		if($this->edit_item_id == 'root'){
			$content .= "\n<tr><td class=\"steelgraulight\" style=\"font-size:10pt;border-top: 1px solid black;border-left: 1px solid black;border-right: 1px solid black;\" ><b>". sprintf(_("%s bearbeiten:"),$this->config['ELMO_INPUTNAME']) . "</b></td></tr>";
			$content .= "<tr><td class=\"steel1\" align=\"center\" style=\"font-size:10pt;border-bottom: 1px solid black;border-left: 1px solid black;border-right: 1px solid black;\"><textarea class=\"add_toolbar resizable\" name=\"edit_input\" style=\"width:100%\" rows=\"30\">"
			. htmlReady($this->tree->input)	. "</textarea></td></tr>";
		} else if($this->tree->isTaskElement($this->edit_item_id)){
			$content .= "\n<tr><td class=\"steelgraulight\" style=\"font-size:10pt;border-top: 1px solid black;border-left: 1px solid black;border-right: 1px solid black;\" ><b>". _("Titel:") . "</b>"
					. "</td></tr>";
			$content .= "<tr><td class=\"steel1\" align=\"center\" style=\"font-size:10pt;border-left: 1px solid black;border-right: 1px solid black;\">
						<input name=\"task_title\" style=\"width:100%\" value=\"".htmlReady( $this->tree->getValue($this->edit_item_id,'name'))."\">"
					. "</td></tr>";
			$content .= "\n<tr><td class=\"steelgraulight\" style=\"font-size:10pt;border-left: 1px solid black;border-right: 1px solid black;\" ><b>". _("Zu erledigen bis:") . "</b>"
					. "</td></tr>";
			$content .= "<tr><td class=\"steel1\" align=\"left\" style=\"font-size:10pt;border-left: 1px solid black;border-right: 1px solid black;\">
						<input name=\"task_completion_day\" value=\"".($this->tree->getValue($this->edit_item_id,'task_completion') ? date("d",$this->tree->getValue($this->edit_item_id,'task_completion')) : '')."\" size=\"2\" maxlength=\"2\" type=\"text\">
						 &nbsp;
						 <input name=\"task_completion_month\" value=\"".($this->tree->getValue($this->edit_item_id,'task_completion') ? date("m",$this->tree->getValue($this->edit_item_id,'task_completion')) : '')."\" size=\"2\" maxlength=\"2\" type=\"text\">
						 &nbsp;
						 <input name=\"task_completion_year\"value=\"".($this->tree->getValue($this->edit_item_id,'task_completion') ? date("Y",$this->tree->getValue($this->edit_item_id,'task_completion')) : '')."\" size=\"4\" maxlength=\"4\" type=\"text\">
						 &nbsp;
						 <img align=\"absmiddle\" src=\"{$GLOBALS['PLUGIN_ASSETS_URL']}images/popupcalendar.png\" border=\"0\" onClick=\"window.open('{$GLOBALS['ABSOLUTE_URI_STUDIP']}termin_eingabe_dispatch.php?form_name=item_form&element_switch=task_completion&imt=".($this->tree->getValue($this->edit_item_id,'task_completion') ? $this->tree->getValue($this->edit_item_id,'task_completion') : time())."&atime=".$this->tree->getValue($this->edit_item_id,'task_completion')."', 'InsertDate', 'dependent=yes, width=210, height=210, left=500, top=150')\">
						</td></tr>";
			$content .= "\n<tr><td class=\"steelgraulight\" style=\"font-size:10pt;border-left: 1px solid black;border-right: 1px solid black;\" ><b>". _("Textfeld in der Antwort:") . "</b>"
					. "</td></tr>";
			$content .= "<tr><td class=\"steel1\" align=\"left\" style=\"font-size:10pt;border-left: 1px solid black;border-right: 1px solid black;\">
						<input type=\"radio\" ".($this->tree->getValue($this->edit_item_id,'enable_answerfield') ? 'checked' : '')." name=\"task_enable_answerfield\" value=\"1\" style=\"vertical-align:top\">
						Ja
						&nbsp;
						<input type=\"radio\"  ".(!$this->tree->getValue($this->edit_item_id,'enable_answerfield') ? 'checked' : '')." name=\"task_enable_answerfield\" value=\"0\" style=\"vertical-align:top\">
						Nein
						</td></tr>";
			$content .= "\n<tr><td class=\"steelgraulight\" style=\"font-size:10pt;border-left: 1px solid black;border-right: 1px solid black;\" ><b>". ("Beschreibung:")."</b></td></tr>";
			$content .= "<tr><td class=\"steel1\" align=\"center\" style=\"font-size:10pt;border-bottom: 1px solid black;border-left: 1px solid black;border-right: 1px solid black;\"><textarea class=\"add_toolbar resizable\" name=\"task_description\" style=\"width:100%\" rows=\"30\">"
			. htmlReady($this->tree->getValue($this->edit_item_id, 'description')). "</textarea></td></tr>";


		} else if($this->tree->isAnswerElement($this->edit_item_id)){
			$answerfield = $this->tree->getValue($this->tree->getValue($this->edit_item_id,'parent_id'), 'enable_answerfield');
			if($answerfield){
				$content .= "\n<tr><td class=\"steelgraulight\" style=\"font-size:10pt;border-top: 1px solid black;border-left: 1px solid black;border-right: 1px solid black;\" ><b>". $this->config['ELMO_ANSWERNAME']. ":</b></td></tr>";
				$content .= "<tr><td class=\"steel1\" align=\"left\" style=\"font-size:10pt;border-bottom: 1px solid black;border-left: 1px solid black;border-right: 1px solid black;\">";
			}
			if($this->tree->getValue($this->edit_item_id, 'user_id') == $GLOBALS['user']->id){
				if($answerfield){
					$content .= "<textarea class=\"add_toolbar resizable\" name=\"task_answer\" style=\"width:100%\" rows=\"30\">"
				. htmlReady($this->tree->getValue($this->edit_item_id, 'answer'))	. "</textarea>";
				}
				$edit_field = 'notes';
				$edit_desc = _("Anmerkungen und Fragen:");
				$show_field = 'feedback';
				$show_desc = _("Rückmeldung:");
				$content .= "\n<tr><td class=\"steelgraulight\" style=\"font-size:10pt;".(!$answerfield ? 'border-top: 1px solid black;':'')."border-left: 1px solid black;border-right: 1px solid black;\" ><b>". $edit_desc . "</b>"
					. "</td></tr>";
				$content .= "<tr><td class=\"steel1\" align=\"left\" style=\"font-size:10pt;border-left: 1px solid black;border-right: 1px solid black;\">
						<textarea class=\"add_toolbar resizable\" name=\"task_$edit_field\" style=\"width:100%\" rows=\"30\">"
						. htmlReady($this->tree->getValue($this->edit_item_id, $edit_field)). "</textarea></td></tr>";
				$content .= "\n<tr><td class=\"steelgraulight\" style=\"font-size:10pt;border-left: 1px solid black;border-right: 1px solid black;\" ><b>". $show_desc ."</b></td></tr>";
				$content .= "<tr><td class=\"steel1\" align=\"left\" style=\"font-size:10pt;border-left: 1px solid black;border-right: 1px solid black;border-bottom: 1px solid black;\">";
				$content .=  '<div>'.formatReady($this->tree->getValue($this->edit_item_id, $show_field)).'&nbsp;</div>';
				$content .= "</td></tr>";
			} else {
				if($answerfield) $content .=  '<div>'.formatReady($this->tree->getValue($this->edit_item_id, 'answer')).'&nbsp;</div>';
				$edit_field = 'feedback';
				$edit_desc = _("Rückmeldung und Antworten:");
				$show_field = 'notes';
				$show_desc = _("Anmerkungen:");
				$content .= "\n<tr><td class=\"steelgraulight\" style=\"font-size:10pt;".(!$answerfield ? 'border-top: 1px solid black;':'')."border-left: 1px solid black;border-right: 1px solid black;\" ><b>". $show_desc . "</b>"
						. "</td></tr>";
				$content .= "<tr><td class=\"steel1\" align=\"left\" style=\"font-size:10pt;border-left: 1px solid black;border-right: 1px solid black;\">";
				$content .=  '<div>'.formatReady($this->tree->getValue($this->edit_item_id, $show_field)).'&nbsp;</div>';
				$content .= "</td></tr>";
				$content .= "\n<tr><td class=\"steelgraulight\" style=\"font-size:10pt;border-left: 1px solid black;border-right: 1px solid black;\" ><b>". $edit_desc ."</b></td></tr>";
				$content .= "<tr><td class=\"steel1\" align=\"left\" style=\"font-size:10pt;".(!$this->admin ? 'border-bottom: 1px solid black;' : '')."border-left: 1px solid black;border-right: 1px solid black;\">
							<textarea class=\"add_toolbar resizable\" name=\"task_$edit_field\" style=\"width:100%\" rows=\"30\">"
						. htmlReady($this->tree->getValue($this->edit_item_id, $edit_field)). "</textarea></td></tr>";
			}
			if ($answerfield) $content .= "</td></tr>";
			if($this->admin){
				$content .= "\n<tr><td class=\"steelgraulight\" style=\"font-size:10pt;border-left: 1px solid black;border-right: 1px solid black;\" ><b>". _("Bearbeitung unabhängig von Erledigungszeit erlaubt bis:") . "</b>"
					. "</td></tr>";
				$content .= "<tr><td class=\"steel1\" align=\"left\" style=\"font-size:10pt;border-left: 1px solid black;border-right: 1px solid black;\">
				<input name=\"task_answer_allowed_day\" value=\"".($this->tree->getValue($this->edit_item_id,'answer_allowed') ? date("d",$this->tree->getValue($this->edit_item_id,'answer_allowed')) : '')."\" size=\"2\" maxlength=\"2\" type=\"text\">
						 &nbsp;
						 <input name=\"task_answer_allowed_month\" value=\"".($this->tree->getValue($this->edit_item_id,'answer_allowed') ? date("m",$this->tree->getValue($this->edit_item_id,'answer_allowed')) : '')."\" size=\"2\" maxlength=\"2\" type=\"text\">
						 &nbsp;
						 <input name=\"task_answer_allowed_year\"value=\"".($this->tree->getValue($this->edit_item_id,'answer_allowed') ? date("Y",$this->tree->getValue($this->edit_item_id,'answer_allowed')) : '')."\" size=\"4\" maxlength=\"4\" type=\"text\">
						 &nbsp;
						 <img align=\"absmiddle\" src=\"{$GLOBALS['PLUGIN_ASSETS_URL']}images/popupcalendar.png\" border=\"0\" onClick=\"window.open('{$GLOBALS['ABSOLUTE_URI_STUDIP']}termin_eingabe_dispatch.php?form_name=item_form&element_switch=task_answer_allowed&imt=".($this->tree->getValue($this->edit_item_id,'answer_allowed') ? $this->tree->getValue($this->edit_item_id,'answer_allowed') : time())."&atime=".$this->tree->getValue($this->edit_item_id,'answer_allowed')."', 'InsertDate', 'dependent=yes, width=210, height=210, left=500, top=150')\">";
				if($this->tree->getValue($this->edit_item_id,'answer_allowed')) {
						$content .= "&nbsp;<img ".tooltip(_("Eintrag entfernen"))." align=\"absmiddle\" src=\"{$GLOBALS['PLUGIN_ASSETS_URL']}images/icons/16/blue/refresh.png\" onclick=\"jQuery('input[name^=task_answer_allowed_]').val('');\">";
				}
				$content .= "</td></tr>";
				$content .= "\n<tr><td class=\"steelgraulight\" style=\"font-size:10pt;border-left: 1px solid black;border-right: 1px solid black;\" ><b>". _("Antwort sichtbar für alle Studierenden:") . "</b>"
						. "</td></tr>";
				$content .= "<tr><td class=\"steel1\" align=\"left\" style=\"font-size:10pt;border-left: 1px solid black;border-bottom: 1px solid black;border-right: 1px solid black;\">
						<input type=\"radio\" ".($this->tree->getValue($this->edit_item_id,'answer_is_visible') ? 'checked' : '')." name=\"task_answer_is_visible\" value=\"1\" style=\"vertical-align:top\">
						Ja
						&nbsp;
						<input type=\"radio\"  ".(!$this->tree->getValue($this->edit_item_id,'answer_is_visible') ? 'checked' : '')." name=\"task_answer_is_visible\" value=\"0\" style=\"vertical-align:top\">
						Nein
						</td></tr>";
			}
		}
		$content .= "<tr><td class=\"steel1\">&nbsp;</td></tr><tr><td class=\"steel1\" align=\"center\"><input type=\"image\" "
				. makeButton("speichern","src") . tooltip("Eingaben speichern") . " border=\"0\">"
				. "&nbsp;<a href=\"" . $this->getSelf("cmd=Cancel&item_id="
				. $this->edit_item_id) . "\">"
				. "<img " .makeButton("abbrechen","src") . tooltip(_("Aktion abbrechen"))
				. " border=\"0\"></a>"
				. "&nbsp;&nbsp;
				<a href=\"". UrlHelper::getLink('show_smiley.php')."\" target=\"_blank\"><font size=\"-1\">Smileys</a>&nbsp;&nbsp;
				<a href=\"".format_help_url("Basis.VerschiedenesFormat")."\" target=\"_blank\">
				<font size=\"-1\">Formatierungshilfen</font></a></td></tr>";

		return $content;
	}

	function getItemMessage($item_id,$colspan = 1){
		$content = "";
		if (is_array($this->msg[$item_id])){
			$content = "\n<tr><td colspan=\"{$colspan}\"><table border=\"0\" cellspacing=\"0\" cellpadding=\"2\" width=\"100%\" style=\"font-size:10pt\">"
						. parse_msg_array_to_string($this->msg[$item_id],'steel1',1,0,1)
						."</table></td></tr><tr>";
		}
		return $content;
	}
	function getSelf($param = false){
		$url = $this->base_uri . "&" . "foo=" . rand();
		if ($this->mode)
			$url .= "&mode=" . $this->mode;
		if ($param)
			$url .= "&" . str_replace('cmd', 'elmocmd', $param);
		$url .= "#anchor";
		return $url;
	}


	function showTree($item_id = "root"){
		parent::showTree($item_id);
		foreach(array_keys((array)$this->open_items) as $item){
			if($this->tree->isTaskElement($item)){
				PluginVisits::SetVisit($this->plugin, $item, 'task');
			}
			if($this->tree->isAnswerElement($item)){
				PluginVisits::SetVisit($this->plugin, md5($item), 'answer');
			}
		}
	}
}
?>
