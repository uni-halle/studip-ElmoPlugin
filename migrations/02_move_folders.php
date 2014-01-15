<?
class MoveFolders extends DBMigration
{
    function description ()
    {
        return 'move existing elmo folders to new top folder';
    }

    function up ()
    {
		
		//$this->db->query("ALTER TABLE `elmo_tasks_answers` CHANGE `answer_allowed` `answer_allowed` INT UNSIGNED NOT NULL ");
		//$this->db->query("ALTER TABLE `elmo_tasks` ADD INDEX ( `issue_id` )  ");
		
		$folders = array();
		$this->db->query("SELECT folder_id, Seminar_id FROM folder 
			INNER JOIN `elmo_themen` ON folder_id = MD5( CONCAT( issue_id, 'elmo' ) )
			INNER JOIN seminare ON MD5( CONCAT( Seminar_id, 'top_folder' ) ) = range_id");
		while($this->db->next_record()) $folders[] = $this->db->Record;
		foreach($folders as $folder){
			$this->db->queryf("INSERT IGNORE INTO folder (folder_id,range_id,user_id,name,description,permission,mkdate,chdate)
							VALUES('%s','%s','%s','%s','%s','%s',UNIX_TIMESTAMP(),UNIX_TIMESTAMP())",
							md5($folder['Seminar_id'] . 'elmo'),
							md5($folder['Seminar_id'] . 'top_folder'),
							$GLOBALS['user']->id,
							mysql_escape_string(chr(160) . _("Elearning Modul")),
							mysql_escape_string('Ablage für alle Dokumente zum Modul. Dieser Ordner wurde automatisch erstellt.'),
							5);
			$this->db->queryf("UPDATE folder SET range_id='%s' WHERE folder_id='%s'",
							md5($folder['Seminar_id'] . 'elmo'),
							$folder['folder_id']);
		}
    }

    function down ()
    {
        
    }
}
?>