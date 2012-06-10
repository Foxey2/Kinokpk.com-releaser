<?php
/**
 * News viewver & admincp
 * @license GNU GPLv3 http://opensource.org/licenses/gpl-3.0.html
 * @package Kinokpk.com releaser
 * @author ZonD80 <admin@kinokpk.com>
 * @copyright (C) 2008-now, ZonD80, Germany, TorrentsBook.com
 * @link http://dev.kinokpk.com
 */


require "include/bittorrent.php";

INIT();
loggedinorreturn();

get_privilege('news_operation');

$action = (string)$_GET["action"];

//   Delete News Item    //////////////////////////////////////////////////////

if ($action == 'delete')
{
	$newsid = (int)$_GET["newsid"];
	if (!is_valid_id($newsid))
	$REL_TPL->stderr($REL_LANG->say_by_key('error'),$REL_LANG->say_by_key('invalid_id'));

	$returnto = makesafe($_GET["returnto"]);

	$REL_DB->query("DELETE FROM news WHERE id=$newsid");
	$REL_DB->query("DELETE FROM comments WHERE toid=$newsid AND type='news'");
	$REL_DB->query("DELETE FROM notifs WHERE type='newscomments' AND checkid=$newsid");

	$REL_CACHE->clearGroupCache("block-news");
	if ($returnto != "")
	safe_redirect($returnto);
	else
	$warning = $REL_LANG->_('News item successfully deleted');
}

elseif ($action == 'add')
{

	$subject = htmlspecialchars((string)$_POST["subject"]);
	if (!$subject)
	$REL_TPL->stderr($REL_LANG->say_by_key('error'),$REL_LANG->_('No subject defined'));

        $image = ((string)$_POST["image"]);
	if (!$image)
	$REL_TPL->stderr($REL_LANG->say_by_key('error'),$REL_LANG->_('No image defined'));

	$body = ((string)$_POST["body"]);
	if (!$body)
	$REL_TPL->stderr($REL_LANG->say_by_key('error'),$REL_LANG->_('No text defined'));

	$added = time();

	$REL_DB->query("INSERT INTO news (userid, added, body, image, subject) VALUES (".
	$CURUSER['id'] . ", $added, " . sqlesc($body) . ", " . sqlesc($image) . ", " . sqlesc($subject) . ")");

	$REL_CACHE->clearGroupCache("block-news");
	$warning = $REL_LANG->_('News item successfully added');

}

elseif ($action == 'edit')
{

	$newsid = (int)$_GET["newsid"];

	if (!is_valid_id($newsid))
	$REL_TPL->stderr($REL_LANG->say_by_key('error'),$REL_LANG->say_by_key('invalid_id'));

	if ($_SERVER['REQUEST_METHOD'] == 'POST')
	{
		$body = (string)$_POST['body'];
		$subject = htmlspecialchars((string)$_POST['subject']);

		if (!$subject)
		$REL_TPL->stderr($REL_LANG->say_by_key('error'),$REL_LANG->_('No subject defined'));

$image = ((string)$_POST["image"]);
	if (!$image)
	$REL_TPL->stderr($REL_LANG->say_by_key('error'),$REL_LANG->_('No image defined'));



		if (!$body)
		$REL_TPL->stderr($REL_LANG->say_by_key('error'), $REL_LANG->_('No text defined'));

		$body = sqlesc(($body));
$image = sqlesc(htmlspecialchars($image));
		$subject = sqlesc($subject);

		$editedat = sqlesc(time());

		$REL_DB->query("UPDATE news SET body=$body, image=$image, subject=$subject WHERE id=$newsid");

		$REL_CACHE->clearGroupCache("block-news");

		$returnto = makesafe($_POST['returnto']);

		if ($returnto != "")
		safe_redirect($returnto);
		else
		$warning = $REL_LANG->_('News item successfully edited');
	}
	else
	{
		$res = $REL_DB->query("SELECT * FROM news WHERE id=$newsid");

		if (mysql_num_rows($res) != 1)
		$REL_TPL->stderr($REL_LANG->say_by_key('error'),$REL_LANG->say_by_key('invalid_id'));

		$arr = mysql_fetch_array($res);
		$returnto = makesafe($_GET['returnto']);
		$REL_TPL->stdhead($REL_LANG->_('News editing'));
		print("<form method=post name=news action=\"".$REL_SEO->make_link('news','action','edit','newsid',$newsid)."\">\n");
		print("<table border=1 cellspacing=0 cellpadding=5>\n");
		print("<tr><td class=colhead>{$REL_LANG->_('News editing')}<input type=hidden name=returnto value=$returnto></td></tr>\n");
		print("<tr><td>{$REL_LANG->_('Subject')}: <input type=text name=subject maxlength=255 size=50 value=\"" . makesafe($arr["subject"]) . "\"/></td></tr>");
                print("<tr><td>{$REL_LANG->_('Picture')}: <input type=text name=image maxlength=255 size=50 value=\"" . makesafe($arr["image"]) . "\"/></td></tr>");
		print("<tr><td style='padding: 0px'>");
		print textbbcode("body",$arr["body"]);
		//<textarea name=body cols=145 rows=5 style='border: 0px'>" . htmlspecialchars($arr["body"]) .
		print("</textarea></td></tr>\n");
		print("<tr><td align=center><input type=submit value='{$REL_LANG->_('Edit')}'></td></tr>\n");
		print("</table>\n");
		print("</form>\n");
		$REL_TPL->stdfoot();
		die;
	}
}

//   Other Actions and followup    ////////////////////////////////////////////

$REL_TPL->stdhead($REL_LANG->_('News'));
if ($warning)
print("<p><font size=-3>($warning)</font></p>");
print("<form method=post name=news action=\"".$REL_SEO->make_link('news','action','add')."\">\n");
print("<table border=1 cellspacing=0 cellpadding=5>\n");
print("<tr><td class=colhead>{$REL_LANG->_('Add news item')}</td></tr>\n");
print("<tr><td>{$REL_LANG->_('Subject')}: <input type=text name=subject maxlength=255 size=50 value=\"" . makesafe($arr["subject"]) . "\"/></td></tr>");
print("<tr><td>{$REL_LANG->_('Picture')}: <input type=text name=image maxlength=255 size=50 value=\"" . makesafe($arr["image"]) . "\"/></td></tr>");
print("<tr><td style='padding: 0px'>");
print textbbcode("body",$arr["body"]);
//<textarea name=body cols=145 rows=5 style='border: 0px'>
print("</textarea></td></tr>\n");
print("<tr><td align=center><input type=submit value='{$REL_LANG->_('Add')}' class=btn></td></tr>\n");
print("</table></form><br /><br />\n");

$REL_TPL->stdfoot();
?>