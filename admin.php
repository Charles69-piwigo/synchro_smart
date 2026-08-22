<?php
if (!defined('PHPWG_ROOT_PATH'))
{
  die('Hacking attempt!');
}

global $template;

// interception AJAX : aucune sortie HTML n'a encore ete produite a ce stade
if (isset($_POST['syncfast_start']) || isset($_POST['syncfast_chunk']))
{
  include(SYNCFAST_PATH . 'admin/sync_ajax.php');
  exit();
}

include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');
include_once(SYNCFAST_PATH . 'admin/functions.inc.php');

$site_id = syncfast_get_local_site_id();
$albums = $site_id > 0 ? syncfast_get_albums_tree($site_id) : array();

$template->assign(
  array(
    'SYNCFAST_ADMIN' => SYNCFAST_ADMIN,
    'SYNCFAST_TOKEN' => get_pwg_token(),
    'syncfast_albums' => $albums,
  )
);

$template->set_filenames(
  array(
    'plugin_admin_content' => SYNCFAST_PATH . 'template/sync.tpl',
  )
);

$template->assign_var_from_handle('ADMIN_CONTENT', 'plugin_admin_content');
