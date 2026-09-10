<div id="sf-success-banner" class="infos" style="display:none;">
  <i class="eiw-icon icon-ok"></i>
  <ul><li id="sf-success-text"></li></ul>
</div>

<div class="titlePage" id="sf-title">
  <h2>{'Synchro Smart'|@translate}</h2>
</div>

<style>
  #sf-title, #sf-title h2 { text-align: left; }
  #sf-wrap, #sf-wrap * { text-align: left; }
  #sf-wrap fieldset { margin-bottom: 18px; }
  #sf-wrap .sf-suboptions { margin-left: 24px; margin-top: 6px; }
  #sf-wrap .sf-suboptions label { display: block; margin: 4px 0; font-weight: normal; }
  #sf-wrap label.sf-main-option { display: block; margin: 6px 0; font-weight: bold; }
  #sf-wrap .sf-option-note { color: #888; font-weight: normal; font-size: 0.9em; }
  #sf-wrap .sf-option-hint { margin: 2px 0 8px 24px; color: #888; font-size: 0.85em; font-style: italic; }
  #sf-wrap .sf-suboptions .sf-option-hint { margin-left: 0; }
  #sf-wrap .sf-album-path { color: #888; font-size: 0.85em; margin-left: 8px; }
  #sf-wrap .sf-album-row { padding: 3px 8px; white-space: nowrap; cursor: pointer; border-radius: 3px; }
  #sf-wrap .sf-album-row:hover { background: #f2f2f2; }
  #sf-wrap .sf-album-row.sf-selected { background: #eaf2fb; }
  #sf-wrap .sf-album-row.sf-implied { opacity: 0.55; cursor: default; }
  #sf-wrap .sf-album-row.sf-implied:hover { background: none; }
  #sf-wrap .sf-albums-scroll { max-height: 420px; max-width: 480px; overflow-y: auto; overflow-x: auto; border: 1px solid #ddd; border-radius: 4px; padding: 6px 4px; }
  #sf-actions { margin: 16px 0 16px 26px; }
  #sf-progress-wrap { display: none; max-width: 700px; margin-top: 16px; margin-left: 26px; }
  #sf-progress-bar-track { border: 1px solid #ccc; border-radius: 3px; height: 18px; background: #eee; overflow: hidden; }
  #sf-progress-bar-fill { height: 100%; width: 0%; background: #4a90d9; transition: width 0.3s ease; }
  #sf-progress-bar-fill.sf-indeterminate {
    width: 100% !important;
    background-image: repeating-linear-gradient(45deg, #4a90d9, #4a90d9 12px, #6ba7e0 12px, #6ba7e0 24px);
    background-size: 34px 34px;
    animation: sfStripes 1s linear infinite;
  }
  @keyframes sfStripes { from { background-position: 0 0; } to { background-position: 34px 0; } }
  @keyframes sfSpin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
  #sf-wrap .sf-spin {
    display: inline-block;
    width: 12px;
    height: 12px;
    margin-right: 4px;
    vertical-align: -2px;
    border: 2px solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    animation: sfSpin 0.6s linear infinite;
  }
  #sf-busy-spin { color: #4a90d9; margin-left: 10px; vertical-align: middle; }
  #sf-status { margin-top: 8px; }
  #sf-counters { margin-top: 8px; max-width: 420px; border-collapse: collapse; }
  #sf-counters td { padding: 3px 10px; border-bottom: 1px solid #eee; }
  #sf-counters td:first-child { color: #555; }
  #sf-counters td:last-child { text-align: right; font-weight: bold; }
  #sf-counters tr.sf-counter-errors td:last-child { color: #c0392b; }
  #sf-alert { display: none; margin-top: 12px; margin-left: 26px; padding: 10px; border: 1px solid #f0c48a; background: #fff4e8; color: #7a4a11; border-radius: 4px; }
  #sf-errors { display: none; margin-top: 12px; margin-left: 26px; padding: 10px; border: 1px solid #e0a0a0; background: #fdf0f0; color: #7a1f1f; border-radius: 4px; max-width: 700px; }
  #sf-errors p { margin: 0 0 6px; }
  #sf-errors ul { margin: 0; padding-left: 18px; max-height: 220px; overflow-y: auto; }
  #sf-errors li { margin: 2px 0; font-size: 0.85em; word-break: break-all; }
  #sf-errors li .sf-error-reason { color: #555; }
  #sf-errors p.sf-errors-hint { margin-top: 8px; font-style: italic; }
  #sf-filter-review { display: none; margin-top: 12px; margin-left: 26px; padding: 10px; border: 1px solid #f0c48a; background: #fff4e8; color: #7a4a11; border-radius: 4px; max-width: 700px; }
  #sf-filter-review p { margin: 0 0 6px; }
  #sf-filter-review ul { margin: 0; padding-left: 18px; max-height: 220px; overflow-y: auto; }
  #sf-filter-review li { margin: 2px 0; font-size: 0.85em; word-break: break-all; }
  #sf-filter-review li .sf-review-meta { color: #555; }
  #sf-filter-review p.sf-filter-review-hint { margin-top: 8px; font-style: italic; }
  #sf-wrap .sf-toggle { display: inline-block; width: 14px; text-align: center; cursor: pointer; color: #888; user-select: none; font-size: 10px; }
  .sf-toggle-spacer { display: inline-block; width: 14px; }
</style>

<div id="sf-wrap">

  <fieldset>
    <legend>{'Synchronization scope'|@translate}</legend>

    <label class="sf-main-option">
      <input type="radio" name="sf-operation" value="dirs"> {'Répertoires uniquement'|@translate}
    </label>

    <label class="sf-main-option">
      <input type="radio" name="sf-operation" value="files" checked> {'Répertoires + fichiers'|@translate}
    </label>
    <p class="sf-option-hint">{'crée / supprime les albums et les photos, puis lit les méta-données des nouvelles photos'|@translate}</p>

    <label class="sf-main-option">
      <input type="radio" name="sf-operation" value="meta"> {'Mise à jour des méta-données'|@translate}
      <span class="sf-option-note">{'(photos déjà en base)'|@translate}</span>
    </label>
    <div class="sf-suboptions" id="sf-meta-suboptions" style="display:none;">
      <label><input type="checkbox" id="sf-meta-desc"> {'Mettre à jour la description'|@translate}</label>
      <div class="sf-suboptions" id="sf-meta-desc-suboptions" style="display:none;">
        <label><input type="checkbox" id="sf-meta-desc-keep-rich"> {'Sauf si la description est enrichie (HTML)'|@translate}</label>
      </div>
      <label><input type="checkbox" id="sf-meta-title"> {'Mettre à jour le titre'|@translate}</label>
      <label><input type="checkbox" id="sf-meta-author"> {'Mettre à jour l\'auteur'|@translate}</label>
      <label><input type="checkbox" id="sf-meta-tags"> {'Mettre à jour les mots-clés'|@translate}</label>
      <p class="sf-option-hint">{'remplace par ceux du fichier ; les tags visages sont préservés'|@translate}</p>
      <div class="sf-suboptions" id="sf-meta-tags-suboptions" style="display:none;">
        <label><input type="checkbox" id="sf-meta-tags-merge"> {'Fusionner : ajouter sans supprimer les mots-clés absents du fichier'|@translate}</label>
      </div>
    </div>

    <label class="sf-main-option" style="margin-top:14px;">
      <input type="checkbox" id="sf-recursive" checked> {'Rechercher dans les sous-répertoires'|@translate}
    </label>
  </fieldset>

  <fieldset>
    <legend>{'Albums'|@translate}</legend>

    {if empty($syncsmart_albums)}
      <p>{'No album with a physical directory was found.'|@translate}</p>
    {else}
    <div class="sf-albums-scroll">
      {foreach from=$syncsmart_albums item=album}
      <div class="sf-album-row" data-id="{$album.id}" data-depth="{$album.depth}"{if $album.depth > 0} style="display:none;"{/if}>
        <span style="padding-left:{$album.depth*20}px;">
          {if $album.has_children}
            <span class="sf-toggle" data-state="collapsed">&#9658;</span>
          {else}
            <span class="sf-toggle-spacer"></span>
          {/if}
          {$album.name}
          {if $album.path}<span class="sf-album-path">{$album.path}</span>{/if}
        </span>
      </div>
      {/foreach}
    </div>
    {/if}
  </fieldset>

  <div id="sf-actions">
    <button type="button" class="buttonLike" id="sf-start"><i class="icon-flash"></i> {'Synchroniser'|@translate}</button>
    <button type="button" class="buttonLike" id="sf-stop" style="display:none;">{'Arrêter'|@translate}</button>
    <span id="sf-busy-spin" class="sf-spin" style="display:none;"></span>
  </div>

  <div id="sf-alert"></div>

  <div id="sf-errors">
    <p><strong>{'Éléments en erreur'|@translate}</strong></p>
    <ul id="sf-errors-list"></ul>
    <p id="sf-errors-truncated" style="display:none;"></p>
    <p class="sf-errors-hint">{'Corrigez le(s) nom(s) ci-dessus puis relancez la synchronisation.'|@translate}</p>
  </div>

  <div id="sf-filter-review">
    <p><strong>{'Filtres SmartAlbums à revoir'|@translate}</strong></p>
    <ul id="sf-filter-review-list"></ul>
    <p id="sf-filter-review-truncated" style="display:none;"></p>
    <p class="sf-filter-review-hint">{'Ces filtres « album » ciblaient un répertoire qui a disparu (renommé ou supprimé). Re-sélectionnez l\'album cible dans la configuration du SmartAlbum concerné.'|@translate}</p>
  </div>

  <div id="sf-progress-wrap">
    <div id="sf-progress-bar-track">
      <div id="sf-progress-bar-fill"></div>
    </div>
    <p id="sf-status" class="formHint"></p>
    <table id="sf-counters" class="table2" style="display:none;">
      <tbody></tbody>
    </table>
  </div>

</div>

{footer_script}
(function() {
  var adminUrl = '{$SYNCSMART_ADMIN|escape:'javascript'}';
  var token = '{$SYNCSMART_TOKEN|escape:'javascript'}';

  var L = {
    confirmRootSync: '{'Aucun album sélectionné, voulez-vous synchroniser ./galleries en totalité ?'|@translate|escape:'javascript'}',
    selectOption: '{'Please select at least one synchronization option.'|@translate|escape:'javascript'}',
    networkError: '{'Network error - automatic retry...'|@translate|escape:'javascript'}',
    startFailed: '{'The synchronization could not be started.'|@translate|escape:'javascript'}',
    chunkFailed: '{'The next batch request failed.'|@translate|escape:'javascript'}',
    stopRequested: '{'Stop requested...'|@translate|escape:'javascript'}',
    starting: '{'Starting...'|@translate|escape:'javascript'}',
    done: '{'Synchronization finished'|@translate|escape:'javascript'}',
    phaseDirs: '{'Directories'|@translate|escape:'javascript'}',
    phaseFiles: '{'Albums'|@translate|escape:'javascript'}',
    phaseMeta: '{'Metadata'|@translate|escape:'javascript'}',
    syncButton: '{'Synchroniser'|@translate|escape:'javascript'}',
    errorsTruncated: '{'et %d autre(s) erreur(s) non affichée(s)...'|@translate|escape:'javascript'}',
    errorTypeLabels: {
      invalid_chars: '{'caractère(s) interdit(s) :'|@translate|escape:'javascript'}',
      missing_dir: '{'répertoire introuvable ou inaccessible'|@translate|escape:'javascript'}',
      metadata_failed: '{'échec de lecture des méta-données'|@translate|escape:'javascript'}'
    },
    tagRecycleWarning: '{'Attention : %d identifiant(s) de tag libéré(s) seront réutilisés par Piwigo au prochain nouveau mot-clé ; ne purgez pas les tags orphelins sans revérifier vos filtres SmartAlbums.'|@translate|escape:'javascript'}',
    filterReviewTruncated: '{'et %d autre(s) filtre(s) non affiché(s)...'|@translate|escape:'javascript'}',
    counterLabels: {
      albums_analyzed: '{'Albums analysés'|@translate|escape:'javascript'}',
      created_categories: '{'Albums créés'|@translate|escape:'javascript'}',
      deleted_categories: '{'Albums supprimés'|@translate|escape:'javascript'}',
      files_analyzed: '{'Photos analysées'|@translate|escape:'javascript'}',
      new_images: '{'Nouvelles photos'|@translate|escape:'javascript'}',
      deleted_images: '{'Photos supprimées'|@translate|escape:'javascript'}',
      meta_updated: '{'Méta-données mises à jour'|@translate|escape:'javascript'}',
      album_filters_fixed: '{'Filtres album recalés'|@translate|escape:'javascript'}',
      album_filters_review: '{'Filtres album à revoir'|@translate|escape:'javascript'}',
      errors: '{'Erreurs'|@translate|escape:'javascript'}'
    }
  };

  var $operationRadios = document.querySelectorAll('input[name="sf-operation"]');
  var $metaSub = document.getElementById('sf-meta-suboptions');
  var $metaDesc = document.getElementById('sf-meta-desc');
  var $metaDescSub = document.getElementById('sf-meta-desc-suboptions');
  var $metaDescKeepRich = document.getElementById('sf-meta-desc-keep-rich');
  var $metaTitle = document.getElementById('sf-meta-title');
  var $metaAuthor = document.getElementById('sf-meta-author');
  var $metaTags = document.getElementById('sf-meta-tags');
  var $metaTagsSub = document.getElementById('sf-meta-tags-suboptions');
  var $metaTagsMerge = document.getElementById('sf-meta-tags-merge');
  var $recursive = document.getElementById('sf-recursive');

  function selectedOperation() {
    for (var i = 0; i < $operationRadios.length; i++) {
      if ($operationRadios[i].checked) { return $operationRadios[i].value; }
    }
    return '';
  }
  var $startBtn = document.getElementById('sf-start');
  var $stopBtn = document.getElementById('sf-stop');
  var $alert = document.getElementById('sf-alert');
  var $errors = document.getElementById('sf-errors');
  var $errorsList = document.getElementById('sf-errors-list');
  var $errorsTruncated = document.getElementById('sf-errors-truncated');
  var $filterReview = document.getElementById('sf-filter-review');
  var $filterReviewList = document.getElementById('sf-filter-review-list');
  var $filterReviewTruncated = document.getElementById('sf-filter-review-truncated');
  var $progressWrap = document.getElementById('sf-progress-wrap');
  var $barFill = document.getElementById('sf-progress-bar-fill');
  var $status = document.getElementById('sf-status');
  var $counters = document.getElementById('sf-counters');
  var $successBanner = document.getElementById('sf-success-banner');
  var $successText = document.getElementById('sf-success-text');
  var $busySpin = document.getElementById('sf-busy-spin');

  var stopRequested = false;

  // les sous-options meta ne concernent que le choix "Mise à jour des méta-données"
  function refreshMetaOptions() {
    $metaSub.style.display = (selectedOperation() === 'meta') ? 'block' : 'none';
  }
  for (var oi = 0; oi < $operationRadios.length; oi++) {
    $operationRadios[oi].addEventListener('change', refreshMetaOptions);
  }
  refreshMetaOptions();

  $metaDesc.addEventListener('change', function() {
    $metaDescSub.style.display = this.checked ? 'block' : 'none';
    if (!this.checked) { $metaDescKeepRich.checked = false; }
  });
  $metaTags.addEventListener('change', function() {
    $metaTagsSub.style.display = this.checked ? 'block' : 'none';
    if (!this.checked) { $metaTagsMerge.checked = false; }
  });
  // retrouve les lignes ancetres d'une ligne en remontant l'ordre prefixe
  // (parent toujours avant ses enfants dans le DOM) via la profondeur
  function getAncestorRows($row) {
    var ancestors = [];
    var depth = parseInt($row.getAttribute('data-depth'), 10);
    var el = $row.previousElementSibling;
    while (el && depth > 0) {
      var elDepth = parseInt(el.getAttribute('data-depth'), 10);
      if (elDepth < depth) {
        ancestors.push(el);
        depth = elDepth;
      }
      el = el.previousElementSibling;
    }
    return ancestors;
  }

  // 'sf-explicit' = la ligne a ete cliquee directement par l'utilisateur.
  // 'sf-implied' = un ancetre est selectionne (explicite ou implicite) et,
  // en mode recursif, l'inclut deja cote serveur (syncsmart_resolve_scope).
  // 'sf-selected' (affichage) = explicit OU implied. On garde les deux
  // premiers etats separes pour qu'un depli/repli de l'ancetre ne "colle"
  // pas une selection implicite sur ses descendants.
  function refreshCascade() {
    var recursive = $recursive.checked;
    document.querySelectorAll('.sf-album-row').forEach(function($row) {
      var explicit = $row.classList.contains('sf-explicit');
      var implied = recursive && getAncestorRows($row).some(function($a) {
        return $a.classList.contains('sf-explicit') || $a.classList.contains('sf-implied');
      });
      $row.classList.toggle('sf-implied', implied);
      $row.classList.toggle('sf-selected', explicit || implied);
    });
  }

  $recursive.addEventListener('change', refreshCascade);

  // cliquer sur une ligne (comme dans galleries_link_manager) la selectionne ;
  // selection unique (comme galleries_link_manager, qui ne choisit qu'un seul
  // dossier) pour eviter les etats d'affichage incoherents quand on clique
  // plusieurs lignes puis qu'on change d'avis
  document.querySelectorAll('.sf-album-row').forEach(function($row) {
    $row.addEventListener('click', function(ev) {
      if (ev.target.closest('.sf-toggle') || $row.classList.contains('sf-implied')) { return; }
      var wasExplicit = $row.classList.contains('sf-explicit');
      document.querySelectorAll('.sf-album-row.sf-explicit').forEach(function($r) {
        $r.classList.remove('sf-explicit');
      });
      if (!wasExplicit) { $row.classList.add('sf-explicit'); }
      refreshCascade();
    });
  });

  refreshCascade();

  // arbre repliable en cascade : la liste est deja triee en ordre prefixe
  // (parent avant ses enfants), donc les descendants d'une ligne sont
  // simplement les lignes suivantes tant que leur profondeur reste superieure
  document.querySelectorAll('.sf-toggle').forEach(function($toggle) {
    $toggle.addEventListener('click', function() {
      var $row = $toggle.closest('.sf-album-row');
      var depth = parseInt($row.getAttribute('data-depth'), 10);
      var expanded = $toggle.getAttribute('data-state') === 'expanded';
      var $next = $row.nextElementSibling;

      if (expanded) {
        // repli : cache tous les descendants, et remet leurs propres
        // toggles a l'etat "replie" pour qu'un futur depli reparte de zero
        while ($next && parseInt($next.getAttribute('data-depth'), 10) > depth) {
          $next.style.display = 'none';
          var $subToggle = $next.querySelector('.sf-toggle');
          if ($subToggle) {
            $subToggle.setAttribute('data-state', 'collapsed');
            $subToggle.innerHTML = '&#9658;';
          }
          $next = $next.nextElementSibling;
        }
        $toggle.setAttribute('data-state', 'collapsed');
        $toggle.innerHTML = '&#9658;';
      } else {
        // depli : ne montre que les enfants directs (profondeur + 1), les
        // niveaux plus profonds restent replies jusqu'a leur propre clic
        while ($next && parseInt($next.getAttribute('data-depth'), 10) > depth) {
          var nextDepth = parseInt($next.getAttribute('data-depth'), 10);
          if (nextDepth === depth + 1) {
            $next.style.display = '';
          }
          $next = $next.nextElementSibling;
        }
        $toggle.setAttribute('data-state', 'expanded');
        $toggle.innerHTML = '&#9660;';
      }
    });
  });

  function showAlert(message) {
    if (!message) {
      $alert.style.display = 'none';
      $alert.textContent = '';
      return;
    }
    $alert.textContent = message;
    $alert.style.display = 'block';
  }

  function showSuccessBanner() {
    $successText.textContent = L.done;
    $successBanner.style.display = '';
  }

  function hideSuccessBanner() {
    $successBanner.style.display = 'none';
  }

  function getCheckedAlbumIds() {
    var ids = [];
    // seules les selections explicites sont envoyees : le backend deduit deja
    // les descendants d'un album selectionne en mode recursif
    document.querySelectorAll('.sf-album-row.sf-explicit').forEach(function($row) { ids.push($row.getAttribute('data-id')); });
    return ids;
  }

  function phaseLabel(phase) {
    if (phase === 'dirs') return L.phaseDirs;
    if (phase === 'files') return L.phaseFiles;
    if (phase === 'meta') return L.phaseMeta;
    return '';
  }

  var COUNTER_ORDER = [
    'albums_analyzed', 'created_categories', 'deleted_categories',
    'files_analyzed', 'new_images', 'deleted_images',
    'meta_updated', 'album_filters_fixed', 'album_filters_review', 'errors'
  ];

  function updateCounters(counters) {
    var $tbody = $counters.querySelector('tbody');
    if (!counters) {
      $counters.style.display = 'none';
      $tbody.innerHTML = '';
      return;
    }
    $tbody.innerHTML = COUNTER_ORDER.map(function(key) {
      var rowClass = key === 'errors' ? ' class="sf-counter-errors"' : '';
      var label = L.counterLabels[key];
      var value = Number(counters[key] || 0);
      return '<tr' + rowClass + '><td>' + label + '</td><td>' + value + '</td></tr>';
    }).join('');
    $counters.style.display = '';
  }

  function updateErrorDetails(details, truncated, totalErrors) {
    if (!details || details.length === 0) {
      $errors.style.display = 'none';
      $errorsList.innerHTML = '';
      $errorsTruncated.style.display = 'none';
      return;
    }
    $errorsList.innerHTML = details.map(function(d) {
      var reason = L.errorTypeLabels[d.type] || d.type;
      if (d.type === 'invalid_chars' && d.chars) {
        reason += ' ' + escapeHtml(d.chars).split(' ').map(function(c) { return '"' + c + '"'; }).join(', ');
      }
      return '<li>' + escapeHtml(d.path) + ' — <span class="sf-error-reason">' + reason + '</span></li>';
    }).join('');
    if (truncated) {
      var remaining = Math.max(Number(totalErrors || 0) - details.length, 0);
      $errorsTruncated.textContent = L.errorsTruncated.replace('%d', remaining);
      $errorsTruncated.style.display = 'block';
    } else {
      $errorsTruncated.style.display = 'none';
    }
    $errors.style.display = 'block';
  }

  function escapeHtml(s) {
    var div = document.createElement('div');
    div.textContent = String(s);
    return div.innerHTML;
  }

  // filtres SmartAlbums 'album' dont le répertoire cible a disparu (renommage) :
  // non modifiés automatiquement, listés pour re-pointage manuel
  function updateFilterReview(list, total) {
    if (!list || list.length === 0) {
      $filterReview.style.display = 'none';
      $filterReviewList.innerHTML = '';
      $filterReviewTruncated.style.display = 'none';
      return;
    }
    $filterReviewList.innerHTML = list.map(function(d) {
      var label = d.breadcrumb || d.smart_album || '?';
      return '<li>' + escapeHtml(label) +
        ' <span class="sf-review-meta">(' + escapeHtml(d.cond || '') + ')</span>' +
        (d.path ? ' — ' + escapeHtml(d.path) : '') + '</li>';
    }).join('');
    var remaining = Math.max(Number(total || 0) - list.length, 0);
    if (remaining > 0) {
      $filterReviewTruncated.textContent = L.filterReviewTruncated.replace('%d', remaining);
      $filterReviewTruncated.style.display = 'block';
    } else {
      $filterReviewTruncated.style.display = 'none';
    }
    $filterReview.style.display = 'block';
  }

  function updateProgress(payload) {
    var total = Number(payload.phase_total || 0);
    var index = Number(payload.phase_index || 0);
    var percent = total > 0 ? Math.min(100, Math.round((index / total) * 100)) : 0;

    $barFill.classList.remove('sf-indeterminate');
    $barFill.style.width = percent + '%';

    var label = phaseLabel(payload.phase);
    var statusText = label ? (label + ' : ' + index + ' / ' + total + ' (' + percent + '%)') : '';
    if (payload.current_label) {
      statusText += ' — ' + payload.current_label;
    }
    $status.textContent = statusText;
    updateCounters(payload.counters);
    updateErrorDetails(payload.error_details, payload.error_details_truncated, payload.counters && payload.counters.errors);
    updateFilterReview(payload.filter_review, payload.counters && payload.counters.album_filters_review);
    if (payload.done && Number(payload.tag_recycle_window || 0) > 0) {
      showAlert(L.tagRecycleWarning.replace('%d', Number(payload.tag_recycle_window)));
    }
  }

  function postApi(action, extraData) {
    var formData = new FormData();
    formData.append('pwg_token', token);
    formData.append(action, '1');

    if (extraData) {
      Object.keys(extraData).forEach(function(key) {
        var value = extraData[key];
        if (Array.isArray(value)) {
          value.forEach(function(v) { formData.append(key + '[]', v); });
        } else {
          formData.append(key, value);
        }
      });
    }

    return fetch(adminUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
      .then(function(response) {
        return response.text().then(function(text) {
          var data;
          try { data = JSON.parse(text); }
          catch (e) { throw new Error(text || 'Invalid JSON response'); }
          if (!response.ok) { throw new Error((data && data.message) || text || ('HTTP ' + response.status)); }
          return data;
        });
      });
  }

  function runChunk() {
    if (stopRequested) {
      $status.textContent = L.stopRequested;
      finish();
      return;
    }

    postApi('syncsmart_chunk').then(function(data) {
      if (!data.success) {
        showAlert(data.message || L.chunkFailed);
        finish();
        return;
      }

      updateProgress(data);

      if (data.done) {
        $status.textContent = L.done;
        showSuccessBanner();
        finish();
        return;
      }

      runChunk();
    }).catch(function() {
      $status.textContent = L.networkError;
      window.setTimeout(runChunk, 3000);
    });
  }

  function finish() {
    $startBtn.disabled = false;
    $startBtn.innerHTML = '<i class="icon-flash"></i> ' + L.syncButton;
    $stopBtn.style.display = 'none';
    $busySpin.style.display = 'none';
  }

  function startSync() {
    showAlert('');
    hideSuccessBanner();
    var albumIds = getCheckedAlbumIds();
    var operation = selectedOperation();
    var rootSync = false;
    var noAlbumsAtAll = document.querySelectorAll('.sf-album-row').length === 0;

    if (albumIds.length === 0 && noAlbumsAtAll) {
      // toute premiere synchronisation : aucun album n'existe encore en base,
      // donc rien a cocher. On amorce directement sur ./galleries en
      // "repertoires uniquement" (les fichiers/meta-donnees suivront lors
      // d'un prochain lancement, une fois les albums crees) sans demander de
      // confirmation puisqu'il n'y a pas d'autre choix possible.
      rootSync = true;
      operation = 'dirs';
    } else {
      if (!operation) {
        showAlert(L.selectOption);
        return;
      }
      if (albumIds.length === 0) {
        if (!window.confirm(L.confirmRootSync)) {
          return;
        }
        rootSync = true;
      }
    }

    stopRequested = false;
    $startBtn.disabled = true;
    $startBtn.innerHTML = '<i class="icon-flash"></i> ' + L.starting;
    $stopBtn.style.display = 'inline-block';
    $stopBtn.disabled = false;
    $busySpin.style.display = 'inline-block';
    $progressWrap.style.display = 'block';
    $barFill.classList.add('sf-indeterminate');
    $status.textContent = L.starting;
    updateCounters(null);
    updateErrorDetails(null);
    updateFilterReview(null);

    postApi('syncsmart_start', {
      cat_ids: albumIds,
      operation: operation,
      meta_desc: $metaDesc.checked ? '1' : '',
      meta_desc_keep_rich: $metaDescKeepRich.checked ? '1' : '',
      meta_title: $metaTitle.checked ? '1' : '',
      meta_author: $metaAuthor.checked ? '1' : '',
      meta_tags: $metaTags.checked ? '1' : '',
      meta_tags_merge: $metaTagsMerge.checked ? '1' : '',
      recursive: $recursive.checked ? '1' : '',
      root_sync: rootSync ? '1' : ''
    }).then(function(data) {
      if (!data.success) {
        showAlert(data.message || L.startFailed);
        finish();
        return;
      }

      updateProgress(data);

      if (data.done) {
        $status.textContent = L.done;
        showSuccessBanner();
        finish();
        return;
      }

      runChunk();
    }).catch(function(error) {
      showAlert((error && error.message) || L.startFailed);
      finish();
    });
  }

  $startBtn.addEventListener('click', startSync);
  $stopBtn.addEventListener('click', function() {
    stopRequested = true;
    $stopBtn.disabled = true;
  });

  document.addEventListener('click', function() {
    hideSuccessBanner();
  });
})();
{/footer_script}
