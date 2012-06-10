<?php
/**
 * CRONJOB cleanup script
 * @license GNU GPLv3 http://opensource.org/licenses/gpl-3.0.html
 * @package Kinokpk.com releaser
 * @author ZonD80 <admin@kinokpk.com>
 * @copyright (C) 2008-now, ZonD80, Germany, TorrentsBook.com
 * @link http://dev.kinokpk.com
 */
header("Content-Type: image/gif");
@set_time_limit(0);
@ignore_user_abort(1);
date_default_timezone_set('UTC');

define ("IN_TRACKER",true);
define ("ROOT_PATH",dirname(__FILE__).'/');
require_once(ROOT_PATH.'include/secrets.php');
require_once(ROOT_PATH.'include/classes.php');
require_once(ROOT_PATH.'include/functions.php');
$time = time();

// connection closed
/* @var database object */
require_once(ROOT_PATH . 'classes/database/database.class.php');
$REL_DB = new REL_DB($db);
unset($db);
//$REL_DB->debug();

$REL_CONFIGrow = $REL_DB->query("SELECT * FROM cache_stats WHERE cache_name IN ('sitename','defaultbaseurl','siteemail','default_language','smtptype')");

while ($REL_CONFIGres = mysql_fetch_assoc($REL_CONFIGrow)) $REL_CONFIG[$REL_CONFIGres['cache_name']] = $REL_CONFIGres['cache_value'];
$REL_CONFIG['lang'] = $REL_CONFIG['default_language'];

/* @var object general cache object */
require_once(ROOT_PATH . 'classes/cache/cache.class.php');
$REL_CACHE=new Cache();
if (REL_CACHEDRIVER=='native') {
	require_once(ROOT_PATH .  'classes/cache/fileCacheDriver.class.php');
	$REL_CACHE->addDriver(NULL, new FileCacheDriver());
}
elseif (REL_CACHEDRIVER=='memcached') {
	require_once(ROOT_PATH .  'classes/cache/MemCacheDriver.class.php');
	$REL_CACHE->addDriver(NULL, new MemCacheDriver());
}

/* @var object links parser/adder/changer for seo */
require_once(ROOT_PATH . 'classes/seo/seo.class.php');
$REL_SEO = new REL_SEO();

/* @var object language system */
require_once(ROOT_PATH . 'classes/lang/lang.class.php');
$REL_LANG = new REL_LANG($REL_CONFIG);


$classes = init_class_array();

$cronrow = $REL_DB->query("SELECT * FROM cron WHERE cron_name IN ('in_cleanup','autoclean_interval','max_dead_torrent_time','pm_delete_sys_days','pm_delete_user_days','signup_timeout','ttl_days','announce_interval','delete_votes','rating_freetime','rating_enabled','rating_perleech','rating_perseed','rating_checktime','rating_dislimit','promote_rating','rating_max','remote_trackers_delete')");

while ($cronres = mysql_fetch_assoc($cronrow)) $REL_CRON[$cronres['cron_name']] = $cronres['cron_value'];

if ($REL_CRON['in_cleanup']) die('Cleanup already running');

$REL_DB->query("UPDATE cron SET cron_value=".time()." WHERE cron_name='last_cleanup'");

$REL_DB->query("UPDATE cron SET cron_value=1 WHERE cron_name='in_cleanup'");

if ($REL_CRON['remote_trackers_delete']) $REL_DB->query("DELETE FROM trackers WHERE num_failed > {$REL_CRON['remote_trackers_delete']}");

$torrents = array();
$res = $REL_DB->query('SELECT fid,seeders,leechers FROM xbt_files');
while ($row = mysql_fetch_assoc($res)) {
	$torrents["seeders = {$row['seeders']}, leechers={$row['leechers']}, lastchecked={$time}"][] = $row['fid'];
}

if ($torrents) {
	$ids = array();
	foreach ($torrents AS $to_set=> $to_ids) {
		$REL_DB->query("UPDATE trackers SET $to_set WHERE torrent IN (".implode(',',$to_ids).") AND tracker='localhost'");
		$ids = array_merge($ids,$to_ids);
	}
}

$REL_DB->query("UPDATE trackers SET seeders=0, leechers=0, lastchecked=$time WHERE tracker='localhost'".($ids?" AND torrent NOT IN (".implode(',',$ids).")":''));

$res = $REL_DB->query("SELECT torrent, SUM(seeders) AS seeders, SUM(leechers) AS leechers FROM trackers GROUP BY torrent");

$torrents = array();
while ($row = mysql_fetch_assoc($res)) {
	$torrents["seeders={$row['seeders']}, leechers={$row['leechers']}".($row['seeders']?", last_action=$time":'')][] = $row['torrent'];
}
if ($torrents) {
	foreach ($torrents AS $to_set=> $to_ids) {
		$REL_DB->query("UPDATE torrents SET $to_set WHERE id IN (".implode(',',$to_ids).")");
			
	}
}

// delete old system and user messages
$secs_system = $REL_CRON['pm_delete_sys_days']*86400;
$dt_system = time() - $secs_system;
$REL_DB->query("DELETE FROM messages WHERE sender = 0 AND archived = 0 AND archived_receiver = 0 AND unread = 0 AND added < $dt_system");

$secs_all = $REL_CRON['pm_delete_user_days']*86400;
$dt_all = time() - $secs_all;
$REL_DB->query("DELETE FROM messages WHERE unread = 0 AND archived = 0 AND archived_receiver = 0 AND added < $dt_all");


// delete unconfirmed users if timeout.
$deadtime = time() - ($REL_CRON['signup_timeout']*86400);
$res = $REL_DB->query("SELECT id FROM users WHERE confirmed=0 AND last_access < $deadtime");
if (mysql_num_rows($res) > 0) {
	while ($arr = mysql_fetch_array($res)) {
		delete_user($arr['id']);


	}
}
//disabled 5 times warned users
/*$res = $REL_DB->query("SELECT id, username, modcomment FROM users WHERE num_warned > 4 AND enabled = 1 ");
 $num = mysql_num_rows($res);
 while ($arr = mysql_fetch_assoc($res)) {
 $modcom = sqlesc(date("Y-m-d") . " - Отключен системой (5 и более предупреждений) " . "\n". $arr[modcomment]);
 $REL_DB->query("UPDATE users SET enabled = 0, dis_reason = 'Отключен системой (5 и более предупреждений)' WHERE id = $arr[id]");
 $REL_DB->query("UPDATE users SET modcomment = $modcom WHERE id = $arr[id]");
 write_log("Пользователь $arr[username] был отключен системой (5 и более предупреждений)","tracker");
 }
 */

/*
 * rating system? it moved to userlogin(), counting individually for each user
 * @see userlogin();
 */

//remove expired warnings
$now = time();
$modcomment = sqlesc(date("Y-m-d") . " - Предупреждение снято системой по таймауту.\n");
$msg = sqlesc("Ваше предупреждение снято по таймауту. Постарайтесь больше не получать предупреждений и следовать правилам.\n");
$REL_DB->query("INSERT INTO messages (sender, receiver, added, msg, poster) SELECT 0, id, $now, $msg, 0 FROM users WHERE warned=1 AND warneduntil < ".time()." AND warneduntil <> 0");
$REL_DB->query("UPDATE users SET warned=0, warneduntil = 0, modcomment = CONCAT($modcomment, modcomment) WHERE warned=1 AND warneduntil < ".time()." AND warneduntil <> 0");

// promote power users
/* MODIFY TO CLASS SYSTEM & XBT
 if ($REL_CRON['rating_enabled']) {
 $msg = sqlesc("Наши поздравления, вы были авто-повышены до ранга <b>Опытный пользовать</b>.");
 $subject = sqlesc("Вы были повышены");
 $modcomment = sqlesc(date("Y-m-d") . " - Повышен до уровня \"".$REL_LANG->say_by_key("class_power_user")."\" системой.\n");
 $REL_DB->query("UPDATE users SET class = ".UC_POWER_USER.", modcomment = CONCAT($modcomment, modcomment) WHERE class = ".UC_USER." AND ratingsum>={$REL_CRON['promote_rating']}");
 $REL_DB->query("INSERT INTO messages (sender, receiver, added, msg, poster, subject) SELECT 0, id, $now, $msg, 0, $subject FROM users WHERE class = ".UC_USER." AND ratingsum>={$REL_CRON['promote_rating']}");

 // demote power users
 $msg = sqlesc("Вы были авто-понижены с ранга <b>Опытный пользователь</b> до ранга <b>Пользователь</b> потому-что ваш рейтинг упал ниже <b>+{$REL_CRON['promote_rating']}</b>.");
 $subject = sqlesc("Вы были понижены");
 $modcomment = sqlesc(date("Y-m-d") . " - Понижен до уровня \"".$REL_LANG->say_by_key("class_user")."\" системой.\n");
 $REL_DB->query("INSERT INTO messages (sender, receiver, added, msg, poster, subject) SELECT 0, id, $now, $msg, 0, $subject FROM users WHERE class = 1 AND ratingsum<{$REL_CRON['promote_rating']}");
 $REL_DB->query("UPDATE users SET class = ".UC_USER.", modcomment = CONCAT($modcomment, modcomment) WHERE class = ".UC_POWER_USER." AND ratingsum<{$REL_CRON['promote_rating']}");
 }
 // delete old torrents MODIFY TO XBT!
 /*if ($REL_CRON['use_ttl']) {
 $dt = time() - ($REL_CRON['ttl_days'] * 86400);
 $res = $REL_DB->query("SELECT id, name FROM torrents WHERE last_action < $dt");
 while ($arr = mysql_fetch_assoc($res))
 {
 deletetorrent($arr['id']);
 write_log("Торрент $arr[id] ($arr[name]) был удален системой (старше чем {$REL_CRON['ttl_days']} дней)","torrent");
 }
 }
 */
// session update moved to include/functions.php
if ($REL_CRON['delete_votes']) {
	$secs = $REL_CRON['delete_votes']*60;
	$dt = time() - $secs;
	$REL_DB->query("DELETE FROM ratings WHERE added < $dt");
}
//$REL_CONFIG['defaultbaseurl'] = mysql_result($REL_DB->query("SELECT cache_value FROM cache_stats WHERE cache_name='defaultbaseurl'"),0);

require_once(ROOT_PATH . "include/createsitemap.php");

// sending emails

$emails = $REL_DB->query("SELECT * FROM cron_emails");

while ($message = mysql_fetch_assoc($emails)) {
	if (strpos(',', $message['emails'])) sent_mail('', $message['subject'].' | '.$REL_CONFIG['sitename'], $REL_CONFIG['siteemail'], $message['subject'], $message['body'],$message['emails']);
	else sent_mail($message['emails'], $message['subject'].' | '.$REL_CONFIG['sitename'], $REL_CONFIG['siteemail'], $message['subject'], $message['body']);

}
$REL_DB->query("TRUNCATE TABLE cron_emails");

$xbt = $REL_DB->query_return("SELECT * FROM xbt_config WHERE name='announce_interval'");
foreach ($xbt as $xbtconfrow) {
    $xbtconf[$xbtconfrow['name']] = $xbtconfrow['value'];
}

$REL_DB->query("DELETE FROM xbt_announce_log WHERE mtime < ".($time-$xbtconf['announce_interval']));
// delete expiried relgroups subsribes
$REL_DB->query("DELETE FROM rg_subscribes WHERE valid_until<$time AND valid_until<>0");

$REL_DB->query("UPDATE cron SET cron_value=cron_value+1 WHERE cron_name='num_cleaned'");
$REL_DB->query("UPDATE cron SET cron_value=0 WHERE cron_name='in_cleanup'");
//$REL_CACHE->clearCache('system','cat_tags');
print base64_decode("R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==");

?>