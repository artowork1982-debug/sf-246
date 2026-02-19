<?php
/**
 * SafetyFlash - Display Target Selector Partial
 *
 * Näyttää kieliversion omat näyttövalinnat.
 * Suodattaa näytöt kielen mukaan ja käyttää flashin OMAA ID:tä.
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
 */

// Flashin oma kieliversiokohtainen ID (EI translation_group_id)
$flashId   = (int)($flash['id'] ?? 0);
$flashLang = $flash['lang'] ?? 'fi';

// Hae vain TÄMÄN KIELEN näytöt
$stmtDisplays = $pdo->prepare("
    SELECT id, site, site_group, label, lang, sort_order
    FROM sf_display_api_keys
    WHERE is_active = 1
      AND lang = :lang
    ORDER BY site_group ASC, sort_order ASC, label ASC
");
$stmtDisplays->execute([':lang' => $flashLang]);
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
?>

<div class="sf-display-target-selector">
    <?php if ($flashLang): ?>
        <p class="sf-help-text">
            ℹ️ <?= htmlspecialchars(
                sf_term('display_showing_lang_displays', $currentUiLang)
                    ?: "Näytetään {$flashLang}-kieliset näytöt",
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </p>
    <?php endif; ?>

    <?php if (empty($availableDisplays)): ?>
        <p class="sf-help-text sf-help-text-muted">—</p>
    <?php else: ?>
        <div class="sf-display-checkboxes">
            <?php foreach ($availableDisplays as $display): ?>
                <?php $isChecked = in_array((string)$display['id'], array_map('strval', $preselectedIds), true); ?>
                <label class="sf-display-checkbox-label">
                    <input
                        type="checkbox"
                        name="display_targets[<?= $flashId ?>][]"
                        value="<?= (int)$display['id'] ?>"
                        <?= $isChecked ? 'checked' : '' ?>
                    >
                    <span class="sf-display-label">
                        <?= htmlspecialchars($display['label'] ?? $display['site'], ENT_QUOTES, 'UTF-8') ?>
                        <?php if (!empty($display['site_group'])): ?>
                            <small class="sf-display-group">(<?= htmlspecialchars($display['site_group'], ENT_QUOTES, 'UTF-8') ?>)</small>
                        <?php endif; ?>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
