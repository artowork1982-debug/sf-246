<?php
// app/pages/settings/tab_worksites.php
declare(strict_types=1);

// Hae työmaat, niiden display API-avaimet ja aktiivisten flashien määrä
$worksites = [];
$worksitesRes = $mysqli->query(
    'SELECT w.id, w.name, w.is_active, k.api_key AS display_api_key, k.id AS display_key_id,
            COUNT(t.id) AS active_flash_count
     FROM sf_worksites w
     LEFT JOIN sf_display_api_keys k ON k.worksite_id = w.id AND k.is_active = 1
     LEFT JOIN sf_flash_display_targets t ON t.display_key_id = k.id AND t.is_active = 1
     GROUP BY w.id, w.name, w.is_active, k.api_key, k.id
     ORDER BY w.name ASC'
);
if (!$worksitesRes) {
    // Fallback if worksite_id column not yet migrated
    $worksitesRes = $mysqli->query('SELECT id, name, is_active, 0 AS active_flash_count FROM sf_worksites ORDER BY name ASC');
}
if ($worksitesRes) {
    while ($w = $worksitesRes->fetch_assoc()) {
        $worksites[] = $w;
    }
    $worksitesRes->free();
}
?>

<h2>
    <img src="<?= $baseUrl ?>/assets/img/icons/worksite.svg" alt="" class="sf-heading-icon" aria-hidden="true">
    <?= htmlspecialchars(
        sf_term('settings_worksites_heading', $currentUiLang) ?? 'Työmaiden hallinta',
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</h2>

<form
    method="post"
    class="sf-form-inline"
action="app/actions/worksites_save.php"
    data-sf-ajax="1"
>
    <input type="hidden" name="form_action" value="add">
    <?= sf_csrf_field() ?>
    <label for="ws-name">
        <?= htmlspecialchars(
            sf_term('settings_worksites_add_label', $currentUiLang) ?? 'Uusi työmaa:',
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </label>
    <input type="text" id="ws-name" name="name" required>
    <button type="submit">
        <?= htmlspecialchars(
            sf_term('btn_add', $currentUiLang) ?? 'Lisää',
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </button>
</form>

<?php if (empty($worksites)): ?>
    <p class="sf-notice sf-notice-info">
        <?= htmlspecialchars(
            sf_term('settings_worksites_empty', $currentUiLang) ?? 'Ei työmaita. Lisää ensimmäinen työmaa yllä olevalla lomakkeella.',
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </p>
<?php else: ?>
<table class="sf-table sf-table-worksites">
    <thead>
        <tr>
            <th>
                <?= htmlspecialchars(
                    sf_term('settings_worksites_col_name', $currentUiLang) ?? 'Nimi',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </th>
            <th>
                <?= htmlspecialchars(
                    sf_term('settings_worksites_col_active', $currentUiLang) ?? 'Aktiivinen',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </th>
            <th>
                <img src="<?= $baseUrl ?>/assets/img/icons/display.svg" alt="" class="sf-icon" aria-hidden="true" style="width:16px;height:16px;vertical-align:middle;">
                <?= htmlspecialchars(
                    sf_term('settings_worksites_col_flashes', $currentUiLang) ?? 'Aktiiviset flashit',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </th>
            <th>
                <?= htmlspecialchars(
                    sf_term('settings_worksites_col_actions', $currentUiLang) ?? 'Toiminnot',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </th>
            <th>
                <img src="<?= $baseUrl ?>/assets/img/icons/playlist.svg" alt="" class="sf-icon" aria-hidden="true" style="width:16px;height:16px;vertical-align:middle;">
                <?= htmlspecialchars(
                    sf_term('settings_worksites_col_playlist', $currentUiLang) ?? 'Ajolista',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </th>
            <th>
                <?= htmlspecialchars(
                    sf_term('xibo_col_heading', $currentUiLang) ?? 'Xibo-koodi',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($worksites as $ws): ?>
            <tr class="<?= ((int)$ws['is_active'] === 1) ? '' : 'is-inactive' ?>">
                <td><?= htmlspecialchars($ws['name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>
    <?= ((int)$ws['is_active'] === 1)
        ? htmlspecialchars(sf_term('common_yes', $currentUiLang) ?? 'Kyllä', ENT_QUOTES, 'UTF-8')
        : htmlspecialchars(sf_term('common_no', $currentUiLang) ?? 'Ei', ENT_QUOTES, 'UTF-8') ?>
</td>
                <td>
                    <span class="sf-flash-count">
                        <?= (int)($ws['active_flash_count'] ?? 0) ?>
                    </span>
                </td>
                <td>
                    <form
                        method="post"
                        class="sf-inline-form"
                        action="app/actions/worksites_save.php"
                        data-sf-ajax="1"
                    >
                        <input type="hidden" name="form_action" value="toggle">
                        <?= sf_csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$ws['id'] ?>">
                        <button type="submit" class="sf-btn sf-btn-sm <?= ((int)$ws['is_active'] === 1) ? 'sf-btn-outline-danger' : 'sf-btn-outline-primary' ?>">
                            <?php
                            if ((int)$ws['is_active'] === 1) {
                                echo htmlspecialchars(
                                    sf_term('settings_worksites_action_disable', $currentUiLang) ?? 'Passivoi',
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            } else {
                                echo htmlspecialchars(
                                    sf_term('settings_worksites_action_enable', $currentUiLang) ?? 'Aktivoi',
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            }
                            ?>
                        </button>
                    </form>
                </td>
                <td>
                    <?php if (!empty($ws['display_api_key'])): ?>
                        <?php if (!empty($ws['display_key_id'])): ?>
                        <a href="<?= htmlspecialchars(
                            ($baseUrl ?? '') . '/index.php?page=playlist_manager&display_key_id=' . (int)$ws['display_key_id'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                           class="sf-btn sf-btn-outline-primary sf-btn-sm">
                            <img src="<?= $baseUrl ?>/assets/img/icons/playlist.svg" alt="" aria-hidden="true" style="width:14px;height:14px;vertical-align:middle;">
                            <?= htmlspecialchars(sf_term('playlist_manager_heading', $currentUiLang) ?? 'Hallinnoi', ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($ws['display_api_key'])): ?>
                        <button type="button"
                            class="sf-btn sf-btn-outline-primary sf-btn-sm"
                            data-modal-open="#xiboModal<?= (int)$ws['id'] ?>">
                            📋 <?= htmlspecialchars(sf_term('xibo_col_heading', $currentUiLang) ?? 'Xibo-koodi', ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php
// Xibo modals - one per worksite that has an API key
foreach ($worksites as $ws):
    if (empty($ws['display_api_key'])) continue;
    $xiboKey = $ws['display_api_key'];
    $xiboLabel = htmlspecialchars($ws['name'], ENT_QUOTES, 'UTF-8');
    $xiboWsId = (int)$ws['id'];
    $playlistBase = rtrim($baseUrl ?? '', '/') . '/app/api/display_playlist.php';
    $htmlUrl = $playlistBase . '?key=' . urlencode($xiboKey) . '&format=html';
    $jsonUrl = $playlistBase . '?key=' . urlencode($xiboKey);
    $jsonApiUrl = $jsonUrl . '&format=json';
    $embeddedHtml = '<div id="sf-slideshow">' . "\n"
        . '  <div id="sf-slide" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#1a1a2e;">' . "\n"
        . '    <p style="color:#aaa;font-size:1.5em;">Ladataan&#8230;</p>' . "\n"
        . '  </div>' . "\n"
        . '</div>' . "\n\n"
        . '<script>' . "\n"
        . '(function(){' . "\n"
        . '  var API_URL = "' . $jsonApiUrl . '";' . "\n"
        . '  var REFRESH_MIN = 5;' . "\n"
        . '  var container = document.getElementById("sf-slide");' . "\n\n"
        . '  function load(){' . "\n"
        . '    var xhr = new XMLHttpRequest();' . "\n"
        . '    xhr.open("GET", API_URL, true);' . "\n"
        . '    xhr.onload = function(){' . "\n"
        . '      if(xhr.status===200){' . "\n"
        . '        try {' . "\n"
        . '          var data = JSON.parse(xhr.responseText);' . "\n"
        . '          if(data.ok && data.items && data.items.length > 0){' . "\n"
        . '            startSlideshow(data.items);' . "\n"
        . '          } else {' . "\n"
        . '            showEmpty(data.lang || "fi");' . "\n"
        . '          }' . "\n"
        . '        } catch(e){ showError(); }' . "\n"
        . '      } else { showError(); }' . "\n"
        . '    };' . "\n"
        . '    xhr.onerror = function(){ showError(); };' . "\n"
        . '    xhr.send();' . "\n"
        . '  }' . "\n\n"
        . '  var current = 0;' . "\n"
        . '  var items = [];' . "\n"
        . '  var timer = null;' . "\n\n"
        . '  function startSlideshow(list){' . "\n"
        . '    items = list;' . "\n"
        . '    current = 0;' . "\n"
        . '    showSlide();' . "\n"
        . '  }' . "\n\n"
        . '  function showSlide(){' . "\n"
        . '    if(!items.length) return;' . "\n"
        . '    var item = items[current];' . "\n"
        . '    container.innerHTML =' . "\n"
        . '      \'<img src="\' + item.image_url + \'" alt="" style="max-width:100%;max-height:100%;object-fit:contain;">\';' . "\n"
        . '    var dur = (item.duration_seconds || 10) * 1000;' . "\n"
        . '    clearTimeout(timer);' . "\n"
        . '    timer = setTimeout(function(){' . "\n"
        . '      current = (current + 1) % items.length;' . "\n"
        . '      showSlide();' . "\n"
        . '    }, dur);' . "\n"
        . '  }' . "\n\n"
        . '  function showEmpty(lang){' . "\n"
        . '    var msgs = {' . "\n"
        . '      fi:"Ei n\u00e4ytett\u00e4vi\u00e4 flasheja",' . "\n"
        . '      sv:"Inga flash att visa",' . "\n"
        . '      en:"No flashes to display",' . "\n"
        . '      it:"Nessun flash da visualizzare",' . "\n"
        . '      el:"\u0394\u03b5\u03bd \u03c5\u03c0\u03ac\u03c1\u03c7\u03bf\u03c5\u03bd flash"' . "\n"
        . '    };' . "\n"
        . '    container.innerHTML =' . "\n"
        . '      \'<p style="color:#ccc;font-size:1.4em;text-align:center;">\' +' . "\n"
        . '      (msgs[lang]||msgs.fi) + \'</p>\';' . "\n"
        . '  }' . "\n\n"
        . '  function showError(){' . "\n"
        . '    container.innerHTML =' . "\n"
        . '      \'<p style="color:#f66;font-size:1.2em;text-align:center;">Yhteysvirhe</p>\';' . "\n"
        . '  }' . "\n\n"
        . '  load();' . "\n"
        . '  setInterval(load, REFRESH_MIN * 60 * 1000);' . "\n"
        . '})();' . "\n"
        . '</script>';
    $embeddedCss = 'body, html {' . "\n"
        . '  margin: 0;' . "\n"
        . '  padding: 0;' . "\n"
        . '  width: 100%;' . "\n"
        . '  height: 100%;' . "\n"
        . '  overflow: hidden;' . "\n"
        . '  background: #1a1a2e;' . "\n"
        . '  font-family: -apple-system, "Segoe UI", sans-serif;' . "\n"
        . '}' . "\n\n"
        . '#sf-slideshow {' . "\n"
        . '  width: 100%;' . "\n"
        . '  height: 100%;' . "\n"
        . '}' . "\n\n"
        . '#sf-slide {' . "\n"
        . '  width: 100%;' . "\n"
        . '  height: 100%;' . "\n"
        . '  display: flex;' . "\n"
        . '  align-items: center;' . "\n"
        . '  justify-content: center;' . "\n"
        . '}' . "\n\n"
        . '#sf-slide img {' . "\n"
        . '  max-width: 100%;' . "\n"
        . '  max-height: 100%;' . "\n"
        . '  object-fit: contain;' . "\n"
        . '  animation: sf-fadein 0.6s ease;' . "\n"
        . '}' . "\n\n"
        . '@keyframes sf-fadein {' . "\n"
        . '  from { opacity: 0; }' . "\n"
        . '  to   { opacity: 1; }' . "\n"
        . '}';
?>
<div class="sf-modal hidden" id="xiboModal<?= $xiboWsId ?>" role="dialog" aria-modal="true" aria-labelledby="xiboModalTitle<?= $xiboWsId ?>">
    <div class="sf-modal-content">
        <div class="sf-modal-header">
            <h3 id="xiboModalTitle<?= $xiboWsId ?>">
                <?= htmlspecialchars(sf_term('xibo_code_heading', $currentUiLang) ?? 'Xibo-integraatiokoodi', ENT_QUOTES, 'UTF-8') ?>
                — <?= $xiboLabel ?>
            </h3>
            <button type="button" data-modal-close class="sf-modal-close" aria-label="<?= htmlspecialchars(sf_term('btn_close', $currentUiLang) ?? 'Sulje', ENT_QUOTES, 'UTF-8') ?>">✕</button>
        </div>
        <div class="sf-modal-body" style="padding:1.25rem;">
            <p style="margin-bottom:1rem;color:var(--sf-text-secondary,#666);font-size:0.9rem;">
                <?= htmlspecialchars(sf_term('xibo_instructions', $currentUiLang) ?? 'Kopioi URL ja liitä se Xibo CMS:n Webpage-widgetin URL-kenttään', ENT_QUOTES, 'UTF-8') ?>
            </p>

            <div style="margin-bottom:1.25rem;">
                <strong style="display:block;margin-bottom:0.4rem;">▸ <?= htmlspecialchars(sf_term('xibo_webpage_url_label', $currentUiLang) ?? 'Webpage Widget URL', ENT_QUOTES, 'UTF-8') ?></strong>
                <div style="display:flex;gap:0.5rem;align-items:stretch;">
                    <code id="xiboHtmlUrl<?= $xiboWsId ?>" style="flex:1;display:block;background:var(--sf-bg-secondary,#f5f5f5);padding:0.5rem 0.75rem;border-radius:4px;font-size:0.82rem;word-break:break-all;"><?= htmlspecialchars($htmlUrl, ENT_QUOTES, 'UTF-8') ?></code>
                    <button type="button" class="sf-btn sf-btn-sm sf-btn-outline-primary sf-xibo-copy-btn" data-copy-target="xiboHtmlUrl<?= $xiboWsId ?>" data-ws-id="<?= $xiboWsId ?>-url">
                        📋 <?= htmlspecialchars(sf_term('xibo_copy_url', $currentUiLang) ?? 'Kopioi URL', ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>
                <span id="xiboCopied<?= $xiboWsId ?>-url" style="display:none;color:green;font-size:0.85rem;margin-top:0.25rem;">✅ <?= htmlspecialchars(sf_term('xibo_copied', $currentUiLang) ?? 'Kopioitu!', ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <div style="margin-bottom:1.25rem;">
                <strong style="display:block;margin-bottom:0.25rem;">▸ <?= htmlspecialchars(sf_term('xibo_embedded_html_label', $currentUiLang) ?? 'HTML-kenttä (Embedded Widget)', ENT_QUOTES, 'UTF-8') ?></strong>
                <p style="margin:0 0 0.5rem;color:var(--sf-text-secondary,#666);font-size:0.85rem;">ℹ️ <?= htmlspecialchars(sf_term('xibo_embedded_instructions', $currentUiLang) ?? 'Liitä HTML ja CSS Xibon Embedded Widget -kenttiin', ENT_QUOTES, 'UTF-8') ?></p>
                <pre id="xiboEmbedHtml<?= $xiboWsId ?>" style="background:var(--sf-bg-secondary,#f5f5f5);padding:0.5rem 0.75rem;border-radius:4px;font-size:0.78rem;overflow:auto;max-height:200px;white-space:pre-wrap;word-break:break-all;margin:0 0 0.4rem;"><code><?= htmlspecialchars($embeddedHtml, ENT_QUOTES, 'UTF-8') ?></code></pre>
                <div style="display:flex;gap:0.5rem;align-items:center;">
                    <button type="button" class="sf-btn sf-btn-sm sf-btn-outline-primary sf-xibo-copy-btn" data-copy-target="xiboEmbedHtml<?= $xiboWsId ?>" data-ws-id="<?= $xiboWsId ?>-html">
                        📋 <?= htmlspecialchars(sf_term('xibo_copy_html', $currentUiLang) ?? 'Kopioi HTML', ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <span id="xiboCopied<?= $xiboWsId ?>-html" style="display:none;color:green;font-size:0.85rem;">✅ <?= htmlspecialchars(sf_term('xibo_copied', $currentUiLang) ?? 'Kopioitu!', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>

            <div style="margin-bottom:1rem;">
                <strong style="display:block;margin-bottom:0.4rem;">▸ <?= htmlspecialchars(sf_term('xibo_embedded_css_label', $currentUiLang) ?? 'CSS-kenttä (Embedded Widget)', ENT_QUOTES, 'UTF-8') ?></strong>
                <pre id="xiboEmbedCss<?= $xiboWsId ?>" style="background:var(--sf-bg-secondary,#f5f5f5);padding:0.5rem 0.75rem;border-radius:4px;font-size:0.78rem;overflow:auto;max-height:200px;white-space:pre-wrap;word-break:break-all;margin:0 0 0.4rem;"><code><?= htmlspecialchars($embeddedCss, ENT_QUOTES, 'UTF-8') ?></code></pre>
                <div style="display:flex;gap:0.5rem;align-items:center;">
                    <button type="button" class="sf-btn sf-btn-sm sf-btn-outline-primary sf-xibo-copy-btn" data-copy-target="xiboEmbedCss<?= $xiboWsId ?>" data-ws-id="<?= $xiboWsId ?>-css">
                        📋 <?= htmlspecialchars(sf_term('xibo_copy_css', $currentUiLang) ?? 'Kopioi CSS', ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <span id="xiboCopied<?= $xiboWsId ?>-css" style="display:none;color:green;font-size:0.85rem;">✅ <?= htmlspecialchars(sf_term('xibo_copied', $currentUiLang) ?? 'Kopioitu!', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
        </div>
        <div class="sf-modal-footer" style="padding:1rem 1.25rem;text-align:right;">
            <button type="button" data-modal-close class="sf-btn sf-btn-secondary">
                <?= htmlspecialchars(sf_term('btn_close', $currentUiLang) ?? 'Sulje', ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
(function () {
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.sf-xibo-copy-btn');
        if (!btn) return;
        var targetId = btn.getAttribute('data-copy-target');
        var wsId = btn.getAttribute('data-ws-id');
        var el = document.getElementById(targetId);
        if (!el) return;
        var text = el.textContent;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                showCopied(wsId);
            }).catch(function () {
                fallbackCopy(text, wsId);
            });
        } else {
            fallbackCopy(text, wsId);
        }
    });

    function fallbackCopy(text, wsId) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        try { document.execCommand('copy'); } catch (err) {}
        document.body.removeChild(ta);
        showCopied(wsId);
    }

    function showCopied(wsId) {
        var msg = document.getElementById('xiboCopied' + wsId);
        if (!msg) return;
        msg.style.display = 'inline';
        setTimeout(function () { msg.style.display = 'none'; }, 2000);
    }
})();
</script>
<?php endif; ?>