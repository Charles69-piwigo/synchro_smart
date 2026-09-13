<?php
/*
Plugin Name: synchro_smart
Version: auto
Description: Synchronisation par lots (anti-timeout 504) avec selection d'albums, et prise en charge des SmartAlbums (id de categories non recycles, filtres "album" recales ou signales lors des renommages). Ajoute un onglet "Synchro Smart" dans Outils > Synchroniser.
Plugin URI: https://piwigo.org/ext/extension_view.php?eid=1111
Author: Charles69
*/

//============= VERSIONS ============================================
/*

version 1.3 - 10/09/2026
  Renommage synchro_fast -> synchro_smart (id du plugin) pour signaler la
  prise en charge des SmartAlbums introduite en 1.2. Aucune evolution
  fonctionnelle : uniquement les prefixes syncfast_/SYNCFAST_ -> syncsmart_/
  SYNCSMART_, le nom affiche "Synchro Rapide" -> "Synchro Smart", et le
  parametre de config syncfast_album_filter_paths -> syncsmart_album_filter_paths.

version 1.2 - 10/09/2026
  Ne recycle plus les id de catégories : allocation au-dessus du
  plus-haut-id-historique (AUTO_INCREMENT de la table) au lieu de MAX(id)+1.
  Un renommage de répertoire = delete + add ; avec MAX(id)+1 le nouveau
  répertoire héritait de l'id de l'ancien, et un filtre SmartAlbums de type
  "album" pointait alors silencieusement un autre album (résultats faux) ou,
  en cond="none" sur un id devenu absent, matchait toutes les photos (flood
  de piwigo_image_category).
  Filtres SmartAlbums "album" : instantané filtre -> chemin absolu pris avant
  la synchro ; après, un filtre dont l'album cible réapparaît exactement au
  même chemin est recalé sur le nouvel id, les autres (renommages) sont
  listés dans le rapport pour re-pointage manuel. Aucune suppression de
  filtre, aucune réécriture d'un filtre encore valide.
  Avertissement si des id de tags libérés vont être réutilisés par Piwigo
  (fenêtre AUTO_INCREMENT > MAX(id)+1 sur la table des tags).

version 1.1 - 01/09/2026
  Périmètre en 3 choix exclusifs (boutons radio) :
  - "Répertoires uniquement"
  - "Répertoires + fichiers" : enchaîne la lecture des méta-données des nouvelles photos
  - "Mise à jour des méta-données" : toutes les photos déjà en base, avec les options par champ
  Options par champ : "Mettre à jour la description / le titre / l'auteur / les mots-clés"
  - décoché : ne remplit que si le champ est vide en base (protège les saisies Piwigo)
  - coché : écrase aussi une valeur existante
  - sous-option "Sauf si la description est enrichie (HTML)"
  GPS : mis à jour automatiquement, mais ne remplace jamais une position déjà enregistrée
  Mots-clés : préservation des tags visages (face_tag) lors d'un remplacement,
  + sous-option "Fusionner" (ajout sans suppression via add_tags)
  Suppression des cases "Initialiser les données existantes" et
  "Même les photos déjà synchronisées" (implicites selon le choix)
  Onglet "Synchro Smart" : affiche la barre d'onglets native (retour possible
  vers Synchronisation / Gestionnaire de sites)

version 1.0 - 22/08/2026
  Création du plugin
  Synchroniser ./galleries sans erreur 504
  Suivi synchro avec barre d'avancement
  Affichage des rép et fichiers erronés
  Tableau de synthèse des résultats

  */
//====================================================================


if (!defined('PHPWG_ROOT_PATH'))
{
  die('Hacking attempt!');
}

defined('SYNCSMART_ID') or define('SYNCSMART_ID', basename(dirname(__FILE__)));
define('SYNCSMART_PATH', PHPWG_PLUGINS_PATH . SYNCSMART_ID . '/');
define('SYNCSMART_ADMIN', get_root_url() . 'admin.php?page=plugin-' . SYNCSMART_ID);

// taille de lot pour la phase de lecture des meta-donnees (la plus couteuse en I/O)
define('SYNCSMART_META_CHUNK_SIZE', 20);

// cle de session utilisee pour stocker l'etat d'avancement (pas de table SQL)
define('SYNCSMART_SESSION_KEY', 'sync_smart_progress');

// nombre maximum d'erreurs detaillees conservees et affichees dans le rapport
// (au-dela, seul le compteur global continue d'augmenter)
define('SYNCSMART_MAX_ERROR_DETAILS', 30);

load_language('plugin.lang', SYNCSMART_PATH);

if (defined('IN_ADMIN'))
{
  add_event_handler('tabsheet_before_select', 'syncsmart_add_sync_tab', EVENT_HANDLER_PRIORITY_NEUTRAL, 2);
}

// ajoute un onglet dans la tabsheet native de la page Outils > Synchroniser
// (le clic navigue vers notre propre page plugin, site_update.php ne sait pas
// afficher un onglet supplementaire dans son propre contenu)
function syncsmart_add_sync_tab($sheets, $tab_id)
{
  if ($tab_id === 'site_update')
  {
    $sheets['sync_smart'] = array(
      'caption' => '<span class="icon-flash"></span>' . l10n('Synchro Smart'),
      'url' => SYNCSMART_ADMIN,
    );
  }

  return $sheets;
}
