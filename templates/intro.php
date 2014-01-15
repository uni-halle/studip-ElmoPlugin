<div style="background-color:white;padding-top:5px;padding-bottom:5px;padding-left:5px;padding-right:5px;">

<table width="100%" border="0" cellpadding="2" cellspacing="0" >
	<tr>
		<td class="blank" width="99%" align="left" valign="top">
		<?if($GLOBALS['perm']->have_studip_perm('tutor', $plugin->getId()) && !$_REQUEST['edit_intro']){?>
			<div style="padding:5px;">
			<a href="<?=PluginEngine::getLink($plugin, array('action' => 'main','edit_intro' => 1))?>">
			<img src="<?=$GLOBALS['PLUGIN_ASSETS_URL']?>images/icons/16/black/add/comment.png" <?=tooltip(_("Einleitungstext anpassen"))?>></a>
			</div>
		<?}?>
		<?if($plugin->config['ELMO_INTRO'] && !$_REQUEST['edit_intro']){?>
			<div style="margin-left:5px;margin-right:5px;margin-bottom:10px;padding:5px;font-size:10pt;">
				<?=formatReady($plugin->config['ELMO_INTRO'])?>
			</div>
		<?}?>
		<?if($GLOBALS['perm']->have_studip_perm('dozent', $plugin->getId()) && $_REQUEST['edit_intro']){?>
			<form action="<?=PluginEngine::getLink($plugin, array('action' => 'main'))?>" method="post">
			<div style="margin-left:5px;margin-right:5px;padding:5px;font-size:10pt;">
			<textarea style="width:100%" name="elmo_intro" class="add_toolbar resizable" rows="30"><?=htmlReady($plugin->config['ELMO_INTRO'])?></textarea>
			</div>
			<div style="margin-left:5px;margin-right:5px;margin-bottom:10px;padding:5px;font-size:10pt;text-align:center">
			<?= makeButton('uebernehmen', 'input', _("Eingaben abspeichern"), 'save') ?>
        &nbsp;
        <a href="<?=PluginEngine::getLink($plugin)?>">
          <?= makeButton('abbrechen', 'img', _("Eingabe abbrechen"), 'cancel') ?>
        </a>
		&nbsp;
		&nbsp;
		<a href="<?php echo UrlHelper::getLink('dispatch.php/smileys')?>" target="_blank"><font size="-1">Smileys</a>&nbsp;&nbsp;
	<a href="<?=format_help_url("Basis.VerschiedenesFormat")?>" target="_blank">
	<font size="-1">Formatierungshilfen</font></a>
			</div>
			</form>
		<?}?>
			<div class="lightgrey" style="margin-left:5px;margin-right:5px;margin-bottom:10px;padding:5px;font-size:10pt;border:1px solid">
				<h3 style="margin-top:5px;">Thematische Gliederung</h3>
				<ol>
				<?
				foreach($issues as $issue_id => $issue){
					$is_visible = ($issue['is_visible'] && $issue['visible_from'] < time());
					if(!$is_visible && !$GLOBALS['perm']->have_studip_perm('dozent', $plugin->getId())) continue;
					?>
					<li style="list-style-type:none;font-size:10pt;padding-top:10px;">
					<a class="tree" style="font-size:10pt;" href="<?=$base_uri?>&issue_id=<?=$issue_id?>&open_item=root">
					<b><?=htmlReady($issue['title'] . ($is_visible ? '' : _(" (unsichtbar)")));?></b>
					<?
					if($content[$issue_id]['new']) echo '<span style="font-size:8pt;padding:5px;color:red">(' . _("geändert") . ')</span>';
					?>
					</a>
					<?if(count($issue['tasks'])){
						echo '<ul>';
						foreach($issue['tasks'] as $task_id => $task){
						echo '<li style="list-style-type:none; font-size:12pt;padding:0px;">';
						echo '<a class="tree" style="font-size:10pt;" href="'.$base_uri.'&issue_id='.$issue_id;
						if(!$GLOBALS['perm']->have_studip_perm('dozent', $plugin->getId())){
							echo '&open_item='.$task_id.'-'.$GLOBALS['user']->id;
						}
						echo '#anchor">';
						echo htmlready($plugin->config['ELMO_TASKNAME'] .': '. $task['title']);
						echo '</a>';
						if($task['task_completion']) echo '<span style="font-size:8pt;padding:5px;">(' . _("Zu erledigen bis:").strftime(" %d.%m.%Y ", $task['task_completion']) . ')</span>';
						if($content[$task_id]['new']) echo '<span style="font-size:8pt;padding:5px;color:red">' . sprintf(_("%s Neue Beiträge/Dokumente"), $content[$task_id]['new']) . '</span>';
						echo '</li>';
						}
						echo '</ul>';
					}?>
					</li>
					<?
				}
				?>
				</ol>
			</div>
		</td>

		<td class="blank" align="center" valign="top">
			<?=$this->render_partial('shoutbox.php');?>
		</td>
	</tr>
	<tr>
		<td class="blank" colspan="2">
		&nbsp;
		</td>
	</tr>
</table>
</div>