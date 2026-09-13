# Synchro Smart

*[Version française](README.fr.md)*

A [Piwigo](https://piwigo.org/) admin plugin providing a chunked, timeout-safe alternative to Piwigo's native directory synchronization tool, with extra safety nets for [SmartAlbums](https://piwigo.org/ext/extension_view.php?eid=396) filters.

## Why

On large galleries, Piwigo's built-in "Synchronize" (`Tools > Synchronize`) runs as one long HTTP request and can hit a reverse-proxy timeout (504) before it finishes. Synchro Smart adds a "Synchro Smart" tab next to it that does the same job — scanning the filesystem, creating/removing albums, importing photos, refreshing metadata — but in small batches driven by repeated AJAX calls, so no single request runs long enough to time out. Progress is shown live with a progress bar and a final results summary.

It also fixes a subtle Piwigo core issue that can silently corrupt SmartAlbums: directory renames are seen by any sync as *delete + add*, and Piwigo's default id allocation (`MAX(id)+1`) can hand the freed id to an unrelated album, silently breaking `type="album"` filters (or, worse, making a `cond="none"` filter match every photo in the gallery). Synchro Smart never recycles category ids, and automatically repairs or flags any SmartAlbums filter affected by a sync.

## Features

- **Chunked sync** — one album (or one metadata batch) per AJAX call, avoiding 504 timeouts on large galleries.
- **Album selection** — pick any single album (with optional recursive subtree) instead of syncing the whole gallery, or leave nothing selected to sync from the site root.
- **Three exclusive sync scopes**, selected via radio buttons:
  - *Directories only* — create/remove albums to match the filesystem.
  - *Directories + files* — also imports new photos, reading their metadata once on import.
  - *Metadata update* — re-reads metadata for every photo already in the database, per selected field.
- **Per-field update policy** for description, title, author, and keywords: fill empty fields only, or also overwrite existing values (with an option to never overwrite an HTML-enriched description).
- **GPS**: always updated automatically from file metadata, but never overwrites a position already saved in Piwigo.
- **Keywords**: optional additive merge (`add_tags`, nothing removed) instead of replace; face-recognition tags (from the `face_tag` plugin, if installed) always survive a replace.
- **Live progress bar**, error listing (capped, with an overall counter beyond the cap), and a final summary table.
- **SmartAlbums protection**:
  - Category ids are never recycled after a deletion, so a directory rename can't silently redirect an existing SmartAlbums `album` filter to the wrong album.
  - Filters that would otherwise break are automatically repaired when the directory reappears at the same path; filters that can't be auto-repaired are listed in the report (with their full album breadcrumb) for manual re-pointing.
  - A warning is shown if tag id recycling is about to affect a `tags` filter.
- Native tabsheet integration: the "Synchro Smart" tab sits alongside Piwigo's own "Synchronize" and "Site Manager" tabs, so navigating between them still works.

## Requirements

- A working [Piwigo](https://piwigo.org/) installation.


## Installation

1. Copy (or clone) this repository into your Piwigo installation's `plugins/` directory. The folder name becomes the plugin id, so keep it as `synchro_smart`:
   ```
   plugins/synchro_smart/
   ```
2. In the Piwigo admin, go to `Plugins > Manage`, find **Synchro Smart**, and activate it.
3. Go to `Tools > Synchronize` — a new **Synchro Smart** tab appears next to the native tabs.

A packaged release zip can also be generated with `generate-release-zip.ps1` (PowerShell) for manual upload through the Piwigo plugin manager.

## Usage

1. Open `Tools > Synchronize > Synchro Smart`.
2. Pick a scope (*Directories only*, *Directories + files*, or *Metadata update*).
3. Select an album in the tree (optionally recursive), or leave the selection empty to sync from the site root.
4. For *Metadata update*, choose which fields to refresh and whether existing values should be overwritten.
5. Start the sync and watch the progress bar. When it finishes, review the results table — and the SmartAlbums filter report, if any filters need manual attention.

**After a run that deleted photos**, manually purge the user cache from `Tools > Maintenance > "Purge user cache"` 

