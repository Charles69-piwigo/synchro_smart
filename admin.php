<?php
if (!defined('PHPWG_ROOT_PATH'))
{
  die('Hacking attempt!');
}

global $template;

// interception AJAX : aucune sortie HTML n'a encore ete produite a ce stade
if (isset($_POST['syncsmart_start']) || isset($_POST['syncsmart_chunk']))
{
  include(SYNCSMART_PATH . 'admin/sync_ajax.php');
  exit();
}

include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');
include_once(SYNCSMART_PATH . 'admin/functions.inc.php');

$site_id = syncsmart_get_local_site_id();
$albums = $site_id > 0 ? syncsmart_get_albums_tree($site_id) : array();

$template->assign(
  array(
    'SYNCSMART_ADMIN' => SYNCSMART_ADMIN,
    'SYNCSMART_TOKEN' => get_pwg_token(),
    'syncsmart_albums' => $albums,
  )
);

// meme tabsheet que la page native Outils > Synchroniser, onglet "Synchro Smart"
// actif : l'evenement tabsheet_before_select reconstruit la liste complete
// (Synchronisation + Gestionnaire de sites via add_core_tabs, + notre onglet via
// syncsmart_add_sync_tab) pour garder l'acces aux autres onglets depuis notre page.
// add_core_tabs() lit $my_base_url en global (cf. admin/include/add_core_tabs.inc.php)
include_once(PHPWG_ROOT_PATH . 'admin/include/tabsheet.class.php');
$my_base_url = get_root_url() . 'admin.php?page=';

$tabsheet = new tabsheet();
$tabsheet->set_id('site_update');
$tabsheet->select('sync_smart');
$tabsheet->assign();

$template->set_filenames(
  array(
    'plugin_admin_content' => SYNCSMART_PATH . 'template/sync.tpl',
  )
);

$template->assign_var_from_handle('ADMIN_CONTENT', 'plugin_admin_content');
