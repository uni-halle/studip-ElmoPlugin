<?php
require_once "lib/classes/Seminar.class.php";
require_once "lib/classes/Modules.class.php";
require_once "ElmoTaskListView.class.php";
require_once "ElmoShoutbox.class.php";
if(!class_exists("PluginVisits")){
	require_once "PluginVisits.class.php";
}
class ElmoPlugin extends AbstractStudIPStandardPlugin {

	var $template_factory;
	var $config = array();
	var $issues = array();
	var $is_not_activatable = false;

	/**
	 *
	 */
	function ElmoPlugin(){
	    AbstractStudIPStandardPlugin::AbstractStudIPStandardPlugin();
	    if ($this->isActivated()) {
	        $this->initialize();
	    }
	}

	function initialize(){
	    $this->setPluginiconname('images/elmo_white.png');
	    $this->setChangeindicatoriconname('images/elmo_red_star.png');
	    $this->template_factory = new Flexi_TemplateFactory(dirname(__FILE__).'/templates/');
	    $this->restoreConfig();
	    $navigation = new PluginNavigation();//$this->getNavigation();
	    $navigation->setDisplayname(_("Übersicht"));
	    $navigation->addLinkParam('action', 'main');
	    $issues = array_keys($this->issues);
	    foreach($issues as $next_issue){
	        $nav = new PluginNavigation();
	        $nav->setDisplayname($this->issues[$next_issue]['title']);
	        $nav->addLinkParam('action', 'main');
	        $nav->addLinkParam('issue_id', $this->issues[$next_issue]['issue_id']);
	        $navigation->addSubmenu($nav);
	    }
	    if ($GLOBALS['perm']->have_studip_perm('tutor', $this->getId())){
	        $themen_nav = new PluginNavigation();
	        $themen_nav->setDisplayname(_("Themenliste"));
	        $themen_nav->addLinkParam('action', 'themen');
	        $navigation->addSubmenu($themen_nav);
	        $config_nav = new PluginNavigation();
	        $config_nav->setDisplayname(_("Anpassen"));
	        $config_nav->addLinkParam('action', 'config');
	        $navigation->addSubmenu($config_nav);
	        $import_nav = new PluginNavigation();
	        $import_nav->setDisplayname(_("Importieren"));
	        $import_nav->addLinkParam('action', 'import');
	        $navigation->addSubmenu($import_nav);
	    }
	    $navigation->setActiveImage($this->getPluginUrl() . '/images/elmo_black.png');
	    $this->setNavigation($navigation);
	    //check if documents are available and secured
	    $modules = new Modules();
	    $modules->writeStatus('documents', $this->getId(), true, 'sem');
	    $modules->writeStatus('documents_folder_permissions', $this->getId(), true, 'sem');
	}

	function getPluginName(){
		return 'Elmo';
	}

	function getDisplaytitle(){
	    return $this->config['ELMO_DISPLAYNAME'] ? $this->config['ELMO_DISPLAYNAME'] : _("Elearning Modul");
	}

	function isShownInOverview(){
	    $this->initialize();
	    $this->setPluginiconname('images/elmo_grey.png');
	    return count($this->issues);
	}


	function getOverviewMessage($has_changed = false){
		if($GLOBALS['perm']->have_perm('tutor')){
			$content = $this->checkChangedDozent();
			$answers = array_sum(array_map(create_function('$a', 'return $a["answers"];'), $content));
			$notes = array_sum(array_map(create_function('$a', 'return $a["notes"];'), $content));
			$documents = array_sum(array_map(create_function('$a', 'return $a["documents"];'), $content));
			if(array_sum(array($answers,$notes,$documents)) == 0 ){
				$ret = sprintf("%s: Keine Antworten", $this->getDisplayTitle());
			} else {
				$ret = sprintf("%s: %s Antworten,%s Anmerkungen,%s Dokumente", $this->getDisplayTitle(), $answers,$notes,$documents);
			}
			if($has_changed){
				$new = array_sum(array_map(create_function('$a', 'return $a["new"];'), $content));
				$ret .= "," . $new . " Neu";
			}
			return $ret;
		} else {
			$content = $this->checkChangedAutor();
			$feedback = array_sum(array_map(create_function('$a', 'return $a["feedback"];'), $content));
			$tasks = array_sum(array_map(create_function('$a', 'return $a["task"];'), $content));
			$issues = array_sum(array_map(create_function('$a', 'return $a["issue"];'), $content));
			$documents = array_sum(array_map(create_function('$a', 'return $a["documents"];'), $content));
			if(array_sum(array($issues,$feedback,$tasks,$documents)) == 0 ){
				$ret = sprintf("%s", $this->getDisplayTitle());
			} else {
				$ret = sprintf("%s: %s Themen,%s Aufgaben,%s Rückmeldungen,%s Dokumente", $this->getDisplayTitle(), $issues,$tasks,$feedback,$documents);
			}
			if($has_changed){
				$new = array_sum(array_map(create_function('$a', 'return $a["new"];'), $content));
				$ret .= "," . $new . " Neu";
			}
			return $ret;
		}
	}

	function hasChanged($lastviewed){
		if($GLOBALS['perm']->have_perm('tutor')){
			$new = array_sum(array_map(create_function('$a', 'return $a["new"];'), $this->checkChangedDozent()));
			return $new;
		} else {
			$new = array_sum(array_map(create_function('$a', 'return $a["new"];'), $this->checkChangedAutor()));
			return $new;
		}
	}

	function checkChangedDozent($modus = 'visitdate'){
		if(is_null($this->changed[$this->getId()])){
			$db = DBManager::Get();
			$this->changed[$this->getId()] = array();
			$st = $db->prepare("SELECT eta . * , pouv.visitdate,pouv.last_visitdate
							FROM themen t
							INNER JOIN elmo_tasks et
							USING ( issue_id )
							INNER JOIN elmo_tasks_answers eta
							USING ( task_id )
							INNER JOIN seminar_user su ON su.Seminar_id = t.seminar_id
							AND eta.user_id = su.user_id
							LEFT JOIN plugins_object_user_visits pouv ON object_id = MD5( CONCAT_WS( '-', eta.task_id, eta.user_id ) )
							AND pluginname = ?
							AND pouv.user_id = ?
							AND TYPE = 'answer'
							WHERE t.seminar_id = ?
							");
			$st->execute(array( $this->getPluginclassname(), $GLOBALS['user']->id, $this->getId()));
			while($row = $st->fetch()){
				$this->changed[$this->getId()][$row['task_id']]['answers'] += (bool)$row['chdate_answer'];
				$this->changed[$this->getId()][$row['task_id']]['notes'] += (bool)$row['chdate_notes'];
				$this->changed[$this->getId()][$row['task_id']]['new'] += ($row['chdate_notes'] > (int)$row[$modus]);
				$this->changed[$this->getId()][$row['task_id']]['new'] += ($row['chdate_answer'] > (int)$row[$modus]);

			}
			$st = $db->prepare("SELECT d.chdate, et.task_id, visitdate ,last_visitdate
						FROM themen t
						INNER JOIN elmo_tasks et
						USING ( issue_id )
						INNER JOIN seminar_user su ON su.seminar_id = t.seminar_id
						AND STATUS = 'autor'
						INNER JOIN folder f ON folder_id = MD5( CONCAT( et.task_id, '-', su.user_id, 'answers' ) )
						INNER JOIN dokumente d ON d.range_id = f.folder_id
						LEFT JOIN plugins_object_user_visits pouv ON object_id = MD5( CONCAT_WS( '-', et.task_id, su.user_id ) )
						AND pluginname = ?
						AND pouv.user_id = ?
						AND TYPE = 'answer'
						WHERE t.seminar_id = ?
						");
			$st->execute(array($this->getPluginclassname(), $GLOBALS['user']->id, $this->getId()));
			while($row = $st->fetch()){
				$this->changed[$this->getId()][$row['task_id']]['documents'] += (bool)$row['chdate'];
				$this->changed[$this->getId()][$row['task_id']]['new'] += ($row['chdate'] > (int)$row[$modus]);
			}
		}
		return $this->changed[$this->getId()];
	}

	function checkChangedAutor($modus = 'visitdate'){
		if(is_null($this->changed[$this->getId()])){
			$db = DBManager::Get();
			$this->changed[$this->getId()] = array();
			$st = $db->prepare("SELECT eta . * , pouv.visitdate,pouv.last_visitdate
							FROM themen t
							INNER JOIN elmo_tasks et
							USING ( issue_id )
							INNER JOIN elmo_tasks_answers eta
							ON eta.task_id=et.task_id AND eta.user_id=?
							LEFT JOIN plugins_object_user_visits pouv ON object_id = MD5( CONCAT_WS( '-', eta.task_id, eta.user_id ) )
							AND pluginname = ?
							AND pouv.user_id = ?
							AND TYPE = 'answer'
							WHERE t.seminar_id = ?
							");
			$st->execute(array($GLOBALS['user']->id, $this->getPluginclassname(), $GLOBALS['user']->id, $this->getId()));
			while($row = $st->fetch()){
				$this->changed[$this->getId()][$row['task_id']]['feedback'] += (bool)$row['chdate_feedback'];
				$this->changed[$this->getId()][$row['task_id']]['new'] += ($row['chdate_feedback'] > (int)$row[$modus]);

			}
			$st = $db->prepare("SELECT et . * , pouv.visitdate,pouv.last_visitdate
							FROM themen t
							INNER JOIN elmo_themen eth
							ON  t.issue_id = eth.issue_id AND eth.is_visible=1 AND eth.visible_from < UNIX_TIMESTAMP()
							INNER JOIN elmo_tasks et
							ON  eth.issue_id = et.issue_id
							LEFT JOIN plugins_object_user_visits pouv ON object_id = et.task_id
							AND pluginname = ?
							AND pouv.user_id = ?
							AND TYPE = 'task'
							WHERE t.seminar_id = ?
							");
			$st->execute(array($this->getPluginclassname(), $GLOBALS['user']->id, $this->getId()));
			while($row = $st->fetch()){
				$this->changed[$this->getId()][$row['task_id']]['task'] += (bool)$row['chdate'];
				$this->changed[$this->getId()][$row['task_id']]['new'] += ($row['chdate'] > (int)$row[$modus]);

			}
			$st = $db->prepare("SELECT d.chdate, et.task_id, visitdate, last_visitdate
						FROM themen t
						INNER JOIN elmo_themen eth
						ON  t.issue_id = eth.issue_id AND eth.is_visible=1 AND eth.visible_from < UNIX_TIMESTAMP()
						INNER JOIN elmo_tasks et
						ON  eth.issue_id = et.issue_id
						INNER JOIN folder f ON folder_id = et.task_id
						INNER JOIN dokumente d ON d.range_id = f.folder_id
						LEFT JOIN plugins_object_user_visits pouv ON object_id = et.task_id
						AND pluginname = ?
						AND pouv.user_id = ?
						AND type = 'task'
						WHERE t.seminar_id = ?
						");
			$st->execute(array( $this->getPluginclassname(), $GLOBALS['user']->id,$this->getId()));
			while($row = $st->fetch()){
				$this->changed[$this->getId()][$row['task_id']]['documents'] += (bool)$row['chdate'];
				$this->changed[$this->getId()][$row['task_id']]['new'] += ($row['chdate'] > (int)$row[$modus]);
			}
			$st = $db->prepare("SELECT eth . * , pouv.visitdate,pouv.last_visitdate
							FROM themen t
							INNER JOIN elmo_themen eth
							ON  t.issue_id = eth.issue_id AND eth.is_visible=1 AND eth.visible_from < UNIX_TIMESTAMP()
							LEFT JOIN plugins_object_user_visits pouv ON object_id = eth.issue_id
							AND pluginname = ?
							AND pouv.user_id = ?
							AND TYPE = 'issue'
							WHERE t.seminar_id = ?
							");
			$st->execute(array( $this->getPluginclassname(), $GLOBALS['user']->id, $this->getId()));
			while($row = $st->fetch()){
				$this->changed[$this->getId()][$row['issue_id']]['issue'] += (bool)$row['chdate'];
				$this->changed[$this->getId()][$row['issue_id']]['new'] += ($row['chdate'] > (int)$row[$modus]);

			}
			$st = $db->prepare("SELECT d.chdate, eth.issue_id, visitdate, last_visitdate
						FROM themen t
						INNER JOIN elmo_themen eth
						ON  t.issue_id = eth.issue_id AND eth.is_visible=1 AND eth.visible_from < UNIX_TIMESTAMP()
						INNER JOIN folder f ON folder_id = MD5( CONCAT( eth.issue_id,'elmo') )
						INNER JOIN dokumente d ON d.range_id = f.folder_id
						LEFT JOIN plugins_object_user_visits pouv ON object_id = eth.issue_id
						AND pluginname = ?
						AND pouv.user_id = ?
						AND type = 'issue'
						WHERE t.seminar_id = ?
						");
			$st->execute(array($this->getPluginclassname(), $GLOBALS['user']->id,$this->getId()));
			while($row = $st->fetch()){
				$this->changed[$this->getId()][$row['issue_id']]['documents'] += (bool)$row['chdate'];
				$this->changed[$this->getId()][$row['issue_id']]['new'] += ($row['chdate'] > (int)$row[$modus]);
			}
		}
		return $this->changed[$this->getId()];
	}

	function display_action($action) {
		PluginVisits::SetVisit($this);
		$this->$action();
	}

	function actionPrintview(){
		$issue_id = $this->unconsumed_path;
		if($issue_id){
			$seminar_id = $this->getId();
			if($GLOBALS['perm']->have_studip_perm('tutor', $seminar_id)){
				$seminar = Seminar::GetInstance($seminar_id);
				$tree = TreeAbstract::GetInstance("ElmoTaskList", array('issue_id' => $issue_id, 'is_admin' => true));
				$semester = SemesterData::GetInstance();
				$seminar_semester = $semester->getSemesterDataByDate($seminar->getSemesterStartTime());
				$dozenten = join(', ', array_map(create_function('$a', 'return $a["Nachname"];'), $seminar->getMembers('dozent')));
				PageLayout::removeStylesheet('style.css');
				PageLayout::addStylesheet('print.css');
				include 'lib/include/html_head.inc.php';
				echo "\n" . '<h1>' . htmlReady(sprintf("%s - %s (%s)", $seminar->getName(), $seminar_semester['name'], $dozenten)) . '</h1>';
				echo "\n" . '<h2>' . htmlReady($this->config['ELMO_INPUTNAME'] . ': ' . $tree->root_name) . '</h2>';
				echo "\n" . '<p style="padding:10px;">' . formatReady($tree->input, true, true);
				$docs = $tree->getDocumentList('root');
				if(count($docs)){
					echo '<ul>';
					foreach($docs as $doc_id => $doc){
						$titel = $doc["t_name"];
						if (($doc["filesize"] /1024 / 1024) >= 1) $titel .= "  (".round ($doc["filesize"] / 1024 / 1024)." MB)";
						else $titel .= "  (".round ($doc["filesize"] / 1024)." kB)";
						$titel .= "  ".strftime("%x-%X",$doc["chdate"]);
						echo'<li >'.htmlready($titel).'</li>';
					}
					echo '</ul>';
				}
				echo '</p>';
				foreach((array)$tree->getKids('root') as $task){
					echo "\n" . '<h3>' . htmlReady($this->config['ELMO_TASKNAME'] . ': ' . $tree->getValue($task,'name')) . '</h3>';
					echo "\n" . '<p style="padding:10px;">' . formatReady($tree->getValue($task,'description'), true, true);
					$docs = $tree->getDocumentList($task);
					if(count($docs)){
						echo '<ul>';
						foreach($docs as $doc_id => $doc){
							$titel = $doc["t_name"];
							if (($doc["filesize"] /1024 / 1024) >= 1) $titel .= "  (".round ($doc["filesize"] / 1024 / 1024)." MB)";
							else $titel .= "  (".round ($doc["filesize"] / 1024)." kB)";
							$titel .= "  ".strftime("%x-%X",$doc["chdate"]);
							echo'<li >'.htmlready($titel).'</li>';
						}
						echo '</ul>';
					}
					echo '</p>';
					foreach((array)$tree->getKids($task) as $answer){
						echo "\n" . '<h4>' . htmlReady($this->config['ELMO_ANSWERNAME'] . ': ' . $tree->getValue($answer,'name')) . '</h4>';
						echo "\n" . '<p style="padding:10px;">' . formatReady($tree->getValue($answer,'answer'), true, true);
						$docs = $tree->getDocumentList($answer);
						if(count($docs)){
							echo '<ul>';
							foreach($docs as $doc_id => $doc){
								$titel = $doc["t_name"];
								if (($doc["filesize"] /1024 / 1024) >= 1) $titel .= "  (".round ($doc["filesize"] / 1024 / 1024)." MB)";
								else $titel .= "  (".round ($doc["filesize"] / 1024)." kB)";
								$titel .= "  ".strftime("%x-%X",$doc["chdate"]);
								echo'<li >'.htmlready($titel).'</li>';
							}
							echo '</ul>';
						}
						echo '</p>';
					}

				}
				include 'lib/include/html_end.inc.php';
				page_close();
			}
		}
	}

	function actionShow(){
	    if (class_exists('PageLayout')) {
	        PageLayout::setHelpKeyword("Basis.Elmo");
	        PageLayout::setTitle($_SESSION['SessSemName']['header_line'] . ' - ' . $this->getDisplayTitle());
	    } else {
	        $GLOBALS['HELP_KEYWORD'] = "Basis.Elmo";
	        $GLOBALS['CURRENT_PAGE'] = $_SESSION['SessSemName']['header_line'] . ' - ' . $this->getDisplayTitle();
	    }
		include 'lib/include/html_head.inc.php';
		include 'lib/include/header.php';
		$GLOBALS['PLUGIN_ASSETS_URL'] = $this->getPluginURL() . '/';
		if($_REQUEST['action'] == 'config' && $GLOBALS['perm']->have_studip_perm('tutor', $this->getId())){
			$this->displayConfigPage();
		} elseif($_REQUEST['action'] == 'themen' && $GLOBALS['perm']->have_studip_perm('tutor', $this->getId())){
			$this->displayThemenPage();
		} elseif($_REQUEST['action'] == 'import' && $GLOBALS['perm']->have_studip_perm('tutor', $this->getId())){
            $this->displayImportPage();
		} elseif(isset($this->issues[$_REQUEST['issue_id']])) {
			PluginVisits::SetVisit($this,$_REQUEST['issue_id'],'issue');
			$this->displayMainPage($_REQUEST['issue_id']);
		} else {
			$this->displayIntroPage();
		}
		include 'lib/include/html_end.inc.php';
		page_close();
	}

	function displayThemenPage(){
        $msg = array();
		$db = new DB_Seminar();
		if(isset($_REQUEST['save_x']) || isset($_REQUEST['add_issue_x'])){
			$elmo_issues = is_array($_REQUEST['elmo_issues']) ? $_REQUEST['elmo_issues'] : array();
			if($_REQUEST['add_issue_x']){
				$elmo_issues['new_entry'] = array('issue_id' => 'new_entry', 'title' => 'Thema ' . (count($this->issues)+1) , 'priority' => count($this->issues));
			}
			foreach($elmo_issues as $issue_id => $data){
				$visible_from = 0;
				if(checkdate((int)$_REQUEST['accesstime_start_month'][$issue_id], (int)$_REQUEST['accesstime_start_day'][$issue_id], (int)$_REQUEST['accesstime_start_year'][$issue_id])){
					$visible_from = mktime(2, 0, 0,(int)$_REQUEST['accesstime_start_month'][$issue_id],(int)$_REQUEST['accesstime_start_day'][$issue_id],(int)$_REQUEST['accesstime_start_year'][$issue_id]);
				}
				if($visible_from && !$this->issues[$issue_id]['is_visible']){
					$is_visible = 1;
				} else if($this->issues[$issue_id]['is_visible'] && !$data['is_visible']) {
					$is_visible = 0;
					$visible_from = 0;
				} else {
					$is_visible = (int)$data['is_visible'];
				}
				$title = trim(stripslashes($data['title']));
				$answer_visible_default = (int)$data['answer_visible_default'];
				if($issue_id != 'new_entry'){
					$db->queryf("UPDATE themen SET title='%s' WHERE issue_id='%s'", mysql_escape_string($title), $issue_id);
					$themen_changed += $db->affected_rows();
					if($is_visible != $this->issues[$issue_id]['is_visible'] || $visible_from != $this->issues[$issue_id]['visible_from'] || $answer_visible_default != $this->issues[$issue_id]['answer_visible_default']){
						$db->queryf("UPDATE elmo_themen SET is_visible ='%s',visible_from='%s',answer_visible_default='%s' WHERE issue_id='%s'", $is_visible,$visible_from,$answer_visible_default,$issue_id);
						if($db->affected_rows()){
							$db->queryf("UPDATE elmo_themen SET chdate=UNIX_TIMESTAMP() WHERE issue_id='%s'", $issue_id);
							$themen_changed += $db->affected_rows();
						}
						if($answer_visible_default != $this->issues[$issue_id]['answer_visible_default']) {
						    $tasks = array_keys(ElmoTask::GetByIssue($issue_id));
							foreach($tasks as $task_id) {
								$db->queryf("UPDATE elmo_tasks_answers SET answer_is_visible ='%s' WHERE task_id='%s'", $answer_visible_default,$task_id);
							}
							$tview = new ElmoTaskListView($issue_id, $this);
							foreach($tasks as $task_id) {
							    if($tview->tree->getNumKids($task_id)) {
							        foreach($tview->tree->getKids($task_id) as $answer_id) {
							            $tview->checkFolder($answer_id);
							        }
							    }
        		            }
							$msg[] = array('msg', sprintf(_("Die Antworten im Thema: %s wurden %s gemacht."), $title, ($answer_visible_default ? _("sichtbar") : _("unsichtbar"))));
						}
					}
				} else {
					$db->queryf("INSERT INTO themen (seminar_id, title,issue_id,priority,mkdate,chdate)
						VALUES ('%s','%s', '%s', '%s', UNIX_TIMESTAMP(),UNIX_TIMESTAMP())",$this->getId(), mysql_escape_string($title), $issue_id = md5(uniqid('themen',1)), count($this->issues));
					$db->queryf("INSERT INTO elmo_themen (issue_id,is_visible,visible_from,answer_visible_default,chdate) VALUES ('%s','%s','%s','%s',UNIX_TIMESTAMP())", $issue_id,$is_visible,$visible_from,$answer_visible_default );
					$themen_changed += $db->affected_rows();
				}
			}
			if($themen_changed) $msg[] = array('msg', _("Themen wurden gespeichert."));

			$this->restoreConfig();
		}
		if(strlen($_REQUEST['delete_issue']) == 32){
		    $tasklist = TreeAbstract::getInstance("ElmoTaskList", array('issue_id' => $_REQUEST['delete_issue'], 'is_admin' => true, 'current_user_id' =>$GLOBALS['user']->id));
			if ($tasklist->getNumKids('root')) {
			    foreach($tasklist->getKids('root') as $kid){
			        $tasklist->deleteTask($kid);
			    }
			}
			$db->queryf("DELETE FROM themen WHERE issue_id='%s' LIMIT 1", $_REQUEST['delete_issue']);
			if($db->affected_rows()) {
				$msg[] = array('msg', _("Thema wurde gelöscht."));
				$this->restoreConfig();
			}
		}
		if(strlen($_REQUEST['move_up']) == 32){
			$issues = array_keys($this->issues);
			$p1 = array_search($_REQUEST['move_up'],$issues);
			if($p1 == 0){
				unset($issues[0]);
				$issues[] = $_REQUEST['move_up'];
			} else {
				$swap = $issues[$p1-1];
				$issues[$p1] = $swap;
				$issues[$p1-1] = $_REQUEST['move_up'];
			}
			foreach(array_values($issues) as $p => $issue_id){
				$db->queryf("UPDATE themen SET priority='%s' WHERE issue_id='%s'", $p, $issue_id);
			}
			$msg[] = array('msg', _("Die Themen wurde sortiert."));
			$this->restoreConfig();
		}
		if(strlen($_REQUEST['move_down']) == 32){
			$issues = array_keys($this->issues);
			$p1 = array_search($_REQUEST['move_down'],$issues);
			if($p1 == (count($issues)-1)){
				array_pop($issues);
				array_unshift($issues, $_REQUEST['move_down']);
			} else {
				$swap = $issues[$p1+1];
				$issues[$p1] = $swap;
				$issues[$p1+1] = $_REQUEST['move_down'];
			}
			foreach(array_values($issues) as $p => $issue_id){
				$db->queryf("UPDATE themen SET priority='%s' WHERE issue_id='%s'", $p, $issue_id);
			}
			$msg[] = array('msg', _("Die Themen wurde sortiert."));
			$this->restoreConfig();
		}

		$template = $this->template_factory->open('themen.php');
		$template->set_attribute('msg', $msg);
        $template->set_attribute('plugin', $this);
		$template->set_attribute('config', $this->config);
        echo $template->render();
	}

    function displayImportPage(){

    	$source_id =& $_SESSION[$this->getPluginname()]['source_id'];

        $msg = array();
        if(Request::submitted('do_search_source')){
            $search_what = Request::quoted('search_source');
            $result = search_range($search_what);
        }
        if(!$GLOBALS['perm']->have_perm('admin')){
            $result = search_range();
        }
        if(is_array($result)){
            $result = array_filter($result, create_function('$r', 'return $r["type"] == "sem";'));
            if(count($result)){
            	$activated = DBManager::get()
            	->query(
            	sprintf("SELECT substring(poiid,4) FROM plugins_activated WHERE pluginid=%s AND state='on' AND substring(poiid,4) IN ('%s')",
            	$this->getPluginid(), join("','", array_keys($result))))->fetchAll(PDO::FETCH_COLUMN);
            	foreach ($result as $i => $r) {
            		if(!in_array($i, $activated)) unset($result[$i]);
            	}
            }
            if(count($result)){
                if($GLOBALS['perm']->have_perm('admin')){
                    $msg[] = array('msg', sprintf(_("Ihre Sucher ergab %s Treffer."), count($result)));
                }
                $show_destination_result = !$GLOBALS['perm']->have_perm('admin') || Request::submitted('do_search_destination');
                $show_source_result = !$GLOBALS['perm']->have_perm('admin') || Request::submitted('do_search_source');
            }

        } else if ($GLOBALS['perm']->have_perm('admin') && $result === false){
            $msg[] = array('info', _("Ihre Suche ergab keine Treffer."));
        }

        if(Request::submitted('do_choose_source')){
            if(Request::option('search_source_result')){
                $source_id = Request::option('search_source_result');
            }
        }
        $semester = SemesterData::GetInstance();

        if($source_id){
            if(!$GLOBALS['perm']->have_studip_perm('tutor', $source_id)){
                throw new Studip_AccessDeniedException(_("Keine Berechtigung."));
            }
            $source = Seminar::getInstance($source_id);
            $seminar_semester = $semester->getSemesterDataByDate($source->getSemesterStartTime());
            $source_name = $source->getName() . ' ('.$seminar_semester['name'].')';
            $backup_issues = $this->issues;
            $backup_config = $this->config;
            $this->restoreConfig($source_id);
            $s_issues = $this->issues;
            $s_config = $this->config;
            $this->issues = $backup_issues;
            $this->config = $backup_config;

        }

        if (Request::submitted('do_import') && $source && count($s_issues)) {
        	$db = DbManager::get();
        	$this->config = $s_config;
        	if ($this->storeConfig()) {
        	   $imsg[] =  _("Konfiguration importiert");
        	}
        	foreach ($s_issues as $i) {
        		$db->exec(sprintf("INSERT INTO themen (seminar_id, title,issue_id,priority,mkdate,chdate)
                        VALUES ('%s','%s', '%s', '%s', UNIX_TIMESTAMP(),UNIX_TIMESTAMP())",
        		$this->getId(), mysql_escape_string($i['title']), $issue_id = md5(uniqid('themen',1)), count($this->issues)));
        		$db->exec(sprintf("INSERT INTO elmo_themen (issue_id,input,chdate) VALUES ('%s','%s',UNIX_TIMESTAMP())",
        		$issue_id,mysql_escape_string($i['input']) ));
        		$imsg[] = _("Thema importiert: ") . $i['title'];
        		$tasklist = TreeAbstract::getInstance("ElmoTaskList", array('issue_id' => $i['issue_id'], 'is_admin' => true, 'current_user_id' =>$GLOBALS['user']->id));
        		$docs = $tasklist->getDocumentList('root');
        		$folder_id = md5($issue_id .'elmo');
        		foreach (array_keys($docs) as $d) {
        			copy_doc($d, $folder_id, $this->getId());
        		}
        		$imsg[] =  _("Dateien zum Thema: ") . count($docs);
        		if ($tasklist->getNumKids('root')) {
        			foreach ($tasklist->getKids('root') as $task) {
        				$nt = new ElmoTask($task);
        				if (!$nt->isNew()) {
        					$nt->setId($nt->getNewId());
        					$nt->setValue('issue_id', $issue_id);
        					$nt->setValue('task_completion', 0);
        					$nt->setNew(true);
        					if ($nt->store()) {
        						$imsg[] =  _("Aufgabe importiert: ") . $nt->getValue('title');
        						$docs = $tasklist->getDocumentList($task);
        						$folder_id = $nt->getId();
        						foreach (array_keys($docs) as $d) {
        							copy_doc($d, $folder_id, $this->getId());
        						}
        						$imsg[] =  _("Dateien zur Aufgabe: ") . count($docs);
        					}
        				}
        			}
        		}
        		$tview = new ElmoTaskListView($issue_id, $this);
        		if($tview->tree->getNumKids('root')) {
        			foreach($tview->tree->getKids('root') as $task) {
        				$tview->checkFolder($task);
        			}
        		}
        	}
        	$source_id = $source = $source_name = $s_issues = null;
        	$this->restoreConfig();
        	$msg[] = array('info', '<ul><li>' . join('</li><li>', $imsg) . '</li></ul>');
        }
        $template = $this->template_factory->open('import.php');
        $template->set_attribute('is_admin', $GLOBALS['perm']->have_perm('admin'));
        $template->set_attribute('msg', $msg);
        $template->set_attribute('plugin', $this);
        $template->set_attribute('config', $this->config);
        $template->set_attribute('result', $result);
        $template->set_attribute('show_source_result', $show_source_result);
        $template->set_attribute('source_name', $source_name);
        $template->set_attribute('source_id', $source_id);
        $template->set_attribute('s_config', $s_config);
        $template->set_attribute('s_issues', $s_issues);

        echo $template->render();
    }

	function displayConfigPage(){
        $msg = array();
		$db = new DB_Seminar();
		if(isset($_REQUEST['save_x']) || isset($_REQUEST['add_issue_x'])){
			foreach(array('displayname','inputname','taskname','answername','task_date_typ','intro') as $name){
				$this->config[strtoupper('elmo_'.$name)] = trim(stripslashes($_REQUEST['elmo_'.$name]));
			}
			if($this->storeConfig()){
				$msg[] = array('msg', _("Die Bezeichnungen wurden gespeichert."));
			}
			$this->restoreConfig();
		}
		$template = $this->template_factory->open('config.php');
		$template->set_attribute('msg', $msg);
        $template->set_attribute('plugin', $this);
		$template->set_attribute('config', $this->config);
        echo $template->render();
	}

	function displayMainPage($issue_id){
		$_the_treeview = new ElmoTaskListView($issue_id, $this);
		$_the_treeview->base_uri = PluginEngine::getLink($this, array('action' => 'main','issue_id' => $issue_id));
		$_the_treeview->icon_path = $this->getPluginUrl() . '/images/';
		$_the_tree = $_the_treeview->tree;
		$_the_treeview->parseCommand();
		$_the_treeview->open_ranges['root'] = true;
		if($_REQUEST['open_item'] == 'root'){
			$_the_treeview->checkFolder($issue_id);
			if($_the_treeview->tree->getNumKidsKids($issue_id)){
				foreach($this->tree->getKidsKids($issue_id) as $kid){
					$_the_treeview->checkFolder($kid);
				}
			}
		}
		$this->handleShoutboxActions();
		$template = $this->template_factory->open('main.php');
		$template->set_attribute('pluginpath', $this->getPluginUrl());
		$template->set_attribute('base_uri', $_the_treeview->base_uri);
		$template->set_attribute('msg', $msg);
		$template->set_attribute('plugin', $this);
		$template->set_attribute('is_admin', $GLOBALS['perm']->have_studip_perm('tutor', $this->getId()));
		$template->set_attribute('_the_treeview', $_the_treeview);
		$template->set_attribute('_the_tree', $_the_tree);
		$template->set_attribute('shoutbox_dozent', ElmoShoutbox::GetBySeminar($this->getId(), 'dozent'));
		$template->set_attribute('shoutbox_autor', ElmoShoutbox::GetBySeminar($this->getId(), 'autor'));
		echo $template->render();
	}

	function displayIntroPage(){
		if(isset($_REQUEST['save_x']) && $GLOBALS['perm']->have_studip_perm('tutor', $this->getId())){
			$this->config['ELMO_INTRO'] = trim(stripslashes($_REQUEST['elmo_intro']));
			if($this->storeConfig()){
				$msg[] = array('msg', _("Die Bezeichnungen wurden gespeichert."));
			}
		}
		$content = ($GLOBALS['perm']->have_perm('tutor') ? $this->checkChangedDozent() : $this->checkChangedAutor());

		$base_uri = PluginEngine::getLink($this, array('action' => 'main'));
		$this->handleShoutboxActions();
		$issues = array();
		foreach($this->issues as $issue_id => $issue){
			$issues[$issue_id] = $issue;
			$issues[$issue_id]['tasks'] = ElmoTask::GetByIssue($issue_id);
		}
		$template = $this->template_factory->open('intro.php');
		$template->set_attribute('issues', $issues);
		$template->set_attribute('content', $content);
		$template->set_attribute('pluginpath', $this->getPluginUrl());
		$template->set_attribute('base_uri', $base_uri);
		$template->set_attribute('plugin', $this);
		$template->set_attribute('is_admin', $GLOBALS['perm']->have_studip_perm('tutor', $this->getId()));
		$template->set_attribute('shoutbox_dozent', ElmoShoutbox::GetBySeminar($this->getId(), 'dozent'));
		$template->set_attribute('shoutbox_autor', ElmoShoutbox::GetBySeminar($this->getId(), 'autor'));
		echo $template->render();
	}

	function handleShoutboxActions(){
	if(isset($_REQUEST['create_remark_x'])){
			$text = trim(stripslashes($_POST['elmo_remark_content']));
			if($text){
				$shoutbox = new ElmoShoutbox();
				$shoutbox->setValue('content', $text);
				$shoutbox->setValue('seminar_id', $this->getId());
				$shoutbox->setValue('user_id', $GLOBALS['user']->id);
				$shoutbox->setValue('type', $GLOBALS['perm']->have_studip_perm('tutor', $this->getId()) ? 'dozent' : 'autor');
				$shoutbox->store();
			}
		}
		if(isset($_REQUEST['kill_remark'])){
			$shoutbox = new ElmoShoutbox($_REQUEST['kill_remark']);
			if(!$shoutbox->isNew() && ($shoutbox->getValue('user_id') == $GLOBALS['user']->id || $GLOBALS['perm']->have_studip_perm('tutor', $this->getId()))){
				$shoutbox->delete();
			}
		}
	}

	function restoreConfig($seminar_id = ''){
		$this->config = array();
		$this->issues = array();
		$db = new DB_Seminar();
		if($seminar_id != '') $id = $seminar_id;
		else $id = $this->getId();
		$db->queryf("SELECT * FROM user_config WHERE user_id='%s' AND field LIKE 'ELMO%%'", $id);
		while($db->next_record()){
			$this->config[$db->f('field')] = trim($db->f('value'));
		}
		$this->config['ELMO_DISPLAYNAME']= $this->config['ELMO_DISPLAYNAME'] ? $this->config['ELMO_DISPLAYNAME'] : _("Elearning Modul");
		$this->config['ELMO_INPUTNAME'] = $this->config['ELMO_INPUTNAME'] ? $this->config['ELMO_INPUTNAME'] : 'Thema';
		$this->config['ELMO_TASKNAME'] = $this->config['ELMO_TASKNAME'] ? $this->config['ELMO_TASKNAME'] : 'Aufgabe';
		$this->config['ELMO_ANSWERNAME'] = $this->config['ELMO_ANSWERNAME'] ? $this->config['ELMO_ANSWERNAME'] : 'Antwort';
		$this->config['ELMO_TASK_DATE_TYP'] = $this->config['ELMO_TASK_DATE_TYP'] ? $this->config['ELMO_TASK_DATE_TYP'] : 8;
		$db->queryf("SELECT is_visible,input,visible_from,answer_visible_default,title,elmo_themen.chdate, themen.issue_id FROM themen INNER JOIN elmo_themen USING(issue_id) WHERE seminar_id='%s' ORDER BY priority", $id);
		while($db->next_record()){
			if($GLOBALS['perm']->have_studip_perm('tutor', $this->getId()) || ($db->f('is_visible') && $db->f('visible_from') < time())){
				$this->issues[$db->f('issue_id')] = $db->Record;
			}
		}
		return count($this->config);
	}

	function storeConfig() {
		$ret = 0;
		$new_config = $this->config;
		$this->restoreConfig();
		$db = new DB_Seminar();
		foreach($new_config as $field => $value){
			if($this->config[$field] != $value){
				$db->queryf("REPLACE INTO user_config (user_id, userconfig_id, field, value, chdate) VALUES ('%s','%s','%s','%s', UNIX_TIMESTAMP())",
							$this->getId(), md5($this->getId().$field), $field, mysql_escape_string($value));
				++$ret;
			}
		}
		$this->restoreConfig();
		return $ret;
	}

}
?>
