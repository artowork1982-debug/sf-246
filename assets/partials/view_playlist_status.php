<?php
/**
 * SafetyFlash - View Page: Playlist Status Display
 * 
 * Näyttää flashin tilan infonäyttö-playlistassa.
 * Vain julkaistuille flasheille. Admineille, turvatiimille ja viestinnälle
 * toiminnot poistaa/palauttaa.
 * 
 * @package SafetyFlash
 * @subpackage Partials
 * @created 2026-02-19
 * 
 * Required variables:
 * @var array $flash Flash data from database
 * @var string $currentUiLang Current UI language
 * @var int $id Flash ID
 * @var bool $isAdmin User is admin
 * @var bool $isSafety User is safety team
 * @var bool $isComms User is communications team
 */

// Näytetään vain julkaistuille flasheille
if (!isset($flash['state']) || $flash['state'] !== 'published') {
    return;
}

// Määritä playlist-status
$displayStatus = 'active'; // oletus
$displayExpiresAt = $flash['display_expires_at'] ?? null;
$displayRemovedAt = $flash['display_removed_at'] ?? null;

if ($displayRemovedAt !== null) {
    $displayStatus = 'removed';
} elseif ($displayExpiresAt !== null && strtotime($displayExpiresAt) < time()) {
    $displayStatus = 'expired';
}

// Oikeudet hallintaan (admin, turvatiimi, viestintä)
$canManage = $isAdmin || $isSafety || $isComms;

// Hae työmaan API-avain aktiivisista display-kohteista
$worksiteApiKey = null;
if (isset($pdo) && $displayStatus === 'active') {
    try {
        $stmtApiKey = $pdo->prepare("
            SELECT k.api_key
            FROM sf_flash_display_targets t
            JOIN sf_display_api_keys k ON k.id = t.display_key_id
            WHERE t.flash_id = ? AND t.is_active = 1 AND k.is_active = 1
            LIMIT 1
        ");
        $stmtApiKey->execute([(int)$id]);
        $keyRow = $stmtApiKey->fetch(PDO::FETCH_ASSOC);
        $worksiteApiKey = $keyRow ? ($keyRow['api_key'] ?? null) : null;
    } catch (Throwable $ek) {
        // Silently ignore — migration may not be applied yet
    }
}

?>

<div class="sf-playlist-status-card sf-playlist-status-<?= htmlspecialchars($displayStatus, ENT_QUOTES, 'UTF-8') ?>">
    <div class="sf-playlist-status-header">
        <h4>
            <?php if ($displayStatus === 'active'): ?>
                <span class="sf-status-icon">📺</span>
                <?= htmlspecialchars(sf_term('playlist_status_active', $currentUiLang) ?? 'Näytetään infonäytöillä', ENT_QUOTES, 'UTF-8') ?>
            <?php elseif ($displayStatus === 'expired'): ?>
                <span class="sf-status-icon">⏰</span>
                <?= htmlspecialchars(sf_term('playlist_status_expired', $currentUiLang) ?? 'Vanhentunut', ENT_QUOTES, 'UTF-8') ?>
            <?php else: ?>
                <span class="sf-status-icon">🚫</span>
                <?= htmlspecialchars(sf_term('playlist_status_removed', $currentUiLang) ?? 'Poistettu playlistasta', ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
        </h4>
    </div>
    
    <div class="sf-playlist-status-body">
        <?php if ($displayStatus === 'active'): ?>
            <?php if ($displayExpiresAt): ?>
                <?php
                $expiryDate = new DateTime($displayExpiresAt);
                $now = new DateTime();
                $interval = $now->diff($expiryDate);
                
                if ($interval->days > 0) {
                    $remainingText = sprintf(
                        sf_term('playlist_expires_in_days', $currentUiLang) ?? 'Vanhenee %d päivän kuluttua',
                        $interval->days
                    );
                } else {
                    $remainingText = sf_term('playlist_expires_today', $currentUiLang) ?? 'Vanhenee tänään';
                }
                ?>
                <p class="sf-playlist-expires">
                    <?= htmlspecialchars($remainingText, ENT_QUOTES, 'UTF-8') ?>
                    <br>
                    <small><?= htmlspecialchars($expiryDate->format('d.m.Y H:i'), ENT_QUOTES, 'UTF-8') ?></small>
                </p>
            <?php else: ?>
                <p class="sf-playlist-no-limit">
                    <?= htmlspecialchars(sf_term('playlist_no_expiry', $currentUiLang) ?? 'Ei vanhenemisaikaa', ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>
        <?php elseif ($displayStatus === 'expired'): ?>
            <p class="sf-playlist-expired-at">
                <?= htmlspecialchars(sf_term('playlist_expired_at', $currentUiLang) ?? 'Vanheni', ENT_QUOTES, 'UTF-8') ?>:
                <br>
                <small><?= htmlspecialchars(date('d.m.Y H:i', strtotime($displayExpiresAt)), ENT_QUOTES, 'UTF-8') ?></small>
            </p>
        <?php else: ?>
            <p class="sf-playlist-removed-at">
                <?= htmlspecialchars(sf_term('playlist_removed_at', $currentUiLang) ?? 'Poistettu', ENT_QUOTES, 'UTF-8') ?>:
                <br>
                <small><?= htmlspecialchars(date('d.m.Y H:i', strtotime($displayRemovedAt)), ENT_QUOTES, 'UTF-8') ?></small>
            </p>
        <?php endif; ?>
    </div>
    
    <?php if ($canManage): ?>
        <div class="sf-playlist-actions">
            <?php if ($worksiteApiKey): ?>
                <a href="<?= htmlspecialchars("{$base}/app/api/display_playlist.php?key={$worksiteApiKey}&format=html", ENT_QUOTES, 'UTF-8') ?>"
                   target="_blank"
                   class="sf-btn sf-btn-outline-primary">
                    📺 <?= htmlspecialchars(sf_term('btn_view_playlist', $currentUiLang) ?? 'Katso ajolista', ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endif; ?>
            <?php if ($displayStatus !== 'removed'): ?>
                <button 
                    type="button" 
                    id="btnRemoveFromPlaylist" 
                    class="sf-btn-outline-danger"
                    data-flash-id="<?= (int)$id ?>"
                >
                    <?= htmlspecialchars(sf_term('btn_remove_from_playlist', $currentUiLang) ?? 'Poista playlistasta', ENT_QUOTES, 'UTF-8') ?>
                </button>
            <?php else: ?>
                <button 
                    type="button" 
                    id="btnRestoreToPlaylist" 
                    class="sf-btn-outline-primary"
                    data-flash-id="<?= (int)$id ?>"
                >
                    <?= htmlspecialchars(sf_term('btn_restore_to_playlist', $currentUiLang) ?? 'Palauta playlistaan', ENT_QUOTES, 'UTF-8') ?>
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
