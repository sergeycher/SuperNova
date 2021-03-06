<?php

define('IN_ADMIN', true);

require('../includes/init.' . substr(strrchr(__FILE__, '.'), 1));

if($user['authlevel'] < 3)
{
  message($lang['sys_noalloaw'], $lang['sys_noaccess']);
  die();
}

define('IN_AJAX', true);

lng_include('admin');

$totaltime = microtime(true);

$ques = array(
//  'DELETE {{users}}.* FROM {{users}} WHERE `user_as_ally` IS NULL and `onlinetime` < unix_timestamp(now()) - ( 60 * 60 * 24 * 45) and metamatter_total <= 0;',

// FK_notes_owner  'DELETE FROM `{{notes}}`     WHERE `owner`          not in (select id from {{users}});',
// FK_fleet_owner  'DELETE FROM `{{fleets}}`    WHERE `fleet_owner`    not in (select id from {{users}});',
// FK 'DELETE FROM `{{buddy}}`     WHERE `sender`         not in (select id from {{users}});',
// FK  'DELETE FROM `{{buddy}}`     WHERE `owner`          not in (select id from {{users}});',
// Not used  'DELETE FROM `{{annonce}}`   WHERE `user`           not in (select id from {{users}});',
//  'DELETE FROM `{{messages}}`  WHERE `message_sender` not in (select id from {{users}});',
  'DELETE FROM `{{messages}}`  WHERE `message_owner`  not in (select id from {{users}});', // TODO NO FK
  'DELETE FROM `{{planets}}`   WHERE `id_owner`       not in (select id from {{users}}) AND id_owner <> 0;', // TODO NO FK
  'DELETE FROM {{aks}} WHERE `id` NOT IN (SELECT DISTINCT `fleet_group` FROM {{fleets}});',


  'DELETE FROM {{alliance}} WHERE id not in (select ally_id from {{users}} WHERE `user_as_ally` IS NOT NULL group by ally_id);',
//  'DELETE FROM {{statpoints}} WHERE stat_type=2 AND id_owner not in (select id from {{alliance}});', // TODO CHECK!

  // UBE reports
  'DELETE FROM `{{ube_report}}` WHERE `ube_report_time_combat` < DATE_SUB(now(), INTERVAL 60 day);',
  'DELETE FROM {{messages}} WHERE message_time < unix_timestamp(now()) - (60 * 60 * 24 * 30);',
  'DELETE FROM {{chat}} WHERE timestamp < unix_timestamp(now()) - (60 * 60 * 24 * 14);',

  // Recalculate Alliance members
  "UPDATE {{alliance}} as a LEFT JOIN (SELECT ally_id, count(*) as ally_memeber_count FROM {{users}} WHERE ally_id IS NOT NULL GROUP BY ally_id) as u ON u.ally_id = a.id
    SET a.`ally_members` = u.ally_memeber_count;",

  // Deleting empty Alliances
  'DELETE FROM {{alliance}} WHERE ally_members <= 0;',
  "UPDATE {{users}} SET ally_id = null, ally_name = null, ally_tag = null, ally_register_time = 0, ally_rank_id = 0 WHERE ally_id not in (select id from {{alliance}});",
);

// doquery('LOCK TABLES {{' . implode('}} WRITE, {{', $sn_cache->tables) . '}} WRITE');

foreach ($ques as $que)
{
  sn_db_transaction_start();
  $QryResult = doquery($que);

  $que = str_replace('{{', "", $que);
  $que = str_replace('}}', "", $que);
  $msg .= '<li>' . htmlspecialchars($que) . ' --- <font color=';
  if ($QryResult)
  {
    $msg .= 'green>OK.';
  }
  else
  {
    $msg .= 'red>FAILED!';
  };
  $msg .= '</font> ' . mysql_affected_rows($link) . ' ' . $lang['adm_records'];
  sn_db_transaction_commit();
  set_time_limit(120);
}

$config->db_saveItem('users_amount', db_user_count());

// doquery('UNLOCK TABLES');


include_once('../scheduler.php');
$totaltime = microtime(true) - $totaltime;

$result = $result ? "<li>{$lang['adm_stat_title']} - {$result}</li>" : '';
$result = '<div align="left"><ul>' . $msg . $result . '</ul></div>';
echo json_encode($result . ' ' . $totaltime);
