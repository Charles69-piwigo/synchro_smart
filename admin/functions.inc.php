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

// -----------------------------------------------------------------------
// Allocation d'id sans recyclage + filets SmartAlbums
// -----------------------------------------------------------------------

// pwg_db_nextval() du coeur Piwigo = MAX(id)+1 : reattribue l'id d'une categorie
// des qu'elle etait la plus haute et qu'on la supprime. Un renommage de
// repertoire etant vu par la synchro comme delete + add, le nouveau repertoire
// herite alors de l'id de l'ancien -> tout filtre SmartAlbums de type 'album'
// qui referencait cet id pointe silencieusement un autre album (resultats
// "n'importe quoi"), ou, en cond='none' sur un id devenu absent, matche TOUTES
// les photos (flood de piwigo_image_category). On alloue donc au-dessus du
// plus-haut-id-jamais-attribue : la valeur AUTO_INCREMENT de la table, que
// MyISAM ne redescend jamais sur DELETE. Un id supprime reste ainsi
// definitivement mort (le filtre casse devient visiblement casse, jamais
// subtilement faux) et syncfast_repair_album_filters() peut le recaler par
// chemin le cas echeant.
function syncfast_hwm_nextval($table, $id_col = 'id')
{
  $next = (int) pwg_db_nextval($id_col, $table);

  $query = '
SELECT AUTO_INCREMENT
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = \'' . $table . '\'
;';
  $row = pwg_db_fetch_row(pwg_query($query));
  $auto_increment = (isset($row[0]) && $row[0] !== null) ? (int) $row[0] : 0;

  return max($next, $auto_increment);
}

// Fenetre de recyclage d'id de tags : AUTO_INCREMENT de TAGS_TABLE au-dela de
// MAX(id)+1 => des id hauts ont ete liberes (typiquement une purge des tags
// orphelins) et le coeur Piwigo (create_tag = MAX(id)+1) les reattribuera au
// prochain nouveau mot-cle. Un filtre SmartAlbums type 'tags' pourrait alors
// pointer un autre tag. On ne reecrit pas le coeur : on signale (compteur nul
// aujourd'hui sur cette install).
function syncfast_tag_recycle_window()
{
  if (!defined('TAGS_TABLE'))
  {
    return 0;
  }

  $query = '
SELECT AUTO_INCREMENT
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = \'' . TAGS_TABLE . '\'
;';
  $row = pwg_db_fetch_row(pwg_query($query));
  $auto_increment = (isset($row[0]) && $row[0] !== null) ? (int) $row[0] : 0;

  list($max_id) = pwg_db_fetch_row(pwg_query('SELECT IFNULL(MAX(id), 0) FROM ' . TAGS_TABLE . ';'));

  $window = $auto_increment - ((int) $max_id + 1);

  return $window > 0 ? $window : 0;
}

// nom de la table des filtres SmartAlbums, ou null si le plugin n'est pas la
function syncfast_category_filters_table()
{
  if (defined('CATEGORY_FILTERS_TABLE'))
  {
    return CATEGORY_FILTERS_TABLE;
  }

  global $prefixeTable;
  $table = $prefixeTable . 'category_filters';

  return pwg_db_num_rows(pwg_query('SHOW TABLES LIKE \'' . $table . '\'')) ? $table : null;
}

// Memoire persistante id-de-token -> chemin absolu des filtres SmartAlbums de
// type 'album', stockee comme un seul parametre serialise dans piwigo_config
// (meme pratique que SmartAlbums / bulk_sync_manager pour leur config). Forme :
//   array( '<filter_id>' => array( '<token_id>' => '<fulldir>', ... ), ... )
// Persistante car le cas utile (repertoire hors-ligne puis re-en-ligne) se joue
// sur DEUX synchros distinctes : la 1re supprime la categorie, la 2nde la
// recree avec un nouvel id — il faut se souvenir du chemin d'origine entre les
// deux pour recaler le filtre.
define('SYNCFAST_FILTER_PATHS_PARAM', 'syncfast_album_filter_paths');

function syncfast_album_filter_paths_load()
{
  global $conf;
  $raw = isset($conf[SYNCFAST_FILTER_PATHS_PARAM]) ? $conf[SYNCFAST_FILTER_PATHS_PARAM] : '';
  if (!is_string($raw) || $raw === '')
  {
    return array();
  }
  $map = @unserialize($raw);

  return is_array($map) ? $map : array();
}

function syncfast_album_filter_paths_save($map)
{
  conf_update_param(SYNCFAST_FILTER_PATHS_PARAM, $map, true);
}

// lit les filtres 'album' courants -> array( filter_id => array(
//   'category_id','smart_name','smart_uppercats','cond','recursive','tokens'=>[int,...] ) )
function syncfast_album_filters_parsed($table)
{
  $query = '
SELECT cf.id, cf.category_id, cf.cond, cf.value, c.name AS smart_name, c.uppercats AS smart_uppercats
  FROM ' . $table . ' cf
  LEFT JOIN ' . CATEGORIES_TABLE . ' c ON c.id = cf.category_id
  WHERE cf.type = \'album\'
;';
  $result = pwg_query($query);

  $filters = array();
  while ($row = pwg_db_fetch_assoc($result))
  {
    $parts = explode(',', (string) $row['value']);
    $recursive = array_shift($parts); // 1er token = 'true' | 'false'
    $tokens = array();
    foreach ($parts as $p)
    {
      $p = trim($p);
      if ($p !== '' && ctype_digit($p))
      {
        $tokens[] = (int) $p;
      }
    }
    $filters[(int) $row['id']] = array(
      'category_id' => (int) $row['category_id'],
      'smart_name' => $row['smart_name'],
      'smart_uppercats' => (string) $row['smart_uppercats'],
      'cond' => $row['cond'],
      'recursive' => ($recursive === 'true') ? 'true' : 'false',
      'tokens' => $tokens,
    );
  }

  return $filters;
}

// fil d'Ariane d'un smart album depuis son uppercats ("id,id,id" finissant par
// lui-meme), via une table de noms deja construite ($names : id => name)
function syncfast_breadcrumb_from_uppercats($uppercats, $names)
{
  $labels = array();
  foreach (explode(',', (string) $uppercats) as $id)
  {
    $id = (int) $id;
    if ($id > 0 && isset($names[$id]))
    {
      $labels[] = $names[$id];
    }
  }

  return implode(' / ', $labels);
}

// Enregistre le chemin absolu actuel de chaque token de filtre 'album' qui
// resout encore a un repertoire. Appele au DEMARRAGE d'une synchro dirs/files
// (avant toute suppression) et re-appele en fin de synchro. Fusionne avec la
// memoire existante ; purge les filtres qui n'existent plus.
function syncfast_record_album_filter_paths($site_id)
{
  $table = syncfast_category_filters_table();
  if ($table === null)
  {
    return;
  }

  $filters = syncfast_album_filters_parsed($table);
  if (empty($filters))
  {
    syncfast_album_filter_paths_save(array());
    return;
  }

  $all_ids = syncfast_get_all_local_category_ids($site_id);
  $id_to_path = empty($all_ids) ? array() : get_fulldirs($all_ids);

  $memory = syncfast_album_filter_paths_load();
  $new_memory = array();

  foreach ($filters as $fid => $f)
  {
    $remembered = isset($memory[$fid]) && is_array($memory[$fid]) ? $memory[$fid] : array();
    $entry = array();
    // uniquement les tokens presents dans la valeur ACTUELLE du filtre : chemin
    // frais si le token resout, sinon on garde la trace deja memorisee (pour
    // qu'une synchro ulterieure puisse encore recaler un repertoire de retour)
    foreach ($f['tokens'] as $tok)
    {
      if (isset($id_to_path[$tok]))
      {
        $entry[(string) $tok] = $id_to_path[$tok];
      }
      elseif (isset($remembered[(string) $tok]))
      {
        $entry[(string) $tok] = $remembered[(string) $tok];
      }
    }
    if (!empty($entry))
    {
      $new_memory[(string) $fid] = $entry;
    }
  }

  syncfast_album_filter_paths_save($new_memory);
}

// En fin de synchro : pour tout token de filtre 'album' dont l'id ne resout
// plus a un repertoire, si la memoire persistante connait son ancien chemin ET
// qu'un album existe DESORMAIS exactement a ce chemin (repertoire revenu au meme
// endroit avec un nouvel id — cas hors-ligne puis re-en-ligne), on reecrit le
// token. Si le chemin memorise a vraiment disparu (renommage / suppression), on
// ne devine rien : on le signale. Jamais de DELETE de filtre, jamais de
// reecriture d'un token encore valide. Re-enregistre la memoire a la fin.
function syncfast_repair_album_filters($site_id)
{
  $result = array('fixed' => 0, 'review' => array());

  $table = syncfast_category_filters_table();
  if ($table === null)
  {
    return $result;
  }

  $filters = syncfast_album_filters_parsed($table);
  if (empty($filters))
  {
    syncfast_album_filter_paths_save(array());
    return $result;
  }

  $all_ids = syncfast_get_all_local_category_ids($site_id);
  $id_to_path = empty($all_ids) ? array() : get_fulldirs($all_ids);
  $path_to_id = array_flip($id_to_path);

  // toutes les categories existantes (physiques ET virtuelles) : un filtre
  // 'album' peut viser un album virtuel (dir NULL, donc absent de get_fulldirs) ;
  // ce n'est pas un token orphelin, il ne faut ni le signaler ni y toucher.
  $existing_ids = array();
  $res = pwg_query('SELECT id FROM ' . CATEGORIES_TABLE . ';');
  while ($row = pwg_db_fetch_row($res))
  {
    $existing_ids[(int) $row[0]] = true;
  }

  // noms des ancetres des smart albums, pour composer leur fil d'Ariane dans le
  // rapport « filtres a revoir »
  $anc_ids = array();
  foreach ($filters as $f)
  {
    foreach (explode(',', $f['smart_uppercats']) as $id)
    {
      $id = (int) $id;
      if ($id > 0)
      {
        $anc_ids[$id] = true;
      }
    }
  }
  $anc_names = array();
  if (!empty($anc_ids))
  {
    $res = pwg_query('SELECT id, name FROM ' . CATEGORIES_TABLE . ' WHERE id IN (' . implode(',', array_keys($anc_ids)) . ');');
    while ($row = pwg_db_fetch_assoc($res))
    {
      $anc_names[(int) $row['id']] = $row['name'];
    }
  }

  $memory = syncfast_album_filter_paths_load();
  $new_memory = array();

  foreach ($filters as $fid => $f)
  {
    $remembered = isset($memory[$fid]) && is_array($memory[$fid]) ? $memory[$fid] : array();
    $entry = array();
    $new_tokens = array();
    $changed = false;

    // un token mort ne casse le filtre que selon le cond :
    //  - 'all' / 'only' : un seul suffit a vider le resultat -> gênant
    //  - 'one' / 'none' : gênant seulement si TOUS les tokens sont morts
    //    ('one' -> vide ; 'none' -> n'exclut plus rien = vecteur du flood)
    $alive = 0;
    foreach ($f['tokens'] as $tok)
    {
      if (isset($existing_ids[$tok]))
      {
        $alive++;
      }
    }
    $row_breaks_on_any_dead = in_array($f['cond'], array('all', 'only'), true);
    $row_broken = $row_breaks_on_any_dead
      ? ($alive < count($f['tokens']))
      : ($alive === 0 && !empty($f['tokens']));

    foreach ($f['tokens'] as $tok)
    {
      if (isset($existing_ids[$tok]))
      {
        // la categorie existe (physique ou virtuelle) : token valide, on le
        // garde ; si elle est physique on (re)memorise son chemin
        $new_tokens[] = $tok;
        if (isset($id_to_path[$tok]))
        {
          $entry[(string) $tok] = $id_to_path[$tok];
        }
        continue;
      }

      // id inexistant : token orphelin -> que sait-on de son ancien chemin ?
      $old_path = isset($remembered[(string) $tok]) ? $remembered[(string) $tok] : null;

      if ($old_path !== null && isset($path_to_id[$old_path]))
      {
        // le repertoire est (re)venu a ce chemin avec un autre id -> on recale
        // (toujours, meme si le filtre n'est pas "casse" : on repare ce qu'on peut)
        $rebind = (int) $path_to_id[$old_path];
        $new_tokens[] = $rebind;
        $entry[(string) $rebind] = $old_path;
        $changed = true;
      }
      else
      {
        // pas recalable : on garde le token, on garde la trace du chemin connu
        $new_tokens[] = $tok;
        if ($old_path !== null)
        {
          $entry[(string) $tok] = $old_path;
        }
        // on ne signale que si ce token mort casse effectivement le filtre
        if ($row_broken)
        {
          $result['review'][] = array(
            'smart_album' => $f['smart_name'],
            'breadcrumb' => syncfast_breadcrumb_from_uppercats($f['smart_uppercats'], $anc_names),
            'cond' => $f['cond'],
            'path' => ($old_path !== null) ? $old_path : '',
          );
        }
      }
    }

    if ($changed)
    {
      $new_value = $f['recursive'] . ',' . implode(',', array_map('intval', $new_tokens));
      pwg_query('UPDATE ' . $table . ' SET value = \'' . $new_value . '\' WHERE id = ' . (int) $fid . ';');
      $result['fixed']++;
    }

    if (!empty($entry))
    {
      $new_memory[(string) $fid] = $entry;
    }
  }

  syncfast_album_filter_paths_save($new_memory);

  return $result;
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

  // syncfast_hwm_nextval (et non pwg_db_nextval = MAX(id)+1) : alloue au-dessus
  // du plus-haut-id-historique pour ne jamais reattribuer l'id d'une categorie
  // supprimee a un autre repertoire — cf. l'en-tete de syncfast_hwm_nextval()
  $next_id = syncfast_hwm_nextval(CATEGORIES_TABLE);
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

// $opts (tous des booleens) pilote l'ecriture champ par champ :
//   update_description / keep_rich_description / update_title / update_author
//   update_tags / merge_tags
// Regle par defaut (option decochee) pour comment/name/author : on ne remplit que
// si le champ est vide en base (jamais d'ecrasement d'une saisie faite dans Piwigo).
// Option cochee : on ecrase aussi une valeur existante. GPS : jamais d'ecrasement
// d'une position deja enregistree.
// Tags : intouches sauf update_tags. Alors : merge_tags => on ajoute les mots-cles
// du fichier sans rien retirer (add_tags) ; sinon la liste du fichier remplace
// tout (set_tags_of), en preservant les tags visages face_tag (fichier sans
// mot-cle => tous les tags hors visages retires).
function syncfast_sync_metadata_batch($cat_ids, $offset, $limit, $only_new, $opts, $site_reader)
{
  $result = array('updated' => 0, 'errors' => 0, 'last_file' => '', 'error_details' => array());

  if (empty($cat_ids))
  {
    return $result;
  }

  $update_description = !empty($opts['update_description']);
  $keep_rich_description = !empty($opts['keep_rich_description']);
  $update_title = !empty($opts['update_title']);
  $update_author = !empty($opts['update_author']);
  $update_tags = !empty($opts['update_tags']);
  // fusion : on ajoute les mots-cles du fichier sans rien retirer. Sinon
  // remplacement : la liste du fichier remplace tout, mais on preserve les
  // tags visages (face_tag stocke ses tags dans les memes tables Piwigo).
  $merge_tags = !empty($opts['merge_tags']);

  // colonnes base relues en plus des infos passees au reader : servent uniquement
  // a decider, ligne par ligne, si on a le droit d'ecrire (champ vide ? deja du
  // HTML ? position GPS deja saisie ?)
  $query = '
SELECT id, path, representative_ext, comment, name, author, latitude, longitude
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

  // tags visages deja poses sur ces photos (face_tag) : a preserver lors d'un
  // remplacement de mots-cles. Dependance douce : si face_tag n'est pas actif,
  // la constante n'existe pas et on ne protege rien de particulier.
  $face_tags_of = array();
  if (!empty($files) && $update_tags && !$merge_tags && defined('FACETAG_PERSON_TAGS_TABLE'))
  {
    $fquery = '
SELECT it.image_id, it.tag_id
  FROM ' . IMAGE_TAG_TABLE . ' it
  JOIN ' . FACETAG_PERSON_TAGS_TABLE . ' fpt ON fpt.tag_id = it.tag_id AND fpt.is_face_tag = 1
  WHERE it.image_id IN (' . implode(',', array_map('intval', array_keys($files))) . ')
;';
    $fres = pwg_query($fquery);
    while ($frow = pwg_db_fetch_assoc($fres))
    {
      $face_tags_of[(int) $frow['image_id']][] = (int) $frow['tag_id'];
    }
  }

  $datas = array();
  $tags_of = array();       // remplacement (set_tags_of)
  $merge_tags_of = array(); // fusion (add_tags)
  $last_file = '';

  foreach ($files as $id => $db_row)
  {
    $last_file = basename($db_row['path']);

    // ne transmettre au reader que ce dont get_sync_metadata() a besoin :
    // s'il recevait comment/name/... il les renverrait tels quels quand le
    // fichier n'a rien, ce qui rendrait indiscernable "fourni par le fichier"
    // de "deja en base"
    $reader_infos = array(
      'id' => $id,
      'path' => $db_row['path'],
      'representative_ext' => $db_row['representative_ext'],
    );

    $data = $site_reader->get_element_metadata($reader_infos);

    if (!is_array($data))
    {
      $result['errors']++;
      $result['error_details'][] = array('path' => $db_row['path'], 'type' => 'metadata_failed');
      continue;
    }

    // une chaine vide venue de l'IPTC ne doit jamais compter comme une valeur
    // (sinon la branche "table temporaire" de mass_updates l'ecrirait)
    foreach (array('comment', 'name', 'author') as $k)
    {
      if (isset($data[$k]) && trim((string) $data[$k]) === '')
      {
        unset($data[$k]);
      }
    }

    // description
    if (isset($data['comment']))
    {
      $db_comment = (string) $db_row['comment'];
      if (!$update_description)
      {
        if (trim($db_comment) !== '')
        {
          unset($data['comment']);
        }
      }
      elseif ($keep_rich_description && $db_comment !== '' && strip_tags($db_comment) !== $db_comment)
      {
        unset($data['comment']);
      }
    }

    // titre
    if (isset($data['name']) && !$update_title && trim((string) $db_row['name']) !== '')
    {
      unset($data['name']);
    }

    // auteur
    if (isset($data['author']) && !$update_author && trim((string) $db_row['author']) !== '')
    {
      unset($data['author']);
    }

    // GPS : ne jamais ecraser une position deja enregistree en base
    if ($db_row['latitude'] !== null && $db_row['latitude'] !== '')
    {
      unset($data['latitude'], $data['longitude']);
    }

    // tags : uniquement sur demande explicite ($update_tags)
    if ($update_tags)
    {
      $file_tag_ids = array();
      foreach (array('keywords', 'tags') as $key)
      {
        if (isset($data[$key]) && trim((string) $data[$key]) !== '')
        {
          foreach (explode(',', $data[$key]) as $tag_name)
          {
            $tag_name = trim($tag_name);
            if ($tag_name !== '')
            {
              $file_tag_ids[] = tag_id_from_tag_name($tag_name);
            }
          }
        }
      }
      $file_tag_ids = array_values(array_unique($file_tag_ids));

      if ($merge_tags)
      {
        // fusion : additif, on ne retire rien
        if (!empty($file_tag_ids))
        {
          $merge_tags_of[$id] = $file_tag_ids;
        }
      }
      else
      {
        // remplacement : liste du fichier + tags visages a preserver.
        // tableau vide accepte : set_tags_of retire alors tous les tags
        // (hors visages) de la photo.
        $keep = isset($face_tags_of[$id]) ? $face_tags_of[$id] : array();
        $tags_of[$id] = array_values(array_unique(array_merge($file_tag_ids, $keep)));
      }
    }

    $data['date_metadata_update'] = CURRENT_DATE;
    $data['id'] = $id;
    $datas[] = $data;
  }

  if (!empty($datas))
  {
    // comment/name/author restent dans la liste des que la config du site les
    // fournit : l'option ne pilote que le gating par ligne ci-dessus (remplir si
    // vide meme sans option), SKIP_EMPTY garde le reste
    $update_fields = array_values(array_intersect(
      array('filesize', 'width', 'height', 'date_creation', 'latitude', 'longitude', 'comment', 'name', 'author', 'date_metadata_update'),
      array_merge($site_reader->get_metadata_attributes(), array('date_metadata_update'))
    ));

    mass_updates(
      IMAGES_TABLE,
      array(
        'primary' => array('id'),
        'update' => $update_fields,
      ),
      $datas,
      MASS_UPDATES_SKIP_EMPTY
    );
  }

  if (!empty($tags_of))
  {
    set_tags_of($tags_of);
  }

  if (!empty($merge_tags_of))
  {
    foreach ($merge_tags_of as $img_id => $tids)
    {
      add_tags($tids, array((int) $img_id));
    }
  }

  $result['updated'] = count($datas);
  $result['last_file'] = $last_file;
  $result['batch_count'] = count($files);

  return $result;
}
