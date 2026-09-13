# Synchro Smart

*[English version](README.md)*

Un plugin d'administration [Piwigo](https://piwigo.org/) qui fournit une alternative à la synchronisation native de répertoires, insensible aux timeouts, avec des garde-fous supplémentaires pour les filtres [SmartAlbums](https://piwigo.org/ext/extension_view.php?eid=396).

## Pourquoi

Sur les galeries volumineuses, la synchronisation native de Piwigo (`Outils > Synchroniser`) s'exécute en une seule requête HTTP longue, qui peut buter sur un timeout de reverse-proxy (504) avant de se terminer. Synchro Smart ajoute un onglet « Synchro Smart » à côté, qui fait le même travail — scanner le système de fichiers, créer/supprimer des albums, importer les photos, rafraîchir les métadonnées — mais par petits lots, via des appels AJAX répétés, de sorte qu'aucune requête individuelle ne dure assez longtemps pour timeout. La progression est affichée en direct avec une barre d'avancement et un tableau de synthèse final.

Le plugin corrige aussi un défaut subtil du cœur de Piwigo qui peut corrompre silencieusement les SmartAlbums : un renommage de répertoire est vu par toute synchro comme un *delete + add*, et l'allocation d'id par défaut de Piwigo (`MAX(id)+1`) peut donner l'id libéré à un album sans rapport, cassant silencieusement les filtres `type="album"` (ou pire, faisant qu'un filtre `cond="none"` matche toutes les photos de la galerie). Synchro Smart ne recycle jamais les id de catégories, et répare ou signale automatiquement tout filtre SmartAlbums affecté par une synchro.

## Fonctionnalités

- **Synchro par lots** — un album (ou un lot de métadonnées) par appel AJAX, ce qui évite les timeouts 504 sur les grandes galeries.
- **Sélection d'album** — choisir un album précis (récursif ou non) plutôt que de synchroniser toute la galerie, ou ne rien sélectionner pour synchroniser depuis la racine du site.
- **Trois périmètres de synchro exclusifs**, via boutons radio :
  - *Répertoires uniquement* — crée/supprime les albums pour coller au système de fichiers.
  - *Répertoires + fichiers* — importe aussi les nouvelles photos, en lisant leurs métadonnées une fois à l'import.
  - *Mise à jour des méta-données* — relit les métadonnées de toutes les photos déjà en base, champ par champ.
- **Politique de mise à jour par champ** pour la description, le titre, l'auteur et les mots-clés : ne remplir que les champs vides, ou aussi écraser les valeurs existantes (avec une option pour ne jamais écraser une description enrichie en HTML).
- **GPS** : toujours mis à jour automatiquement depuis les méta-données du fichier, mais ne remplace jamais une position déjà enregistrée dans Piwigo.
- **Mots-clés** : fusion additive optionnelle (`add_tags`, rien n'est supprimé) au lieu d'un remplacement ; les tags de reconnaissance faciale (du plugin `face_tag`, s'il est installé) survivent toujours à un remplacement.
- **Barre de progression en direct**, liste des erreurs (plafonnée, avec un compteur global au-delà du plafond), et tableau de synthèse final.
- **Protection SmartAlbums** :
  - Les id de catégories ne sont jamais recyclés après une suppression, donc un renommage de répertoire ne peut pas rediriger silencieusement un filtre SmartAlbums `album` existant vers le mauvais album.
  - Les filtres qui seraient autrement cassés sont réparés automatiquement quand le répertoire réapparaît au même chemin ; ceux qui ne peuvent pas être réparés automatiquement sont listés dans le rapport (avec le fil d'Ariane complet de l'album) pour un re-pointage manuel.
  - Un avertissement est affiché si un recyclage d'id de tag est sur le point d'affecter un filtre `tags`.
- Intégration native à la barre d'onglets : l'onglet « Synchro Smart » cohabite avec les onglets natifs « Synchroniser » et « Gestionnaire de sites », la navigation entre eux reste fonctionnelle.

## Prérequis

- Une installation [Piwigo](https://piwigo.org/) fonctionnelle.


## Installation

1. Copier (ou cloner) ce dépôt dans le répertoire `plugins/` de votre installation Piwigo. Le nom du dossier devient l'id du plugin, à conserver tel quel :
   ```
   plugins/synchro_smart/
   ```
2. Dans l'admin Piwigo, aller dans `Plugins > Gérer`, trouver **Synchro Smart**, et l'activer.
3. Aller dans `Outils > Synchroniser` — un nouvel onglet **Synchro Smart** apparaît à côté des onglets natifs.

Un zip de release peut aussi être généré avec `generate-release-zip.ps1` (PowerShell) pour un dépôt manuel via le gestionnaire de plugins Piwigo.

## Utilisation

1. Ouvrir `Outils > Synchroniser > Synchro Smart`.
2. Choisir un périmètre (*Répertoires uniquement*, *Répertoires + fichiers*, ou *Mise à jour des méta-données*).
3. Sélectionner un album dans l'arbre (récursif ou non), ou laisser la sélection vide pour synchroniser depuis la racine du site.
4. Pour *Mise à jour des méta-données*, choisir les champs à rafraîchir et si les valeurs existantes doivent être écrasées.
5. Lancer la synchro et suivre la barre de progression. À la fin, consulter le tableau de résultats — et le rapport de filtres SmartAlbums, si des filtres nécessitent une intervention manuelle.

**Après une synchro ayant supprimé des photos**, purger manuellement le cache utilisateur depuis `Outils > Maintenance > « Purger le cache utilisateur »` 