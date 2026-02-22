<?php
/**
 * SafetyFlash - Playlist Manager
 *
 * Ajolistan hallintanäkymä. Näyttää työmaan ajolistan ja mahdollistaa
 * järjestyksen muuttamisen ylös/alas-nuolilla tai drag & drop -toiminnolla.
 *
 * @package SafetyFlash
 * @subpackage Partials
 * @created 2026-02-22
 *
 * Required variables (from parent or URL params):
 * @var PDO|null   $pdo             Database connection (optional — creates own if missing)
 * @var string     $baseUrl         Base URL
 * @var string     $currentUiLang   Current UI language
 */

$displayKeyId = (int)($_GET['display_key_id'] ?? 0);

if ($displayKeyId <= 0) {
    echo '<p class="sf-notice sf-notice-error">' .
        htmlspecialchars(sf_term('playlist_empty', $currentUiLang) ?? 'Ajolista on tyhjä — ei aktiivisia flasheja tällä näytöllä', ENT_QUOTES, 'UTF-8') .
        '</p>';
    return;
}

// Ensure DB connection
if (!isset($pdo)) {
    require_once __DIR__ . '/../../assets/lib/Database.php';
    try {
        $pdo = Database::getInstance();
    } catch (Throwable $e) {
        echo '<p class="sf-notice sf-notice-error">DB error</p>';
        return;
    }
}

// Fetch display info
try {
    $stmtKey = $pdo->prepare("SELECT id, label, site, lang, api_key FROM sf_display_api_keys WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmtKey->execute([$displayKeyId]);
    $displayKey = $stmtKey->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $displayKey = null;
}

if (!$displayKey) {
    echo '<p class="sf-notice sf-notice-error">Näyttöä ei löydy tai se ei ole aktiivinen.</p>';
    return;
}

// Fetch playlist items
try {
    $stmtItems = $pdo->prepare("
        SELECT
            f.id,
            f.title,
            f.preview_filename,
            f.type,
            COALESCE(t.sort_order, 0) AS sort_order
        FROM sf_flashes f
        INNER JOIN sf_flash_display_targets t ON t.flash_id = f.id
        WHERE t.display_key_id = :display_key_id
          AND t.is_active = 1
          AND f.state = 'published'
          AND (f.display_expires_at IS NULL OR f.display_expires_at > NOW())
          AND f.display_removed_at IS NULL
        ORDER BY COALESCE(t.sort_order, 0) ASC, f.published_at DESC
        LIMIT 100
    ");
    $stmtItems->execute([':display_key_id' => $displayKeyId]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $items = [];
}

$displayLabel = htmlspecialchars($displayKey['label'] ?? $displayKey['site'], ENT_QUOTES, 'UTF-8');
$playlistUrl  = htmlspecialchars("{$baseUrl}/app/api/display_playlist.php?key={$displayKey['api_key']}&format=html", ENT_QUOTES, 'UTF-8');
$csrfToken    = sf_csrf_token();
?>

<div class="sf-page-container" id="playlistManagerWrap">
    <div class="sf-page-header">
        <h1 class="sf-page-title">
            📺 <?= htmlspecialchars(sf_term('playlist_manager_heading', $currentUiLang) ?? 'Ajolistan hallinta', ENT_QUOTES, 'UTF-8') ?>
            — <?= $displayLabel ?>
        </h1>
        <a href="<?= $playlistUrl ?>" target="_blank" class="sf-btn sf-btn-outline-primary">
            📺 <?= htmlspecialchars(sf_term('btn_view_playlist', $currentUiLang) ?? 'Katso ajolista', ENT_QUOTES, 'UTF-8') ?>
        </a>
    </div>

    <?php if (empty($items)): ?>
        <p class="sf-notice sf-notice-info">
            <?= htmlspecialchars(sf_term('playlist_empty', $currentUiLang) ?? 'Ajolista on tyhjä — ei aktiivisia flasheja tällä näytöllä', ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php else: ?>
        <div id="sfPlaylistSaveMsg" class="sf-notice sf-notice-success" style="display:none;">
            <?= htmlspecialchars(sf_term('playlist_reorder_saved', $currentUiLang) ?? 'Järjestys tallennettu', ENT_QUOTES, 'UTF-8') ?>
        </div>

        <ul id="sfPlaylistItems" class="sf-playlist-manager-list"
            data-display-key-id="<?= $displayKeyId ?>"
            data-reorder-url="<?= htmlspecialchars("{$baseUrl}/app/api/playlist_reorder.php", ENT_QUOTES, 'UTF-8') ?>"
            data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <?php foreach ($items as $i => $item): ?>
                <?php
                $previewUrl = $item['preview_filename']
                    ? htmlspecialchars("{$baseUrl}/uploads/previews/{$item['preview_filename']}", ENT_QUOTES, 'UTF-8')
                    : htmlspecialchars("{$baseUrl}/assets/img/camera-placeholder.png", ENT_QUOTES, 'UTF-8');
                ?>
                <li class="sf-playlist-manager-item" data-flash-id="<?= (int)$item['id'] ?>">
                    <span class="sf-pm-drag-handle" title="Vedä siirtääksesi" aria-hidden="true">⠿</span>
                    <img src="<?= $previewUrl ?>"
                         alt=""
                         class="sf-pm-thumb"
                         loading="lazy">
                    <span class="sf-pm-title"><?= htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="sf-pm-order-btns">
                        <button type="button"
                                class="sf-pm-btn-up"
                                title="<?= htmlspecialchars(sf_term('playlist_move_up', $currentUiLang) ?? 'Siirrä ylös', ENT_QUOTES, 'UTF-8') ?>"
                                aria-label="<?= htmlspecialchars(sf_term('playlist_move_up', $currentUiLang) ?? 'Siirrä ylös', ENT_QUOTES, 'UTF-8') ?>"
                                <?= ($i === 0) ? 'disabled' : '' ?>>▲</button>
                        <button type="button"
                                class="sf-pm-btn-down"
                                title="<?= htmlspecialchars(sf_term('playlist_move_down', $currentUiLang) ?? 'Siirrä alas', ENT_QUOTES, 'UTF-8') ?>"
                                aria-label="<?= htmlspecialchars(sf_term('playlist_move_down', $currentUiLang) ?? 'Siirrä alas', ENT_QUOTES, 'UTF-8') ?>"
                                <?= ($i === count($items) - 1) ? 'disabled' : '' ?>>▼</button>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="sf-playlist-manager-actions">
            <button type="button" id="sfPlaylistSaveBtn" class="sf-btn sf-btn-primary">
                <?= htmlspecialchars(sf_term('btn_save', $currentUiLang) ?? 'Tallenna järjestys', ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    <?php endif; ?>
</div>

<link rel="stylesheet" href="<?= htmlspecialchars("{$baseUrl}/assets/css/display-ttl.css", ENT_QUOTES, 'UTF-8') ?>">
<script src="<?= htmlspecialchars("{$baseUrl}/assets/js/playlist-manager.js", ENT_QUOTES, 'UTF-8') ?>"></script>
