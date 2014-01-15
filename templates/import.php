<div style="background-color:white;padding-top:5px;padding-bottom:5px;padding-left:20px;padding-right:20px;">
<?if(count($msg)){?>
    <table width="100%">
    <?=parse_msg_array($msg,'blank',1,0)?>
    </table>
<?}?>
<form name="elmoimport" action="<?=PluginEngine::getLink($plugin, array('action' => 'import')) ?>" method="POST">
<h2><?php echo _("Quelle:"). '&nbsp;' . htmlready($source_name)?>
<?php if($source_id):?>
    <a title="<?php echo _("Zur Quellveranstaltung springen")?>" href="<?php echo UrlHelper::getLink('seminar_main.php', array('cid' => $source_id))?>">
    <img src="<?php echo $GLOBALS['PLUGIN_ASSETS_URL'] . 'images/icons/16/blue/link-intern.png'?>">
    </a>
<?php endif?>
</h2>
<?php if($show_source_result):?>
    <label style="padding-right:10px;width:100px;display:block;float:left;" for="search_source_result">
    <?php echo _("Quelle wählen:")?>
    </label>
    <select name="search_source_result" id="search_source_result" >
    <?php foreach($result as $s_id => $data):?>
        <option value="<?php echo $s_id?>" <?php echo $source_id == $s_id ? 'selected' : ''?>>
        <?php echo htmlready($data['name'])?>
        </option>
    <?php endforeach?>
    </select>
    <?php if($is_admin):?>
        <input type="image" src="<?php echo $GLOBALS['PLUGIN_ASSETS_URL'].'images/icons/16/blue/refresh.png'?>" name="do_search_cancel">
    <?php endif?>
    <?php echo makeButton('auswaehlen', 'input', '','do_choose_source')?>

<?php else:?>
    <label style="padding-right:10px;width:100px;display:block;float:left;" for="search_destination">
    <?php echo _("Quelle suchen:")?>
    </label>
    <input type="text" id="search_source" name="search_source" size="40">
    <input style="vertical-align:middle;" type="image" src="<?php echo $GLOBALS['PLUGIN_ASSETS_URL'].'images/icons/16/blue/search.png'?>" name="do_search_source">
<?php endif?>

<? if ($s_issues) {
	echo '<div>' . count($s_issues) .  _(" Themen gefunden") . '</div>';
	echo '<div>' . _("Import starten") . '&nbsp;' . makeButton('ok', 'input', '', 'do_import') . '</a></div>';

}

?>
</form>
</div>