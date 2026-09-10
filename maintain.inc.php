<?php

// pas de table SQL : la progression transite par la session PHP. Seule trace en
// base : le parametre de config 'syncsmart_album_filter_paths' (memoire des
// chemins de cibles de filtres SmartAlbums, cf. admin/functions.inc.php),
// nettoye a la desinstallation.

function plugin_install($plugin_id, $plugin_version, &$errors)
{
}

function plugin_activate($plugin_id, $plugin_version, &$errors)
{
}

function plugin_deactivate($plugin_id)
{
}

function plugin_uninstall()
{
  if (function_exists('conf_delete_param'))
  {
    conf_delete_param('syncsmart_album_filter_paths');
  }
}
