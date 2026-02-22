<?php
/**
 * SafetyFlash - Display Target Selector Partial
 *
 * Näyttää kaikki aktiiviset näytöt chip-tyylisillä valintanapeilla.
 * Ei suodata kielen mukaan — kaikki aktiiviset työmaat näkyvissä.
 * Ryhmittelee näytöt site_group-kentän mukaan.
 *
 * Odottaa muuttujia:
 *   $flash        — array, kieliversion data (id, lang, title)
 *   $pdo          — PDO-yhteys
 *   $currentUiLang — string, UI-kieli
 *   $context      — string, 'publish' | 'safety_team'
 *
 * @package SafetyFlash
 * @subpackage Partials
 * @created 2026-02-19
 * @updated 2026-02-22 - chip-style selector, all worksites visible
 */

// Flashin oma kieliversiokohtainen ID (EI translation_group_id)
$flashId = (int)($flash['id'] ?? 0);

// Hae KAIKKI aktiiviset näytöt (ei kielisuodatusta)
$stmtDisplays = $pdo->prepare("
    SELECT id, site, site_group, label, lang, sort_order
    FROM sf_display_api_keys
    WHERE is_active = 1
    ORDER BY site_group ASC, sort_order ASC, label ASC
");
$stmtDisplays->execute();
$availableDisplays = $stmtDisplays->fetchAll(PDO::FETCH_ASSOC);

// Hae esivalinnat flashin OMALLA ID:llä (EI translation_group_id)
$preselectedIds = [];
if ($flashId > 0) {
    $stmtPre = $pdo->prepare("
        SELECT display_key_id FROM sf_flash_display_targets
        WHERE flash_id = ?
    ");
    $stmtPre->execute([$flashId]);
    $preselectedIds = $stmtPre->fetchAll(PDO::FETCH_COLUMN);
}

// Ryhmittele näytöt site_group-kentän mukaan
$grouped = [];
foreach ($availableDisplays as $display) {
    $group = $display['site_group'] ?: '';
    $grouped[$group][] = $display;
}
?>

<div class="sf-display-target-selector">
    <?php if (empty($availableDisplays)): ?>
        <p class="sf-help-text sf-help-text-muted">—</p>
    <?php else: ?>
        <?php foreach ($grouped as $groupName => $displays): ?>
            <?php if ($groupName !== ''): ?>
                <p class="sf-display-group-heading"><strong><?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') ?></strong></p>
            <?php endif; ?>
            <div class="sf-display-chips">
                <?php foreach ($displays as $display): ?>
                    <?php $isChecked = in_array((string)$display['id'], array_map('strval', $preselectedIds), true); ?>
                    <label class="sf-display-chip <?= $isChecked ? 'sf-display-chip-selected' : '' ?>">
                        <input
                            type="checkbox"
                            class="sf-display-chip-input"
                            name="display_targets[<?= $flashId ?>][]"
                            value="<?= (int)$display['id'] ?>"
                            <?= $isChecked ? 'checked' : '' ?>
                        >
                        <?= htmlspecialchars($display['label'] ?? $display['site'], ENT_QUOTES, 'UTF-8') ?>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
