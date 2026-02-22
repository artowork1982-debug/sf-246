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
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>