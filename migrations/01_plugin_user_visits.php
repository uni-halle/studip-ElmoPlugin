<?
class PluginUserVisits extends DBMigration
{
    function description ()
    {
        return 'database changes for user visits';
    }

    function up ()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `plugins_object_user_visits` (
				  `object_id` char(32) binary NOT NULL,
				  `user_id` char(32) binary NOT NULL,
				  `pluginname` char(50) binary NOT NULL,
				  `type` char(10) binary NOT NULL,
				  `visitdate` int(10) unsigned NOT NULL default '0',
				  `last_visitdate` int(10) unsigned NOT NULL default '0',
				  PRIMARY KEY  (`object_id`,`user_id`,`pluginname`,`type`)
				) ENGINE=MyISAM;
				");
    }

    function down ()
    {

    }
}
?>