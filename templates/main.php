<div style="background-color:white;padding-top:5px;padding-bottom:5px;padding-left:5px;padding-right:5px;">
<?
if ($msg)	{
	echo "\n<table width=\"99%\" border=\"0\" cellpadding=\"2\" cellspacing=\"0\">";
	parse_msg ($msg,"§","blank",1,false);
	echo "\n</table>";
}
?>
<table width="100%" border="0" cellpadding="2" cellspacing="0">
	<tr>
		<td class="blank" width="99%" align="left" valign="top">
			<table width="100%" border="0" cellpadding="2" cellspacing="0">
				<tr>
					<td align="center">
					<?
					$_the_treeview->showTree();
					?>
					</td>
				</tr>
			</table>
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
