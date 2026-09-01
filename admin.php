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

// meme tabsheet que la page native Outils > Synchroniser, onglet "Synchro Rapide"
// actif : l'evenement tabsheet_before_select reconstruit la liste complete
// (Synchronisation + Gestionnaire de sites via add_core_tabs, + notre onglet via
// syncfast_add_sync_tab) pour garder l'acces aux autres onglets depuis notre page.
// add_core_tabs() lit $my_base_url en global (cf. admin/include/add_core_tabs.inc.php)
include_once(PHPWG_ROOT_PATH . 'admin/include/tabsheet.class.php');
$my_base_url = get_root_url() . 'admin.php?page=';

$tabsheet = new tabsheet();
$tabsheet->set_id('site_update');
$tabsheet->select('sync_fast');
$tabsheet->assign();

$template->set_filenames(
  array(
    'plugin_admin_content' => SYNCFAST_PATH . 'template/sync.tpl',
  )
);

$template->assign_var_from_handle('ADMIN_CONTENT', 'plugin_admin_content');
