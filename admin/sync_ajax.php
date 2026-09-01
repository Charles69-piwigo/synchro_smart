<?php
// Point d'entree AJAX de la synchro par lots (pattern repris de face_tag) :
// _start compte le travail et initialise la session, _chunk avance d'un pas
// (un album pour les phases dirs/files, un lot de photos pour la phase meta)
// et renvoie du JSON. Rien n'est precalcule/mis en cache au-dela du perimetre
// (liste d'ids d'albums) : chaque appel _chunk relit l'etat reel en base/disque.

if (!defined('PHPWG_ROOT_PATH'))
{
  die('Hacking attempt!');
}

ob_start();

register_shutdown_function(
  function ()
  {
    $error = error_get_last();
    if (!$error)
    {
      return;
    }
    if (!in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR)))
    {
      return;
    }

    while (ob_get_level() > 0)
    {
      ob_end_clean();
    }

    if (!headers_sent())
    {
      header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode(array(
      'success' => false,
      'message' => 'PHP fatal: ' . $error['message'] . ' in ' . basename($error['file']) . ':' . $error['line'],
    ));
  }
);

// Piwigo core a deja demarre la session PHP dans include/common.inc.php

check_status(ACCESS_ADMINISTRATOR);
check_pwg_token();

include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');
include_once(SYNCFAST_PATH . 'admin/functions.inc.php');

function syncfast_progress_payload($state, $extra = array())
{
  $payload = array(
    'phase' => $state['phase'],
    'phase_index' => 0,
    'phase_total' => 0,
    'current_label' => '',
    'counters' => $state['counters'],
    'error_details' => $state['error_details'],
    'error_details_truncated' => $state['counters']['errors'] > count($state['error_details']),
    'done' => $state['phase'] === 'done',
  );

  switch ($state['phase'])
  {
    case 'dirs':
      $payload['phase_index'] = $state['dirs_done'];
      $payload['phase_total'] = $state['dirs_total'];
      break;
    case 'files':
      $payload['phase_index'] = $state['files_done'];
      $payload['phase_total'] = $state['files_total'];
      break;
    case 'meta':
      $payload['phase_index'] = $state['meta_index'];
      $payload['phase_total'] = $state['meta_total'];
      break;
  }

  return array_merge($payload, $extra);
}

// conserve un nombre plafonne d'erreurs detaillees (chemin + type) pour
// affichage dans le rapport ; au-dela, seul le compteur global continue
function syncfast_append_error_details(&$state, $details)
{
  foreach ($details as $detail)
  {
    if (count($state['error_details']) >= SYNCFAST_MAX_ERROR_DETAILS)
    {
      break;
    }
    $state['error_details'][] = $detail;
  }
}

// determine la prochaine phase a partir de l'etat courant et avance dedans si besoin
function syncfast_advance_phase(&$state, $site_id)
{
  if ($state['phase'] === 'dirs' && empty($state['dirs_queue']))
  {
    // en synchro "racine" (./galleries en totalite), le perimetre n'est pas
    // un sous-arbre d'ids coches mais tout ce qui existe desormais en base
    // pour ce site (y compris ce que la phase repertoires vient de creer)
    $state['cat_ids'] = $state['root_sync']
      ? syncfast_get_all_local_category_ids($site_id)
      : syncfast_resolve_scope($state['checked_ids'], $site_id, $state['recursive']);

    if ($state['operation'] === 'files')
    {
      $state['files_queue'] = $state['cat_ids'];
      $state['files_total'] = count($state['cat_ids']);
      $state['files_done'] = 0;
      $state['phase'] = 'files';
    }
    else
    {
      syncfast_enter_meta_or_done($state);
    }
  }
  elseif ($state['phase'] === 'files' && empty($state['files_queue']))
  {
    syncfast_enter_meta_or_done($state);
  }
}

// 'files' enchaine une passe meta des NOUVELLES photos (date_metadata_update
// IS NULL) ; 'meta' traite TOUTES les photos du perimetre ; 'dirs' s'arrete la
function syncfast_enter_meta_or_done(&$state)
{
  if ($state['operation'] === 'files' || $state['operation'] === 'meta')
  {
    // en mode "meta seule" (pas de scan repertoires/fichiers), aucune ligne
    // n'a encore alimente le compteur albums_analyzed : on le renseigne ici
    // avec le perimetre resolu, pour que le rapport ne montre pas 0 albums.
    if ($state['counters']['albums_analyzed'] === 0)
    {
      $state['counters']['albums_analyzed'] = count($state['cat_ids']);
    }
    $only_new = ($state['operation'] === 'files');
    $state['meta_total'] = syncfast_count_meta_targets($state['cat_ids'], $only_new);
    $state['meta_index'] = 0;
    $state['phase'] = 'meta';
  }
  else
  {
    $state['phase'] = 'done';
  }
}

$action = '';
if (isset($_POST['syncfast_start']))
{
  $action = 'start';
}
elseif (isset($_POST['syncfast_chunk']))
{
  $action = 'chunk';
}

$response = array('success' => false);

if ($action === 'start')
{
  $site_id = syncfast_get_local_site_id();
  $checked_ids = isset($_POST['cat_ids']) && is_array($_POST['cat_ids']) ? $_POST['cat_ids'] : array();
  $checked_ids = array_values(array_unique(array_filter(array_map('intval', $checked_ids))));

  // choix unique (radio) : dirs = repertoires seuls ; files = repertoires +
  // fichiers + meta des nouvelles photos ; meta = mise a jour meta de toutes
  // les photos deja en base, avec les options champ par champ ci-dessous
  $operation = isset($_POST['operation']) && in_array($_POST['operation'], array('dirs', 'files', 'meta'), true) ? $_POST['operation'] : '';
  $meta_desc = !empty($_POST['meta_desc']);
  $meta_desc_keep_rich = !empty($_POST['meta_desc_keep_rich']);
  $meta_title = !empty($_POST['meta_title']);
  $meta_author = !empty($_POST['meta_author']);
  $meta_tags = !empty($_POST['meta_tags']);
  $meta_tags_merge = !empty($_POST['meta_tags_merge']);
  $recursive = !empty($_POST['recursive']);
  // demande explicite du front (aucun album coche, confirmee par l'admin ou
  // amorce de la toute premiere synchro) : traiter ./galleries en totalite
  $root_sync = !empty($_POST['root_sync']);

  if ((empty($checked_ids) && !$root_sync) || !$operation || $site_id <= 0)
  {
    $response['message'] = l10n('Please select at least one album and one synchronization option.');
  }
  else
  {
    list($dbnow) = pwg_db_fetch_row(pwg_query('SELECT NOW();'));
    if (!defined('CURRENT_DATE'))
    {
      define('CURRENT_DATE', $dbnow);
    }

    $state = array(
      'site_id' => $site_id,
      'operation' => $operation,
      'meta_desc' => $meta_desc,
      'meta_desc_keep_rich' => $meta_desc_keep_rich,
      'meta_title' => $meta_title,
      'meta_author' => $meta_author,
      'meta_tags' => $meta_tags,
      'meta_tags_merge' => $meta_tags_merge,
      'recursive' => $recursive,
      'root_sync' => $root_sync,
      'checked_ids' => $checked_ids,
      'cat_ids' => $root_sync
        ? syncfast_get_all_local_category_ids($site_id)
        : syncfast_resolve_scope($checked_ids, $site_id, $recursive),
      'phase' => '',
      'dirs_queue' => array(),
      'dirs_total' => 0,
      'dirs_done' => 0,
      'files_queue' => array(),
      'files_total' => 0,
      'files_done' => 0,
      'meta_total' => 0,
      'meta_index' => 0,
      'counters' => array(
        'albums_analyzed' => 0,
        'created_categories' => 0,
        'deleted_categories' => 0,
        'files_analyzed' => 0,
        'new_images' => 0,
        'deleted_images' => 0,
        'meta_updated' => 0,
        'errors' => 0,
      ),
      'error_details' => array(),
    );

    if ($operation === 'dirs' || $operation === 'files')
    {
      // cat_id 0 = racine du site (aucune categorie precise), voir
      // syncfast_scan_directories_for_album() / syncfast_get_site_root_dir()
      $state['dirs_queue'] = $root_sync ? array(0) : $checked_ids;
      $state['dirs_total'] = $root_sync ? 1 : count($checked_ids);
      $state['phase'] = 'dirs';
    }
    else
    {
      syncfast_enter_meta_or_done($state);
    }

    $_SESSION[SYNCFAST_SESSION_KEY] = $state;

    $response = array_merge(
      array('success' => true, 'message' => ''),
      syncfast_progress_payload($state)
    );
  }
}
elseif ($action === 'chunk')
{
  if (empty($_SESSION[SYNCFAST_SESSION_KEY]))
  {
    $response['message'] = l10n('No active synchronization was found.');
  }
  else
  {
    $state = $_SESSION[SYNCFAST_SESSION_KEY];
    $site_id = $state['site_id'];

    if (!defined('CURRENT_DATE'))
    {
      list($dbnow) = pwg_db_fetch_row(pwg_query('SELECT NOW();'));
      define('CURRENT_DATE', $dbnow);
    }

    $current_label = '';

    if ($state['phase'] === 'dirs' && !empty($state['dirs_queue']))
    {
      $cat_id = array_shift($state['dirs_queue']);
      $current_label = ($cat_id === 0) ? './galleries' : syncfast_get_category_label($cat_id);

      $r = syncfast_scan_directories_for_album($cat_id, $site_id, $state['recursive']);
      $state['counters']['albums_analyzed'] += $r['analyzed'];
      $state['counters']['created_categories'] += $r['created'];
      $state['counters']['deleted_categories'] += $r['deleted'];
      $state['counters']['deleted_images'] += $r['deleted_images'];
      $state['counters']['errors'] += $r['errors'];
      syncfast_append_error_details($state, $r['error_details']);
      $state['dirs_done']++;

      if (empty($state['dirs_queue']))
      {
        update_category('all');
        update_global_rank();
      }

      syncfast_advance_phase($state, $site_id);
    }
    elseif ($state['phase'] === 'files' && !empty($state['files_queue']))
    {
      $cat_id = array_shift($state['files_queue']);
      $current_label = syncfast_get_category_label($cat_id);

      $site_reader = syncfast_get_site_reader($site_id);
      $r = syncfast_sync_files_for_album($cat_id, $site_id, $site_reader);
      $state['counters']['files_analyzed'] += $r['analyzed'];
      $state['counters']['new_images'] += $r['new'];
      $state['counters']['deleted_images'] += $r['deleted'];
      $state['counters']['errors'] += $r['errors'];
      syncfast_append_error_details($state, $r['error_details']);
      $state['files_done']++;

      if (empty($state['files_queue']))
      {
        update_category('all');
      }

      syncfast_advance_phase($state, $site_id);
    }
    elseif ($state['phase'] === 'meta' && $state['meta_index'] < $state['meta_total'])
    {
      $site_reader = syncfast_get_site_reader($site_id);

      // operation 'files' : passe meta des NOUVELLES photos uniquement
      // (date_metadata_update IS NULL). Le filtre retrecit a chaque lot traite,
      // donc un OFFSET qui avance sauterait des lignes : on reste a 0.
      // operation 'meta' : filtre stable (toutes les photos), OFFSET classique.
      $is_files_pass = ($state['operation'] === 'files');
      $only_new = $is_files_pass;
      $query_offset = $only_new ? 0 : $state['meta_index'];

      if ($is_files_pass)
      {
        // nouvelles photos : remplir uniquement les champs vides + importer les
        // mots-cles en fusion (jamais de suppression, au cas ou la photo aurait
        // deja des tags poses avant cette premiere passe meta)
        $meta_opts = array(
          'update_description' => false,
          'keep_rich_description' => false,
          'update_title' => false,
          'update_author' => false,
          'update_tags' => true,
          'merge_tags' => true,
        );
      }
      else
      {
        $meta_opts = array(
          'update_description' => !empty($state['meta_desc']),
          'keep_rich_description' => !empty($state['meta_desc_keep_rich']),
          'update_title' => !empty($state['meta_title']),
          'update_author' => !empty($state['meta_author']),
          'update_tags' => !empty($state['meta_tags']),
          'merge_tags' => !empty($state['meta_tags_merge']),
        );
      }

      $r = syncfast_sync_metadata_batch(
        $state['cat_ids'],
        $query_offset,
        SYNCFAST_META_CHUNK_SIZE,
        $only_new,
        $meta_opts,
        $site_reader
      );

      $state['counters']['meta_updated'] += $r['updated'];
      $state['counters']['errors'] += $r['errors'];
      syncfast_append_error_details($state, $r['error_details']);
      $current_label = $r['last_file'];

      $batch_count = isset($r['batch_count']) ? $r['batch_count'] : 0;
      $state['meta_index'] += max($batch_count, 1);

      if ($batch_count < SYNCFAST_META_CHUNK_SIZE || $state['meta_index'] >= $state['meta_total'])
      {
        $state['meta_index'] = $state['meta_total'];
        $state['phase'] = 'done';
      }
    }
    else
    {
      $state['phase'] = 'done';
    }

    $_SESSION[SYNCFAST_SESSION_KEY] = $state;

    if ($state['phase'] === 'done')
    {
      unset($_SESSION[SYNCFAST_SESSION_KEY]);
    }

    $response = array_merge(
      array('success' => true, 'message' => ''),
      syncfast_progress_payload($state, array('current_label' => $current_label))
    );
  }
}
else
{
  $response['message'] = l10n('Unknown action.');
}

$debug_output = trim(ob_get_clean());
if ($debug_output !== '')
{
  $response['message'] = empty($response['message']) ? $debug_output : ($response['message'] . "\n" . $debug_output);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($response);
exit();
