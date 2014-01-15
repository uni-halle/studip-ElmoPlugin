<?
require_once 'lib/object.inc.php';

class PluginVisits {
	
	private static $visits_data = array();
	
	public static function SetVisit($plugin, $object_id = null, $type = 'plugin', $user_id = null){
		$pluginname = $plugin->getPluginclassname();
		if(!$object_id) $object_id = $plugin->getId();
		if(!$user_id) $user_id = $GLOBALS['user']->id;
		$last_visit = (int)self::GetVisit($plugin, $object_id, $type, $user_id, false);
			if($last_visit < object_get_visit($plugin->getId(), 'sem', false, false)){
				$st = DBManager::get()->prepare("REPLACE INTO plugins_object_user_visits (pluginname,object_id,user_id,type,visitdate,last_visitdate) VALUES (?,?,?,?,UNIX_TIMESTAMP(),?)");
				$rs = $st->execute(array($pluginname, $object_id, $user_id, $type, $last_visit));
				$key = join('-', array($pluginname, $object_id, $user_id, $type));
				unset(self::$visits_data[$key]);
				return $rs;
		}
		return false;
	}
	
	public static function GetVisit($plugin, $object_id = null, $type = 'plugin', $user_id = null, $mode = 'last'){
		$pluginname = $plugin->getPluginclassname();
		if(!$object_id) $object_id = $plugin->getId();
		if(!$user_id) $user_id = $GLOBALS['user']->id;
		$key = join('-', array($pluginname, $object_id, $user_id, $type));
		if(!isset(self::$visits_data[$key])){
			$st = DBManager::get()->prepare("SELECT * FROM plugins_object_user_visits WHERE pluginname=? AND object_id=? AND user_id=? AND type=?");
			$st->execute(array($pluginname, $object_id, $user_id, $type));
			self::$visits_data[$key] = $st->fetch();
		}
		return $mode == 'last' ? (int)self::$visits_data[$key]['last_visitdate'] : (int)self::$visits_data[$key]['visitdate'];
	}
}

?>