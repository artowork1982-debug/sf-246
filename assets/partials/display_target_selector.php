<?php
/**
 * SafetyFlash - Display Target Selector
 *
 * Uudelleenkäytettävä UI-komponentti näyttövalintaan.
 * Käytetään turvatiimin luontivaiheessa (context='create')
 * ja viestinnän julkaisumodaalissa (context='publish').
 *
 * @package SafetyFlash
 * @subpackage Partials
 * @created 2026-02-19
 *
 * Required variables:
 * @var PDO    $pdo           Tietokantayhteys
 * @var array|null $flash     Flashin tiedot (id, translation_group_id) — voi olla null
 * @var string $currentUiLang Käyttöliittymän kieli
 * @var string $context       'create' tai 'publish'
 */

declare(strict_types=1);

// Fallback muuttujille
$context        = $context ?? 'publish';
$currentUiLang  = $currentUiLang ?? ($_SESSION['ui_lang'] ?? 'fi');

// Uniikki ID kontekstin mukaan (vältetään ID-konfliktit kun molemmat modalit ovat samalla sivulla)
$selectorId = 'displayTargetSelector_' . htmlspecialchars($context, ENT_QUOTES, 'UTF-8');

// Hae kaikki aktiiviset näytöt ryhmiteltyinä
$displays = [];
try {
    $stmtDisplays = $pdo->prepare("
        SELECT id, label, site_group, sort_order
        FROM sf_display_api_keys
        WHERE is_active = 1
        ORDER BY site_group ASC, sort_order ASC, label ASC
    ");
    $stmtDisplays->execute();
    $displays = $stmtDisplays->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // Näyttö saattaa puuttua jos migraatio ei ole ajettu
    error_log('display_target_selector: ' . $e->getMessage());
}

// Hae esivalinnat jos flash_id on olemassa
$preselectedIds = [];
if (!empty($flash['id'])) {
    $groupId = !empty($flash['translation_group_id'])
        ? (int)$flash['translation_group_id']
        : (int)$flash['id'];
    try {
        $stmtPre = $pdo->prepare("
            SELECT display_key_id
            FROM sf_flash_display_targets
            WHERE flash_id = ?
        ");
        $stmtPre->execute([$groupId]);
        $preselectedIds = array_map('intval', $stmtPre->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) {
        error_log('display_target_selector preselect: ' . $e->getMessage());
    }
}

// Ryhmittele näytöt
$groups = [];
foreach ($displays as $display) {
    $group = $display['site_group'] ?: '';
    $groups[$group][] = $display;
}
ksort($groups);

$labelText       = sf_term('display_targets_label', $currentUiLang) ?? '🖥️ Infonäytöt';
$preselectedNote = sf_term('display_preselected_by_safety', $currentUiLang)
    ?? 'Turvatiimi on esivalinnut merkityt näytöt';
$selectAllText   = sf_term('select_all', $currentUiLang) ?? 'Valitse kaikki';
$deselectAllText = sf_term('deselect_all', $currentUiLang) ?? 'Tyhjennä';
$preselBadge     = sf_term('preselected_by_safety_short', $currentUiLang) ?? 'turvatiimi ✓';
?>

<div class="sf-display-targets" id="<?= $selectorId ?>">
    <label class="sf-label" style="font-weight:600; margin-bottom:0.4rem; display:block;">
        <?= htmlspecialchars($labelText, ENT_QUOTES, 'UTF-8') ?>
    </label>

    <?php if ($context === 'publish' && !empty($preselectedIds)): ?>
        <p class="sf-help-text" style="margin-bottom:0.5rem;">
            <?= htmlspecialchars($preselectedNote, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($displays)): ?>
        <div class="sf-display-actions">
            <button type="button" class="sf-btn-link" onclick="sfToggleAllDisplays(<?= json_encode($selectorId) ?>, true)">
                <?= htmlspecialchars($selectAllText, ENT_QUOTES, 'UTF-8') ?>
            </button>
            <button type="button" class="sf-btn-link" onclick="sfToggleAllDisplays(<?= json_encode($selectorId) ?>, false)">
                <?= htmlspecialchars($deselectAllText, ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>

        <?php foreach ($groups as $groupName => $groupDisplays): ?>
            <div class="sf-display-group">
                <div class="sf-display-group-header">
                    <strong><?= htmlspecialchars($groupName ?: '—', ENT_QUOTES, 'UTF-8') ?></strong>
                    <span class="sf-display-group-count">(<?= count($groupDisplays) ?>)</span>
                </div>
                <?php foreach ($groupDisplays as $display):
                    $displayId = (int)$display['id'];
                    $isPreselected = in_array($displayId, $preselectedIds, true);
                    $optionClass = $isPreselected ? 'sf-display-option preselected' : 'sf-display-option';
                ?>
                    <label class="<?= $optionClass ?>">
                        <input
                            type="checkbox"
                            name="display_targets[]"
                            value="<?= $displayId ?>"
                            <?= $isPreselected ? 'checked' : '' ?>
                        >
                        <span><?= htmlspecialchars($display['label'] ?? ('ID ' . $displayId), ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if ($isPreselected): ?>
                            <span class="sf-preselected-badge"><?= htmlspecialchars($preselBadge, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="sf-help-text">
            <?php
            echo htmlspecialchars(
                sf_term('display_targets_none', $currentUiLang) ?? 'Ei aktiivisia infonäyttöjä.',
                ENT_QUOTES, 'UTF-8'
            );
            ?>
        </p>
    <?php endif; ?>
</div>

<script>
if (typeof sfToggleAllDisplays === 'undefined') {
    function sfToggleAllDisplays(selectorId, checked) {
        var container = document.getElementById(selectorId);
        if (!container) { return; }
        container.querySelectorAll('input[type="checkbox"]')
            .forEach(function(cb) { cb.checked = checked; });
    }
}
// Legacy alias (problem statement API)
if (typeof toggleAllDisplays === 'undefined') {
    function toggleAllDisplays(checked) {
        sfToggleAllDisplays('displayTargetSelector_publish', checked);
    }
}
</script>
