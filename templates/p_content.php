 <tr>
 <td class="lightgrey" colspan="2" valign="top">
 <div style="font-size: 8pt;padding: 2px; width: 100%; height:200px;overflow: auto;">
 <?foreach($content as $entry){?>
	 <div  style="font-weight:bold;">
		 <?=date('d.m.Y H:i', $entry['chdate'])?>
		 &nbsp;
		<a href="about.php?username=<?=$entry['username']?>">
		<?=htmlready($entry['fullname'])?>
		</a>:
	</div>
	<div style="padding:2px;">
	 <?=formatReady($entry['content'])?>
	 <?if($entry['user_id'] == $GLOBALS['user']->id || $is_admin){?>
		 <div style="text-align: right;">
		 <a href="<?=$base_uri?>&kill_remark=<?=$entry['id']?>">
		 <img src="<?=$GLOBALS['PLUGIN_ASSETS_URL']?>images/icons/16/black/trash.png" border="0" <?=tooltip(_("Eintrag l�schen"))?>>
		 </a>
		 </div>
 	<?}?>
	</div>
 <?}?>
 </div>
 </td>
 </tr>
