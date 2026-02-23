<?php
/**
 * SafetyFlash - Display Target Selector Partial
 *
 * Näyttää kaikki aktiiviset näytöt maa/kieliryhmitetyillä chip-napeilla,
 * hakukentällä yksittäisten näyttöjen löytämiseen sekä valintanäytöllä.
 *
 * Odottaa muuttujia:
 *   $flash        — array, kieliversion data (id, lang, title)
 *   $pdo          — PDO-yhteys
 *   $currentUiLang — string, UI-kieli
 *   $context      — string, 'publish' | 'safety_team'
 *
 * Valinnainen override:
 *   $preselectedIds — array, jos asetettu ennen includeaa, käytetään sellaisenaan
 *
 * @package SafetyFlash
 * @subpackage Partials
 * @created 2026-02-19
 * @updated 2026-02-23 - country/lang group chips + search + selection display
 */

// Flashin oma kieliversiokohtainen ID (EI translation_group_id)
$flashId = (int)($flash['id'] ?? 0);

// Hae KAIKKI aktiiviset näytöt
$availableDisplays = [];
try {
    $stmtDisplays = $pdo->prepare("
        SELECT id, site, site_group, label, lang, sort_order
        FROM sf_display_api_keys
        WHERE is_active = 1
        ORDER BY lang ASC, sort_order ASC, label ASC
    ");
    $stmtDisplays->execute();
    $availableDisplays = $stmtDisplays->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $eDtSel) {
    // Silently ignore — taulu saattaa puuttua ennen migraatiota
}

// Hae esivalinnat — käytä annettua $preselectedIds jos asetettu, muuten hae kannasta
if (!isset($preselectedIds)) {
    $preselectedIds = [];
    if ($flashId > 0) {
        try {
            $stmtPre = $pdo->prepare("
                SELECT display_key_id FROM sf_flash_display_targets
                WHERE flash_id = ?
            ");
            $stmtPre->execute([$flashId]);
            $preselectedIds = $stmtPre->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $eDtSelPre) {
            // Silently ignore
        }
    }
}
$preselectedIds = array_map('intval', $preselectedIds);

// Maa/kielikartta
$dtLangMap = [
    'fi' => ['flag' => '🇫🇮', 'name' => sf_term('country_finland', $currentUiLang)],
    'sv' => ['flag' => '🇸🇪', 'name' => sf_term('country_sweden', $currentUiLang)],
    'en' => ['flag' => '🇬🇧', 'name' => sf_term('country_uk', $currentUiLang)],
    'it' => ['flag' => '🇮🇹', 'name' => sf_term('country_italy', $currentUiLang)],
    'el' => ['flag' => '🇬🇷', 'name' => sf_term('country_greece', $currentUiLang)],
];

// Ryhmittele näytöt kielen mukaan
$dtByLang = [];
foreach ($availableDisplays as $dtDisp) {
    $dtLang = $dtDisp['lang'] ?: 'fi';
    $dtByLang[$dtLang][] = $dtDisp;
}
?>

<div class="sf-display-target-selector">
    <?php if (empty($availableDisplays)): ?>
        <p class="sf-help-text sf-help-text-muted">—</p>
    <?php else: ?>

        <?php if (!empty($dtByLang)): ?>
        <div class="sf-dt-lang-chips">
            <?php foreach ($dtByLang as $dtLang => $dtLangDisplays): ?>
                <?php $dtLInfo = $dtLangMap[$dtLang] ?? ['flag' => '🌐', 'name' => strtoupper($dtLang)]; ?>
                <?php
                // Kaikki kyseisen kielen näytöt valittuna?
                $dtLangIds = array_map('intval', array_column($dtLangDisplays, 'id'));
                $dtAllSelected = !empty($dtLangIds) && empty(array_diff($dtLangIds, $preselectedIds));
                ?>
                <button type="button"
                        class="sf-dt-lang-chip<?= $dtAllSelected ? ' sf-dt-lang-chip-active' : '' ?>"
                        data-lang="<?= htmlspecialchars($dtLang, ENT_QUOTES, 'UTF-8') ?>">
                    <?= $dtLInfo['flag'] ?> <?= htmlspecialchars($dtLInfo['name'], ENT_QUOTES, 'UTF-8') ?>
                    <span class="sf-dt-lang-count">(<?= count($dtLangDisplays) ?>)</span>
                </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Hakukenttä -->
        <div class="sf-dt-search-row">
            <input type="text"
                   class="sf-dt-search-input"
                   placeholder="🔍 <?= htmlspecialchars(sf_term('comms_search_worksites', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>"
                   autocomplete="off">
            <p class="sf-dt-search-hint"><?= htmlspecialchars(sf_term('comms_search_hint', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></p>
        </div>

        <!-- Hakutulokset (piilotettu oletuksena) -->
        <div class="sf-dt-search-results hidden">
            <?php foreach ($availableDisplays as $dtDisplay): ?>
                <?php $dtIsChecked = in_array((int)$dtDisplay['id'], $preselectedIds, true); ?>
                <label class="sf-dt-result-item hidden"
                       data-search="<?= htmlspecialchars(strtolower($dtDisplay['label'] ?? $dtDisplay['site']), ENT_QUOTES, 'UTF-8') ?>"
                       data-lang="<?= htmlspecialchars($dtDisplay['lang'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="checkbox"
                           class="sf-display-chip-input dt-display-chip-cb"
                           name="display_targets[<?= $flashId ?>][]"
                           value="<?= (int)$dtDisplay['id'] ?>"
                           data-label="<?= htmlspecialchars($dtDisplay['label'] ?? $dtDisplay['site'], ENT_QUOTES, 'UTF-8') ?>"
                           data-lang="<?= htmlspecialchars($dtDisplay['lang'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           <?= $dtIsChecked ? 'checked' : '' ?>>
                    <span class="sf-ws-name"><?= htmlspecialchars($dtDisplay['label'] ?? $dtDisplay['site'], ENT_QUOTES, 'UTF-8') ?></span>
                </label>
            <?php endforeach; ?>
        </div>

        <!-- Valintanäyttö -->
        <div class="sf-dt-selection-display<?= empty($preselectedIds) ? ' hidden' : '' ?>">
            <div class="sf-dt-selection-label"><?= htmlspecialchars(sf_term('comms_your_selection', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="sf-dt-selection-tags"></div>
        </div>

    <?php endif; ?>
</div>
