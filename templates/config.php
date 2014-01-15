<div style="background-color:white;padding-top:5px;padding-bottom:5px;padding-left:20px;padding-right:20px;">
<?if(count($msg)){?>
	<table width="100%">
	<?=parse_msg_array($msg,'blank',1,0)?>
	</table>
<?}?>
<form name="elmoconfig" action="<?=PluginEngine::getLink($plugin, array('action' => 'config')) ?>" method="POST">
  <table class="zebra" width="100%" cellpadding="2" cellspacing="0">
     <tr>
      <td align="center" colspan="2">
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
      </td>
    </tr>

    <tr>
      <td colspan="2" style="font-weight:bold;">
        <?= _("Anpassung des Themenbereiches:") ?>
      </td>
     </tr>
	<tr>
      <td width="30%" style="padding-left:20px;">
        <?= _("Bezeichnung des thematischen Inputs:")?>
       </td>
	   <td>
		<input type="text" name="elmo_inputname" style="vertical-align:middle" value="<?=htmlReady($config['ELMO_INPUTNAME'])?>" size="80">
      </td>
    </tr>
	<tr>
	<tr>
      <td width="30%" style="padding-left:20px;">
        <?= _("Bezeichnung der Aufgabenstellung:")?>
       </td>
	   <td>
		<input type="text" name="elmo_taskname" style="vertical-align:middle" value="<?=htmlReady($config['ELMO_TASKNAME'])?>" size="80">
      </td>
    </tr>
	<tr>
      <td width="30%" style="padding-left:20px;">
        <?= _("Bezeichnung der Antwort:")?>
       </td>
	   <td>
		<input type="text" name="elmo_answername" style="vertical-align:middle" value="<?=htmlReady($config['ELMO_ANSWERNAME'])?>" size="80">
      </td>
    </tr>
	<tr>
      <td width="30%" style="padding-left:20px;">
        <?= _("Termintyp für Aufgabenstellung:")?>
       </td>
	   <td>
	   <select name="elmo_task_date_typ">
	  <?foreach($GLOBALS['TERMIN_TYP'] as $i => $termin){
		  ?>
		  <option value="<?=$i?>" <?=($config['ELMO_TASK_DATE_TYP'] == $i ? 'selected' : '')?>><?=$termin['name']?></option>
		  <?
	  }?>
	   </select>
      </td>
    </tr>
	<tr>
      <td colspan="2" style="font-weight:bold;padding-top:10px;">
        <?= _("Anpassung der Übersicht:") ?>
      </td>
     </tr>
	<tr>
      <td width="30%" style="padding-left:20px;">
        <?= _("Angezeigter Name in der Menüleiste:")?>
       </td>
	   <td>
		<input type="text" name="elmo_displayname" style="vertical-align:middle" value="<?=htmlReady($config['ELMO_DISPLAYNAME'])?>" size="80">
      </td>
    </tr>
	<tr>
      <td width="30%" style="padding-left:20px;">
        <?= _("Einleitungstext in der Übersicht:")?>
       </td>
	   <td>
	   <textarea name="elmo_intro" class="add_toolbar resizable" cols="80" rows="5"><?=htmlReady($config['ELMO_INTRO'])?></textarea>
      </td>
    </tr>
    <tr>
      <td align="center" colspan="2">
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
      </td>
    </tr>
  </table>
</form>
</div>