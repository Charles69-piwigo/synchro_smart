<?php
/*
Plugin Name: synchro_fast
Version: 1.1
Description: Synchronisation par lots (anti-timeout 504) avec selection d'albums. Ajoute un onglet "Synchro Rapide" dans Outils > Synchroniser.
Plugin URI:
Author: Charles69
*/

//============= VERSIONS ============================================
/*

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
  Onglet "Synchro Rapide" : affiche la barre d'onglets native (retour possible
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

defined('SYNCFAST_ID') or define('SYNCFAST_ID', basename(dirname(__FILE__)));
define('SYNCFAST_PATH', PHPWG_PLUGINS_PATH . SYNCFAST_ID . '/');
define('SYNCFAST_ADMIN', get_root_url() . 'admin.php?page=plugin-' . SYNCFAST_ID);

// taille de lot pour la phase de lecture des meta-donnees (la plus couteuse en I/O)
define('SYNCFAST_META_CHUNK_SIZE', 20);

// cle de session utilisee pour stocker l'etat d'avancement (pas de table SQL)
define('SYNCFAST_SESSION_KEY', 'sync_fast_progress');

// nombre maximum d'erreurs detaillees conservees et affichees dans le rapport
// (au-dela, seul le compteur global continue d'augmenter)
define('SYNCFAST_MAX_ERROR_DETAILS', 30);

load_language('plugin.lang', SYNCFAST_PATH);

if (defined('IN_ADMIN'))
{
  add_event_handler('tabsheet_before_select', 'syncfast_add_sync_tab', EVENT_HANDLER_PRIORITY_NEUTRAL, 2);
}

// ajoute un onglet dans la tabsheet native de la page Outils > Synchroniser
// (le clic navigue vers notre propre page plugin, site_update.php ne sait pas
// afficher un onglet supplementaire dans son propre contenu)
function syncfast_add_sync_tab($sheets, $tab_id)
{
  if ($tab_id === 'site_update')
  {
    $sheets['sync_fast'] = array(
      'caption' => '<span class="icon-flash"></span>' . l10n('Synchro Rapide'),
      'url' => SYNCFAST_ADMIN,
    );
  }

  return $sheets;
}
