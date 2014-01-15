CREATE TABLE IF NOT EXISTS `elmo_tasks` (
  `task_id` varchar(32) NOT NULL default '',
  `issue_id` varchar(32) NOT NULL default '',
  `title` varchar(255) NOT NULL default '',
  `description` text NOT NULL,
  `task_completion` int(10) unsigned NOT NULL default '0',
  `enable_answerfield` tinyint(3) unsigned NOT NULL default '0',
  `priority` tinyint(3) unsigned NOT NULL default '0',
  `chdate` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`task_id`),
  KEY `issue_id` (`issue_id`)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS `elmo_tasks_answers` (
  `task_id` varchar(32) NOT NULL default '',
  `user_id` varchar(32) NOT NULL default '',
  `answer` text NOT NULL,
  `notes` text NOT NULL,
  `feedback` text NOT NULL,
  `chdate_answer` int(10) unsigned NOT NULL default '0',
  `chdate_feedback` int(10) unsigned NOT NULL default '0',
  `chdate_notes` int(10) unsigned NOT NULL default '0',
  `answer_allowed` int(10) unsigned NOT NULL default '0',
  `answer_is_visible` tinyint(3) unsigned NOT NULL default '0',
  PRIMARY KEY  (`task_id`,`user_id`)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS `elmo_themen` (
  `issue_id` varchar(32) NOT NULL default '',
  `is_visible` tinyint(3) unsigned NOT NULL default '0',
  `input` text NOT NULL,
  `visible_from` int(10) unsigned NOT NULL default '0',
  `answer_visible_default` tinyint(3) unsigned NOT NULL default '0',
  `chdate` int(10) unsigned NOT NULL,
  PRIMARY KEY  (`issue_id`)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS `elmo_shoutbox` (
  `id` varchar(32) NOT NULL default '',
  `seminar_id` varchar(32) NOT NULL default '',
  `user_id` varchar(32) NOT NULL default '',
  `content` text NOT NULL,
  `type` enum('autor','dozent') NOT NULL default 'autor',
  `chdate` int(11) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `seminar_id` (`seminar_id`,`type`)
) ENGINE=MyISAM;
CREATE TABLE IF NOT EXISTS `plugins_object_user_visits` (
  `object_id` char(32) binary NOT NULL,
  `user_id` char(32) binary NOT NULL,
  `pluginname` char(50) binary NOT NULL,
  `type` char(10) binary NOT NULL,
  `visitdate` int(10) unsigned NOT NULL default '0',
  `last_visitdate` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`object_id`,`user_id`,`pluginname`,`type`)
) ENGINE=MyISAM;