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
    $embeddedCode = "<script>\nfetch(" . json_encode($jsonUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ")\n  .then(r => r.json())\n  .then(data => { console.log(data); });\n<\/script>";
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
                <strong style="display:block;margin-bottom:0.4rem;">▸ <?= htmlspecialchars(sf_term('xibo_webpage_url', $currentUiLang) ?? 'Webpage Widget URL', ENT_QUOTES, 'UTF-8') ?></strong>
                <div style="display:flex;gap:0.5rem;align-items:stretch;">
                    <code id="xiboHtmlUrl<?= $xiboWsId ?>" style="flex:1;display:block;background:var(--sf-bg-secondary,#f5f5f5);padding:0.5rem 0.75rem;border-radius:4px;font-size:0.82rem;word-break:break-all;"><?= htmlspecialchars($htmlUrl, ENT_QUOTES, 'UTF-8') ?></code>
                    <button type="button" class="sf-btn sf-btn-sm sf-btn-outline-primary sf-xibo-copy-btn" data-copy-target="xiboHtmlUrl<?= $xiboWsId ?>" data-ws-id="<?= $xiboWsId ?>-html">
                        📋 <?= htmlspecialchars(sf_term('btn_copy', $currentUiLang) ?? 'Kopioi', ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>
                <span id="xiboCopied<?= $xiboWsId ?>-html" style="display:none;color:green;font-size:0.85rem;margin-top:0.25rem;">✅ <?= htmlspecialchars(sf_term('msg_copied', $currentUiLang) ?? 'Kopioitu!', ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <div style="margin-bottom:1.25rem;">
                <strong style="display:block;margin-bottom:0.4rem;">▸ <?= htmlspecialchars(sf_term('xibo_embedded_code', $currentUiLang) ?? 'Embedded Widget (JavaScript)', ENT_QUOTES, 'UTF-8') ?></strong>
                <div style="display:flex;gap:0.5rem;align-items:stretch;">
                    <code id="xiboEmbedCode<?= $xiboWsId ?>" style="flex:1;display:block;background:var(--sf-bg-secondary,#f5f5f5);padding:0.5rem 0.75rem;border-radius:4px;font-size:0.82rem;word-break:break-all;white-space:pre-wrap;"><?= htmlspecialchars($embeddedCode, ENT_QUOTES, 'UTF-8') ?></code>
                    <button type="button" class="sf-btn sf-btn-sm sf-btn-outline-primary sf-xibo-copy-btn" data-copy-target="xiboEmbedCode<?= $xiboWsId ?>" data-ws-id="<?= $xiboWsId ?>-embed">
                        📋 <?= htmlspecialchars(sf_term('btn_copy', $currentUiLang) ?? 'Kopioi', ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>
                <span id="xiboCopied<?= $xiboWsId ?>-embed" style="display:none;color:green;font-size:0.85rem;margin-top:0.25rem;">✅ <?= htmlspecialchars(sf_term('msg_copied', $currentUiLang) ?? 'Kopioitu!', ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <div style="margin-bottom:1rem;">
                <strong style="display:block;margin-bottom:0.4rem;">▸ <?= htmlspecialchars(sf_term('xibo_json_endpoint', $currentUiLang) ?? 'JSON API', ENT_QUOTES, 'UTF-8') ?></strong>
                <div style="display:flex;gap:0.5rem;align-items:stretch;">
                    <code id="xiboJsonUrl<?= $xiboWsId ?>" style="flex:1;display:block;background:var(--sf-bg-secondary,#f5f5f5);padding:0.5rem 0.75rem;border-radius:4px;font-size:0.82rem;word-break:break-all;"><?= htmlspecialchars($jsonUrl, ENT_QUOTES, 'UTF-8') ?></code>
                    <button type="button" class="sf-btn sf-btn-sm sf-btn-outline-primary sf-xibo-copy-btn" data-copy-target="xiboJsonUrl<?= $xiboWsId ?>" data-ws-id="<?= $xiboWsId ?>-json">
                        📋 <?= htmlspecialchars(sf_term('btn_copy', $currentUiLang) ?? 'Kopioi', ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>
                <span id="xiboCopied<?= $xiboWsId ?>-json" style="display:none;color:green;font-size:0.85rem;margin-top:0.25rem;">✅ <?= htmlspecialchars(sf_term('msg_copied', $currentUiLang) ?? 'Kopioitu!', ENT_QUOTES, 'UTF-8') ?></span>
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