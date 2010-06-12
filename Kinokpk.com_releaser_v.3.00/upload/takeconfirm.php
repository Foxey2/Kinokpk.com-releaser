<?php
/*
 Project: Kinokpk.com releaser
 This file is part of Kinokpk.com releaser.
 Kinokpk.com releaser is based on TBDev,
 originally by RedBeard of TorrentBits, extensively modified by
 Gartenzwerg and Yuna Scatari.
 Kinokpk.com releaser is free software;
 you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.
 Kinokpk.com is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.
 You should have received a copy of the GNU General Public License
 along with Kinokpk.com releaser; if not, write to the
 Free Software Foundation, Inc., 59 Temple Place, Suite 330, Boston,
 MA  02111-1307  USA
 Do not remove above lines!
 */

require_once("include/bittorrent.php");
dbconn();
getlang('takeconfirm');
loggedinorreturn();

if (!is_valid_id($_GET["id"]))
stderr($tracker_lang['error'], $tracker_lang['invalid_id']);

$id = (int) $_GET["id"];
if (is_array($_POST["conusr"]))                            {
	$ids = @implode(",", array_map("intval", $_POST["conusr"]));
	sql_query("UPDATE invites SET confirmed=1 WHERE inviteid IN (" . $ids . ") AND confirmed=0".( get_user_class() < UC_SYSOP ? " AND inviter = $CURUSER[id]" : "")) or sqlerr(__FILE__,__LINE__);
	$cronrow = sql_query("SELECT * FROM cron WHERE cron_name IN ('rating_perinvite','rating_enabled')");
	while ($cronres = mysql_fetch_array($cronrow)) $CRON[$cronres['cron_name']] = $cronres['cron_value'];

	if ($CRON['rating_enabled']) {
		sql_query("UPDATE users SET ratingsum=ratingsum+{$CRON['rating_perinvite']} WHERE id IN($ids,$id)") or sqlerr(__FILE__,__LINE__);
	}

	$ids = explode(',',$ids);
	if ($ids)
	foreach ($ids as $id) {
		sql_query("INSERT INTO friends (userid,friendid,confirmed) VALUES ({$CURUSER['id']},$id,1)");

		write_sys_msg($id,sprintf($tracker_lang['invite_confirmed'],$CRON['rating_per_invite']),$tracker_lang['invite_confirmed_title']);
	}
}
safe_redirect(" invite.php?id=$id");

?>