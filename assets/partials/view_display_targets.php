<?php
/**
 * SafetyFlash - View Page: Display Targets Status
 *
 * Näyttää millä infonäytöillä flash näytetään.
 * Vain julkaistuille flasheille.
 *
 * @package SafetyFlash
 * @subpackage Partials
 * @created 2026-02-19
 *
 * Required variables:
 * @var PDO    $pdo           Tietokantayhteys
 * @var array  $flash         Flash data from database
 * @var string $currentUiLang Current UI language
 * @var int    $id            Flash ID
 */

// Näytetään vain julkaistuille flasheille
if (!isset($flash['state']) || $flash['state'] !== 'published') {
    return;
}

$groupId = !empty($flash['translation_group_id'])
    ? (int)$flash['translation_group_id']
    : (int)$flash['id'];

// Hae kohdistetut näytöt
$targets = [];
try {
    $stmtTargets = $pdo->prepare("
        SELECT
            t.display_key_id,
            t.is_active,
            t.activated_at,
            d.label,
            d.site_group
        FROM sf_flash_display_targets t
        INNER JOIN sf_display_api_keys d ON d.id = t.display_key_id
        WHERE t.flash_id = ?
        ORDER BY d.site_group ASC, d.label ASC
    ");
    $stmtTargets->execute([$groupId]);
    $targets = $stmtTargets->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // Taulu saattaa puuttua jos migraatio ei ole ajettu
    return;
}

if (empty($targets)) {
    return;
}

$labelText    = sf_term('display_targets_label', $currentUiLang) ?? '🖥️ Infonäytöt';
$activeText   = sf_term('display_target_active', $currentUiLang) ?? 'Aktiivinen';
$pendingText  = sf_term('display_target_pending', $currentUiLang) ?? 'Odottaa julkaisua';
$countText    = sf_term('display_targets_count', $currentUiLang) ?? 'näytöllä';
$activeCount  = count(array_filter($targets, fn($t) => (bool)$t['is_active']));
?>

<div class="sf-display-targets-status">
    <h4 style="margin:0 0 0.5rem; font-size:0.95rem;">
        <?= htmlspecialchars($labelText, ENT_QUOTES, 'UTF-8') ?>
        <span style="font-weight:normal; color:var(--sf-text-secondary,#666); font-size:0.85em;">
            (<?= $activeCount ?> <?= htmlspecialchars($countText, ENT_QUOTES, 'UTF-8') ?>)
        </span>
    </h4>
    <ul style="list-style:none; margin:0; padding:0; font-size:0.88rem;">
        <?php foreach ($targets as $target): ?>
            <li style="padding:0.2rem 0; display:flex; align-items:center; gap:0.4rem;">
                <?php if ((bool)$target['is_active']): ?>
                    <span title="<?= htmlspecialchars($activeText, ENT_QUOTES, 'UTF-8') ?>">✅</span>
                <?php else: ?>
                    <span title="<?= htmlspecialchars($pendingText, ENT_QUOTES, 'UTF-8') ?>">⏳</span>
                <?php endif; ?>
                <span>
                    <?php if (!empty($target['site_group'])): ?>
                        <span style="color:var(--sf-text-secondary,#666);">
                            <?= htmlspecialchars($target['site_group'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        —
                    <?php endif; ?>
                    <?= htmlspecialchars($target['label'] ?? ('ID ' . (int)$target['display_key_id']), ENT_QUOTES, 'UTF-8') ?>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
