<?php
if (!defined('PHPWG_ROOT_PATH'))
{
  die('Hacking attempt!');
}

// -----------------------------------------------------------------------
// Site local
// -----------------------------------------------------------------------

// retourne l'id du premier site local (non distant) de l'installation
function syncfast_get_local_site_id()
{
  $query = '
SELECT id, galleries_url
  FROM ' . SITES_TABLE . '
  ORDER BY id ASC
;';
  $result = pwg_query($query);

  while ($row = pwg_db_fetch_assoc($result))
  {
    if (!url_is_remote($row['galleries_url']))
    {
      return (int) $row['id'];
    }
  }

  return 0;
}

function syncfast_get_site_reader($site_id)
{
  include_once(PHPWG_ROOT_PATH . 'admin/site_reader_local.php');

  return new LocalSiteReader(syncfast_get_site_gallery_url($site_id));
}

function syncfast_get_site_gallery_url($site_id)
{
  $query = '
SELECT galleries_url
  FROM ' . SITES_TABLE . '
  WHERE id = ' . (int) $site_id . '
  LIMIT 1
;';
  list($site_url) = pwg_db_fetch_row(pwg_query($query));

  return $site_url;
}

// racine physique du site (meme resolution que admin/site_update.php quand
// aucun cat_id n'est fourni) : utilisee pour amorcer la toute premiere
// synchronisation, avant qu'aucune categorie n'existe encore en base
function syncfast_get_site_root_dir($site_id)
{
  return preg_replace('#/*$#', '', syncfast_get_site_gallery_url($site_id));
}

// -----------------------------------------------------------------------
// Tableau des albums (pour l'UI)
// -----------------------------------------------------------------------

// liste des albums physiques (dir IS NOT NULL) avec profondeur et fil d'ariane,
// meme technique que galleries_link_manager (depth = substr_count(global_rank, '.'))
function syncfast_get_albums_tree($site_id)
{
  $query = '
SELECT id, name, uppercats, global_rank
  FROM ' . CATEGORIES_TABLE . '
  WHERE site_id = ' . (int) $site_id . '
    AND dir IS NOT NULL
  ORDER BY global_rank ASC
;';
  $result = pwg_query($query);

  $rows = array();
  $name_map = array();

  while ($row = pwg_db_fetch_assoc($result))
  {
    $rows[] = $row;
    $name_map[(int) $row['id']] = $row['name'];
  }

  // nb_images n'est plus une colonne de CATEGORIES_TABLE (deplacee vers
  // USER_CACHE_CATEGORIES_TABLE, un cache par utilisateur) : on le calcule
  // directement, decompte propre a chaque album (non recursif)
  $query = '
SELECT category_id, COUNT(*) AS nb_images
  FROM ' . IMAGE_CATEGORY_TABLE . '
  GROUP BY category_id
;';
  $result_counts = pwg_query($query);
  $counts = array();
  while ($row = pwg_db_fetch_assoc($result_counts))
  {
    $counts[(int) $row['category_id']] = (int) $row['nb_images'];
  }

  $albums = array();
  $row_count = count($rows);

  foreach ($rows as $index => $row)
  {
    $cat_id = (int) $row['id'];
    $depth = substr_count($row['global_rank'], '.');
    $breadcrumb = array();

    foreach (explode(',', $row['uppercats']) as $up_id)
    {
      $up_id = (int) $up_id;
      if ($up_id > 0 && $up_id !== $cat_id && isset($name_map[$up_id]))
      {
        $breadcrumb[] = $name_map[$up_id];
      }
    }

    // la liste est triee par global_rank (ordre prefixe) : un album a des
    // enfants si l'album suivant est plus profond que lui
    $has_children = ($index + 1 < $row_count)
      && substr_count($rows[$index + 1]['global_rank'], '.') > $depth;

    $albums[] = array(
      'id' => $cat_id,
      'name' => $row['name'],
      'depth' => $depth,
      'path' => implode(' / ', $breadcrumb),
      'nb_images' => isset($counts[$cat_id]) ? $counts[$cat_id] : 0,
      'has_children' => $has_children,
    );
  }

  return $albums;
}

function syncfast_get_category_label($cat_id)
{
  $query = '
SELECT name
  FROM ' . CATEGORIES_TABLE . '
  WHERE id = ' . (int) $cat_id . '
  LIMIT 1
;';
  $result = pwg_query($query);
  $row = pwg_db_fetch_assoc($result);

  return $row ? $row['name'] : ('#' . $cat_id);
}

// -----------------------------------------------------------------------
// Resolution du perimetre (albums coches + descendants si recursif)
// -----------------------------------------------------------------------

function syncfast_resolve_scope($checked_ids, $site_id, $recursive)
{
  $checked_ids = array_values(array_unique(array_filter(array_map('intval', $checked_ids))));

  if (empty($checked_ids))
  {
    return array();
  }

  if (!$recursive)
  {
    return $checked_ids;
  }

  $conditions = array();
  foreach ($checked_ids as $id)
  {
    $conditions[] = 'uppercats ' . DB_REGEX_OPERATOR . ' \'(^|,)' . $id . '(,|$)\'';
  }

  $query = '
SELECT id
  FROM ' . CATEGORIES_TABLE . '
  WHERE site_id = ' . (int) $site_id . '
    AND dir IS NOT NULL
    AND (' . implode(' OR ', $conditions) . ')
;';
  $result = pwg_query($query);

  $ids = $checked_ids;
  while ($row = pwg_db_fetch_assoc($result))
  {
    $ids[] = (int) $row['id'];
  }

  return array_values(array_unique($ids));
}

// tous les albums locaux d'un site (dir IS NOT NULL), sans filtrer par
// sous-arbre : utilise quand une synchro "racine" (./galleries en totalite)
// doit couvrir tout ce qui existe deja en base, en plus de ce qui vient
// d'etre cree pendant la phase repertoires
function syncfast_get_all_local_category_ids($site_id)
{
  $query = '
SELECT id
  FROM ' . CATEGORIES_TABLE . '
  WHERE site_id = ' . (int) $site_id . '
    AND dir IS NOT NULL
;';
  $result = pwg_query($query);

  $ids = array();
  while ($row = pwg_db_fetch_assoc($result))
  {
    $ids[] = (int) $row['id'];
  }

  return $ids;
}

// $conf['sync_chars_regex'] est personnalisable par site (voir
// config_default.inc.php) : impossible de deviner a l'avance la liste des
// caracteres autorises pour un message d'erreur generique. On identifie donc
// precisement le(s) caractere(s) fautif(s) en testant chacun individuellement
// contre la regex reellement configuree (chaque caractere seul doit encore
// satisfaire ^[...]+$ s'il fait partie de l'ensemble autorise).
function syncfast_find_invalid_chars($name)
{
  global $conf;

  $chars = preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY);
  if ($chars === false)
  {
    // nom non UTF-8 valide : repli sur un decoupage octet par octet
    $chars = str_split($name);
  }

  $invalid = array();
  foreach ($chars as $char)
  {
    if (!preg_match($conf['sync_chars_regex'], $char) && !in_array($char, $invalid, true))
    {
      $invalid[] = $char;
    }
  }

  return $invalid;
}

// -----------------------------------------------------------------------
// Phase 1 : creation des albums manquants, un album source a la fois
// (cat_id === 0 = racine du site ./galleries, cf. syncfast_get_site_root_dir :
// amorce de la toute premiere synchronisation, avant qu'aucun album existe)
// -----------------------------------------------------------------------

function syncfast_scan_directories_for_album($cat_id, $site_id, $recursive)
{
  global $conf;

  $result = array('created' => 0, 'deleted' => 0, 'deleted_images' => 0, 'errors' => 0, 'analyzed' => 0, 'error_details' => array());

  if ($cat_id === 0)
  {
    // cat_id 0 = amorce de la toute premiere synchronisation (aucune
    // categorie n'existe encore en base) : on scanne directement la racine
    // physique du site plutot que le repertoire d'une categorie existante
    $basedir = syncfast_get_site_root_dir($site_id);
  }
  else
  {
    $fulldirs_root = get_fulldirs(array($cat_id));
    if (empty($fulldirs_root[$cat_id]))
    {
      return $result;
    }
    $basedir = $fulldirs_root[$cat_id];
  }

  if (!is_dir($basedir))
  {
    // le repertoire n'existe plus physiquement : on ne cree jamais de categorie
    // sans verification disque a l'instant present (evite les "albums fantomes")
    $result['errors']++;
    $result['error_details'][] = array('path' => $basedir, 'type' => 'missing_dir');
    return $result;
  }

  // cat_id 0 (racine) couvre tout le site ; sinon on se limite au sous-arbre
  // de la categorie scannee
  $scope_condition = ($cat_id === 0)
    ? ''
    : ' AND (id = ' . (int) $cat_id . ' OR uppercats ' . DB_REGEX_OPERATOR . ' \'(^|,)' . (int) $cat_id . '(,|$)\')';

  $query = '
SELECT id, uppercats, global_rank, status, visible
  FROM ' . CATEGORIES_TABLE . '
  WHERE dir IS NOT NULL
    AND site_id = ' . (int) $site_id . $scope_condition . '
;';
  $db_categories = hash_from_query($query, 'id');
  $db_fulldirs = array_flip(get_fulldirs(array_keys($db_categories)));

  // scan disque reel : uniquement ce que get_fs_directories trouve maintenant
  $fs_fulldirs = get_fs_directories($basedir, $recursive);
  if ($cat_id !== 0)
  {
    // basedir lui-meme ne doit etre ajoute que quand il represente une
    // categorie existante (cas normal) : $db_fulldirs contient alors deja
    // cette entree, donc array_diff() l'exclut naturellement des "nouveaux"
    // repertoires. En mode racine (cat_id 0), la racine ./galleries n'est
    // JAMAIS une categorie elle-meme ; l'ajouter ici la ferait passer pour un
    // "nouvel album manquant" et creerait une categorie fantome pour le
    // dossier racine lui-meme.
    $fs_fulldirs[] = $basedir;
  }
  // nombre reel de repertoires examines (l'album lui-meme + tous ses
  // sous-repertoires en mode recursif), et non le nombre d'albums coches au
  // depart : un seul album coche peut englober des dizaines de sous-albums
  $result['analyzed'] = count($fs_fulldirs);

  $next_rank = array('NULL' => 1);
  foreach (array_keys($db_categories) as $id)
  {
    $next_rank[$id] = 1;
  }

  $query = '
SELECT id_uppercat, MAX(`rank`) + 1 AS next_rank
  FROM ' . CATEGORIES_TABLE . '
  GROUP BY id_uppercat
;';
  $rank_result = pwg_query($query);
  while ($row = pwg_db_fetch_assoc($rank_result))
  {
    $parent_key = empty($row['id_uppercat']) ? 'NULL' : (int) $row['id_uppercat'];
    $next_rank[$parent_key] = (int) $row['next_rank'];
  }

  $next_id = pwg_db_nextval('id', CATEGORIES_TABLE);
  $inserts = array();

  foreach (array_diff($fs_fulldirs, array_keys($db_fulldirs)) as $fulldir)
  {
    $dir = basename($fulldir);
    if (!preg_match($conf['sync_chars_regex'], $dir))
    {
      $result['errors']++;
      $result['error_details'][] = array('path' => $fulldir, 'type' => 'invalid_chars', 'chars' => implode(' ', syncfast_find_invalid_chars($dir)));
      continue;
    }

    $insert = array(
      'id' => $next_id++,
      'dir' => $dir,
      'name' => str_replace('_', ' ', $dir),
      'site_id' => $site_id,
      'commentable' => boolean_to_string($conf['newcat_default_commentable']),
      'status' => $conf['newcat_default_status'],
      'visible' => boolean_to_string($conf['newcat_default_visible']),
    );

    $parent_dir = dirname($fulldir);
    if (isset($db_fulldirs[$parent_dir]))
    {
      $parent = $db_fulldirs[$parent_dir];
      $insert['id_uppercat'] = $parent;
      $insert['uppercats'] = $db_categories[$parent]['uppercats'] . ',' . $insert['id'];
      $insert['rank'] = $next_rank[$parent]++;
      $insert['global_rank'] = $db_categories[$parent]['global_rank'] . '.' . $insert['rank'];

      if ('private' == $db_categories[$parent]['status'])
      {
        $insert['status'] = 'private';
      }
      if ('false' == $db_categories[$parent]['visible'])
      {
        $insert['visible'] = 'false';
      }
    }
    else
    {
      $insert['uppercats'] = $insert['id'];
      $insert['rank'] = $next_rank['NULL']++;
      $insert['global_rank'] = $insert['rank'];
    }

    $inserts[] = $insert;

    $db_categories[$insert['id']] = array(
      'id' => $insert['id'],
      'uppercats' => $insert['uppercats'],
      'global_rank' => $insert['global_rank'],
      'status' => $insert['status'],
      'visible' => $insert['visible'],
    );
    $db_fulldirs[$fulldir] = $insert['id'];
    $next_rank[$insert['id']] = 1;
  }

  if (!empty($inserts))
  {
    $dbfields = array('id', 'dir', 'name', 'site_id', 'id_uppercat', 'uppercats', 'commentable', 'visible', 'status', 'rank', 'global_rank');
    mass_inserts(CATEGORIES_TABLE, $dbfields, $inserts);

    $category_ids = array();
    foreach ($inserts as $c)
    {
      $category_ids[] = $c['id'];
    }

    pwg_activity('album', $category_ids, 'add', array('sync' => true));
    add_permission_on_category($category_ids, get_admins());

    $result['created'] = count($inserts);
  }

  // categories dont le repertoire a disparu du disque (seulement dans ce
  // sous-arbre, jamais au-dela du perimetre scanne) : meme logique que la
  // synchro native (site_update.php), pour rester coherent avec elle
  $to_delete = array();
  foreach (array_diff(array_keys($db_fulldirs), $fs_fulldirs) as $fulldir)
  {
    if ($db_fulldirs[$fulldir] != $cat_id)
    {
      $to_delete[] = $db_fulldirs[$fulldir];
    }
  }

  if (!empty($to_delete))
  {
    // delete_categories() supprime aussi, sans le signaler a l'appelant, les
    // photos physiquement stockees dans ces albums (delete_elements) : on
    // compte avant suppression pour pouvoir le refleter dans le rapport
    $query = '
SELECT COUNT(*)
  FROM ' . IMAGES_TABLE . '
  WHERE storage_category_id IN (' . implode(',', array_map('intval', $to_delete)) . ')
;';
    list($result['deleted_images']) = pwg_db_fetch_row(pwg_query($query));
    $result['deleted_images'] = (int) $result['deleted_images'];

    delete_categories($to_delete);
    $result['deleted'] = count($to_delete);
  }

  return $result;
}

// -----------------------------------------------------------------------
// Phase 2 : fichiers, un album a la fois (pas de recursion : chaque
// sous-album cree en phase 1 est traite comme sa propre etape de file d'attente)
// -----------------------------------------------------------------------

// liste les fichiers presents directement dans $path (pas de recursion dans
// les sous-repertoires). get_elements() de LocalSiteReader (fonction Piwigo
// native) recurse toujours et ignore $conf['sync_exclude_folders'] (ce filtre
// n'est applique que par get_fs_directories(), utilisee en phase 1) ; comme
// chaque sous-repertoire legitime devient deja sa propre categorie traitee a
// son propre tour de la file d'attente, ne jamais descendre dans les
// sous-repertoires ici evite a la fois de contaminer l'album en cours avec
// des fichiers d'un repertoire exclu et de perdre du temps a les lire pour
// rien (repertoires "raw", "video", etc. potentiellement volumineux)
function syncfast_list_files_in_dir($site_reader, $path)
{
  global $conf;

  $fs = array();
  $contents = @opendir($path);
  if ($contents === false)
  {
    return $fs;
  }

  while (($node = readdir($contents)) !== false)
  {
    if ($node === '.' || $node === '..' || !is_file($path . '/' . $node))
    {
      continue;
    }

    $extension = strtolower(get_extension($node));
    if (!isset($conf['flip_file_ext'][$extension]))
    {
      continue;
    }

    $filename_wo_ext = get_filename_wo_extension($node);
    $representative_ext = null;
    if (!isset($conf['flip_picture_ext'][$extension]))
    {
      $representative_ext = $site_reader->get_representative_ext($path, $filename_wo_ext);
    }

    $fs[$path . '/' . $node] = array('representative_ext' => $representative_ext);

    if ($conf['enable_formats'])
    {
      $fs[$path . '/' . $node]['formats'] = $site_reader->get_formats($path, $filename_wo_ext);
    }
  }
  closedir($contents);
  ksort($fs);

  return $fs;
}

function syncfast_sync_files_for_album($cat_id, $site_id, $site_reader)
{
  global $conf, $user;

  $result = array('new' => 0, 'deleted' => 0, 'errors' => 0, 'analyzed' => 0, 'error_details' => array());

  $fulldirs = get_fulldirs(array($cat_id));
  if (empty($fulldirs[$cat_id]) || !is_dir($fulldirs[$cat_id]))
  {
    $result['errors']++;
    $result['error_details'][] = array('path' => isset($fulldirs[$cat_id]) ? $fulldirs[$cat_id] : ('#' . $cat_id), 'type' => 'missing_dir');
    return $result;
  }
  $basedir = $fulldirs[$cat_id];

  $fs = syncfast_list_files_in_dir($site_reader, $basedir);
  $result['analyzed'] = count($fs);

  $query = '
SELECT id, path
  FROM ' . IMAGES_TABLE . '
  WHERE storage_category_id = ' . (int) $cat_id . '
;';
  $db_elements = simple_hash_from_query($query, 'id', 'path');

  $next_element_id = pwg_db_nextval('id', IMAGES_TABLE);
  $inserts = array();
  $insert_links = array();
  $insert_formats = array();

  foreach (array_diff(array_keys($fs), $db_elements) as $path)
  {
    $filename = basename($path);
    if (!preg_match($conf['sync_chars_regex'], $filename))
    {
      $result['errors']++;
      $result['error_details'][] = array('path' => $path, 'type' => 'invalid_chars', 'chars' => implode(' ', syncfast_find_invalid_chars($filename)));
      continue;
    }

    $insert = array(
      'id' => $next_element_id++,
      'file' => pwg_db_real_escape_string($filename),
      'name' => pwg_db_real_escape_string(get_name_from_file($filename)),
      'date_available' => CURRENT_DATE,
      'path' => pwg_db_real_escape_string($path),
      'representative_ext' => $fs[$path]['representative_ext'],
      'storage_category_id' => (int) $cat_id,
      'added_by' => $user['id'],
    );

    $inserts[] = $insert;
    $insert_links[] = array('image_id' => $insert['id'], 'category_id' => (int) $cat_id);

    if ($conf['enable_formats'] && !empty($fs[$path]['formats']))
    {
      foreach ($fs[$path]['formats'] as $ext => $filesize)
      {
        $insert_formats[] = array('image_id' => $insert['id'], 'ext' => $ext, 'filesize' => $filesize);
      }
    }
  }

  if (!empty($inserts))
  {
    mass_inserts(IMAGES_TABLE, array_keys($inserts[0]), $inserts);
    mass_inserts(IMAGE_CATEGORY_TABLE, array_keys($insert_links[0]), $insert_links);

    $image_ids = array();
    foreach ($inserts as $i)
    {
      $image_ids[] = $i['id'];
    }
    pwg_activity('photo', $image_ids, 'add', array('sync' => true));

    if (!empty($insert_formats))
    {
      mass_inserts(IMAGE_FORMAT_TABLE, array_keys($insert_formats[0]), $insert_formats);
    }
  }

  $result['new'] = count($inserts);

  $to_delete = array();
  foreach (array_diff($db_elements, array_keys($fs)) as $path)
  {
    $id = array_search($path, $db_elements);
    if ($id !== false)
    {
      $to_delete[] = (int) $id;
    }
  }

  if (!empty($to_delete))
  {
    delete_elements($to_delete);
    $result['deleted'] = count($to_delete);
  }

  // rafraichit les attributs de fichier (representative_ext, etc.) pour ce seul album
  $files = get_filelist($cat_id, $site_id, false, false);
  $datas = array();
  foreach ($files as $id => $file)
  {
    $data = $site_reader->get_element_update_attributes($file['path']);
    if (is_array($data))
    {
      $data['id'] = $id;
      $datas[] = $data;
    }
  }

  if (!empty($datas))
  {
    mass_updates(
      IMAGES_TABLE,
      array('primary' => array('id'), 'update' => $site_reader->get_update_attributes()),
      $datas
    );
  }

  return $result;
}

// -----------------------------------------------------------------------
// Phase 3 : meta-donnees, par lots de fichiers (pattern face_tag : LIMIT/OFFSET
// recalcule a chaque appel, rien de precalcule en session)
// -----------------------------------------------------------------------

function syncfast_count_meta_targets($cat_ids, $only_new)
{
  if (empty($cat_ids))
  {
    return 0;
  }

  $query = '
SELECT COUNT(*)
  FROM ' . IMAGES_TABLE . '
  WHERE storage_category_id IN (' . implode(',', array_map('intval', $cat_ids)) . ')
';
  if ($only_new)
  {
    $query .= ' AND date_metadata_update IS NULL';
  }
  $query .= ';';

  list($total) = pwg_db_fetch_row(pwg_query($query));

  return (int) $total;
}

function syncfast_sync_metadata_batch($cat_ids, $offset, $limit, $only_new, $overwrite_existing, $site_reader)
{
  $result = array('updated' => 0, 'errors' => 0, 'last_file' => '', 'error_details' => array());

  if (empty($cat_ids))
  {
    return $result;
  }

  $query = '
SELECT id, path, representative_ext
  FROM ' . IMAGES_TABLE . '
  WHERE storage_category_id IN (' . implode(',', array_map('intval', $cat_ids)) . ')
';
  if ($only_new)
  {
    $query .= ' AND date_metadata_update IS NULL';
  }
  $query .= '
  ORDER BY id ASC
  LIMIT ' . (int) $limit . '
  OFFSET ' . (int) $offset . '
;';

  $files = hash_from_query($query, 'id');

  $datas = array();
  $tags_of = array();
  $last_file = '';

  foreach ($files as $id => $element_infos)
  {
    $data = $site_reader->get_element_metadata($element_infos);
    $last_file = basename($element_infos['path']);

    if (is_array($data))
    {
      $data['date_metadata_update'] = CURRENT_DATE;
      $data['id'] = $id;
      $datas[] = $data;

      foreach (array('keywords', 'tags') as $key)
      {
        if (isset($data[$key]))
        {
          if (!isset($tags_of[$id]))
          {
            $tags_of[$id] = array();
          }
          foreach (explode(',', $data[$key]) as $tag_name)
          {
            $tags_of[$id][] = tag_id_from_tag_name($tag_name);
          }
        }
      }
    }
    else
    {
      $result['errors']++;
      $result['error_details'][] = array('path' => $element_infos['path'], 'type' => 'metadata_failed');
    }
  }

  if (!empty($datas))
  {
    mass_updates(
      IMAGES_TABLE,
      array(
        'primary' => array('id'),
        'update' => array_unique(
          array_merge(
            array_diff($site_reader->get_metadata_attributes(), array('keywords', 'tags')),
            array('date_metadata_update')
          )
        ),
      ),
      $datas,
      $overwrite_existing ? 0 : MASS_UPDATES_SKIP_EMPTY
    );
  }

  if (!empty($tags_of))
  {
    set_tags_of($tags_of);
  }

  $result['updated'] = count($datas);
  $result['last_file'] = $last_file;
  $result['batch_count'] = count($files);

  return $result;
}
