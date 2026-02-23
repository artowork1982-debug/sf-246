<?php
// assets/pages/view.php
declare(strict_types=1);

// DEBUG
ini_set('display_errors', '1');
error_reporting(E_ALL);
ini_set('error_log', __DIR__ . '/../../assets/logs/php_errors.log');

require_once __DIR__ . '/../../app/includes/protect.php';
require_once __DIR__ .'/../../app/includes/statuses.php';
require_once __DIR__ . '/../../app/actions/helpers.php';

$base = rtrim($config['base_url'] ?? '', '/');

// --- DB: PDO ---
try {
    $pdo = Database::getInstance();
} catch (Throwable $e) {
    $errorLang = $_SESSION['ui_lang'] ?? 'fi';
    echo '<p>' . htmlspecialchars(sf_term('db_error', $errorLang), ENT_QUOTES, 'UTF-8') . '</p>';
    exit;
}

// --- ID ---
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    $errorLang = $_SESSION['ui_lang'] ?? 'fi';
    echo '<p>' . htmlspecialchars(sf_term('invalid_id', $errorLang), ENT_QUOTES, 'UTF-8') . '</p>';
    exit;
}

// --- Safetyflash ---
$stmt = $pdo->prepare("
    SELECT *,
        DATE_FORMAT(created_at, '%d.%m.%Y %H:%i')   AS createdFmt,
        DATE_FORMAT(updated_at, '%d.%m.%Y %H:%i')   AS updatedFmt,
        DATE_FORMAT(occurred_at, '%d.%m.%Y %H:%i')  AS occurredFmt
    FROM sf_flashes
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$flash = $stmt->fetch();

if (!$flash) {
    $errorLang = $_SESSION['ui_lang'] ?? 'fi';
    echo '<p>' . htmlspecialchars(sf_term('flash_not_found', $errorLang), ENT_QUOTES, 'UTF-8') . '</p>';
    exit;
}

// Record user's last read timestamp
$currentUser = sf_current_user();
if ($currentUser && $flash) {
    // Use translation_group_id if available (marks all language versions as read)
    $flashId = !empty($flash['translation_group_id'])
        ? (int)$flash['translation_group_id']
        : (int)$flash['id'];
    $userId = (int)$currentUser['id'];
    
    // Upsert the last_read_at timestamp
    try {
        $readStmt = $pdo->prepare("
            INSERT INTO sf_flash_reads (flash_id, user_id, last_read_at)
            VALUES (:flash_id, :user_id, NOW())
            ON DUPLICATE KEY UPDATE last_read_at = NOW()
        ");
        $readStmt->execute([
            ':flash_id' => $flashId,
            ':user_id' => $userId
        ]);
    } catch (PDOException $e) {
        // Silently fail if table doesn't exist yet - migration might not be applied
        error_log('Failed to update flash read timestamp: ' . $e->getMessage());
    }
}

$uiLang          = $_SESSION['ui_lang'] ?? 'fi';
$currentUiLang   = $uiLang ?? 'fi';

// Check if user can manage reviewers (admin, safety team, or original creator)
$canManageReviewers = false;
if ($currentUser) {
    $userRoleId = (int)($currentUser['role_id'] ?? 0);
    $userId = (int)($currentUser['id'] ?? 0);
    $flashCreatorId = (int)($flash['created_by'] ?? 0);
    
    // Admin (1), Safety Team (3), or original creator
    $canManageReviewers = ($userRoleId === 1 || $userRoleId === 3 || $userId === $flashCreatorId);
}

// Mäppää state -> CSS-luokka view-sivun pillille
$stateClassMap = [
    'draft'          => 'status-pill-draft',
    'pending_supervisor' => 'status-pill-supervisor',
    'pending_review' => 'status-pill-pending',
    'request_info'   => 'status-pill-request',
    'reviewed'       => 'status-pill-reviewed',
    'to_comms'       => 'status-pill-comms',
    'published'      => 'status-pill-published',
];
$metaStatusClass = $stateClassMap[$flash['state']] ?? '';
$statusLabel     = function_exists('sf_status_label') ? (sf_status_label($flash['state'], $currentUiLang) ?? '') : '';

// Lokia varten ryhmän juuri
$logFlashId = !empty($flash['translation_group_id'])
    ? (int)$flash['translation_group_id']
    : (int)$flash['id'];

// Hae lokit (ryhmän juurella)
$logs = [];
$logStmt = $pdo->prepare("
    SELECT 
        l.id,
        l.event_type,
        l.description,
        l.created_at,
        l.user_id,
        l.parent_comment_id as parent_id,
        u.first_name,
        u.last_name
    FROM safetyflash_logs l
    LEFT JOIN sf_users u ON u.id = l.user_id
    WHERE l.flash_id = ?
    ORDER BY l.created_at DESC
");
$logStmt->execute([$logFlashId]);
$logs = $logStmt->fetchAll();

// Fallback: jos lokitaulu on tyhjä, näytä vähintään luontiaika
if (empty($logs)) {
    $creatorName = trim(($flash['created_by_first_name'] ?? '') .' ' .($flash['created_by_last_name'] ?? ''));
    if ($creatorName === '') $creatorName = null;

    $logs = [[
        'id' => 0,
        'event_type' => 'created',
        'description' => sf_term('log_created', $currentUiLang) ?? 'Created',
        'created_at' => $flash['created_at'] ?? ($flash['createdFmtRaw'] ?? null),
        'first_name' => $creatorName ? ($flash['created_by_first_name'] ?? '') : null,
        'last_name'  => $creatorName ? ($flash['created_by_last_name'] ?? '') : null,
    ]];
}

// Onko tämä kieliversio vai alkuperäinen flash?
$isTranslation = !empty($flash['translation_group_id'])
    && (int) $flash['translation_group_id'] !== (int) $flash['id'];

$editUrl  = $base .  '/index.php?page=form&id=' .  $id;

// --- Työmaavastaavan tarkistus: näytä kenellä tarkistuksessa ---
$pendingSupervisorUsers = [];
$selectedSupervisorIds = [];

if (($flash['state'] ?? '') === 'pending_supervisor') {
    $rawSel = $flash['selected_approvers'] ?? null;

    if (!empty($rawSel)) {
        $decoded = json_decode((string)$rawSel, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // Tue sekä [1,2] että {"approver_ids":[1,2]} muodot
            if (isset($decoded['approver_ids']) && is_array($decoded['approver_ids'])) {
                $decoded = $decoded['approver_ids'];
            }

            $selectedSupervisorIds = array_values(array_unique(array_map('intval', $decoded)));
            $selectedSupervisorIds = array_values(array_filter($selectedSupervisorIds, fn($v) => (int)$v > 0));
        }
    }

    if (!empty($selectedSupervisorIds)) {
        $placeholders = implode(',', array_fill(0, count($selectedSupervisorIds), '?'));
        $stmtSup = $pdo->prepare("
            SELECT id, first_name, last_name, email
            FROM sf_users
            WHERE id IN ($placeholders)
            ORDER BY last_name, first_name
        ");
        $stmtSup->execute($selectedSupervisorIds);
        $pendingSupervisorUsers = $stmtSup->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

// --- Tuetut kielet ja lippujen ikonit ---
$supportedLangs = [
    'fi' => ['label' => 'FI', 'icon' => 'finnish-flag.png'],
    'sv' => ['label' => 'SV', 'icon' => 'swedish-flag.png'],
    'en' => ['label' => 'EN', 'icon' => 'english-flag.png'],
    'it' => ['label' => 'IT', 'icon' => 'italian-flag.png'],
    'el' => ['label' => 'EL', 'icon' => 'greece-flag.png'], // 'el' on Kreikan kielikoodi
];

// Lippu-apufunktio kieliversioiden visuaaliseen tunnistamiseen
if (!function_exists('sf_lang_flag')) {
    function sf_lang_flag(string $lang): string {
        return match($lang) {
            'fi' => '🇫🇮',
            'sv' => '🇸🇪',
            'en' => '🇬🇧',
            'it' => '🇮🇹',
            'el' => '🇬🇷',
            default => '🏳️',
        };
    }
}

// --- Kieliversiot & preview ---
require_once __DIR__ .'/../services/render_services.php';

$currentId   = (int) ($flash['id'] ?? 0);
$currentLang = $flash['lang'] ?? 'fi';

$translationGroupId = !empty($flash['translation_group_id'])
    ? (int) $flash['translation_group_id']
    : $currentId;

$translations = [];
if ($translationGroupId > 0 && function_exists('sf_get_flash_translations')) {
    $translations = sf_get_flash_translations($pdo, $translationGroupId);
    if (!isset($translations[$currentLang]) && $currentId > 0) {
        $translations[$currentLang] = $currentId;
    }
}

// Check preview status
$previewStatus = $flash['preview_status'] ?? 'completed';
$isPreviewPending = ($previewStatus === 'pending' || $previewStatus === 'processing');

// Jos preview_filename puuttuu, yritä generoida se
if (empty($flash['preview_filename']) && $currentId > 0 && function_exists('sf_generate_flash_preview') && !$isPreviewPending) {
    try {
        sf_generate_flash_preview($pdo, $currentId);
        // Hae uudelleen
        $stmtPrev = $pdo->prepare("SELECT preview_filename, preview_status FROM sf_flashes WHERE id = ?");
        $stmtPrev->execute([$currentId]);
        $prevRow = $stmtPrev->fetch();
        if ($prevRow && !empty($prevRow['preview_filename'])) {
            $flash['preview_filename'] = $prevRow['preview_filename'];
        }
        if ($prevRow && !empty($prevRow['preview_status'])) {
            $previewStatus = $prevRow['preview_status'];
            $isPreviewPending = ($previewStatus === 'pending' || $previewStatus === 'processing');
        }
    } catch (Throwable $e) {
        error_log("Could not auto-generate preview for flash {$currentId}: " .$e->getMessage());
    }
}

// Cache-buster kuville (estää vanhan kuvan näkymisen viewissä)
$sfCacheBust = function (string $url, ?string $absPath): string {
    if (!empty($absPath) && is_file($absPath)) {
        $v = (string) filemtime($absPath);
        return $url . (strpos($url, '?') === false ? '?' : '&') . 'v=' . rawurlencode($v);
    }
    return $url;
};

// --- Preview-kuva 1 ---
$previewUrl = "{$base}/assets/img/camera-placeholder.png";
$previewAbsPath = null;

if (!empty($flash['preview_filename'])) {
    $filename = $flash['preview_filename'];
    $previewPathNew = __DIR__ .'/../../uploads/previews/' .$filename;
    $previewPathOld = __DIR__ .'/../../img/' .$filename; // legacy

    if (is_file($previewPathNew)) {
        $previewUrl = "{$base}/uploads/previews/" .$filename;
        $previewAbsPath = $previewPathNew;
    } elseif (is_file($previewPathOld)) {
        $previewUrl = "{$base}/img/" .$filename;
        $previewAbsPath = $previewPathOld;
    }
}

$previewUrl = $sfCacheBust($previewUrl, $previewAbsPath);

// --- Preview-kuva 2 (vain tutkintatiedotteille) ---
$previewUrl2 = null;
$hasSecondCard = false;
$previewAbsPath2 = null;

// Check if second card exists by checking if the file exists (simpler and more reliable)
if ($flash['type'] === 'green' && !empty($flash['preview_filename_2'])) {
    $filename2 = $flash['preview_filename_2'];
    $previewPath2New = __DIR__ .'/../../uploads/previews/' .$filename2;
    $previewPath2Old = __DIR__ .'/../../img/' .$filename2;
    
    if (is_file($previewPath2New)) {
        $previewUrl2 = "{$base}/uploads/previews/" .$filename2;
        $previewAbsPath2 = $previewPath2New;
        $hasSecondCard = true;
    } elseif (is_file($previewPath2Old)) {
        $previewUrl2 = "{$base}/img/" .$filename2;
        $previewAbsPath2 = $previewPath2Old;
        $hasSecondCard = true;
    }
}

if (!empty($previewUrl2)) {
    $previewUrl2 = $sfCacheBust($previewUrl2, $previewAbsPath2);
}
// UUSI: Editorissa generoitu rasteri (uploads/edited) – luetaan annotations_datasta
$sfAnn = json_decode($flash['annotations_data'] ?? '', true);
$sfEditedImages = (is_array($sfAnn) && isset($sfAnn['edited_images']) && is_array($sfAnn['edited_images']))
    ? $sfAnn['edited_images']
    : [];

$sfGetEditedUrl = function (int $slot) use ($sfEditedImages, $base, $sfCacheBust): ?string {
    $key = (string) $slot;
    if (!empty($sfEditedImages[$key])) {
        $file = $sfEditedImages[$key];
        $url  = $base .'/uploads/edited/' .$file;
        $abs  = __DIR__ .'/../../uploads/edited/' .$file;
        return $sfCacheBust($url, $abs);
    }
    return null;
};

// Kuvapolkujen muodostaminen JS:lle
$getImageUrlForJs = function ($filename) use ($base) {
    if (empty($filename)) {
        return '';
    }
    
    // Tarkista ensin uploads/images
    $path = "uploads/images/{$filename}";
    $fullPath = __DIR__ ."/../../{$path}";
    if (file_exists($fullPath)) {
        return "{$base}/{$path}";
    }
    
    // Tarkista uploads/library (kuvakirjasto)
    $libPath = "uploads/library/{$filename}";
    $libFullPath = __DIR__ ."/../../{$libPath}";
    if (file_exists($libFullPath)) {
        return "{$base}/{$libPath}";
    }
    
    // Vanha polku (legacy)
    $oldPath = "img/{$filename}";
    $oldFullPath = __DIR__ . "/../../{$oldPath}";
    if (file_exists($oldFullPath)) {
        return "{$base}/{$oldPath}";
    }
    
    // Palauta tyhjä jos ei löydy
    return '';
};

// Hae originaalin grid_bitmap jos tämä on kieliversio
$originalGridBitmap = $flash['grid_bitmap'] ?? '';
$originalGridBitmapUrl = '';

if (empty($originalGridBitmap) && ! empty($flash['translation_group_id'])) {
    // Hae originaalin grid_bitmap
    $origStmt = $pdo->prepare("SELECT grid_bitmap FROM sf_flashes WHERE id = ?  LIMIT 1");
    $origStmt->execute([(int)$flash['translation_group_id']]);
    $origRow = $origStmt->fetch();
    if ($origRow && !empty($origRow['grid_bitmap'])) {
        $originalGridBitmap = $origRow['grid_bitmap'];
    }
}

// Muodosta grid_bitmap URL
if (! empty($originalGridBitmap)) {
    if (strpos($originalGridBitmap, 'data:image/') === 0) {
        $originalGridBitmapUrl = $originalGridBitmap;
    } else {
        $gridPath = __DIR__ . '/../../uploads/grids/' . $originalGridBitmap;
        if (file_exists($gridPath)) {
            $originalGridBitmapUrl = $base .  '/uploads/grids/' . $originalGridBitmap;
        }
    }
}

// Prepare flash data for JavaScript - ALWAYS use parent ID for creating translations
$parentFlashId = !empty($flash['translation_group_id']) 
    ? (int)$flash['translation_group_id'] 
    : (int)$flash['id'];

$flashDataForJs = [
    'id' => $parentFlashId,  // Use parent ID for translation creation
    'current_id' => (int)$flash['id'],  // Current flash being viewed
    'translation_group_id' => $flash['translation_group_id'] ?? null,
    'type' => $flash['type'],
    'title' => $flash['title'],
    'title_short' => $flash['title_short'] ?? $flash['summary'] ?? '',
    'description' => $flash['description'] ?? '',
    'root_causes' => $flash['root_causes'] ?? '',
    'actions' => $flash['actions'] ??  '',
    'site' => $flash['site'] ??  '',
    'site_detail' => $flash['site_detail'] ?? '',
    'occurred_at' => $flash['occurred_at'] ?? '',
    'lang' => $flash['lang'] ??  'fi',
    'image_main' => $flash['image_main'] ?? '',
    'image_2' => $flash['image_2'] ?? '',
    'image_3' => $flash['image_3'] ?? '',
    'image_main_url' => ($sfGetEditedUrl(1) ?: $getImageUrlForJs($flash['image_main'] ?? null)),
    'image_2_url' => ($sfGetEditedUrl(2) ?: $getImageUrlForJs($flash['image_2'] ?? null)),
    'image_3_url' => ($sfGetEditedUrl(3) ?: $getImageUrlForJs($flash['image_3'] ?? null)),
    'image1_transform' => $flash['image1_transform'] ?? '',
    'image2_transform' => $flash['image2_transform'] ?? '',
    'image3_transform' => $flash['image3_transform'] ?? '',
    'grid_style' => $flash['grid_style'] ?? 'grid-3-main-top',
    'grid_bitmap' => $originalGridBitmap,
    'grid_bitmap_url' => $originalGridBitmapUrl,
];

// --- Tyyppien labelit termistön kautta ---
$typeKeyMap = [
    'red'    => 'first_release',
    'yellow' => 'dangerous_situation',
    'green'  => 'investigation_report',
];
$typeKey   = $typeKeyMap[$flash['type']] ?? null;
$typeLabel = $typeKey ? sf_term($typeKey, $currentUiLang) : 'Safetyflash';

// --- Apu: generaattori lokirivin avataria varten (nimi -> initials) ---
function sf_avatar_initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $initials = '';
    foreach ($parts as $p) {
        if ($p !== '') $initials .= mb_strtoupper(mb_substr($p, 0, 1));
        if (mb_strlen($initials) >= 2) break;
    }
    return $initials ?: 'SF';
}
// ===== TOIMINTOJEN MÄÄRITYS KÄYTTÄJÄN ROOLIN JA TILAN MUKAAN =====
$currentUser = sf_current_user();
$roleId = (int)($currentUser['role_id'] ?? 0);
$currentUserId = (int)($currentUser['id'] ?? 0);
$createdBy = (int)($flash['created_by'] ?? 0);
$stateVal = $flash['state'] ?? 'draft';

$isOwner = ($currentUserId > 0 && $createdBy === $currentUserId);
$isAdmin = ($roleId === 1);
$isSafety = ($roleId === 3);
$isComms = ($roleId === 4);

$actions = [];

// Check if archived - if so, disable most actions
$isArchived = !empty($flash['is_archived']);

// Kommentointi kaikille kirjautuneille (ei arkistoiduille)
if (!$isArchived) {
    $actions[] = 'comment';
}

// If archived, no further actions allowed
if ($isArchived) {
    // Archived flashes cannot be edited or modified
    // Only viewing is allowed
} else {
    // Määritä toiminnot tilan ja roolin mukaan
switch ($stateVal) {
    case 'draft':
        if ($isOwner || $isAdmin) {
            $actions[] = 'edit';
            $actions[] = 'delete';
            $actions[] = 'send_to_review';
        }
        break;

case 'pending_supervisor':
        // Check if user is a supervisor
        require_once __DIR__ . '/../../app/services/ApprovalRouting.php';
        $isSupervisor = ApprovalRouting::isUserSupervisor($pdo, $currentUserId);
        $isSelectedApprover = ApprovalRouting::isUserSelectedApprover($pdo, (int)$id, $currentUserId);

        // Työmaavastaava (valittu tarkistaja) + admin: normaalit workflow-toiminnot
        if (($isSupervisor && $isSelectedApprover) || $isAdmin) {
            $actions[] = 'edit';
            $actions[] = 'send_to_safety';  // Send to safety team
            $actions[] = 'request';         // Request more info from creator
        }

        // Turvatiimi: saa muokata tässä tilassa
        if ($isSafety) {
            $actions[] = 'edit';
        }

        // Admin + turvatiimi voi “hyväksyä ja lähettää viestintään” suoraan tässä tilassa
        if ($isAdmin || $isSafety) {
            $actions[] = 'approve_to_comms';
        }

        break;

    case 'pending_review': 
        if ($isSafety || $isAdmin) {
            $actions[] = 'edit';
            $actions[] = 'request';     // Palauta korjattavaksi
            $actions[] = 'comms';       // Lähetä viestintään
        }
        break;

case 'request_info': 
    if ($isOwner || $isAdmin) {
        $actions[] = 'edit';
        // send_to_review poistettu - se näkyy jo lomakkeen esikatselussa
    }
    break;

    case 'reviewed':
        if ($isSafety || $isAdmin) {
            $actions[] = 'edit';
            $actions[] = 'comms';
        }
        break;

    case 'to_comms':
        if ($isComms || $isAdmin) {
            $actions[] = 'edit';
            $actions[] = 'publish';
            $actions[] = 'request';     // Palauta turvatiimille
        }
        // Turvatiimi voi myös muokata viestinnällä-tilassa
        if ($isSafety) {
            $actions[] = 'edit';
        }
        break;

    case 'published': 
        if ($isAdmin || $isComms) {
            $actions[] = 'edit';
        }
        // Add archive action for admin and safety team
        if (($isAdmin || $isSafety) && !$isArchived) {
            $actions[] = 'archive';
        }
        // Infonäyttöjen hallinta julkaistuille flasheille
        if ($isAdmin || $isSafety || $isComms) {
            $actions[] = 'display_targets';
        }
        break;
}

// Poista duplikaatit
$actions = array_unique($actions);
// Admin voi aina poistaa
if ($isAdmin && ! in_array('delete', $actions)) {
    $actions[] = 'delete';
}
}

$hasActions = ! empty($actions);

// Determine if current user can edit this flash (used in Images tab JS)
$canEdit = in_array('edit', $actions);

// Determine if current user can add extra images (broader than canEdit - allows owner/admin/safety in all states)
$canAddExtraImages = $isOwner || $isAdmin || $isSafety;

$iconBase = $base .'/assets/img/icons/';
?>
<?php $stateCss = preg_replace('/[^a-z0-9_\-]/i', '', (string)($flash['state'] ?? '')); ?>
<div class="sf-page-container">
<div class="view-container view-state-<?= htmlspecialchars($stateCss, ENT_QUOTES, 'UTF-8') ?>">
    <div class="view-back" style="display: flex; justify-content: space-between; align-items: center;">
        <a
          href="<?= htmlspecialchars($base) ?>/index.php?page=list"
          class="btn-back"
          aria-label="<?= htmlspecialchars(sf_term('back_to_list', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>"
        >
          ← <?= htmlspecialchars(sf_term('back_to_list', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
        </a>
        <?php if (($flash['type'] ?? '') === 'green'): ?>
        <button
           id="btnGenerateReport"
           data-report-url="<?= htmlspecialchars($base) ?>/app/api/generate_report.php?id=<?= (int)$id ?>"
           class="btn-report-topright"
           aria-label="<?= htmlspecialchars(sf_term('btn_report', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>">
            <img src="<?= $iconBase ?>report.svg" alt="" class="btn-report-icon">
            <span><?= htmlspecialchars(sf_term('btn_report', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
        <?php endif; ?>
    </div>

    <!-- ===== FOOTER ACTION BAR (siirretty ylös, jotta näkyy heti) ===== -->
    <?php if ($hasActions): ?>
    <div class="view-footer-actions" role="toolbar" aria-label="Toiminnot">
        <div class="view-footer-buttons-4col">

            <?php if (in_array('comment', $actions)): ?>
                <button class="footer-btn fb-comment" id="footerComment" type="button" aria-label="<?= htmlspecialchars(sf_term('footer_comment', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>">
                    <img src="<?= $iconBase ?>comment_icon.svg" alt="" class="footer-icon">
                    <span class="btn-label"><?= htmlspecialchars(sf_term('footer_comment', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
            <?php endif; ?>

            <?php if (in_array('edit', $actions)): ?>
                <button class="footer-btn fb-edit" id="footerEdit" type="button" aria-label="<?= htmlspecialchars(sf_term('footer_edit', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>">
                    <img src="<?= $iconBase ?>edit_icon.svg" alt="" class="footer-icon">
                    <span class="btn-label"><?= htmlspecialchars(sf_term('footer_edit', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
            <?php endif; ?>

            <?php if (in_array('delete', $actions)): ?>
                <button class="footer-btn fb-delete" id="footerDelete" type="button" aria-label="<?= htmlspecialchars(sf_term('footer_delete', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>">
                    <img src="<?= $iconBase ?>delete_icon.svg" alt="" class="footer-icon">
                    <span class="btn-label"><?= htmlspecialchars(sf_term('footer_delete', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
            <?php endif; ?>

            <?php if (in_array('request', $actions)): ?>
                <button class="footer-btn fb-request" id="footerRequest" type="button" aria-label="<?= htmlspecialchars(sf_term('footer_return', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>">
                    <img src="<?= $iconBase ?>reverse_icon.svg" alt="" class="footer-icon">
                    <span class="btn-label"><?= htmlspecialchars(sf_term('footer_return', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
            <?php endif; ?>

            <?php if (in_array('approve_to_comms', $actions)): ?>
                <?php
                $lblApproveToComms = [
                    'fi' => 'Hyväksy → viestintään',
                    'sv' => 'Godkänn → kommunikation',
                    'en' => 'Approve → Comms',
                    'it' => 'Approva → Comunicazione',
                    'el' => 'Έγκριση → Επικοινωνία',
                ][$currentUiLang] ?? 'Approve → Comms';
                ?>
                <button
                    class="footer-btn fb-comms"
                    id="footerApproveToComms"
                    type="button"
                    data-modal-open="#modalToComms"
                    aria-label="<?= htmlspecialchars($lblApproveToComms, ENT_QUOTES, 'UTF-8') ?>"
                >
                    <img src="<?= $iconBase ?>communications_icon.svg" alt="" class="footer-icon">
                    <span class="btn-label"><?= htmlspecialchars($lblApproveToComms, ENT_QUOTES, 'UTF-8') ?></span>
                </button>
            <?php endif; ?>

            <?php if (in_array('comms', $actions)): ?>
                <button class="footer-btn fb-comms" id="footerComms" type="button" aria-label="<?= htmlspecialchars(sf_term('footer_to_comms', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>">
                    <img src="<?= $iconBase ?>communications_icon.svg" alt="" class="footer-icon">
                    <span class="btn-label"><?= htmlspecialchars(sf_term('footer_to_comms', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
            <?php endif; ?>

            <?php if (in_array('send_to_safety', $actions)): ?>
                <button class="footer-btn fb-send-safety" id="footerSendSafety" type="button" aria-label="<?= htmlspecialchars(sf_term('footer_send_to_safety', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>">
                    <img src="<?= $iconBase ?>forward_icon.svg" alt="" class="footer-icon">
                    <span class="btn-label"><?= htmlspecialchars(sf_term('footer_send_to_safety', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
            <?php endif; ?>

            <?php if (in_array('publish', $actions)): ?>
                <button class="footer-btn fb-publish" id="footerPublish" type="button" aria-label="<?= htmlspecialchars(sf_term('footer_publish', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>">
                    <img src="<?= $iconBase ?>publish_icon.svg" alt="" class="footer-icon">
                    <span class="btn-label"><?= htmlspecialchars(sf_term('footer_publish', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
            <?php endif; ?>

            <?php if (in_array('archive', $actions)): ?>
                <button class="footer-btn fb-archive" id="footerArchive" type="button" aria-label="<?= htmlspecialchars(sf_term('btn_archive', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>">
                    <img src="<?= $iconBase ?>archive_icon.svg" alt="" class="footer-icon">
                    <span class="btn-label"><?= htmlspecialchars(sf_term('btn_archive', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
            <?php endif; ?>

            <?php if (in_array('display_targets', $actions)): ?>
                <button class="footer-btn fb-display-targets" id="footerDisplayTargets" type="button" aria-label="<?= htmlspecialchars(sf_term('footer_display_targets', $currentUiLang) ?? 'Infonäytöt', ENT_QUOTES, 'UTF-8') ?>">
                    <img src="<?= $iconBase ?>display.svg" alt="" class="footer-icon">
                    <span class="btn-label"><?= htmlspecialchars(sf_term('footer_display_targets', $currentUiLang) ?? 'Infonäytöt', ENT_QUOTES, 'UTF-8') ?></span>
                </button>
            <?php endif; ?>

            <?php if (in_array('send_to_review', $actions)): ?>
                <form method="post" action="<?= htmlspecialchars($base) ?>/app/actions/send_to_review.php?id=<?= (int) $id ?>" class="footer-form">
                    <?= sf_csrf_field() ?>
                    <button class="footer-btn fb-comms" type="submit">
                        <img src="<?= $iconBase ?>communications_icon.svg" alt="" class="footer-icon">
                        <span class="btn-label"><?= htmlspecialchars(sf_term('footer_send_to_review', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                </form>
            <?php endif; ?>

        </div>
    </div>
    <?php endif; // End of hasActions check ?>

    <div
      class="lang-switcher"
      role="tablist"
      aria-label="<?= htmlspecialchars(sf_term('view_languages_aria', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>"
    >
        <?php foreach ($supportedLangs as $langCode => $langData):
            $hasTranslation = isset($translations[$langCode]);
            $isActive = ($langCode === $currentLang);
            
            // LISÄTTY: Arkistoidussa näytetään vain olemassa olevat käännökset
            if ($isArchived && ! $hasTranslation) {
                continue; // Ohita puuttuvat käännökset arkistoidussa
            }
            
            // Build tooltip text based on state
            $tooltipText = '';
            if ($isActive) {
                // Active version tooltip
                $tooltipText = sf_term('lang_tooltip_active_' . $langCode, $currentUiLang) ?? '';
            } elseif ($hasTranslation) {
                // Created version tooltip (go to version)
                $tooltipText = sf_term('lang_tooltip_goto_' . $langCode, $currentUiLang) ?? '';
            } else {
                // Missing version tooltip (add version)
                $tooltipText = sf_term('lang_tooltip_add_' . $langCode, $currentUiLang) ?? '';
            }
            
            // Get add button text
            $addButtonText = sf_term('lang_add_button_text', $currentUiLang) ?? '+Lisää';
        ?>
            <div class="lang-chip <?= $isActive ? 'active' : '' ?> <?= $hasTranslation ? 'has-version' : 'no-version' ?>" role="button" tabindex="0" title="<?= htmlspecialchars($tooltipText, ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($hasTranslation): ?>
                    <a href="index.php?page=view&id=<?= (int)$translations[$langCode] ?>" class="lang-link">
                        <img class="lang-flag-img" src="<?= htmlspecialchars($base) ?>/assets/img/<?= htmlspecialchars($langData['icon']) ?>" alt="<?= htmlspecialchars($langData['label']) ?>">
                        <span class="lang-label"><?= htmlspecialchars($langData['label']) ?></span>
                    </a>
                <?php elseif (! $isArchived): ?>
                    <!-- Näytä + -nappi vain jos EI arkistoitu -->
                    <button type="button" class="lang-add-button" data-lang="<?= htmlspecialchars($langCode) ?>" data-lang-label="<?= htmlspecialchars($langData['label']) ?>" data-base-id="<?= (int)$currentId ?>" onclick="sfConfirmTranslation(this)">
                        <img class="lang-flag-img" src="<?= htmlspecialchars($base) ?>/assets/img/<?= htmlspecialchars($langData['icon']) ?>" alt="<?= htmlspecialchars($langData['label']) ?>">
                        <span class="lang-label"><?= htmlspecialchars($addButtonText) ?></span>
                    </button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- UUSI RAKENNE ALKAA TÄSTÄ -->
    <div class="view-layout">
        <!-- Vasen palsta -->
        <div class="view-left">
            <div class="view-box preview-box" 
                 data-flash-id="<?= (int)$flash['id'] ?>"
                 data-preview-status="<?= htmlspecialchars($previewStatus) ?>">
                <!-- Loading spinner for preview image -->
                <div class="preview-loading-spinner" id="previewSpinner">
                    <div class="spinner"></div>
                    <span class="spinner-text"><?= htmlspecialchars(sf_term('loading', $currentUiLang) ?: 'Ladataan...', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                
                <?php if ($flash['type'] === 'green' && $hasSecondCard): ?>
                    <!-- TUTKINTATIEDOTE: Välilehdet kahdelle kortille -->
                    <div class="sf-view-preview-tabs" id="sfViewPreviewTabs">
                        <button type="button"
                                class="sf-view-tab-btn active"
                                data-target="preview1">
                            <?= htmlspecialchars(sf_term('card_1_summary', $currentUiLang) ?? '1. Yhteenveto', ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button type="button"
                                class="sf-view-tab-btn"
                                data-target="preview2">
                            <?= htmlspecialchars(sf_term('card_2_investigation', $currentUiLang) ?? '2. Juurisyyt & toimenpiteet', ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </div>

                    <div class="sf-view-preview-cards">
                        <div class="sf-view-preview-card active" id="viewPreview1">
                            <img src="<?= htmlspecialchars($previewUrl) ?>" alt="Preview kortti 1"
                                 class="preview-image" id="viewPreviewImage1"
                                 loading="eager">
                        </div>
                        <div class="sf-view-preview-card" id="viewPreview2" style="display:none;">
                            <?php if ($previewUrl2): ?>
                                <img src="<?= htmlspecialchars($previewUrl2) ?>" alt="Preview kortti 2"
                                     class="preview-image" id="viewPreviewImage2"
                                     loading="lazy"
                                     decoding="async">
                            <?php else: ?>
                                <div class="sf-preview-placeholder">
                                    <p>
                                        <?= htmlspecialchars(
                                            sf_term('preview_2_not_generated', $currentUiLang)
                                            ?? 'Kortin 2 preview-kuvaa ei ole vielä generoitu.',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Kaksi latausnappia vierekkäin (EI dropdown) -->
                    <!-- Note: Already inside hasSecondCard block, but double-check both files exist -->
                    <?php if (!empty($flash['preview_filename']) && $previewUrl2): ?>
                    <div class="sf-download-buttons">
                        <a href="<?= htmlspecialchars($previewUrl) ?>" 
                           download="<?= htmlspecialchars(sf_generate_download_filename($flash, 1)) ?>"
                           class="sf-btn-download">
                            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                                <polyline points="7 10 12 15 17 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                                <line x1="12" y1="15" x2="12" y2="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span><?= htmlspecialchars(sf_term('card_1_label', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></span>
                        </a>
                        <a href="<?= htmlspecialchars($previewUrl2) ?>" 
                           download="<?= htmlspecialchars(sf_generate_download_filename($flash, 2)) ?>"
                           class="sf-btn-download">
                            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                                <polyline points="7 10 12 15 17 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                                <line x1="12" y1="15" x2="12" y2="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span><?= htmlspecialchars(sf_term('card_2_label', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></span>
                        </a>
                    </div>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- NORMAALI: Yksi preview-kuva (red/yellow tai green ilman toista korttia) -->
                    <?php if ($isPreviewPending): ?>
                        <!-- Skeleton placeholder when preview is being generated -->
                        <div class="skeleton-preview-placeholder">
                            <div class="skeleton-preview-box">
                                <div class="skeleton-preview-image skeleton"></div>
                            </div>
                            <div class="sf-preview-pending-message sf-generating-overlay">
                                <div class="sf-generating-content">
                                    <div class="sf-generating-spinner"></div>
                                    <div class="sf-generating-text"><?= htmlspecialchars(sf_term('preview_being_generated', $currentUiLang) ?: 'Esikatselukuvaa luodaan...', ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="sf-preview-progress-wrap">
                                        <div class="sf-preview-progress-bar" style="width: 10%"></div>
                                    </div>
                                    <div class="sf-preview-progress-text">10%</div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <img src="<?= htmlspecialchars($previewUrl) ?>" alt="Preview"
                             class="preview-image" id="viewPreviewImage"
                             loading="eager">
                    <?php endif; ?>

                    <?php if (!empty($flash['preview_filename']) && !$isPreviewPending): ?>
                        <div class="preview-download-wrapper">
                            <a href="<?= htmlspecialchars($previewUrl) ?>"
                               download="<?= htmlspecialchars(sf_generate_download_filename($flash)) ?>"
                               class="btn-download-preview"
                               title="<?= htmlspecialchars(sf_term('download_preview', $currentUiLang) ?? 'Lataa kuva', ENT_QUOTES, 'UTF-8') ?>">
                                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"
                                          stroke="currentColor" stroke-width="2"
                                          stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                                    <polyline points="7 10 12 15 17 10"
                                              stroke="currentColor" stroke-width="2"
                                              stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                                    <line x1="12" y1="15" x2="12" y2="3"
                                          stroke="currentColor" stroke-width="2"
                                          stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span>
                                    <?= htmlspecialchars(sf_term('download_preview', $currentUiLang) ?? 'Lataa JPG', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div> <!-- .preview-box -->

            <!-- KOMMENTIT JA TAPAHTUMALOKI TAB-NÄKYMÄ -->
            <div class="sf-view-activity-section view-box">
                <div class="sf-activity-tabs">
                    <button class="sf-activity-tab active" data-tab="comments">
                        <img src="<?= $base ?>/assets/img/icons/comment.svg" alt="" class="sf-tab-icon">
                        <span><?= htmlspecialchars(sf_term('activity_tab_comments', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="sf-tab-badge" id="commentCount">0</span>
                    </button>
                    <button class="sf-activity-tab" data-tab="events">
                        <img src="<?= $base ?>/assets/img/icons/timeline.svg" alt="" class="sf-tab-icon">
                        <span><?= htmlspecialchars(sf_term('activity_tab_events', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                    <button class="sf-activity-tab" data-tab="all">
                        <img src="<?= $base ?>/assets/img/icons/list.svg" alt="" class="sf-tab-icon">
                        <span><?= htmlspecialchars(sf_term('activity_tab_all', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                    <button class="sf-activity-tab" data-tab="versions">
                        <img src="<?= $base ?>/assets/img/icons/version-document.svg" alt="" class="sf-tab-icon">
                        <span><?= htmlspecialchars(sf_term('activity_tab_versions', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                    <button class="sf-activity-tab" data-tab="images" id="imagesTabBtn">
                        <img src="<?= $base ?>/assets/img/icons/image.svg" alt="" class="sf-tab-icon">
                        <span><?= htmlspecialchars(sf_term('activity_tab_images', $currentUiLang) ?: 'Kuvat', ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                </div>

                <!-- KOMMENTIT TAB -->
                <div class="sf-tab-content active" id="tabComments">
                    <div class="sf-comments-container">
                        <?php
                        // Suodata vain kommentit ja submission kommentit
                        $comments = array_filter($logs, function($log) {
                            $eventType = $log['event_type'] ?? '';
                            return in_array($eventType, ['comment_added', 'submission_comment']);
                        });
                        
                        if (empty($comments)):
                        ?>
                            <div class="sf-empty-state">
                                <img src="<?= $base ?>/assets/img/icons/no-comments.svg" alt="" class="sf-empty-icon">
                                <p><?= htmlspecialchars(sf_term('comments_empty', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        <?php else:
                            // Group comments by parent
                            $topLevelComments = [];
                            $repliesByParent = [];
                            
                            foreach ($comments as $comment) {
                                $parentId = $comment['parent_id'] ?? null;
                                if ($parentId) {
                                    if (!isset($repliesByParent[$parentId])) {
                                        $repliesByParent[$parentId] = [];
                                    }
                                    $repliesByParent[$parentId][] = $comment;
                                } else {
                                    $topLevelComments[] = $comment;
                                }
                            }
                            
                            // Function to render a single comment
                            function renderComment($comment, $repliesByParent, $currentUserId, $isAdmin, $currentUiLang, $base, $isReply = false) {
                                $first = trim((string)($comment['first_name'] ?? ''));
                                $last = trim((string)($comment['last_name'] ?? ''));
                                $fullName = trim($first . ' ' . $last);
                                $avatarInitials = sf_avatar_initials($fullName);
                                
                                $eventType = $comment['event_type'] ?? '';
                                $isSubmissionComment = ($eventType === 'submission_comment');
                                
                                // Parse kommentti description-kentästä
                                $descRaw = $comment['description'] ?? '';
                                $commentText = '';
                                if (preg_match('/log_comment_label:\s*(.+)/i', $descRaw, $match)) {
                                    $commentText = trim($match[1]);
                                } else {
                                    $commentText = $descRaw;
                                }
                                
                                // Relatiivinen aika
                                $timeAgo = sf_time_ago($comment['created_at'], $currentUiLang);
                                
                                $isOwnComment = ($comment['user_id'] ?? 0) == ($currentUserId ?? 0);
                                $replyClass = $isReply ? 'sf-comment-reply' : '';
                                $parentIdAttr = !empty($comment['parent_id']) ? ' data-parent-id="' . (int)$comment['parent_id'] . '"' : '';
                            ?>
                                <div class="sf-comment-item <?= $isOwnComment ? 'sf-comment-own' : '' ?> <?= $replyClass ?>" data-comment-id="<?= (int)$comment['id'] ?>"<?= $parentIdAttr ?>>
                                    <div class="sf-comment-avatar" data-name="<?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($avatarInitials, ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <div class="sf-comment-content">
                                        <div class="sf-comment-header">
                                            <span class="sf-comment-author"><?= htmlspecialchars($fullName ?: 'Unknown', ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if ($isSubmissionComment): ?>
                                                <span class="sf-comment-badge sf-badge-submission" style="background: #3b82f6; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; margin-left: 8px;">
                                                    📤 <?= htmlspecialchars(sf_term('submission_comment_badge', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            <?php endif; ?>
                                            <span class="sf-comment-time" title="<?= htmlspecialchars($comment['created_at'], ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($timeAgo, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </div>
                                        <div class="sf-comment-body">
                                            <?= nl2br(htmlspecialchars($commentText, ENT_QUOTES, 'UTF-8')) ?>
                                        </div>
                                        <?php if (!$isSubmissionComment): ?>
                                        <div class="sf-comment-actions">
                                            <button type="button" class="sf-comment-action-btn btn-reply-comment" data-comment-id="<?= (int)$comment['id'] ?>">
                                                <img src="<?= $base ?>/assets/img/icons/reply.svg" alt="" class="sf-action-icon">
                                                <?= htmlspecialchars(sf_term('comment_reply', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                                            </button>
                                            <?php if ($isOwnComment || $isAdmin): ?>
                                                <button type="button" class="sf-comment-action-btn btn-edit-comment" data-comment-id="<?= (int)$comment['id'] ?>">
                                                    <img src="<?= $base ?>/assets/img/icons/edit.svg" alt="" class="sf-action-icon">
                                                    <?= htmlspecialchars(sf_term('comment_edit', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                                                </button>
                                                <button type="button" class="sf-comment-action-btn btn-delete-comment sf-text-danger" data-comment-id="<?= (int)$comment['id'] ?>">
                                                    <img src="<?= $base ?>/assets/img/icons/delete.svg" alt="" class="sf-action-icon">
                                                    <?= htmlspecialchars(sf_term('comment_delete', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php
                                // Render replies recursively
                                if (isset($repliesByParent[$comment['id']])) {
                                    foreach ($repliesByParent[$comment['id']] as $reply) {
                                        renderComment($reply, $repliesByParent, $currentUserId, $isAdmin, $currentUiLang, $base, true);
                                    }
                                }
                            }
                        ?>
                            <div class="sf-comments-list">
                                <?php
                                // Render all top-level comments with their replies
                                foreach ($topLevelComments as $topComment) {
                                    renderComment($topComment, $repliesByParent, $currentUserId, $isAdmin, $currentUiLang, $base, false);
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- TAPAHTUMAT TAB -->
                <div class="sf-tab-content" id="tabEvents">
                    <div class="sf-events-timeline">
                        <?php
                        // Helper function to translate flash type names
                        function sf_translate_flash_type($typeKey, $lang) {
                            $typeMap = [
                                'red' => 'first_release',
                                'yellow' => 'dangerous_situation',
                                'green' => 'investigation_report',
                            ];
                            
                            $termKey = $typeMap[$typeKey] ?? null;
                            return $termKey ? sf_term($termKey, $lang) : $typeKey;
                        }
                        
                        // Suodata tapahtumat (ei kommentteja)
                        $events = array_filter($logs, function($log) {
                            return ($log['event_type'] ?? '') !== 'comment_added';
                        });
                        
                        if (empty($events)):
                        ?>
                            <div class="sf-empty-state">
                                <img src="<?= $base ?>/assets/img/icons/no-events.svg" alt="" class="sf-empty-icon">
                                <p><?= htmlspecialchars(sf_term('events_empty', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($events as $event):
                                $first = trim((string)($event['first_name'] ?? ''));
                                $last = trim((string)($event['last_name'] ?? ''));
                                $fullName = trim($first . ' ' . $last);
                                
                                $eventType = $event['event_type'] ?? 'UNKNOWN_EVENT';
                                $eventLabel = sf_term($eventType, $currentUiLang) ?: $eventType;
                                
                                // Määritä ikoni event_type:n perusteella
                                $eventIcons = [
                                    'created' => 'create.svg',
                                    'updated' => 'edit.svg',
                                    'state_changed' => 'status-change.svg',
                                    'sent_to_review' => 'send.svg',
                                    'sent_to_comms' => 'send.svg',
                                    'published' => 'publish.svg',
                                    'approved' => 'approve.svg',
                                    'rejected' => 'reject.svg',
                                    'archived' => 'archive.svg',
                                    'deleted' => 'delete.svg',
                                ];
                                $iconFile = $eventIcons[$eventType] ?? 'info.svg';
                                
                                $timeAgo = sf_time_ago($event['created_at'], $currentUiLang);
                                
                                // Parse event description with same logic as All tab
                                $descRaw = $event['description'] ?? '';
                                $descToShow = '';
                                
                                // Käsittele monirivinen kuvaus
                                $lines = explode("\n", $descRaw);
                                
                                foreach ($lines as $line) {
                                    $line = trim($line);
                                    if ($line === '') {
                                        continue;
                                    }
                                    
                                    $translatedLine = $line;
                                    
                                    // 1. OCCURRED_AT -muoto: occurred_at: 2026-01-16 16:33:00 → 2026-01-16T16:33
                                    if (preg_match('/^occurred_at:\s*(.+?)\s*→\s*(.+)$/u', $line, $matches)) {
                                        $beforeLabel = sf_term('occurred_at', $currentUiLang);
                                        $before = trim($matches[1]);
                                        $after = trim($matches[2]);
                                        
                                        // Poista T-kirjain päivämäärästä jos on
                                        $after = str_replace('T', ' ', $after);
                                        
                                        $translatedLine = '<strong>' . $beforeLabel . ':</strong> ' . $before . ' → ' . $after;
                                    }
                                    // 2. STATUS PIPE -muoto: log_status_set|status:published
                                    elseif (preg_match('/^(log_\w+)\|status:(\w+)$/u', $line, $matches)) {
                                        $logKey = $matches[1];
                                        $statusKey = $matches[2];
                                        
                                        $logTranslated = sf_term($logKey, $currentUiLang);
                                        $statusTranslated = sf_status_label($statusKey, $currentUiLang);
                                        
                                        $translatedLine = '<strong>' . $logTranslated . ':</strong> ' . $statusTranslated;
                                    }
                                    // 3. DISTRIBUTION SENT -muoto: log_distribution_sent|countries:fi|details:Suomi: 1 vastaanottajaa
                                    elseif (preg_match('/^log_distribution_sent\|countries:([^|]+)\|details:(.+)$/u', $line, $matches)) {
                                        $logTranslated = sf_term('log_distribution_sent', $currentUiLang);
                                        $details = trim($matches[2]);
                                        
                                        $translatedLine = '<strong>' . $logTranslated . ':</strong> ' . $details;
                                    }
                                    // 4. MULTI-PARAM PIPE -muoto: log_something|param1:value1|param2:value2
                                    elseif (preg_match('/^(log_\w+)\|(.+)$/u', $line, $matches)) {
                                        $logKey = $matches[1];
                                        $paramsStr = $matches[2];
                                        
                                        $logTranslated = sf_term($logKey, $currentUiLang);
                                        
                                        // Parse parameters
                                        $params = [];
                                        $paramParts = explode('|', $paramsStr);
                                        
                                        foreach ($paramParts as $part) {
                                            if (strpos($part, ':') !== false) {
                                                [$key, $val] = array_map('trim', explode(':', $part, 2));
                                                $params[$key] = $val;
                                            }
                                        }
                                        
                                        // Jos on details, näytä vain se
                                        if (isset($params['details'])) {
                                            $translatedLine = '<strong>' . $logTranslated . ':</strong> ' . $params['details'];
                                        } else {
                                            $translatedLine = '<strong>' . $logTranslated . '</strong>';
                                        }
                                    }
                                    // 5. LABEL -muoto: log_comment_label: kommenttiteksti
                                    elseif (preg_match('/^(log_\w+_label):\s*(.+)$/u', $line, $matches)) {
                                        $labelKey = $matches[1];
                                        $content = trim($matches[2]);
                                        
                                        $labelTranslated = sf_term($labelKey, $currentUiLang);
                                        
                                        $translatedLine = '<strong>' . $labelTranslated . ':</strong> ' . $content;
                                    }
                                    // 6. FIELD CHANGE -muoto: title_short: "old" → "new" (with quotes)
                                    elseif (preg_match('/^([a-z_]+):\s*"([^"]+)"\s*→\s*"([^"]+)"$/u', $line, $matches)) {
                                        $fieldKey = $matches[1];
                                        $oldValue = trim($matches[2]);
                                        $newValue = trim($matches[3]);
                                        
                                        $fieldTranslated = sf_term($fieldKey, $currentUiLang);
                                        
                                        $translatedLine = '<strong>' . $fieldTranslated . ':</strong> ' . $oldValue . ' → ' . $newValue;
                                    }
                                    // 6b. TYPE CHANGE - special handling: type: red → yellow
                                    elseif (preg_match('/^type:\s*(\w+)\s*→\s*(\w+)$/u', $line, $matches)) {
                                        $oldType = trim($matches[1]);
                                        $newType = trim($matches[2]);
                                        
                                        $oldTranslated = sf_translate_flash_type($oldType, $currentUiLang);
                                        $newTranslated = sf_translate_flash_type($newType, $currentUiLang);
                                        
                                        $fieldTranslated = sf_term('type', $currentUiLang);
                                        
                                        $translatedLine = '<strong>' . $fieldTranslated . ':</strong> ' . $oldTranslated . ' → ' . $newTranslated;
                                    }
                                    // 7. FIELD CHANGE without quotes: title_short: old → new
                                    elseif (preg_match('/^([a-z_]+):\s*([^→]+)\s*→\s*(.+)$/u', $line, $matches)) {
                                        $fieldKey = $matches[1];
                                        $oldValue = trim($matches[2]);
                                        $newValue = trim($matches[3]);
                                        
                                        // If values look like status keys, translate them
                                        if (preg_match('/^[a-z_]+$/', $oldValue)) {
                                            $oldTranslated = sf_status_label($oldValue, $currentUiLang);
                                            if ($oldTranslated && $oldTranslated !== $oldValue) {
                                                $oldValue = $oldTranslated;
                                            }
                                        }
                                        
                                        if (preg_match('/^[a-z_]+$/', $newValue)) {
                                            $newTranslated = sf_status_label($newValue, $currentUiLang);
                                            if ($newTranslated && $newTranslated !== $newValue) {
                                                $newValue = $newTranslated;
                                            }
                                        }
                                        
                                        $fieldTranslated = sf_term($fieldKey, $currentUiLang);
                                        
                                        $translatedLine = '<strong>' . $fieldTranslated . ':</strong> ' . $oldValue . ' → ' . $newValue;
                                    }
                                    // 8. SIMPLE KEY:VALUE -muoto: log_something: value
                                    elseif (preg_match('/^(log_\w+|[a-z_]+):\s*(.+)$/u', $line, $matches)) {
                                        $key = $matches[1];
                                        $value = trim($matches[2]);
                                        
                                        $keyTranslated = sf_term($key, $currentUiLang);
                                        
                                        // Jos avain on tuntematon, älä käännä
                                        if ($keyTranslated === $key && strpos($key, 'log_') !== 0) {
                                            $translatedLine = $line; // Pidä alkuperäinen
                                        } else {
                                            $translatedLine = '<strong>' . $keyTranslated . ':</strong> ' . $value;
                                        }
                                    }
                                    // 9. Yritä kääntää koko rivi
                                    else {
                                        $translated = sf_term($line, $currentUiLang);
                                        if ($translated !== $line) {
                                            $translatedLine = $translated;
                                        }
                                    }
                                    
                                    if ($descToShow !== '') {
                                        $descToShow .= "\n";
                                    }
                                    $descToShow .= $translatedLine;
                                }
                                
                                // Process and sanitize
                                $descProcessed = function_exists('sf_log_status_replace')
                                    ? sf_log_status_replace($descToShow, $currentUiLang)
                                    : $descToShow;
                                
                                $descAllowed = strip_tags($descProcessed, '<span><strong>');
                            ?>
                                <div class="sf-event-item" data-event-type="<?= htmlspecialchars($eventType, ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="sf-event-icon">
                                        <img src="<?= $base ?>/assets/img/icons/<?= htmlspecialchars($iconFile, ENT_QUOTES, 'UTF-8') ?>" alt="">
                                    </div>
                                    <div class="sf-event-content">
                                        <div class="sf-event-header">
                                            <span class="sf-event-label"><?= htmlspecialchars($eventLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="sf-event-time"><?= htmlspecialchars($timeAgo, ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <?php if (!empty($fullName)): ?>
                                            <div class="sf-event-user">
                                                <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($descToShow)): ?>
                                            <div class="sf-event-description">
                                                <?= nl2br($descAllowed) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- KAIKKI TAB -->
                <div class="sf-tab-content" id="tabAll">
                    <div class="sf-all-activity">
                        <?php if (empty($logs)): ?>
                            <div class="sf-empty-state">
                                <img src="<?= $base ?>/assets/img/icons/no-activity.svg" alt="" class="sf-empty-icon">
                                <p><?= htmlspecialchars(sf_term('activity_empty', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        <?php else: ?>
                            <ul class="sf-activity-list">
                                <?php foreach ($logs as $logRow):
                            $first = trim((string)($logRow['first_name'] ?? ''));
                            $last  = trim((string)($logRow['last_name'] ?? ''));
                            $fullName = trim($first . ' ' . $last);
                            $avatarTxt = sf_avatar_initials($fullName);

                            $eventKey   = $logRow['event_type'] ?? 'UNKNOWN_EVENT';
                            $eventLabel = sf_term($eventKey, $currentUiLang);

                            $descRaw = $logRow['description'] ?? '';

// Käännä lokikuvaus - PARANNETTU VERSIO
$descToShow = '';

// Käsittele monirivinen kuvaus
$lines = explode("\n", $descRaw);

foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '') {
        continue;
    }
    
    $translatedLine = $line;
    
    // 1. OCCURRED_AT -muoto: occurred_at: 2026-01-16 16:33:00 → 2026-01-16T16:33
    if (preg_match('/^occurred_at:\s*(.+?)\s*→\s*(.+)$/u', $line, $matches)) {
        $beforeLabel = sf_term('occurred_at', $currentUiLang);
        $before = trim($matches[1]);
        $after = trim($matches[2]);
        
        // Poista T-kirjain päivämäärästä jos on
        $after = str_replace('T', ' ', $after);
        
        $translatedLine = '<strong>' . $beforeLabel . ':</strong> ' . $before . ' → ' . $after;
    }
    // 2. STATUS PIPE -muoto: log_status_set|status:published
    elseif (preg_match('/^(log_\w+)\|status:(\w+)$/u', $line, $matches)) {
        $logKey = $matches[1];
        $statusKey = $matches[2];
        
        $logTranslated = sf_term($logKey, $currentUiLang);
        $statusTranslated = sf_status_label($statusKey, $currentUiLang);
        
        $translatedLine = '<strong>' . $logTranslated . ':</strong> ' . $statusTranslated;
    }
    // 3. DISTRIBUTION SENT -muoto: log_distribution_sent|countries:fi|details:Suomi: 1 vastaanottajaa
    elseif (preg_match('/^log_distribution_sent\|countries:([^|]+)\|details:(.+)$/u', $line, $matches)) {
        $logTranslated = sf_term('log_distribution_sent', $currentUiLang);
        $details = trim($matches[2]);
        
        $translatedLine = '<strong>' . $logTranslated . ':</strong> ' . $details;
    }
    // 4. MULTI-PARAM PIPE -muoto: log_something|param1:value1|param2:value2
    elseif (preg_match('/^(log_\w+)\|(.+)$/u', $line, $matches)) {
        $logKey = $matches[1];
        $paramsStr = $matches[2];
        
        $logTranslated = sf_term($logKey, $currentUiLang);
        
        // Parse parameters
        $params = [];
        $paramParts = explode('|', $paramsStr);
        
        foreach ($paramParts as $part) {
            if (strpos($part, ':') !== false) {
                [$key, $val] = array_map('trim', explode(':', $part, 2));
                $params[$key] = $val;
            }
        }
        
        // Jos on details, näytä vain se
        if (isset($params['details'])) {
            $translatedLine = '<strong>' . $logTranslated . ':</strong> ' . $params['details'];
        } else {
            $translatedLine = '<strong>' . $logTranslated . '</strong>';
        }
    }
    // 5. LABEL -muoto: log_comment_label: kommenttiteksti
    elseif (preg_match('/^(log_\w+_label):\s*(.+)$/u', $line, $matches)) {
        $labelKey = $matches[1];
        $content = trim($matches[2]);
        
        $labelTranslated = sf_term($labelKey, $currentUiLang);
        
        $translatedLine = '<strong>' . $labelTranslated . ':</strong> ' . $content;
    }
    // 6. FIELD CHANGE -muoto: title_short: "old" → "new" (with quotes)
    elseif (preg_match('/^([a-z_]+):\s*"([^"]+)"\s*→\s*"([^"]+)"$/u', $line, $matches)) {
        $fieldKey = $matches[1];
        $oldValue = trim($matches[2]);
        $newValue = trim($matches[3]);
        
        $fieldTranslated = sf_term($fieldKey, $currentUiLang);
        
        $translatedLine = '<strong>' . $fieldTranslated . ':</strong> ' . $oldValue . ' → ' . $newValue;
    }
    // 6b. TYPE CHANGE - special handling: type: red → yellow
    elseif (preg_match('/^type:\s*(\w+)\s*→\s*(\w+)$/u', $line, $matches)) {
        $oldType = trim($matches[1]);
        $newType = trim($matches[2]);
        
        $oldTranslated = sf_translate_flash_type($oldType, $currentUiLang);
        $newTranslated = sf_translate_flash_type($newType, $currentUiLang);
        
        $fieldTranslated = sf_term('type', $currentUiLang);
        
        $translatedLine = '<strong>' . $fieldTranslated . ':</strong> ' . $oldTranslated . ' → ' . $newTranslated;
    }
    // 7. FIELD CHANGE without quotes: title_short: old → new
    elseif (preg_match('/^([a-z_]+):\s*([^→]+)\s*→\s*(.+)$/u', $line, $matches)) {
        $fieldKey = $matches[1];
        $oldValue = trim($matches[2]);
        $newValue = trim($matches[3]);
        
        // If values look like status keys, translate them
        if (preg_match('/^[a-z_]+$/', $oldValue)) {
            $oldTranslated = sf_status_label($oldValue, $currentUiLang);
            if ($oldTranslated && $oldTranslated !== $oldValue) {
                $oldValue = $oldTranslated;
            }
        }
        
        if (preg_match('/^[a-z_]+$/', $newValue)) {
            $newTranslated = sf_status_label($newValue, $currentUiLang);
            if ($newTranslated && $newTranslated !== $newValue) {
                $newValue = $newTranslated;
            }
        }
        
        $fieldTranslated = sf_term($fieldKey, $currentUiLang);
        
        $translatedLine = '<strong>' . $fieldTranslated . ':</strong> ' . $oldValue . ' → ' . $newValue;
    }
    // 8. SIMPLE KEY:VALUE -muoto: log_something: value
    elseif (preg_match('/^(log_\w+|[a-z_]+):\s*(.+)$/u', $line, $matches)) {
        $key = $matches[1];
        $value = trim($matches[2]);
        
        $keyTranslated = sf_term($key, $currentUiLang);
        
        // Jos avain on tuntematon, älä käännä
        if ($keyTranslated === $key && strpos($key, 'log_') !== 0) {
            $translatedLine = $line; // Pidä alkuperäinen
        } else {
            $translatedLine = '<strong>' . $keyTranslated . ':</strong> ' . $value;
        }
    }
    // 9. Yritä kääntää koko rivi
    else {
        $translated = sf_term($line, $currentUiLang);
        if ($translated !== $line) {
            $translatedLine = $translated;
        }
    }
    
    if ($descToShow !== '') {
        $descToShow .= "\n";
    }
    $descToShow .= $translatedLine;
}

// Process status replacements (if function exists)
$descProcessed = function_exists('sf_log_status_replace')
    ? sf_log_status_replace($descToShow, $currentUiLang)
    : $descToShow;

// Sanitize: allow only <strong> and <span> tags
$descAllowed = strip_tags($descProcessed, '<strong><span>');

                            // Korostetaan eri viestityypit (kommentti, viesti viestinnälle, palautus)
                            $messageLabels = [
                                'comment' => [
                                    'fi' => 'Kommentti:',
                                    'sv' => 'Kommentar:',
                                    'en' => 'Comment:',
                                    'it' => 'Commento:',
                                    'el' => 'Σχόλιο:',
                                ],
                                'comms' => [
                                    'fi' => 'Viesti viestinnälle:',
                                    'sv' => 'Meddelande till kommunikation:',
                                    'en' => 'Message to communications:',
                                    'it' => 'Messaggio alla comunicazione:',
                                    'el' => 'Μήνυμα προς επικοινωνία:',
                                ],
                                'return' => [
                                    'fi' => 'Palautteen syy:',
                                    'sv' => 'Anledning till återkoppling:',
                                    'en' => 'Reason for return:',
                                    'it' => 'Motivo del reso:',
                                    'el' => 'Λόγος επιστροφής:',
                                ],
                            ];

                            $descHighlighted = $descAllowed;

                            // Käy läpi kaikki viestityypit ja korvaa ne tyylitellyillä laatikoilla
                            foreach ($messageLabels as $msgType => $labels) {
                                $label = $labels[$currentUiLang] ?? $labels['en'];
                                $cssClass = 'sf-log-' . $msgType; // sf-log-comment, sf-log-comms, sf-log-return

                                $pattern = '/(^|\R)\s*' . preg_quote($label, '/') . '\s*(.+)$/u';
                                $descHighlighted = preg_replace(
                                    $pattern,
                                    '$1<div class="sf-log-message-box ' . $cssClass . '"><span class="sf-log-message-label">' . htmlspecialchars($label) . '</span> $2</div>',
                                    $descHighlighted
                                );
                            }
                            $plainDescLen = mb_strlen(strip_tags($descAllowed));
                            $needsMore    = $plainDescLen > 300;
                        ?>
                            <li class="sf-log-item" id="log-<?= (int)$logRow['id'] ?>">
                                <?php if ($isAdmin): ?>
                                    <button type="button" 
                                            class="sf-log-delete-btn" 
                                            data-log-id="<?= (int)$logRow['id'] ?>"
                                            title="<?= htmlspecialchars(sf_term('log_delete', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>">
                                        <img src="<?= $base ?>/assets/img/icons/delete_icon.svg" 
                                             alt="<?= htmlspecialchars(sf_term('log_delete_alt', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>" 
                                             class="sf-log-delete-icon">
                                    </button>
                                <?php endif; ?>
                                <div class="sf-log-avatar" data-name="<?= htmlspecialchars($fullName) ?>">
                                    <?= htmlspecialchars($avatarTxt) ?>
                                </div>

                                <div>
                                    <div class="sf-log-header-row" role="group" aria-label="<?= htmlspecialchars($eventLabel) ?>">
                                        <span class="sf-log-type"><?= htmlspecialchars($eventLabel) ?></span>
                                        <span class="sf-log-time"><?= htmlspecialchars($logRow['created_at'] ?? '') ?></span>
                                    </div>

                                    <div class="sf-log-message<?= $needsMore ? '' : ' expanded' ?>">
                                        <?= nl2br($descHighlighted) ?>
                                    </div>

                                    <?php if ($needsMore): ?>
                                        <div
                                          class="sf-log-more"
                                          role="button"
                                          tabindex="0"
                                          aria-expanded="false"
                                        >
                                          <?= htmlspecialchars(sf_term('log_show_more', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="sf-log-meta" aria-hidden="false">
                                    <div class="sf-log-user">
                                        <?= $fullName !== '' ? htmlspecialchars($fullName) : htmlspecialchars(sf_term('log_system_user', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                    </div>
                </div>

                <!-- VERSIOT TAB -->
                <div class="sf-tab-content" id="tabVersions">
                    <div class="sf-versions-container">
                        <?php
                        // Fetch published versions
                        $stmtVersions = $pdo->prepare("
                            SELECT s.*, u.first_name, u.last_name 
                            FROM sf_flash_snapshots s
                            LEFT JOIN sf_users u ON s.published_by = u.id
                            WHERE s.flash_id = ?
                            ORDER BY s.published_at DESC
                        ");
                        $stmtVersions->execute([$logFlashId]);
                        $snapshots = $stmtVersions->fetchAll();
                        $totalVersions = count($snapshots);
                        
                        ?>
                        
                        
                        <?php if (empty($snapshots)): ?>
                            <div class="sf-empty-state">
                                <img src="<?= $base ?>/assets/img/icons/version.svg" alt="" class="sf-empty-icon">
                                <p><?= htmlspecialchars(sf_term('version_no_versions', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        <?php else: ?>
                            <div class="sf-version-list">
                                <?php 
                                // Define version type mappings once before loop
                                $versionTypeIcons = [
                                    'ensitiedote' => 'icon-red.png',
                                    'vaaratilanne' => 'icon-yellow.png',
                                    'tutkintatiedote' => 'icon-green.png',
                                    'paivitys' => 'icon-yellow.png',
                                ];
                                $versionTypeColors = [
                                    'ensitiedote' => 'red',
                                    'vaaratilanne' => 'yellow',
                                    'tutkintatiedote' => 'green',
                                    'paivitys' => 'yellow',
                                ];
                                
                                foreach ($snapshots as $index => $snapshot): 
                                    $versionTypeLabel = sf_term('version_' . $snapshot['version_type'], $currentUiLang) ?? $snapshot['version_type'];
                                    $publisherName = trim(($snapshot['first_name'] ?? '') . ' ' . ($snapshot['last_name'] ?? ''));
                                    if ($publisherName === '') {
                                        $publisherName = sf_term('log_system_user', $currentUiLang) ?? 'System';
                                    }
                                    $publishedDate = date('d.m.Y', strtotime($snapshot['published_at']));
                                    $publishedTime = date('H:i', strtotime($snapshot['published_at']));
                                    $isLatest = ($index === 0);
                                    $versionNum = $totalVersions - $index;
                                    
                                    // Map snapshot's version_type to icon and color
                                    $snapshotVersionType = $snapshot['version_type'] ?? 'vaaratilanne';
                                    $versionIcon = $versionTypeIcons[$snapshotVersionType] ?? 'icon-yellow.png';
                                    $versionColorClass = $versionTypeColors[$snapshotVersionType] ?? 'yellow';
                                    
                                    // Language display
                                    $snapshotLang = strtoupper($snapshot['lang'] ?? 'FI');
                                    $langFlags = [
                                        'FI' => '🇫🇮',
                                        'SV' => '🇸🇪',
                                        'EN' => '🇬🇧',
                                        'IT' => '🇮🇹',
                                        'EL' => '🇬🇷',
                                    ];
                                    $langFlag = $langFlags[$snapshotLang] ?? '';
                                ?>
                                    <div class="sf-version-card sf-version-type-<?= htmlspecialchars($versionColorClass, ENT_QUOTES, 'UTF-8') ?><?= $isLatest ? ' sf-version-latest' : '' ?>">
                                        <?php if ($isLatest): ?>
                                            <span class="sf-version-badge"><?= htmlspecialchars(sf_term('version_current', $currentUiLang) ?? 'Current', ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                        
                                        <div class="sf-version-header">
                                            <div class="sf-version-icon-wrapper">
                                                <!-- USE TYPE-SPECIFIC ICON -->
                                                <img src="<?= $base ?>/assets/img/<?= htmlspecialchars($versionIcon, ENT_QUOTES, 'UTF-8') ?>" alt="" class="sf-version-icon">
                                            </div>
                                            <div class="sf-version-title-block">
                                                <h4 class="sf-version-type-label">
                                                    <?= htmlspecialchars($versionTypeLabel, ENT_QUOTES, 'UTF-8') ?>
                                                    <span class="sf-version-lang-badge"><?= $langFlag ?> <?= $snapshotLang ?></span>
                                                </h4>
                                                <span class="sf-version-number">
                                                    <?= htmlspecialchars(sf_term('version_number', $currentUiLang) ?? 'Version', ENT_QUOTES, 'UTF-8') ?> <?= $versionNum ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="sf-version-meta-row">
                                            <div class="sf-version-meta-item">
                                                <img src="<?= $base ?>/assets/img/icons/timeline.svg" alt="" class="sf-version-meta-icon">
                                                <span class="sf-version-date"><?= htmlspecialchars($publishedDate, ENT_QUOTES, 'UTF-8') ?></span>
                                                <span class="sf-version-time"><?= htmlspecialchars($publishedTime, ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                            <div class="sf-version-meta-item">
                                                <img src="<?= $base ?>/assets/img/icons/user.svg" alt="" class="sf-version-meta-icon">
                                                <span class="sf-version-publisher"><?= htmlspecialchars($publisherName, ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                        </div>
                                        
                                        <button class="sf-version-view-btn" 
                                                onclick="openVersionModal('<?= htmlspecialchars($base . $snapshot['image_path'], ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($versionTypeLabel, ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($snapshot['published_at'], ENT_QUOTES, 'UTF-8') ?>')">
                                            <img src="<?= $base ?>/assets/img/icons/eye_icon.svg" alt="" class="sf-btn-icon">
                                            <?= htmlspecialchars(sf_term('version_view', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- IMAGES TAB -->
                <div class="sf-tab-content" id="tabImages">
                    <div class="images-tab-content">
                        <div class="images-loading" id="imagesLoading">
                            <div class="images-spinner"></div>
                            <p><?= htmlspecialchars(sf_term('images_loading', $currentUiLang) ?: 'Ladataan kuvia...', ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <div id="imagesUploadContainer" style="display: none; margin-bottom: 1rem;">
                            <button type="button" id="imagesUploadBtn" class="sf-btn sf-btn-primary">
                                <?= htmlspecialchars(sf_term('add_images_btn', $currentUiLang) ?: 'Lisää kuvia', ENT_QUOTES, 'UTF-8') ?>
                            </button>
                        </div>
                        <div class="images-grid" id="imagesGrid" style="display: none;">
                            <!-- Images will be loaded here by JavaScript -->
                        </div>
                        <div class="no-images-message" id="noImagesMessage" style="display: none;">
                            <p><?= htmlspecialchars(sf_term('no_images_message', $currentUiLang) ?: 'Ei kuvia.', ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </div>
                </div>

            </div>
        </div>

<!-- Oikea palsta -->
        <div class="view-right">

            <?php
            // Hae kaikki kieliversiot oikean palstan tilakorttia varten
            $stmtAllLangVers = $pdo->prepare("
                SELECT id, lang, state, title, published_at FROM sf_flashes
                WHERE id = :gid OR translation_group_id = :gid2
                ORDER BY FIELD(lang, 'fi', 'sv', 'en', 'it', 'el')
            ");
            $stmtAllLangVers->execute([':gid' => $translationGroupId, ':gid2' => $translationGroupId]);
            $allLangVersions = $stmtAllLangVers->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <?php /* Kieliversiot-osio poistettu sidebarista — tuplatieto, näkyy jo yläosan välilehtinä */ ?>

            <?php
            // Tarkista onko tämä julkaisematon kieliversio jonka ryhmässä on jo julkaistuja
            $isUnpublishedTranslation = false;
            if ($flash['state'] === 'draft' && !empty($flash['translation_group_id'])) {
                $stmtGroupPublished = $pdo->prepare("
                    SELECT COUNT(*) FROM sf_flashes
                    WHERE (id = ? OR translation_group_id = ?)
                      AND state = 'published' AND id != ?
                ");
                $gidCheck = (int)$flash['translation_group_id'];
                $stmtGroupPublished->execute([$gidCheck, $gidCheck, $flash['id']]);
                $isUnpublishedTranslation = (int)$stmtGroupPublished->fetchColumn() > 0;
            }
            ?>

            <?php if ($isUnpublishedTranslation && ($isAdmin || $isSafety || $isComms)): ?>
            <div class="sf-card sf-publish-single-card">
                <h4>
                    <?= sf_lang_flag($flash['lang']) ?>
                    <?= htmlspecialchars(sf_term('publish_language_version', $currentUiLang) ?? 'Julkaise kieliversio', ENT_QUOTES, 'UTF-8') ?>
                </h4>
                <p class="sf-help-text">
                    <?= htmlspecialchars(sf_term('publish_single_description', $currentUiLang) ?? 'Tämä kieliversio on luonnos. Julkaise se erikseen omille infonäytöilleen.', ENT_QUOTES, 'UTF-8') ?>
                </p>
                <button type="button"
                        class="sf-btn sf-btn-primary"
                        id="btnPublishSingleLang"
                        data-flash-id="<?= (int)$flash['id'] ?>"
                        data-flash-lang="<?= htmlspecialchars($flash['lang'], ENT_QUOTES, 'UTF-8') ?>"
                        onclick="openPublishSingleModal()">
                    <?= htmlspecialchars(sf_term('btn_publish_language_version', $currentUiLang) ?? 'Julkaise tämä kieliversio →', ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
            <?php endif; ?>

            <?php if (file_exists(__DIR__ . '/../partials/view_playlist_status.php')): ?>
                <?php require __DIR__ . '/../partials/view_playlist_status.php'; ?>
            <?php endif; ?>

            <div class="view-meta-include">
                <?php require __DIR__ . '/../partials/view_meta_box.php'; ?>
            </div>

            <?php /* view_display_targets.php removed: info is already accessible via the footer "Infonäytöt" button modal */ ?>
            <?php /* if (file_exists(__DIR__ . '/../partials/view_display_targets.php')): ?>
                <?php require __DIR__ . '/../partials/view_display_targets.php'; ?>
            <?php endif; */ ?>
        </div>
    </div> <!-- .view-layout -->

</div> <!-- .view-container -->
</div> <!-- .sf-page-container -->

<!-- ===== MODALIT ===== -->
<div class="sf-modal hidden" id="modalEdit" role="dialog" aria-modal="true" aria-labelledby="modalEditTitle">
    <div class="sf-modal-content">
        <h2 id="modalEditTitle">
            <?= htmlspecialchars(sf_term('modal_edit_title', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
        </h2>
        <p>
            <?= htmlspecialchars(sf_term('modal_edit_text', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
        </p>
        <div class="sf-modal-actions">
            <button
              type="button"
              class="sf-btn sf-btn-secondary"
              data-modal-close="modalEdit"
            >
              <?= htmlspecialchars(sf_term('btn_cancel', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
            </button>
            <button
              type="button"
              class="sf-btn sf-btn-primary"
              id="modalEditOk"
            >
              <?= htmlspecialchars(sf_term('btn_ok_edit', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    </div>
</div>

<div class="sf-modal hidden" id="modalComment" role="dialog" aria-modal="true" aria-labelledby="modalCommentTitle">
    <div class="sf-modal-content">
        <h2 id="modalCommentTitle">
            <?= htmlspecialchars(sf_term('modal_comment_title', $currentUiLang) ?? 'Lisää kommentti', ENT_QUOTES, 'UTF-8') ?>
        </h2>
        <form method="post" action="<?= htmlspecialchars($base) ?>/app/actions/comment.php?id=<?= (int)$id ?>" id="commentForm">
            <?= sf_csrf_field() ?>
            <input type="hidden" id="editCommentId" name="comment_id" value="">
            <label for="commentMessage">
                <?= htmlspecialchars(sf_term('modal_comment_label', $currentUiLang) ?? 'Kommentti', ENT_QUOTES, 'UTF-8') ?>
            </label>
            <textarea
              id="commentMessage"
              name="message"
              rows="4"
              maxlength="2000"
              placeholder="<?= htmlspecialchars(sf_term('modal_comment_placeholder', $currentUiLang) ?? '', ENT_QUOTES, 'UTF-8') ?>"
            ></textarea>
            <div class="sf-modal-actions">
                <button
                  type="button"
                  class="sf-btn sf-btn-secondary"
                  data-modal-close="modalComment"
                >
                  <?= htmlspecialchars(sf_term('btn_cancel', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button type="submit" class="sf-btn sf-btn-primary">
                  <?= htmlspecialchars(sf_term('btn_comment_send', $currentUiLang) ?? 'Tallenna kommentti', ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="sf-modal hidden" id="modalRequestInfo" role="dialog" aria-modal="true" aria-labelledby="modalRequestInfoTitle">
    <div class="sf-modal-content">
        <h2 id="modalRequestInfoTitle">
            <?= htmlspecialchars(sf_term('modal_request_title', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
        </h2>
        <form method="post" action="<?= htmlspecialchars($base) ?>/app/actions/request_info.php?id=<?= (int)$id ?>">
            <?= sf_csrf_field() ?>
            <label for="reqMessage">
                <?= htmlspecialchars(sf_term('modal_request_label', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
            </label>
            <textarea
              id="reqMessage"
              name="message"
              rows="4"
              placeholder="<?= htmlspecialchars(sf_term('modal_request_placeholder', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>"
            ></textarea>
            <div class="sf-modal-actions">
                <button
                  type="button"
                  class="sf-btn sf-btn-secondary"
                  data-modal-close="modalRequestInfo"
                >
                  <?= htmlspecialchars(sf_term('btn_cancel', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button type="submit" class="sf-btn sf-btn-primary">
                  <?= htmlspecialchars(sf_term('btn_send_request', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Send to Communications (Multi-step) -->
<div class="sf-modal hidden" id="modalToComms" role="dialog" aria-modal="true" aria-labelledby="modalToCommsTitle">
    <div class="sf-modal-content sf-modal-comms">
        
        <!-- STEP 1: Language Versions -->
        <div class="sf-comms-step" id="commsStep1">
            <h2 id="modalToCommsTitle">
                <?= htmlspecialchars(sf_term('modal_to_comms_title', $currentUiLang) ?? 'Lähetä viestintään', ENT_QUOTES, 'UTF-8') ?>
            </h2>
            
            <div class="sf-step-indicator">
                <span class="sf-step active">1</span>
                <span class="sf-step-line"></span>
                <span class="sf-step">2</span>
                <span class="sf-step-line"></span>
                <span class="sf-step">3</span>
                <span class="sf-step-line"></span>
                <span class="sf-step">4</span>
            </div>

            <form id="commsForm">
                <?= sf_csrf_field() ?>
                
                <div class="sf-field">
                    <label class="sf-label">
                        <?= htmlspecialchars(sf_term('comms_step1_languages', $currentUiLang) ?? 'Valitse kieliversiot', ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <p class="sf-help-text">
                        <?= htmlspecialchars(sf_term('comms_step1_languages_help', $currentUiLang) ?? 'Valitse mitkä kieliversiot lähetetään viestintään', ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    
                    <div class="sf-language-chips">
                        <?php foreach ($supportedLangs as $langCode => $langData): 
                            $isDefault = ($langCode === 'fi');
                        ?>
                            <label class="sf-chip-toggle <?= $isDefault ? 'selected' : '' ?>">
                                <input type="checkbox" 
                                       name="languages[]" 
                                       value="<?= htmlspecialchars($langCode) ?>"
                                       <?= $isDefault ? 'checked' : '' ?>>
                                <img src="<?= htmlspecialchars($base) ?>/assets/img/<?= htmlspecialchars($langData['icon']) ?>" 
                                     alt="<?= htmlspecialchars($langData['label']) ?>"
                                     class="lang-flag-img"
                                     style="width: 26px; height: 26px; border-radius: 50%; object-fit: cover;">
                                <span><?= htmlspecialchars($langData['label']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>

            <div class="sf-modal-actions">
                <button type="button" class="sf-btn sf-btn-secondary" data-modal-close="modalToComms">
                    <?= htmlspecialchars(sf_term('btn_cancel', $currentUiLang) ?? 'Peruuta', ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button type="button" class="sf-btn sf-btn-primary" id="btnCommsStep1Next">
                    <?= htmlspecialchars(sf_term('btn_next', $currentUiLang) ?? 'Seuraava', ENT_QUOTES, 'UTF-8') ?> →
                </button>
            </div>
        </div>

        <!-- STEP 2: Xibo Screens - COMPACT VERSION -->
        <div class="sf-comms-step hidden" id="commsStep2">
            <div class="sf-comms-header-row">
                <h2><?= htmlspecialchars(sf_term('modal_to_comms_title', $currentUiLang) ?? 'Lähetä viestintään', ENT_QUOTES, 'UTF-8') ?></h2>
                <div class="sf-step-indicator sf-step-indicator-inline">
                    <span class="sf-step done">✓</span>
                    <span class="sf-step-line done"></span>
                    <span class="sf-step active">2</span>
                    <span class="sf-step-line"></span>
                    <span class="sf-step">3</span>
                    <span class="sf-step-line"></span>
                    <span class="sf-step">4</span>
                </div>
            </div>

            <div class="sf-field">
                <label class="sf-label"><?= htmlspecialchars(sf_term('comms_step2_screens', $currentUiLang) ?? 'Xibo-näytöt', ENT_QUOTES, 'UTF-8') ?></label>
                <p class="sf-help-text"><?= htmlspecialchars(sf_term('comms_step2_screens_help', $currentUiLang) ?? 'Valitse työmaanäytöt jossa SafetyFlash esitetään', ENT_QUOTES, 'UTF-8') ?></p>
                
                <!-- Inline radio options -->
                <div class="sf-radio-options-inline">
                    <label class="sf-radio-pill">
                        <input type="radio" name="screens_option" value="all" id="screensAll" checked>
                        <span>Kaikki näytöt</span>
                    </label>
                    <label class="sf-radio-pill">
                        <input type="radio" name="screens_option" value="selected" id="screensSelected">
                        <span>Valitse</span>
                    </label>
                </div>
                
                <!-- Compact selection panel -->
                <div id="commsScreensSelection" class="sf-compact-selection hidden">
                    <!-- Header with clear button -->
                    <div class="sf-selection-header">
                        <span class="sf-selection-title"><?= htmlspecialchars(sf_term('comms_select_targets', $currentUiLang) ?? 'Valitse kohteet', ENT_QUOTES, 'UTF-8') ?></span>
                        <button type="button" id="btnClearSelections" class="sf-btn-text-small">
                            <?= htmlspecialchars(sf_term('btn_clear_selections', $currentUiLang) ?? 'Tyhjennä valinnat', ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </div>
                    
                    <!-- Country chips in one row -->
                    <div class="sf-country-row">
                        <?php
                        // Fetch worksites from sf_worksites grouped by country
                        try {
                            $countryStmt = Database::getInstance()->prepare("
                                SELECT country, COUNT(*) as count 
                                FROM sf_worksites 
                                WHERE is_active = 1 
                                GROUP BY country
                            ");
                            $countryStmt->execute();
                            $countryCounts = $countryStmt->fetchAll(PDO::FETCH_KEY_PAIR);
                        } catch (Exception $e) {
                            $countryCounts = ['fi' => 0];
                        }
                        
                        $countries = [
                            'fi' => ['flag' => '🇫🇮', 'name' => 'Suomi'],
                            'it' => ['flag' => '🇮🇹', 'name' => 'Italia'],
                            'el' => ['flag' => '🇬🇷', 'name' => 'Kreikka'],
                        ];
                        
                        foreach ($countries as $code => $data):
                            $count = $countryCounts[$code] ?? 0;
                            if ($count === 0) continue;
                        ?>
                            <label class="sf-country-chip-compact" data-country="<?= $code ?>">
                                <input type="checkbox" name="countries[]" value="<?= $code ?>">
                                <span class="sf-cc-flag"><?= $data['flag'] ?></span>
                                <span class="sf-cc-name"><?= $data['name'] ?></span>
                                <span class="sf-cc-count">(<?= $count ?>)</span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Search row -->
                    <div class="sf-search-row">
                        <input type="text" 
                               id="worksiteSearchInput" 
                               class="sf-search-compact" 
                               placeholder="🔍 Hae työmaata...">
                    </div>
                    
                    <!-- Search results (HIDDEN by default) -->
                    <div id="worksiteSearchResults" class="sf-search-results hidden">
                        <?php
                        try {
                            $wsStmt = Database::getInstance()->prepare("
                                SELECT id, name, country 
                                FROM sf_worksites 
                                WHERE is_active = 1 
                                ORDER BY name ASC
                            ");
                            $wsStmt->execute();
                            while ($ws = $wsStmt->fetch(PDO::FETCH_ASSOC)):
                                $flag = $countries[$ws['country']]['flag'] ?? '🏳️';
                        ?>
                            <label class="sf-ws-result hidden" data-search="<?= htmlspecialchars(strtolower($ws['name'])) ?>">
                                <input type="checkbox" name="worksites[]" value="<?= (int)$ws['id'] ?>">
                                <span class="sf-ws-name"><?= htmlspecialchars($ws['name']) ?></span>
                                <span class="sf-ws-flag"><?= $flag ?></span>
                            </label>
                        <?php 
                            endwhile;
                        } catch (Exception $e) {
                            error_log('Error loading worksites: ' . $e->getMessage());
                        }
                        ?>
                    </div>
                    
                    <!-- Current selection (only shows when something selected) -->
                    <div id="selectionDisplay" class="sf-selection-display hidden">
                        <span class="sf-sel-label">Valittu:</span>
                        <div id="selectionTags" class="sf-sel-tags"></div>
                    </div>
                </div>
            </div>

            <div class="sf-modal-actions">
                <button type="button" class="sf-btn sf-btn-secondary" id="btnCommsStep2Back">← Takaisin</button>
                <button type="button" class="sf-btn sf-btn-primary" id="btnCommsStep2Next">Seuraava →</button>
            </div>
        </div>

        <!-- STEP 3: Distribution (Simplified) -->
        <div class="sf-comms-step hidden" id="commsStep3">
            <h2><?= htmlspecialchars(sf_term('modal_to_comms_title', $currentUiLang) ?? 'Lähetä viestintään', ENT_QUOTES, 'UTF-8') ?></h2>
            
            <div class="sf-step-indicator">
                <span class="sf-step done">✓</span>
                <span class="sf-step-line done"></span>
                <span class="sf-step done">✓</span>
                <span class="sf-step-line done"></span>
                <span class="sf-step active">3</span>
                <span class="sf-step-line"></span>
                <span class="sf-step">4</span>
            </div>

            <div class="sf-field">
                <label class="sf-label">
                    <?= htmlspecialchars(sf_term('comms_step3_wider_distribution', $currentUiLang) ?? 'Laajempi jakelu', ENT_QUOTES, 'UTF-8') ?>
                </label>
                <p class="sf-help-text">
                    <?= htmlspecialchars(sf_term('comms_step3_wider_distribution_help', $currentUiLang) ?? 'Lähetä SafetyFlash myös laajemmalle jakelulistalle', ENT_QUOTES, 'UTF-8') ?>
                </p>
                
                <div class="sf-toggle-card">
                    <div class="sf-toggle-card-content">
                        <div class="sf-toggle-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21.21 15.89A10 10 0 1 1 8 2.83"/>
                                <path d="M22 12A10 10 0 0 0 12 2v10z"/>
                            </svg>
                        </div>
                        <div class="sf-toggle-text">
                            <strong><?= htmlspecialchars(sf_term('comms_wider_distribution_label', $currentUiLang) ?? 'Lähetä laajempaan jakeluun', ENT_QUOTES, 'UTF-8') ?></strong>
                            <small id="widerDistributionLabel"><?= htmlspecialchars(sf_term('comms_wider_distribution_no', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></small>
                        </div>
                    </div>
                    <label class="sf-modern-toggle">
                        <input type="checkbox" name="wider_distribution" id="widerDistribution" value="1">
                        <span class="sf-modern-toggle-track">
                            <span class="sf-modern-toggle-thumb"></span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="sf-modal-actions">
                <button type="button" class="sf-btn sf-btn-secondary" id="btnCommsStep3Back">
                    ← <?= htmlspecialchars(sf_term('btn_back', $currentUiLang) ?? 'Takaisin', ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button type="button" class="sf-btn sf-btn-primary" id="btnCommsStep3Next">
                    <?= htmlspecialchars(sf_term('btn_next', $currentUiLang) ?? 'Seuraava', ENT_QUOTES, 'UTF-8') ?> →
                </button>
            </div>
        </div>

        <!-- STEP 4: Summary & Message -->
        <div class="sf-comms-step hidden" id="commsStep4">
            <h2><?= htmlspecialchars(sf_term('modal_to_comms_title', $currentUiLang) ?? 'Lähetä viestintään', ENT_QUOTES, 'UTF-8') ?></h2>
            
            <div class="sf-step-indicator">
                <span class="sf-step done">✓</span>
                <span class="sf-step-line done"></span>
                <span class="sf-step done">✓</span>
                <span class="sf-step-line done"></span>
                <span class="sf-step done">✓</span>
                <span class="sf-step-line done"></span>
                <span class="sf-step active">4</span>
            </div>

            <div class="sf-comms-summary">
                <h3><?= htmlspecialchars(sf_term('comms_summary_title', $currentUiLang) ?? 'Yhteenveto', ENT_QUOTES, 'UTF-8') ?></h3>
                
                <div class="sf-summary-item">
                    <img src="<?= htmlspecialchars($base) ?>/assets/img/icons/globe.svg" alt="" class="sf-summary-icon">
                    <strong><?= htmlspecialchars(sf_term('comms_summary_languages', $currentUiLang) ?? 'Kieliversiot', ENT_QUOTES, 'UTF-8') ?></strong>
                    <span id="commsSummaryLanguages">-</span>
                </div>
                
                <div class="sf-summary-item">
                    <img src="<?= htmlspecialchars($base) ?>/assets/img/icons/screen.svg" alt="" class="sf-summary-icon">
                    <strong><?= htmlspecialchars(sf_term('comms_summary_screens', $currentUiLang) ?? 'Xibo-näytöt', ENT_QUOTES, 'UTF-8') ?></strong>
                    <span id="commsSummaryScreens">-</span>
                </div>
                
                <div class="sf-summary-item">
                    <img src="<?= htmlspecialchars($base) ?>/assets/img/icons/megaphone.svg" alt="" class="sf-summary-icon">
                    <strong><?= htmlspecialchars(sf_term('comms_summary_distribution', $currentUiLang) ?? 'Jakelu', ENT_QUOTES, 'UTF-8') ?></strong>
                    <span id="commsSummaryDistribution">-</span>
                </div>
            </div>

            <div class="sf-field" style="margin-top: 1.5rem;">
                <label for="commsMessage" class="sf-label">
                    <img src="<?= htmlspecialchars($base) ?>/assets/img/icons/comment.svg" alt="" style="width: 16px; height: 16px; opacity: 0.7; margin-right: 4px; vertical-align: middle;">
                    <?= htmlspecialchars(sf_term('modal_to_comms_label', $currentUiLang) ?? 'Viesti viestintään (valinnainen)', ENT_QUOTES, 'UTF-8') ?>
                </label>
                <textarea
                  id="commsMessage"
                  name="message"
                  rows="4"
                  class="sf-textarea"
                  placeholder="<?= htmlspecialchars(sf_term('modal_to_comms_placeholder', $currentUiLang) ?? 'Lisätiedot viestintätiimille...', ENT_QUOTES, 'UTF-8') ?>"
                ></textarea>
            </div>

            <div class="sf-modal-actions">
                <button type="button" class="sf-btn sf-btn-secondary" id="btnCommsStep4Back">
                    ← <?= htmlspecialchars(sf_term('btn_back', $currentUiLang) ?? 'Takaisin', ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button type="submit" class="sf-btn sf-btn-primary" id="btnCommsSend">
                    <?= htmlspecialchars(sf_term('btn_send_comms', $currentUiLang) ?? 'Lähetä viestintään', ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
        </div>

    </div>
</div>

<!-- Modal: Send to Safety Team (from Supervisor) -->
<div class="sf-modal hidden" id="modalSendSafety" role="dialog" aria-modal="true" aria-labelledby="modalSendSafetyTitle">
    <div class="sf-modal-content">
        <h2 id="modalSendSafetyTitle">
            <?= htmlspecialchars(sf_term('modal_send_safety_title', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
        </h2>
        <form method="post" action="<?= htmlspecialchars($base) ?>/app/actions/supervisor_to_safety.php">
            <?= sf_csrf_field() ?>
            <input type="hidden" name="flash_id" value="<?= (int)$id ?>">
            <label for="safetyMessage">
                <?= htmlspecialchars(sf_term('modal_send_safety_message_label', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
            </label>
            <textarea
              id="safetyMessage"
              name="message"
              rows="4"
              placeholder="<?= htmlspecialchars(sf_term('modal_send_safety_placeholder', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>"
            ></textarea>
            <div class="sf-modal-actions">
                <button
                  type="button"
                  class="sf-btn sf-btn-secondary"
                  data-modal-close="modalSendSafety"
                >
                  <?= htmlspecialchars(sf_term('btn_cancel', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button type="submit" class="sf-btn sf-btn-primary">
                  <?= htmlspecialchars(sf_term('footer_send_to_safety', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="sf-modal hidden" id="modalPublish" role="dialog" aria-modal="true" aria-labelledby="modalPublishTitle">
    <div class="sf-modal-content sf-modal-publish-stepper">

        <!-- Stepper-indikaattori -->
        <div class="sf-step-indicator sf-publish-step-indicator">
            <span class="sf-step active" id="publishStepDot1" title="<?= htmlspecialchars(sf_term('publish_step1_title', $currentUiLang) ?? 'Perustiedot', ENT_QUOTES, 'UTF-8') ?>">1</span>
            <span class="sf-step-line" id="publishStepLine1"></span>
            <span class="sf-step" id="publishStepDot2" title="<?= htmlspecialchars(sf_term('publish_step2_title', $currentUiLang) ?? 'Infonäytöt', ENT_QUOTES, 'UTF-8') ?>">2</span>
            <span class="sf-step-line" id="publishStepLine2"></span>
            <span class="sf-step" id="publishStepDot3" title="<?= htmlspecialchars(sf_term('publish_step3_title', $currentUiLang) ?? 'Aika-asetukset', ENT_QUOTES, 'UTF-8') ?>">3</span>
            <span class="sf-step-line" id="publishStepLine3"></span>
            <span class="sf-step" id="publishStepDot4" title="<?= htmlspecialchars(sf_term('publish_step4_title', $currentUiLang) ?? 'Vahvista', ENT_QUOTES, 'UTF-8') ?>">4</span>
        </div>

        <!-- Julkaisuvaihtoehdot — yhteinen form kaikille askelille -->
        <form id="publishForm" method="POST" action="<?= htmlspecialchars($base) ?>/app/actions/publish.php?id=<?= (int)$id ?>">
            <?= sf_csrf_field() ?>

            <!-- ===== VAIHE 1: Perustiedot ===== -->
            <div class="sf-publish-step" id="publishStep1">
                <h2 id="modalPublishTitle">
                    <?= htmlspecialchars(sf_term('modal_publish_title', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                    <small class="sf-publish-step-label"><?= htmlspecialchars(sf_term('publish_step1_title', $currentUiLang) ?? 'Perustiedot', ENT_QUOTES, 'UTF-8') ?></small>
                </h2>
                <p><?= htmlspecialchars(sf_term('modal_publish_text', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></p>

                <div class="sf-publish-options">
                    <!-- Lähetä jakeluryhmälle -->
                    <label class="sf-checkbox-option">
                        <input type="checkbox" name="send_to_distribution" id="publishSendDistribution" value="1">
                        <span class="sf-checkbox-label">
                            <strong><?= htmlspecialchars(sf_term('publish_send_to_distribution', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></strong>
                            <small><?= htmlspecialchars(sf_term('publish_send_to_distribution_hint', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></small>
                        </span>
                    </label>

                    <!-- Maakohtainen jakelu -->
                    <div class="sf-country-selection" id="countrySelectionDiv" style="display:none;">
                        <label class="sf-label"><?= htmlspecialchars(sf_term('publish_select_countries', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></label>
                        <div class="sf-country-flags">
                            <?php
                            $distributionCountries = [
                                'fi' => ['label_key' => 'country_finland', 'icon' => 'finnish-flag.png', 'default' => true],
                                'sv' => ['label_key' => 'country_sweden', 'icon' => 'swedish-flag.png', 'default' => false],
                                'en' => ['label_key' => 'country_uk', 'icon' => 'english-flag.png', 'default' => false],
                                'it' => ['label_key' => 'country_italy', 'icon' => 'italian-flag.png', 'default' => false],
                                'el' => ['label_key' => 'country_greece', 'icon' => 'greece-flag.png', 'default' => false],
                            ];
                            foreach ($distributionCountries as $countryCode => $countryData):
                            ?>
                                <label class="sf-flag-chip">
                                    <input type="checkbox"
                                           name="distribution_countries[]"
                                           value="<?= htmlspecialchars($countryCode) ?>"
                                           <?= $countryData['default'] ? 'checked' : '' ?>>
                                    <img src="<?= htmlspecialchars($base) ?>/assets/img/<?= htmlspecialchars($countryData['icon']) ?>"
                                         alt="<?= htmlspecialchars(sf_term($countryData['label_key'], $currentUiLang), ENT_QUOTES, 'UTF-8') ?>">
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Henkilövahinkoja - VAIN punaisille -->
                    <?php if (($flash['type'] ?? '') === 'red'): ?>
                    <label class="sf-checkbox-option sf-checkbox-warning">
                        <input type="checkbox" name="has_personal_injury" id="publishPersonalInjury" value="1">
                        <span class="sf-checkbox-label">
                            <strong>⚠️ <?= htmlspecialchars(sf_term('publish_personal_injury', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></strong>
                            <small><?= htmlspecialchars(sf_term('publish_personal_injury_hint', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></small>
                        </span>
                    </label>
                    <?php endif; ?>

                    <!-- Otsikon esikatselu -->
                    <div class="sf-email-subject-preview" id="emailSubjectPreview" style="display:none;">
                        <strong><?= htmlspecialchars(sf_term('publish_subject_preview', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>:</strong>
                        <code id="emailSubjectText"></code>
                    </div>
                </div>

                <div class="sf-modal-actions">
                    <button type="button" class="sf-btn sf-btn-secondary" data-modal-close="modalPublish">
                        <?= htmlspecialchars(sf_term('btn_cancel', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <button type="button" class="sf-btn sf-btn-primary" id="btnPublishStep1Next">
                        <?= htmlspecialchars(sf_term('btn_next', $currentUiLang) ?? 'Seuraava', ENT_QUOTES, 'UTF-8') ?> →
                    </button>
                </div>
            </div>

            <!-- ===== VAIHE 2: Työmaan infonäytöt ===== -->
            <div class="sf-publish-step hidden" id="publishStep2">
                <h2><?= htmlspecialchars(sf_term('publish_step2_title', $currentUiLang) ?? 'Työmaan infonäytöt', ENT_QUOTES, 'UTF-8') ?></h2>

                <!-- Infonäyttövalinnat per kieliversio -->
                <?php
                $originalFlash = $flash;
                $stmtLangVersions = $pdo->prepare("
                    SELECT id, lang, title FROM sf_flashes
                    WHERE id = :gid OR translation_group_id = :gid2
                    ORDER BY FIELD(lang, 'fi', 'sv', 'en', 'it', 'el')
                ");
                $stmtLangVersions->execute([':gid' => $translationGroupId, ':gid2' => $translationGroupId]);
                $langVersions = $stmtLangVersions->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <?php if (!empty($langVersions)): ?>
                    <div class="sf-display-targets-section">
                        <?php foreach ($langVersions as $ver): ?>
                            <div class="sf-lang-display-section">
                                <?php
                                    $flash = $ver;
                                    $context = 'publish';
                                    require __DIR__ . '/../partials/display_target_selector.php';
                                ?>
                            </div>
                        <?php endforeach; ?>
                        <?php $flash = $originalFlash; ?>
                    </div>
                <?php endif; ?>

                <div class="sf-modal-actions">
                    <button type="button" class="sf-btn sf-btn-secondary" id="btnPublishStep2Back">
                        ← <?= htmlspecialchars(sf_term('btn_back', $currentUiLang) ?? 'Takaisin', ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <button type="button" class="sf-btn sf-btn-primary" id="btnPublishStep2Next">
                        <?= htmlspecialchars(sf_term('btn_next', $currentUiLang) ?? 'Seuraava', ENT_QUOTES, 'UTF-8') ?> →
                    </button>
                </div>
            </div>

            <!-- ===== VAIHE 3: Aika-asetukset ===== -->
            <div class="sf-publish-step hidden" id="publishStep3">
                <h2><?= htmlspecialchars(sf_term('publish_step3_title', $currentUiLang) ?? 'Aika-asetukset', ENT_QUOTES, 'UTF-8') ?></h2>

                <!-- Näkyvyysaika infonäytöillä -->
                <?php require __DIR__ . '/../partials/publish_display_ttl.php'; ?>

                <!-- Näyttökesto per kuva -->
                <?php require __DIR__ . '/../partials/publish_display_duration.php'; ?>

                <div class="sf-modal-actions">
                    <button type="button" class="sf-btn sf-btn-secondary" id="btnPublishStep3Back">
                        ← <?= htmlspecialchars(sf_term('btn_back', $currentUiLang) ?? 'Takaisin', ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <button type="button" class="sf-btn sf-btn-primary" id="btnPublishStep3Next">
                        <?= htmlspecialchars(sf_term('btn_next', $currentUiLang) ?? 'Seuraava', ENT_QUOTES, 'UTF-8') ?> →
                    </button>
                </div>
            </div>

            <!-- ===== VAIHE 4: Vahvistus ===== -->
            <div class="sf-publish-step hidden" id="publishStep4">
                <h2><?= htmlspecialchars(sf_term('publish_step4_title', $currentUiLang) ?? 'Vahvista julkaisu', ENT_QUOTES, 'UTF-8') ?></h2>

                <div class="sf-publish-summary" id="publishSummary">
                    <dl class="sf-summary-list">
                        <dt><?= htmlspecialchars(sf_term('publish_summary_distribution', $currentUiLang) ?? 'Jakeluryhmä', ENT_QUOTES, 'UTF-8') ?></dt>
                        <dd id="summaryDistribution">—</dd>
                        <dt><?= htmlspecialchars(sf_term('publish_summary_displays', $currentUiLang) ?? 'Infonäytöt', ENT_QUOTES, 'UTF-8') ?></dt>
                        <dd id="summaryDisplays">—</dd>
                        <dt><?= htmlspecialchars(sf_term('publish_summary_ttl', $currentUiLang) ?? 'Näkyvyysaika', ENT_QUOTES, 'UTF-8') ?></dt>
                        <dd id="summaryTtl">—</dd>
                        <dt><?= htmlspecialchars(sf_term('publish_summary_duration', $currentUiLang) ?? 'Näyttökesto', ENT_QUOTES, 'UTF-8') ?></dt>
                        <dd id="summaryDuration">—</dd>
                    </dl>
                </div>

                <div class="sf-modal-actions">
                    <button type="button" class="sf-btn sf-btn-secondary" id="btnPublishStep4Back">
                        ← <?= htmlspecialchars(sf_term('btn_back', $currentUiLang) ?? 'Takaisin', ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <button type="submit" class="sf-btn sf-btn-primary">
                        <?= htmlspecialchars(sf_term('btn_publish', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>
            </div>

        </form>
    </div>
</div>

<!-- Publish Single Language Version Modal -->
<div id="publishSingleModal" class="sf-modal hidden" role="dialog" aria-modal="true" aria-labelledby="publishSingleModalTitle">
    <div class="sf-modal-content">
        <div class="sf-modal-header">
            <h3 id="publishSingleModalTitle">
                <?= sf_lang_flag($flash['lang']) ?>
                <?= htmlspecialchars(sf_term('publish_language_version', $currentUiLang) ?? 'Julkaise kieliversio', ENT_QUOTES, 'UTF-8') ?>
                — <?= htmlspecialchars(strtoupper($flash['lang']), ENT_QUOTES, 'UTF-8') ?>
            </h3>
            <button type="button" class="sf-modal-close-btn" onclick="closePublishSingleModal()">&times;</button>
        </div>

        <form method="post" action="<?= htmlspecialchars($base) ?>/app/actions/publish.php?id=<?= (int)$flash['id'] ?>">
            <?= sf_csrf_field() ?>
            <input type="hidden" name="publish_mode" value="single">

            <div class="sf-modal-body">
                <!-- TTL valitsin -->
                <?php require __DIR__ . '/../partials/publish_display_ttl.php'; ?>

                <!-- Kesto valitsin -->
                <?php require __DIR__ . '/../partials/publish_display_duration.php'; ?>

                <!-- Näyttövalitsin — kaikki aktiiviset näytöt -->
                <div class="sf-lang-display-section">
                    <?php
                        $context = 'publish';
                        require __DIR__ . '/../partials/display_target_selector.php';
                    ?>
                </div>

                <!-- Henkilövahinko -->
                <?php if (($flash['type'] ?? '') === 'red'): ?>
                <div class="sf-checkbox-option sf-checkbox-warning" style="margin-top:1rem;">
                    <label>
                        <input type="checkbox" name="has_personal_injury" value="1"
                            <?= !empty($flash['has_personal_injury']) ? 'checked' : '' ?>>
                        ⚠️ <?= htmlspecialchars(sf_term('publish_personal_injury', $currentUiLang) ?? 'Henkilövahinko', ENT_QUOTES, 'UTF-8') ?>
                    </label>
                </div>
                <?php endif; ?>
            </div>

            <div class="sf-modal-footer">
                <button type="button" class="sf-btn sf-btn-secondary" onclick="closePublishSingleModal()">
                    <?= htmlspecialchars(sf_term('btn_cancel', $currentUiLang) ?? 'Peruuta', ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button type="submit" class="sf-btn sf-btn-primary">
                    <?= sf_lang_flag($flash['lang']) ?>
                    <?= htmlspecialchars(sf_term('btn_publish_language_version', $currentUiLang) ?? 'Julkaise kieliversio', ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- JavaScript otsikon esikatseluun -->
<script>
(function() {
    const distributionCheckbox = document.getElementById('publishSendDistribution');
    const injuryCheckbox = document.getElementById('publishPersonalInjury');
    const previewDiv = document.getElementById('emailSubjectPreview');
    const subjectText = document.getElementById('emailSubjectText');
    const countryDiv = document.getElementById('countrySelectionDiv');
    
    if (!distributionCheckbox || !previewDiv) return;
    
    const flashType = <?= json_encode($flash['type'] ?? 'yellow', JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const flashTitle = <?= json_encode($flash['title'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const flashSite = <?= json_encode($flash['worksite'] ?? $flash['site'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    
    const typeLabels = {
        red: '🔴 <?= addslashes(sf_term('email_type_red', $currentUiLang)) ?>',
        yellow: '🟡 <?= addslashes(sf_term('email_type_yellow', $currentUiLang)) ?>',
        green: '🟢 <?= addslashes(sf_term('email_type_green', $currentUiLang)) ?>'
    };
    
    const injuryPrefix = '⚠️ <?= addslashes(sf_term('email_personal_injury_warning', $currentUiLang)) ?>';
    
    function updatePreview() {
        const showPreview = distributionCheckbox.checked;
        previewDiv.style.display = showPreview ? 'block' : 'none';
        
        // Show/hide country selection
        if (countryDiv) {
            countryDiv.style.display = showPreview ? 'block' : 'none';
        }
        
        if (!showPreview) return;
        
        let parts = [];
        
        // Henkilövahinko-varoitus (vain jos valittu ja punainen)
        if (injuryCheckbox && injuryCheckbox.checked && flashType === 'red') {
            parts.push(injuryPrefix);
        }
        
        // Tyyppi
        parts.push(typeLabels[flashType] || typeLabels.yellow);
        
        // Otsikko
        if (flashTitle) {
            parts.push(flashTitle);
        }
        
        // Työmaa
        if (flashSite) {
            parts.push('(' + flashSite + ')');
        }
        
        subjectText.textContent = parts.join(' - ');
    }
    
    distributionCheckbox.addEventListener('change', updatePreview);
    if (injuryCheckbox) {
        injuryCheckbox.addEventListener('change', updatePreview);
    }
    
    updatePreview();
})();
</script>

<script>
// ===== Publish Modal Stepper =====
(function () {
    'use strict';

    var currentStep = 1;
    var totalSteps = 4;

    function showPublishStep(step) {
        for (var i = 1; i <= totalSteps; i++) {
            var el = document.getElementById('publishStep' + i);
            if (el) {
                if (i === step) {
                    el.classList.remove('hidden');
                } else {
                    el.classList.add('hidden');
                }
            }
            // Update dot states
            var dot = document.getElementById('publishStepDot' + i);
            if (dot) {
                dot.classList.toggle('active', i === step);
                dot.classList.toggle('done', i < step);
            }
            // Update line states
            if (i < totalSteps) {
                var line = document.getElementById('publishStepLine' + i);
                if (line) {
                    line.classList.toggle('done', i < step);
                }
            }
        }
        currentStep = step;
        if (step === 4) {
            updatePublishSummary();
        }
    }

    function updatePublishSummary() {
        // Distribution
        var distCb = document.getElementById('publishSendDistribution');
        var summaryDist = document.getElementById('summaryDistribution');
        if (summaryDist && distCb) {
            summaryDist.textContent = distCb.checked
                ? (window.SF_TERMS && window.SF_TERMS.publish_yes ? window.SF_TERMS.publish_yes : '✅ Kyllä')
                : '—';
        }

        // TTL — read the label text directly from the selected chip
        var ttlInput = document.querySelector('#publishForm input[name="display_ttl_days"]:checked');
        var summaryTtl = document.getElementById('summaryTtl');
        if (summaryTtl) {
            var ttlLabel = ttlInput ? ttlInput.closest('label') : null;
            summaryTtl.textContent = ttlLabel ? ttlLabel.textContent.trim() : (ttlInput ? ttlInput.value : '—');
        }

        // Duration — read the label text directly from the selected chip
        var durInput = document.querySelector('#publishForm input[name="display_duration_seconds"]:checked');
        var summaryDur = document.getElementById('summaryDuration');
        if (summaryDur) {
            var durLabel = durInput ? durInput.closest('label') : null;
            summaryDur.textContent = durLabel ? durLabel.textContent.trim() : (durInput ? durInput.value + 's' : '—');
        }

        // Display targets
        var selectedDisplays = [];
        document.querySelectorAll('#publishForm .sf-display-chip-input:checked').forEach(function (cb) {
            var chip = cb.closest('.sf-display-chip');
            if (chip) {
                selectedDisplays.push(chip.textContent.trim());
            }
        });
        var summaryDisplays = document.getElementById('summaryDisplays');
        if (summaryDisplays) {
            summaryDisplays.textContent = selectedDisplays.length > 0 ? selectedDisplays.join(', ') : '—';
        }
    }

    function init() {
        var btn1Next = document.getElementById('btnPublishStep1Next');
        var btn2Back = document.getElementById('btnPublishStep2Back');
        var btn2Next = document.getElementById('btnPublishStep2Next');
        var btn3Back = document.getElementById('btnPublishStep3Back');
        var btn3Next = document.getElementById('btnPublishStep3Next');
        var btn4Back = document.getElementById('btnPublishStep4Back');

        if (btn1Next) btn1Next.addEventListener('click', function () { showPublishStep(2); });
        if (btn2Back) btn2Back.addEventListener('click', function () { showPublishStep(1); });
        if (btn2Next) btn2Next.addEventListener('click', function () { showPublishStep(3); });
        if (btn3Back) btn3Back.addEventListener('click', function () { showPublishStep(2); });
        if (btn3Next) btn3Next.addEventListener('click', function () { showPublishStep(4); });
        if (btn4Back) btn4Back.addEventListener('click', function () { showPublishStep(3); });

        // Reset to step 1 when modal opens
        var footerPublish = document.getElementById('footerPublish');
        if (footerPublish) {
            footerPublish.addEventListener('click', function () {
                showPublishStep(1);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>

<div class="sf-modal hidden" id="modalDelete" role="dialog" aria-modal="true" aria-labelledby="modalDeleteTitle">
    <div class="sf-modal-content">
        <h2 id="modalDeleteTitle">
            <?= htmlspecialchars(sf_term('modal_delete_title', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
        </h2>
        <div id="deleteModalContent">
            <!-- Content will be populated by JavaScript -->
            <p>
                <?= htmlspecialchars(sf_term('modal_delete_text', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>
        <form method="post" action="<?= htmlspecialchars($base) ?>/app/actions/delete.php?id=<?= (int)$id ?>">
            <?= sf_csrf_field() ?>
            <div class="sf-modal-actions">
                <button
                  type="button"
                  class="sf-btn sf-btn-secondary"
                  data-modal-close="modalDelete"
                >
                  <?= htmlspecialchars(sf_term('btn_cancel', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button type="submit" class="sf-btn sf-btn-danger">
                  <?= htmlspecialchars(sf_term('btn_delete', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reviewer Modals -->
<div class="sf-modal hidden" id="modalAddReviewer" role="dialog" aria-modal="true" aria-labelledby="modalAddReviewerTitle">
    <div class="sf-modal-content">
        <div class="sf-modal-header">
            <h2 id="modalAddReviewerTitle">
                <?= htmlspecialchars(sf_term('reviewer_add_modal_title', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
            </h2>
            <button type="button" class="sf-modal-close-btn" data-modal-close="modalAddReviewer">×</button>
        </div>
        
        <div class="sf-modal-body">
            <div class="sf-field">
                <label for="reviewerSearch" class="sf-label">
                    <?= htmlspecialchars(sf_term('reviewer_select_user', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </label>
                <div class="sf-search-select">
                    <input type="text" 
                           id="reviewerSearch" 
                           class="sf-search-input"
                           placeholder="<?= htmlspecialchars(sf_term('reviewer_search_placeholder', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>"
                           autocomplete="off">
                    <div class="sf-search-dropdown hidden" id="reviewerSearchDropdown">
                        <!-- Dynamically populated by JavaScript -->
                    </div>
                </div>
                <input type="hidden" id="selectedReviewerId" value="">
                <div id="selectedReviewerDisplay" class="selected-reviewer-display hidden"></div>
            </div>
        </div>
        
        <div class="sf-modal-actions">
            <button type="button" class="sf-btn sf-btn-secondary" data-modal-close="modalAddReviewer">
                <?= htmlspecialchars(sf_term('btn_cancel', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
            </button>
            <button type="button" id="btnAddReviewer" class="sf-btn sf-btn-primary">
                <?= htmlspecialchars(sf_term('add_reviewer', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    </div>
</div>

<div class="sf-modal hidden" id="modalReplaceReviewer" role="dialog" aria-modal="true" aria-labelledby="modalReplaceReviewerTitle">
    <div class="sf-modal-content">
        <div class="sf-modal-header">
            <h2 id="modalReplaceReviewerTitle">
                <?= htmlspecialchars(sf_term('reviewer_replace_modal_title', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
            </h2>
            <button type="button" class="sf-modal-close-btn" data-modal-close="modalReplaceReviewer">×</button>
        </div>
        
        <div class="sf-modal-body">
            <div class="sf-field" id="currentReviewersSection">
                <label class="sf-label">
                    <?= htmlspecialchars(sf_term('reviewer_current_reviewers', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </label>
                <div id="currentReviewersDisplay" class="current-reviewers-display">
                    <!-- Populated by JavaScript -->
                </div>
            </div>
            
            <div class="sf-field">
                <label for="reviewerSearchReplace" class="sf-label">
                    <?= htmlspecialchars(sf_term('reviewer_select_user', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </label>
                <div class="sf-search-select">
                    <input type="text" 
                           id="reviewerSearchReplace" 
                           class="sf-search-input"
                           placeholder="<?= htmlspecialchars(sf_term('reviewer_search_placeholder', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>"
                           autocomplete="off">
                    <div class="sf-search-dropdown hidden" id="reviewerSearchReplaceDropdown">
                        <!-- Dynamically populated by JavaScript -->
                    </div>
                </div>
                <input type="hidden" id="selectedReviewerIdReplace" value="">
                <div id="selectedReviewerDisplayReplace" class="selected-reviewer-display hidden"></div>
            </div>
        </div>
        
        <div class="sf-modal-actions">
            <button type="button" class="sf-btn sf-btn-secondary" data-modal-close="modalReplaceReviewer">
                <?= htmlspecialchars(sf_term('btn_cancel', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
            </button>
            <button type="button" id="btnReplaceReviewer" class="sf-btn sf-btn-primary">
                <?= htmlspecialchars(sf_term('replace_reviewer', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    </div>
</div>

<script>
// Store flash data for delete modal
window.sfFlashData = {
    id: <?= (int)$id ?>,
    translationGroupId: <?= !empty($flash['translation_group_id']) ? (int)$flash['translation_group_id'] : 'null' ?>,
    lang: '<?= htmlspecialchars($flash['lang'] ?? 'fi', ENT_QUOTES, 'UTF-8') ?>',
    isTranslation: <?= !empty($flash['translation_group_id']) ? 'true' : 'false' ?>,
    uiLang: '<?= htmlspecialchars($currentUiLang, ENT_QUOTES, 'UTF-8') ?>',
    title: '<?= htmlspecialchars($flash['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>',
    type: '<?= htmlspecialchars($flash['type'] ?? '', ENT_QUOTES, 'UTF-8') ?>',
    site: '<?= htmlspecialchars($flash['site'] ?? '', ENT_QUOTES, 'UTF-8') ?>'
};

// Translation terms for JavaScript
window.sfDeleteTerms = {
    delete_original_confirm_title: <?= json_encode(sf_term('delete_original_confirm_title', $currentUiLang), JSON_UNESCAPED_UNICODE) ?>,
    delete_original_confirm_message: <?= json_encode(sf_term('delete_original_confirm_message', $currentUiLang), JSON_UNESCAPED_UNICODE) ?>,
    delete_original_versions_count: <?= json_encode(sf_term('delete_original_versions_count', $currentUiLang), JSON_UNESCAPED_UNICODE) ?>,
    delete_translation_confirm_title: <?= json_encode(sf_term('delete_translation_confirm_title', $currentUiLang), JSON_UNESCAPED_UNICODE) ?>,
    delete_translation_confirm_message: <?= json_encode(sf_term('delete_translation_confirm_message', $currentUiLang), JSON_UNESCAPED_UNICODE) ?>,
    delete_translation_which: <?= json_encode(sf_term('delete_translation_which', $currentUiLang), JSON_UNESCAPED_UNICODE) ?>,
    lang_name_fi: <?= json_encode(sf_term('lang_name_fi', $currentUiLang), JSON_UNESCAPED_UNICODE) ?>,
    lang_name_sv: <?= json_encode(sf_term('lang_name_sv', $currentUiLang), JSON_UNESCAPED_UNICODE) ?>,
    lang_name_en: <?= json_encode(sf_term('lang_name_en', $currentUiLang), JSON_UNESCAPED_UNICODE) ?>,
    lang_name_it: <?= json_encode(sf_term('lang_name_it', $currentUiLang), JSON_UNESCAPED_UNICODE) ?>,
    lang_name_el: <?= json_encode(sf_term('lang_name_el', $currentUiLang), JSON_UNESCAPED_UNICODE) ?>
};

// Update delete modal content when opened
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('[data-modal-open="modalDelete"]');
    
    deleteButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            updateDeleteModalContent();
        });
    });
});

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function getTypeInfo(type) {
    const typeMap = {
        'red': { dot: '🔴', label: 'Ensitiedote' },
        'yellow': { dot: '🟡', label: 'Vaaratilanne' },
        'green': { dot: '🟢', label: 'Tutkintatiedote' }
    };
    return typeMap[type] || { dot: '⚪', label: type };
}

function updateDeleteModalContent() {
    const modalTitle = document.getElementById('modalDeleteTitle');
    const modalContent = document.getElementById('deleteModalContent');
    const flashData = window.sfFlashData;
    const terms = window.sfDeleteTerms;
    
    if (!flashData.isTranslation) {
        // Deleting original - check for translations
        const groupId = flashData.id;
        
        // Fetch translations via AJAX
        const url = new URL('<?= htmlspecialchars($base) ?>/app/api/get_flash_translations.php', window.location.origin);
        url.searchParams.set('group_id', groupId);
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.ok && data.translations && Object.keys(data.translations).length > 0) {
                    // Has translations - show warning
                    const count = Object.keys(data.translations).length;
                    const langNames = [];
                    
                    for (const lang in data.translations) {
                        const langKey = 'lang_name_' + lang;
                        if (terms[langKey]) {
                            langNames.push(terms[langKey]);
                        }
                    }
                    
                    modalTitle.textContent = terms.delete_original_confirm_title;
                    
                    const typeInfo = getTypeInfo(flashData.type);
                    const flashDetails = typeInfo.dot + ' ' + typeInfo.label + ' – \'' + escapeHtml(flashData.title) + '\' (' + escapeHtml(flashData.site) + ')';
                    
                    let html = '<p style="margin-bottom: 1rem;"><strong>Poistetaan:</strong> ' + flashDetails + '</p>';
                    html += '<p style="margin-bottom: 0.5rem;">' + terms.delete_original_confirm_message + '</p>';
                    html += '<p style="margin-bottom: 0.5rem;"><strong>' + terms.delete_original_versions_count.replace('%d', count) + '</strong></p>';
                    html += '<ul style="margin-left: 1.5rem;">';
                    langNames.forEach(name => {
                        html += '<li>' + escapeHtml(name) + '</li>';
                    });
                    html += '</ul>';
                    
                    modalContent.innerHTML = html;
                } else {
                    // No translations - use default message
                    modalTitle.textContent = terms.delete_original_confirm_title;
                    
                    const typeInfo = getTypeInfo(flashData.type);
                    const flashDetails = typeInfo.dot + ' ' + typeInfo.label + ' – \'' + escapeHtml(flashData.title) + '\' (' + escapeHtml(flashData.site) + ')';
                    
                    let html = '<p style="margin-bottom: 1rem;"><strong>Poistetaan:</strong> ' + flashDetails + '</p>';
                    html += '<p>' + terms.delete_original_confirm_message + '</p>';
                    
                    modalContent.innerHTML = html;
                }
            })
            .catch(error => {
                console.error('Error fetching translations:', error);
                // Fallback to default message
                modalTitle.textContent = terms.delete_original_confirm_title;
                
                const typeInfo = getTypeInfo(flashData.type);
                const flashDetails = typeInfo.dot + ' ' + typeInfo.label + ' – \'' + escapeHtml(flashData.title) + '\' (' + escapeHtml(flashData.site) + ')';
                
                let html = '<p style="margin-bottom: 1rem;"><strong>Poistetaan:</strong> ' + flashDetails + '</p>';
                html += '<p>' + terms.delete_original_confirm_message + '</p>';
                
                modalContent.innerHTML = html;
            });
    } else {
        // Deleting translation
        const langKey = 'lang_name_' + flashData.lang;
        const langName = terms[langKey] || flashData.lang;
        
        // Get language flag emoji
        const langFlags = {
            'fi': '🇫🇮',
            'sv': '🇸🇪', 
            'en': '🇬🇧',
            'it': '🇮🇹',
            'el': '🇬🇷'
        };
        const flag = langFlags[flashData.lang] || '🏳️';
        
        modalTitle.textContent = terms.delete_translation_confirm_title;
        
        let html = '<p style="margin-bottom: 1rem;"><strong>Poistetaan:</strong> ' + flag + ' ' + escapeHtml(langName) + ' kieliversio tiedotteesta \'' + escapeHtml(flashData.title) + '\'</p>';
        html += '<p>' + terms.delete_translation_confirm_message + '</p>';
        
        modalContent.innerHTML = html;
    }
}
</script>

<!-- ARKISTOI-MODAALI -->
<div class="sf-modal hidden" id="modalArchive" role="dialog" aria-modal="true" aria-labelledby="modalArchiveTitle">
    <div class="sf-modal-content">
        <h2 id="modalArchiveTitle">
            <?= htmlspecialchars(sf_term('archive_confirm_title', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
        </h2>
        <p>
            <?= htmlspecialchars(sf_term('archive_confirm_message', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
        </p>
        <div class="sf-modal-actions">
            <button
              type="button"
              class="sf-btn sf-btn-secondary"
              data-modal-close="modalArchive"
            >
              <?= htmlspecialchars(sf_term('btn_cancel', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
            </button>
            <button type="button" class="sf-btn sf-btn-primary" id="modalArchiveConfirm">
              <?= htmlspecialchars(sf_term('btn_archive', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    </div>
</div>

<!-- POISTA KOMMENTTI -MODAALI -->
<div class="sf-modal hidden" id="modalDeleteComment" role="dialog" aria-modal="true" aria-labelledby="modalDeleteCommentTitle">
    <div class="sf-modal-content">
        <h2 id="modalDeleteCommentTitle">
            <?= htmlspecialchars(sf_term('comment_delete_title', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
        </h2>
        <p>
            <?= htmlspecialchars(sf_term('comment_delete_confirm', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
        </p>
        <p class="sf-help-text" style="color: #dc2626; margin-top: 0.5rem;">
            <?= htmlspecialchars(sf_term('comment_delete_warning', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
        </p>
        <div class="sf-modal-actions">
            <button type="button" class="sf-btn sf-btn-secondary" data-modal-close="modalDeleteComment">
                <?= htmlspecialchars(sf_term('btn_cancel', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
            </button>
            <button type="button" class="sf-btn sf-btn-danger" id="modalDeleteCommentConfirm">
                <?= htmlspecialchars(sf_term('btn_delete', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    </div>
</div>

<!-- DELETE LOG ENTRY MODAL (admin only) -->
<div class="sf-modal hidden" id="modalDeleteLog" role="dialog" aria-modal="true">
    <div class="sf-modal-content">
        <h2><?= htmlspecialchars(sf_term('log_delete_title', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></h2>
        <p><?= htmlspecialchars(sf_term('log_delete_confirm', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></p>
        <div class="sf-modal-actions">
            <button type="button" class="sf-btn sf-btn-secondary" data-modal-close="modalDeleteLog">
                <?= htmlspecialchars(sf_term('btn_cancel', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
            </button>
            <button type="button" class="sf-btn sf-btn-danger" id="confirmDeleteLog">
                <?= htmlspecialchars(sf_term('btn_delete', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    </div>
</div>

<!-- KIELIVERSIO-MODAALI (vaiheittainen) -->
<div class="sf-modal hidden" id="modalTranslation" role="dialog" aria-modal="true" aria-labelledby="modalTranslationTitle">
    <div class="sf-modal-content sf-modal-translation">
        
        <!-- VAIHE 1: Lomake -->
        <div class="sf-translation-step" id="translationStep1">
            <h2 id="modalTranslationTitle">
                <?php echo htmlspecialchars(sf_term('modal_translation_title', $currentUiLang) ?? 'Luo kieliversio', ENT_QUOTES, 'UTF-8'); ?>
            </h2>
            
            <div class="sf-step-indicator">
                <span class="sf-step active">1</span>
                <span class="sf-step-line"></span>
                <span class="sf-step">2</span>
            </div>

            <form id="translationForm">
                <input type="hidden" name="source_id" value="<?php echo (int)$flash['id']; ?>">
                <input type="hidden" name="target_lang" id="translationTargetLang" value="">
                
                <div class="sf-field">
                    <label class="sf-label">
                        <?php echo htmlspecialchars(sf_term('translation_target_lang', $currentUiLang) ?? 'Kohdekieli', ENT_QUOTES, 'UTF-8'); ?>
                    </label>
                    <div class="sf-translation-lang-display" id="translationLangDisplay"></div>
                </div>

                <div class="sf-field">
                    <label for="translationTitleShort" class="sf-label">
                        <?php echo htmlspecialchars(sf_term('short_title_label', $currentUiLang) ?? 'Lyhyt kuvaus', ENT_QUOTES, 'UTF-8'); ?> *
                    </label>
                    <textarea 
                        name="title_short" 
                        id="translationTitleShort" 
                        class="sf-textarea" 
                        rows="2" 
                        maxlength="125"
                        required
                    ></textarea>
                    <div class="sf-char-count"><span id="titleCharCount">0</span>/125</div>
                </div>

                <div class="sf-field">
                    <label for="translationDescription" class="sf-label">
                        <?php echo htmlspecialchars(sf_term('description_label', $currentUiLang) ?? 'Kuvaus', ENT_QUOTES, 'UTF-8'); ?> *
                    </label>
                    <textarea 
                        name="description" 
                        id="translationDescription" 
                        class="sf-textarea" 
                        rows="5"
                        maxlength="900"
                        required
                    ></textarea>
                    <div class="sf-char-count"><span id="descCharCount">0</span>/900</div>
                </div>

                <?php if ($flash['type'] === 'green'): ?>
                    <div class="sf-field">
                        <label for="translationRootCauses" class="sf-label">
                            <?php echo htmlspecialchars(sf_term('root_cause_label', $currentUiLang) ?? 'Juurisyyt', ENT_QUOTES, 'UTF-8'); ?>
                        </label>
                        <textarea name="root_causes" id="translationRootCauses" class="sf-textarea" rows="3"></textarea>
                    </div>

                    <div class="sf-field">
                        <label for="translationActions" class="sf-label">
                            <?php echo htmlspecialchars(sf_term('actions_label', $currentUiLang) ?? 'Toimenpiteet', ENT_QUOTES, 'UTF-8'); ?>
                        </label>
                        <textarea name="actions" id="translationActions" class="sf-textarea" rows="3"></textarea>
                    </div>
                <?php endif; ?>
            </form>

            <div class="sf-modal-actions">
                <button type="button" class="sf-btn sf-btn-secondary" data-modal-close="modalTranslation">
                    <?php echo htmlspecialchars(sf_term('btn_cancel', $currentUiLang) ?? 'Peruuta', ENT_QUOTES, 'UTF-8'); ?>
                </button>
                <button type="button" class="sf-btn sf-btn-primary" id="btnToStep2">
                    <?php echo htmlspecialchars(sf_term('btn_next', $currentUiLang) ?? 'Seuraava', ENT_QUOTES, 'UTF-8'); ?> →
                </button>
            </div>
        </div>

        <!-- VAIHE 2: Esikatselu -->
        <div class="sf-translation-step hidden" id="translationStep2">
            <h2>
                <?php echo htmlspecialchars(sf_term('preview_and_save', $currentUiLang) ?? 'Esikatselu ja tallennus', ENT_QUOTES, 'UTF-8'); ?>
            </h2>
            
            <div class="sf-step-indicator">
                <span class="sf-step done">✓</span>
                <span class="sf-step-line done"></span>
                <span class="sf-step active">2</span>
            </div>

            <div class="sf-translation-preview-wrapper">
                <div id="sfTranslationPreviewContainer">
                    <?php require __DIR__ .'/../partials/preview_modal.php'; ?>
                </div>
            </div>

            <div id="translationStatus" class="sf-translation-status"></div>

            <div class="sf-modal-actions">
                <button type="button" class="sf-btn sf-btn-secondary" id="btnBackToStep1">
                    ← <?php echo htmlspecialchars(sf_term('btn_back', $currentUiLang) ?? 'Takaisin', ENT_QUOTES, 'UTF-8'); ?>
                </button>
                <button type="button" class="sf-btn sf-btn-primary" id="btnSaveTranslation">
                    <?php echo htmlspecialchars(sf_term('btn_save_translation', $currentUiLang) ?? 'Tallenna kieliversio', ENT_QUOTES, 'UTF-8'); ?>
                </button>
            </div>
        </div>

    </div>
</div>

<!-- KIELIVERSIO VAHVISTUSMODAALI (kevyt) -->
<div class="sf-modal hidden" id="modalTranslationConfirm" role="dialog" aria-modal="true" aria-labelledby="modalTranslationConfirmTitle">
    <div class="sf-modal-backdrop" onclick="sfCloseTranslationConfirm()"></div>
    <div class="sf-modal-content sf-modal-confirm">
        <div class="sf-modal-header">
            <h3 id="modalTranslationConfirmTitle">
                <span style="margin-right: 8px;">🌐</span>
                <?php echo htmlspecialchars(sf_term('modal_translation_confirm_title', $currentUiLang) ?? 'Luo kieliversio', ENT_QUOTES, 'UTF-8'); ?>
            </h3>
            <button class="sf-modal-close" onclick="sfCloseTranslationConfirm()">✕</button>
        </div>
        <div class="sf-modal-body">
            <p class="sf-confirm-question">
                <?php echo htmlspecialchars(sf_term('modal_translation_confirm_message', $currentUiLang) ?? 'Haluatko luoda kieliversion tälle SafetyFlashille?', ENT_QUOTES, 'UTF-8'); ?>
            </p>
            
            <div class="sf-confirm-card" id="translationConfirmCard">
                <!-- Target language row with flag -->
                <div class="sf-confirm-lang-row" id="translationConfirmLangRow">
                    <!-- Populated by JS: flag image + language name + code -->
                </div>
                
                <!-- Source flash info -->
                <div class="sf-confirm-source">
                    <span class="sf-confirm-source-label">
                        <?php echo htmlspecialchars(sf_term('confirm_source_flash_label', $currentUiLang), ENT_QUOTES, 'UTF-8'); ?>:
                    </span>
                    <span class="sf-confirm-source-title" id="translationConfirmSourceTitle">
                        <!-- Populated by JS from SF_FLASH_DATA -->
                    </span>
                </div>
                
                <!-- Meta row: site + type badge -->
                <div class="sf-confirm-meta">
                    <span class="sf-confirm-site" id="translationConfirmSite">
                        <!-- Populated by JS: 📍 Site name -->
                    </span>
                    <span class="sf-confirm-type-badge" id="translationConfirmType">
                        <!-- Populated by JS: colored dot + type name -->
                    </span>
                </div>
            </div>
        </div>
        <div class="sf-modal-footer">
            <button type="button" class="sf-btn sf-btn-secondary" onclick="sfCloseTranslationConfirm()">
                <?php echo htmlspecialchars(sf_term('btn_cancel', $currentUiLang) ?? 'Peruuta', ENT_QUOTES, 'UTF-8'); ?>
            </button>
            <button type="button" class="sf-btn sf-btn-primary" id="btnConfirmTranslation">
                <?php echo htmlspecialchars(sf_term('btn_create_translation', $currentUiLang) ?? 'Kyllä, luo kieliversio', ENT_QUOTES, 'UTF-8'); ?>
            </button>
        </div>
    </div>
</div>

<!-- VERSION MODAL -->
<div class="sf-modal hidden" id="versionModal" role="dialog" aria-modal="true" aria-labelledby="versionModalTitle">
    <div class="sf-modal-backdrop" onclick="closeVersionModal()"></div>
    <div class="sf-modal-content sf-version-modal">
        <div class="sf-modal-header">
            <h3 id="versionModalTitle"><?= htmlspecialchars(sf_term('version_ensitiedote', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></h3>
            <span id="versionModalDate" class="sf-version-modal-date"></span>
            <button class="sf-modal-close" onclick="closeVersionModal()">✕</button>
        </div>
        <div class="sf-modal-body">
            <img id="versionModalImage" src="" alt="SafetyFlash version" class="sf-version-image">
        </div>
        <div class="sf-modal-footer">
            <a id="versionDownloadBtn" href="" download class="sf-btn sf-btn-primary">
                📥 <?= htmlspecialchars(sf_term('version_download', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
            </a>
        </div>
    </div>
</div>

<?php if (in_array('display_targets', $actions ?? [])): ?>
<?php require __DIR__ . '/../partials/modal_display_targets.php'; ?>
<?php endif; ?>


<?php /* Footer action bar siirretty ylös (näkyy heti sivun latautuessa). */ ?>

<!-- html2canvas tarvitaan kuvan generointiin -->
<script src="<?= sf_asset_url('assets/js/vendor/html2canvas.min.js', $base) ?>"></script>

<!-- Safetyflash CSS & JS -->
<link rel="stylesheet" href="<?= sf_asset_url('assets/css/display-ttl.css', $base) ?>">
<link rel="stylesheet" href="<?= sf_asset_url('assets/css/preview.css', $base) ?>">
<link rel="stylesheet" href="<?= sf_asset_url('assets/css/copy-to-clipboard.css', $base) ?>">
<link rel="stylesheet" href="<?= sf_asset_url('assets/css/image_captions.css', $base) ?>">
<!-- view.js and copy-to-clipboard.js are loaded in index.php with versioning, removed duplicates here -->
<script src="<?= sf_asset_url('assets/js/translation.js', $base) ?>"></script>
<script src="<?= sf_asset_url('assets/js/display-playlist.js', $base) ?>"></script>
<script src="<?= sf_asset_url('assets/js/comms-modal.js', $base) ?>"></script>
<?php if (in_array('display_targets', $actions ?? [])): ?>
<script src="<?= sf_asset_url('assets/js/display-targets-modal.js', $base) ?>"></script>
<?php endif; ?>

<!-- Sivukohtaiset datat -->
<script>
window.SF_LOG_SHOW_MORE   = <?php echo json_encode(sf_term('log_show_more', $currentUiLang)); ?>;
window.SF_LOG_SHOW_LESS   = <?php echo json_encode(sf_term('log_show_less', $currentUiLang)); ?>;
window.SF_BASE_URL        = <?php echo json_encode($base); ?>;
window.SF_CSRF_TOKEN      = <?php echo json_encode(sf_csrf_token()); ?>;
window.SF_FLASH_ID        = <?php echo json_encode($id); ?>;
window.SF_CAN_EDIT        = <?php echo json_encode($canEdit); ?>;
window.SF_EDIT_URL        = <?php echo json_encode($editUrl); ?>;
window.SF_TERMS = {
    comment_delete_confirm: <?php echo json_encode(sf_term('comment_delete_confirm', $currentUiLang)); ?>,
    comment_deleted: <?php echo json_encode(sf_term('comment_deleted', $currentUiLang)); ?>,
    comment_updated: <?php echo json_encode(sf_term('comment_updated', $currentUiLang)); ?>,
    comment_delete_error: <?php echo json_encode(sf_term('comment_delete_error', $currentUiLang)); ?>,
    comment_update_error: <?php echo json_encode(sf_term('comment_update_error', $currentUiLang)); ?>,
    comment_add_error: <?php echo json_encode(sf_term('comment_add_error', $currentUiLang)); ?>,
    comment_error_empty: <?php echo json_encode(sf_term('comment_error_empty', $currentUiLang)); ?>,
    comment_added: <?php echo json_encode(sf_term('comment_added', $currentUiLang)); ?>,
    comment_reply: <?php echo json_encode(sf_term('comment_reply', $currentUiLang)); ?>,
    comment_edit: <?php echo json_encode(sf_term('comment_edit', $currentUiLang)); ?>,
    comment_delete: <?php echo json_encode(sf_term('comment_delete', $currentUiLang)); ?>,
    modal_comment_edit_title: <?php echo json_encode(sf_term('modal_comment_edit_title', $currentUiLang)); ?>,
    modal_comment_reply_title: <?php echo json_encode(sf_term('modal_comment_reply_title', $currentUiLang)); ?>,
    modal_comment_title: <?php echo json_encode(sf_term('modal_comment_title', $currentUiLang)); ?>,
    comments_empty: <?php echo json_encode(sf_term('comments_empty', $currentUiLang)); ?>,
    time_just_now: <?php echo json_encode(sf_term('time_just_now', $currentUiLang)); ?>,
    // Communications modal terms
    comms_summary_none: <?php echo json_encode(sf_term('comms_summary_none', $currentUiLang) ?? 'Ei valintoja'); ?>,
    comms_screens_all: <?php echo json_encode(sf_term('comms_screens_all', $currentUiLang) ?? 'Kaikki näytöt'); ?>,
    comms_all_countries: <?php echo json_encode(sf_term('comms_all_countries', $currentUiLang) ?? 'Kaikki maat'); ?>,
    comms_summary_worksites: <?php echo json_encode(sf_term('comms_summary_worksites', $currentUiLang) ?? 'työmaata'); ?>,
    comms_screens_selected: <?php echo json_encode(sf_term('comms_screens_selected', $currentUiLang) ?? 'Valitse työmaat'); ?>,
    comms_summary_no_distribution: <?php echo json_encode(sf_term('comms_summary_no_distribution', $currentUiLang) ?? 'Ei jakelulistoja'); ?>,
    comms_error_no_languages: <?php echo json_encode(sf_term('comms_error_no_languages', $currentUiLang) ?? 'Valitse vähintään yksi kieliversio'); ?>,
    comms_wider_distribution_yes: <?php echo json_encode(sf_term('comms_wider_distribution_yes', $currentUiLang) ?? 'Kyllä, lähetä laajempaan jakeluun'); ?>,
    comms_wider_distribution_no: <?php echo json_encode(sf_term('comms_wider_distribution_no', $currentUiLang) ?? 'Ei, vain valitut näytöt'); ?>,
    comms_summary_yes: <?php echo json_encode(sf_term('comms_summary_yes', $currentUiLang) ?? 'Kyllä'); ?>,
    comms_summary_no: <?php echo json_encode(sf_term('comms_summary_no', $currentUiLang) ?? 'Ei'); ?>,
    country_finland: <?php echo json_encode(sf_term('country_finland', $currentUiLang) ?? 'Suomi'); ?>,
    country_italy: <?php echo json_encode(sf_term('country_italy', $currentUiLang) ?? 'Italia'); ?>,
    country_greece: <?php echo json_encode(sf_term('country_greece', $currentUiLang) ?? 'Kreikka'); ?>,
    status_sending: <?php echo json_encode(sf_term('status_sending', $currentUiLang) ?? 'Lähetetään...'); ?>,
    error_sending: <?php echo json_encode(sf_term('error_sending', $currentUiLang) ?? 'Virhe lähetyksessä'); ?>,
    error_network: <?php echo json_encode(sf_term('error_network', $currentUiLang) ?? 'Verkkovirhe'); ?>,
    btn_send_comms: <?php echo json_encode(sf_term('btn_send_comms', $currentUiLang) ?? 'Lähetä viestintään'); ?>,
    log_delete_error: <?php echo json_encode(sf_term('log_delete_error', $currentUiLang)); ?>,
    log_deleted: <?php echo json_encode(sf_term('log_deleted', $currentUiLang)); ?>,
    // Extra images gallery terms
    extra_img_delete_confirm: <?php echo json_encode(sf_term('extra_img_delete_confirm', $currentUiLang)); ?>,
    delete_success: <?php echo json_encode(sf_term('delete_success', $currentUiLang)); ?>,
    delete_error: <?php echo json_encode(sf_term('delete_error', $currentUiLang)); ?>,
    unknown_error: <?php echo json_encode(sf_term('unknown_error', $currentUiLang)); ?>,
    select_image_files: <?php echo json_encode(sf_term('select_image_files', $currentUiLang)); ?>,
    images_loading_error: <?php echo json_encode(sf_term('images_loading_error', $currentUiLang)); ?>,
    images_uploading: <?php echo json_encode(sf_term('images_uploading', $currentUiLang)); ?>,
    upload_success: <?php echo json_encode(sf_term('upload_success', $currentUiLang)); ?>,
    upload_error: <?php echo json_encode(sf_term('upload_error', $currentUiLang)); ?>,
    upload_modal_title: <?php echo json_encode(sf_term('upload_modal_title', $currentUiLang)); ?>,
    upload_drag_text: <?php echo json_encode(sf_term('upload_drag_text', $currentUiLang)); ?>,
    btn_cancel: <?php echo json_encode(sf_term('btn_cancel', $currentUiLang)); ?>,
    btn_delete: <?php echo json_encode(sf_term('btn_delete', $currentUiLang)); ?>,
    // Translation confirmation modal terms
    type_red: <?php echo json_encode(sf_term('type_red', $currentUiLang)); ?>,
    type_yellow: <?php echo json_encode(sf_term('type_yellow', $currentUiLang)); ?>,
    type_green: <?php echo json_encode(sf_term('type_green', $currentUiLang)); ?>,
    confirm_creating_translation: <?php echo json_encode(sf_term('confirm_creating_translation', $currentUiLang)); ?>,
    // Publish summary terms
    publish_yes: <?php echo json_encode(sf_term('publish_yes', $currentUiLang) ?? '✅ Kyllä'); ?>
};
window.SF_FLASH_DATA      = <?php echo json_encode($flashDataForJs); ?>;
window.SF_SUPPORTED_LANGS = <?php echo json_encode($supportedLangs); ?>;
window.SF_CSRF_TOKEN      = <?php echo json_encode(sf_csrf_token()); ?>;
window.SF_ARCHIVE_BTN_TEXT = <?php echo json_encode(sf_term('btn_archive', $currentUiLang)); ?>;
window.SF_ARCHIVING_TEXT  = <?php echo json_encode(sf_term('archiving_in_progress', $currentUiLang) ?: 'Archiving...'); ?>;

// Käännökset translation.js:lle - kaikki tuetut kielet
window.SF_TRANSLATIONS = {
    metaLabels: {
        fi: { site: <?php echo json_encode(sf_term('preview_meta_site', 'fi')); ?>, date: <?php echo json_encode(sf_term('preview_meta_date', 'fi')); ?> },
        sv: { site: <?php echo json_encode(sf_term('preview_meta_site', 'sv')); ?>, date: <?php echo json_encode(sf_term('preview_meta_date', 'sv')); ?> },
        en: { site: <?php echo json_encode(sf_term('preview_meta_site', 'en')); ?>, date: <?php echo json_encode(sf_term('preview_meta_date', 'en')); ?> },
        it: { site: <?php echo json_encode(sf_term('preview_meta_site', 'it')); ?>, date: <?php echo json_encode(sf_term('preview_meta_date', 'it')); ?> },
        el: { site: <?php echo json_encode(sf_term('preview_meta_site', 'el')); ?>, date: <?php echo json_encode(sf_term('preview_meta_date', 'el')); ?> }
    },
    messages: {
        validationFillRequired: <?php echo json_encode(sf_term('validation_fill_required', $currentUiLang)); ?>,
        generatingImage: <?php echo json_encode(sf_term('generating_image', $currentUiLang)); ?>,
        saving: <?php echo json_encode(sf_term('status_saving', $currentUiLang)); ?>,
        translationSaved: <?php echo json_encode(sf_term('translation_saved', $currentUiLang)); ?>,
        errorPrefix: <?php echo json_encode(sf_term('error_prefix', $currentUiLang)); ?>,
        saveTranslationButton: <?php echo json_encode(sf_term('save_translation_button', $currentUiLang)); ?>
    }
};

// Hide loading spinner and fade in preview image
document.addEventListener('DOMContentLoaded', function() {
    const previewSpinner = document.getElementById('previewSpinner');
    const previewImages = document.querySelectorAll('.preview-box .preview-image');
    
    if (previewSpinner && previewImages.length > 0) {
        // Function to hide spinner and show image with fade-in
        const showImageFn = function(img) {
            previewSpinner.classList.add('loaded');
            img.classList.add('loaded');
        };
        
        previewImages.forEach(function(img) {
            // If image is already loaded (from cache)
            if (img.complete && img.naturalHeight !== 0) {
                showImageFn(img);
            } else {
                // Wait for image to load
                img.addEventListener('load', function() {
                    showImageFn(img);
                });
                // Handle error - still hide spinner
                img.addEventListener('error', function() {
                    previewSpinner.classList.add('loaded');
                });
            }
        });
        
        // Fallback: show everything after 3 seconds regardless
        setTimeout(function() {
            previewSpinner.classList.add('loaded');
            previewImages.forEach(function(img) {
                img.classList.add('loaded');
            });
        }, 3000);
    }

    // ===== COPY TO CLIPBOARD BUTTONS =====
    if (window.SafetyFlashCopy) {
        // Load translations for copy buttons
        window.SF_I18N = window.SF_I18N || {};
        window.SF_I18N.copy_image = <?php echo json_encode(sf_term('copy_image', $currentUiLang)); ?>;
        window.SF_I18N.report_generating = <?php echo json_encode(sf_term('report_generating', $currentUiLang)); ?>;
window.SF_I18N.report_success = <?php echo json_encode(sf_term('report_success', $currentUiLang)); ?>;
window.SF_I18N.report_error = <?php echo json_encode(sf_term('report_error', $currentUiLang)); ?>;
window.SF_I18N.report_button_loading = <?php echo json_encode(sf_term('report_button_loading', $currentUiLang)); ?>;
window.SF_I18N.report_button_done = <?php echo json_encode(sf_term('report_button_done', $currentUiLang)); ?>;
window.SF_I18N.report_button_error = <?php echo json_encode(sf_term('report_button_error', $currentUiLang)); ?>;
        window.SF_I18N.copying_image = <?php echo json_encode(sf_term('copying_image', $currentUiLang)); ?>;
        window.SF_I18N.image_copied = <?php echo json_encode(sf_term('image_copied', $currentUiLang)); ?>;
        window.SF_I18N.copy_failed = <?php echo json_encode(sf_term('copy_failed', $currentUiLang)); ?>;
        window.SF_I18N.preview_error = <?php echo json_encode(sf_term('preview_generation_error', $currentUiLang)); ?>;
        window.SF_I18N.refresh_page = <?php echo json_encode(sf_term('preview_refresh_page', $currentUiLang)); ?>;

        // Add copy button for card 1 (all flash types)
        const viewPreview1 = document.getElementById('viewPreview1');
        const viewPreviewImage = document.getElementById('viewPreviewImage');
        const previewBox = document.querySelector('.preview-box');
        
        if (viewPreview1) {
            // Tutkintatiedote with tabs - add button to card container
            window.SafetyFlashCopy.addCopyButton(viewPreview1, {
                label: window.SF_I18N.copy_image,
                copyingLabel: window.SF_I18N.copying_image,
                successMessage: window.SF_I18N.image_copied,
                errorMessage: window.SF_I18N.copy_failed,
                position: 'top-right'
            });
        } else if (viewPreviewImage && previewBox) {
            // Normal flash (red/yellow) - add button to preview-box
            window.SafetyFlashCopy.addCopyButton(previewBox, {
                label: window.SF_I18N.copy_image,
                copyingLabel: window.SF_I18N.copying_image,
                successMessage: window.SF_I18N.image_copied,
                errorMessage: window.SF_I18N.copy_failed,
                position: 'top-right'
            });
        }

        // Add copy button for card 2 (tutkintatiedote only, if exists)
        const viewPreview2 = document.getElementById('viewPreview2');
        if (viewPreview2 && viewPreview2.querySelector('img')) {
            window.SafetyFlashCopy.addCopyButton(viewPreview2, {
                label: window.SF_I18N.copy_image,
                copyingLabel: window.SF_I18N.copying_image,
                successMessage: window.SF_I18N.image_copied,
                errorMessage: window.SF_I18N.copy_failed,
                position: 'top-right'
            });
        }
    }
});
</script>
<!-- Preview Polling Module -->
<script src="<?= sf_asset_url('assets/js/preview-polling.js', $base) ?>"></script>
<script>
// Initialize polling for view page
document.addEventListener('DOMContentLoaded', function() {
    const previewBox = document.querySelector('.preview-box[data-preview-status]');
    if (!previewBox) return;
    
    const flashId = previewBox.dataset.flashId;
    const previewStatus = previewBox.dataset.previewStatus;
    const id = parseInt(flashId);
    
    if (!flashId || isNaN(id) || id <= 0) return;
    
    if (previewStatus === 'pending' || previewStatus === 'processing') {
        const progressBar = document.querySelector('.sf-preview-progress-bar');
        const progressText = document.querySelector('.sf-preview-progress-text');
        const pendingMessage = document.querySelector('.sf-preview-pending-message');
        const previewImage = document.querySelector('.preview-image');
        
        window.SFPreviewPolling.start(id, {
            onProgress: (id, progress, status) => {
                if (progressBar) {
                    progressBar.style.width = progress + '%';
                }
                if (progressText) {
                    progressText.textContent = progress + '%';
                }
            },
            onComplete: (id, previewUrl, previewUrl2) => {
                // Replace skeleton with actual image with fade-in
                const skeletonPlaceholder = document.querySelector('.skeleton-preview-placeholder');
                if (skeletonPlaceholder && previewUrl) {
                    const img = document.createElement('img');
                    img.src = previewUrl;
                    img.alt = 'Preview';
                    img.className = 'preview-image';
                    img.loading = 'eager';
                    img.style.opacity = '0';
                    
                    img.onload = () => {
                        skeletonPlaceholder.style.opacity = '0';
                        setTimeout(() => {
                            skeletonPlaceholder.parentNode.replaceChild(img, skeletonPlaceholder);
                            img.style.transition = 'opacity 0.5s ease';
                            img.style.opacity = '1';
                        }, 300);
                    };
                }
                
                // Update status attribute
                previewBox.dataset.previewStatus = 'completed';
            },
            onFailed: (id) => {
                if (progressText) {
                    progressText.textContent = window.SF_I18N?.preview_error || 'Error';
                }
                if (pendingMessage) {
                    pendingMessage.classList.add('sf-generating-failed');
                }
            },
            onTimeout: (id) => {
                if (progressText) {
                    progressText.textContent = window.SF_I18N?.refresh_page || 'Refresh page';
                }
            }
        });
    }
});

// ========== VERSION MODAL FUNCTIONS ==========
function openVersionModal(imagePath, versionType, publishedAt) {
    const modal = document.getElementById('versionModal');
    const title = document.getElementById('versionModalTitle');
    const date = document.getElementById('versionModalDate');
    const image = document.getElementById('versionModalImage');
    const downloadBtn = document.getElementById('versionDownloadBtn');
    
    title.textContent = versionType;
    
    // Format date
    const dateObj = new Date(publishedAt);
    const langCode = '<?= $currentUiLang ?>';
    date.textContent = '<?= htmlspecialchars(sf_term('version_published', $currentUiLang), ENT_QUOTES, 'UTF-8') ?> ' + 
                       dateObj.toLocaleString(langCode);
    
    image.src = imagePath;
    downloadBtn.href = imagePath;
    
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeVersionModal() {
    const modal = document.getElementById('versionModal');
    modal.classList.add('hidden');
    modal.style.display = 'none';
    document.body.style.overflow = '';
}

// ESC key closes version modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const versionModal = document.getElementById('versionModal');
        if (versionModal && !versionModal.classList.contains('hidden')) {
            closeVersionModal();
        }
    }
});

// ===== REVIEWER FUNCTIONALITY =====
(function() {
    'use strict';
    
    const flashId = <?= (int)$id ?>;
    const baseUrl = '<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>';
    const uiLang = '<?= htmlspecialchars($currentUiLang, ENT_QUOTES, 'UTF-8') ?>';
    
    // Translation terms
    const terms = {
        reviewerAdded: <?= json_encode(sf_term('reviewer_added', $currentUiLang), JSON_UNESCAPED_UNICODE) ?>,
        reviewerReplaced: <?= json_encode(sf_term('reviewer_replaced', $currentUiLang), JSON_UNESCAPED_UNICODE) ?>,
        reviewerRemoved: <?= json_encode(sf_term('reviewer_removed', $currentUiLang), JSON_UNESCAPED_UNICODE) ?>,
        reviewerError: <?= json_encode(sf_term('reviewer_error', $currentUiLang), JSON_UNESCAPED_UNICODE) ?>,
        reviewerRemoveConfirm: <?= json_encode(sf_term('reviewer_remove_confirm', $currentUiLang), JSON_UNESCAPED_UNICODE) ?>,
        errorPrefix: <?= json_encode(sf_term('error_prefix', $currentUiLang), JSON_UNESCAPED_UNICODE) ?>,
        errorNetwork: <?= json_encode(sf_term('error_network', $currentUiLang), JSON_UNESCAPED_UNICODE) ?>
    };
    
    // XSS prevention helper
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Show notification
    function showNotification(message, type = 'success') {
        // Use existing notification system if available
        if (window.sfShowNotification) {
            window.sfShowNotification(message, type);
        } else {
            alert(message);
        }
    }
    
    // Open modal helper
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('hidden');
            document.body.classList.add('sf-modal-open');
        }
    }
    
    // Close modal helper
    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('hidden');
            const openModals = document.querySelectorAll('.sf-modal:not(.hidden)');
            if (openModals.length === 0) {
                document.body.classList.remove('sf-modal-open');
            }
        }
    }
    
    // Fetch and refresh reviewer list
    function refreshReviewerList() {
        fetch(baseUrl + '/app/api/get_flash_reviewers.php?flash_id=' + flashId)
            .then(response => response.json())
            .then(data => {
                if (data.ok) {
                    updateReviewerDisplay(data.reviewers);
                }
            })
            .catch(err => {
                console.error('Error refreshing reviewer list:', err);
            });
    }
    
    // Update reviewer display
    function updateReviewerDisplay(reviewers) {
        const reviewerList = document.getElementById('reviewerList');
        const reviewerEmpty = document.getElementById('reviewerEmpty');
        
        if (reviewers && reviewers.length > 0) {
            if (reviewerEmpty) reviewerEmpty.classList.add('hidden');
            if (reviewerList) {
                reviewerList.innerHTML = '';
                reviewers.forEach(reviewer => {
                    const card = createReviewerCard(reviewer);
                    reviewerList.appendChild(card);
                });
                reviewerList.classList.remove('hidden');
            }
        } else {
            if (reviewerList) reviewerList.classList.add('hidden');
            if (reviewerEmpty) reviewerEmpty.classList.remove('hidden');
        }
    }
    
    // Create reviewer card element
    function createReviewerCard(reviewer) {
        const card = document.createElement('div');
        card.className = 'reviewer-card';
        card.dataset.userId = reviewer.user_id;
        
        const name = escapeHtml((reviewer.first_name || '') + ' ' + (reviewer.last_name || '')).trim();
        const email = escapeHtml(reviewer.email || '');
        const assignedAt = escapeHtml(reviewer.assigned_at_formatted || '');
        
        card.innerHTML = `
            <div class="reviewer-info">
                <div class="reviewer-name">${name || 'ID ' + reviewer.user_id}</div>
                ${email ? `<div class="reviewer-email">${email}</div>` : ''}
                ${assignedAt ? `<div class="reviewer-assigned"><?= htmlspecialchars(sf_term('reviewer_assigned_at', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>: ${assignedAt}</div>` : ''}
            </div>
            <?php if ($canManageReviewers): ?>
            <button type="button" class="reviewer-remove-btn" data-user-id="${reviewer.user_id}" data-flash-id="${flashId}" title="<?= htmlspecialchars(sf_term('remove_reviewer', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>">
                <svg viewBox="0 0 24 24" focusable="false">
                    <line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
            <?php endif; ?>
        `;
        
        return card;
    }
    
    // Search users for reviewers
    let searchTimeout = null;
    function setupUserSearch(inputId, dropdownId, selectedIdField, displayField) {
        const searchInput = document.getElementById(inputId);
        const dropdown = document.getElementById(dropdownId);
        const selectedId = document.getElementById(selectedIdField);
        const display = document.getElementById(displayField);
        
        if (!searchInput || !dropdown) return;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                dropdown.classList.add('hidden');
                dropdown.innerHTML = '';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                fetch(baseUrl + '/app/api/search_reviewers.php?query=' + encodeURIComponent(query))
                    .then(response => response.json())
                    .then(data => {
                        if (data.ok && data.users) {
                            displaySearchResults(data.users, dropdown, selectedId, display, searchInput);
                        }
                    })
                    .catch(err => {
                        console.error('Search error:', err);
                    });
            }, 300);
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });
    }
    
    // Display search results
    function displaySearchResults(users, dropdown, selectedIdField, displayField, searchInput) {
        if (users.length === 0) {
            dropdown.innerHTML = '<div class="sf-dropdown-item sf-dropdown-empty"><?= htmlspecialchars(sf_term('reviewer_no_users_found', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></div>';
            dropdown.classList.remove('hidden');
            return;
        }
        
        dropdown.innerHTML = users.map(user => {
            const name = escapeHtml(user.name || (user.first_name + ' ' + user.last_name));
            const email = escapeHtml(user.email || '');
            const roleName = escapeHtml(user.role_name || '');
            
            return `<div class="sf-dropdown-item" data-user-id="${user.id}" data-name="${name}" data-email="${email}">
                <div class="user-info">
                    <div class="user-name">${name}</div>
                    <div class="user-details">${email}${roleName ? ' - ' + roleName : ''}</div>
                </div>
            </div>`;
        }).join('');
        
        dropdown.classList.remove('hidden');
        
        // Handle selection
        dropdown.querySelectorAll('.sf-dropdown-item').forEach(item => {
            if (!item.classList.contains('sf-dropdown-empty')) {
                item.addEventListener('click', function() {
                    const userId = this.dataset.userId;
                    const userName = this.dataset.name;
                    const userEmail = this.dataset.email;
                    
                    selectedIdField.value = userId;
                    searchInput.value = '';
                    dropdown.classList.add('hidden');
                    
                    // Show selected user
                    displayField.innerHTML = `
                        <div class="selected-user-chip">
                            <span>${escapeHtml(userName)}</span>
                            <button type="button" class="remove-selection">×</button>
                        </div>
                    `;
                    displayField.classList.remove('hidden');
                    
                    // Handle removal
                    displayField.querySelector('.remove-selection').addEventListener('click', function() {
                        selectedIdField.value = '';
                        displayField.classList.add('hidden');
                        displayField.innerHTML = '';
                    });
                });
            }
        });
    }
    
    // Add reviewer button handlers
    document.querySelectorAll('.reviewer-action-btn[data-action="add"]').forEach(btn => {
        btn.addEventListener('click', function() {
            const selectedId = document.getElementById('selectedReviewerId');
            const display = document.getElementById('selectedReviewerDisplay');
            if (selectedId) selectedId.value = '';
            if (display) {
                display.classList.add('hidden');
                display.innerHTML = '';
            }
            openModal('modalAddReviewer');
        });
    });
    
    // Replace reviewer button handlers
    document.querySelectorAll('.reviewer-action-btn[data-action="replace"]').forEach(btn => {
        btn.addEventListener('click', function() {
            // Fetch current reviewers and display them
            fetch(baseUrl + '/app/api/get_flash_reviewers.php?flash_id=' + flashId)
                .then(response => response.json())
                .then(data => {
                    if (data.ok) {
                        const display = document.getElementById('currentReviewersDisplay');
                        if (display && data.reviewers && data.reviewers.length > 0) {
                            display.innerHTML = data.reviewers.map(r => {
                                const name = escapeHtml((r.first_name || '') + ' ' + (r.last_name || '')).trim();
                                return `<div class="current-reviewer-chip">${name || 'ID ' + r.user_id}</div>`;
                            }).join('');
                        } else if (display) {
                            display.innerHTML = '<div class="no-reviewers"><?= htmlspecialchars(sf_term('reviewer_no_reviewers', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></div>';
                        }
                    }
                });
            
            const selectedId = document.getElementById('selectedReviewerIdReplace');
            const display = document.getElementById('selectedReviewerDisplayReplace');
            if (selectedId) selectedId.value = '';
            if (display) {
                display.classList.add('hidden');
                display.innerHTML = '';
            }
            openModal('modalReplaceReviewer');
        });
    });
    
    // Remove reviewer button handlers (event delegation)
    document.addEventListener('click', function(e) {
        const removeBtn = e.target.closest('.reviewer-remove-btn');
        if (removeBtn) {
            const userId = removeBtn.dataset.userId;
            const flashId = removeBtn.dataset.flashId;
            
            if (confirm(terms.reviewerRemoveConfirm)) {
                removeReviewer(flashId, userId);
            }
        }
    });
    
    // Add reviewer action
    const btnAddReviewer = document.getElementById('btnAddReviewer');
    if (btnAddReviewer) {
        btnAddReviewer.addEventListener('click', function() {
            const userId = document.getElementById('selectedReviewerId').value;
            
            if (!userId) {
                showNotification('<?= htmlspecialchars(sf_term('reviewer_select_user', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>', 'warning');
                return;
            }
            
            const formData = new FormData();
            formData.append('flash_id', flashId);
            formData.append('user_id', userId);
            formData.append('csrf_token', window.SF_CSRF_TOKEN);
            
            fetch(baseUrl + '/app/api/add_reviewer.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.ok) {
                    showNotification(terms.reviewerAdded, 'success');
                    closeModal('modalAddReviewer');
                    refreshReviewerList();
                } else {
                    showNotification(terms.errorPrefix + ': ' + (data.error || terms.reviewerError), 'error');
                }
            })
            .catch(err => {
                console.error('Add reviewer error:', err);
                showNotification(terms.errorNetwork, 'error');
            });
        });
    }
    
    // Replace reviewer action
    const btnReplaceReviewer = document.getElementById('btnReplaceReviewer');
    if (btnReplaceReviewer) {
        btnReplaceReviewer.addEventListener('click', function() {
            const userId = document.getElementById('selectedReviewerIdReplace').value;
            
            if (!userId) {
                showNotification('<?= htmlspecialchars(sf_term('reviewer_select_user', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>', 'warning');
                return;
            }
            
            const formData = new FormData();
            formData.append('flash_id', flashId);
            formData.append('user_id', userId);
            formData.append('csrf_token', window.SF_CSRF_TOKEN);
            
            fetch(baseUrl + '/app/api/replace_reviewer.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.ok) {
                    showNotification(terms.reviewerReplaced, 'success');
                    closeModal('modalReplaceReviewer');
                    refreshReviewerList();
                } else {
                    showNotification(terms.errorPrefix + ': ' + (data.error || terms.reviewerError), 'error');
                }
            })
            .catch(err => {
                console.error('Replace reviewer error:', err);
                showNotification(terms.errorNetwork, 'error');
            });
        });
    }
    
    // Remove reviewer function
    function removeReviewer(flashId, userId) {
        const formData = new FormData();
        formData.append('flash_id', flashId);
        formData.append('user_id', userId);
        formData.append('csrf_token', window.SF_CSRF_TOKEN);
        
        fetch(baseUrl + '/app/api/remove_reviewer.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.ok) {
                showNotification(terms.reviewerRemoved, 'success');
                refreshReviewerList();
            } else {
                showNotification(terms.errorPrefix + ': ' + (data.error || terms.reviewerError), 'error');
            }
        })
        .catch(err => {
            console.error('Remove reviewer error:', err);
            showNotification(terms.errorNetwork, 'error');
        });
    }
    
    // Setup search functionality for both modals
    setupUserSearch('reviewerSearch', 'reviewerSearchDropdown', 'selectedReviewerId', 'selectedReviewerDisplay');
    setupUserSearch('reviewerSearchReplace', 'reviewerSearchReplaceDropdown', 'selectedReviewerIdReplace', 'selectedReviewerDisplayReplace');
    
})();
</script>

<!-- Image Lightbox Modal -->
<div class="image-lightbox" id="imageLightbox">
    <button class="image-lightbox-close" id="lightboxClose" aria-label="<?= htmlspecialchars(sf_term('btn_close', $currentUiLang) ?: 'Sulje', ENT_QUOTES, 'UTF-8') ?>">&times;</button>
    <div class="image-lightbox-content">
        <img id="lightboxImage" src="" alt="">
    </div>
</div>

<!-- Upload Modal -->
<div class="sf-modal hidden" id="uploadModal" role="dialog" aria-modal="true" aria-labelledby="uploadModalTitle">
    <div class="sf-modal-backdrop"></div>
    <div class="sf-modal-content sf-upload-modal-content">
        <div class="sf-modal-header">
            <h3 id="uploadModalTitle"><?= htmlspecialchars(sf_term('upload_modal_title', $currentUiLang) ?: 'Lisää kuvia', ENT_QUOTES, 'UTF-8') ?></h3>
            <button class="sf-modal-close" id="uploadModalClose" aria-label="<?= htmlspecialchars(sf_term('btn_close', $currentUiLang) ?: 'Sulje', ENT_QUOTES, 'UTF-8') ?>">&times;</button>
        </div>
        <div class="sf-modal-body">
            <div class="sf-upload-drop-zone" id="uploadDropZone">
                <div class="sf-upload-drop-icon">📁</div>
                <div class="sf-upload-drop-text"><?= htmlspecialchars(sf_term('upload_drag_text', $currentUiLang) ?: 'Vedä ja pudota kuvia tähän', ENT_QUOTES, 'UTF-8') ?></div>
                <div class="sf-upload-drop-hint"><?= htmlspecialchars(sf_term('or', $currentUiLang) ?: 'tai', ENT_QUOTES, 'UTF-8') ?></div>
                <button type="button" class="sf-btn sf-btn-primary sf-upload-browse-btn" id="uploadBrowseBtn">
                    <?= htmlspecialchars(sf_term('upload_browse_btn', $currentUiLang) ?: 'Lataa koneelta', ENT_QUOTES, 'UTF-8') ?>
                </button>
                <input type="file" id="uploadFileInput" accept="image/*" multiple style="display: none;">
            </div>
            <div class="sf-upload-progress" id="uploadProgress">
                <div class="sf-upload-progress-bar">
                    <div class="sf-upload-progress-fill" id="uploadProgressFill">0%</div>
                </div>
                <div class="sf-upload-progress-text" id="uploadProgressText"></div>
            </div>
        </div>
    </div>
</div>

<!-- Images Tab JavaScript -->
<script src="<?= sf_asset_url('assets/js/modules/extra_images_view.js', $base) ?>"></script>
<script>
(function() {
    'use strict';
    
    const flashId = <?= (int)$id ?>;
    const baseUrl = '<?= $base ?>';
    const canEdit = <?= json_encode($canEdit ?? false) ?>;
    const canAddExtraImages = <?= json_encode($canAddExtraImages ?? false) ?>;
    
    <?php
    // Build main images array for JavaScript
    $mainImages = [];
    $imageFields = [
        'image_main' => ['caption' => 'image1_caption', 'imageType' => 'main1'],
        'image_2' => ['caption' => 'image2_caption', 'imageType' => 'main2'],
        'image_3' => ['caption' => 'image3_caption', 'imageType' => 'main3']
    ];
    foreach ($imageFields as $field => $meta) {
        if (!empty($flash[$field])) {
            $filename = $flash[$field];
            $mainImages[] = [
                'url' => $base . '/uploads/images/' . $filename,
                // Main images use full-size for both URL and thumb_url since they're already optimized
                // and don't have separate thumbnails in the uploads/images directory
                'thumb_url' => $base . '/uploads/images/' . $filename,
                'isMain' => true,
                'filename' => $filename,
                'caption' => $flash[$meta['caption']] ?? '',
                'imageType' => $meta['imageType'],
                'flash_id' => (int)$id
            ];
        }
    }
    ?>
    const mainImages = <?= json_encode($mainImages) ?>;
    
    let imagesLoaded = false;
    
    // Set SF_BASE_URL for the module
    window.SF_BASE_URL = baseUrl;
    
    // Tab switching logic
    const tabs = document.querySelectorAll('.sf-activity-tab');
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const targetTab = this.dataset.tab;
            
            // Handle images tab activation (lazy loading)
            if (targetTab === 'images' && !imagesLoaded) {
                imagesLoaded = true;
                window.initExtraImages(flashId, canEdit, mainImages, canAddExtraImages);
            }
            
            // Update active states
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Show/hide tab content
            const allTabContent = document.querySelectorAll('.sf-tab-content');
            allTabContent.forEach(content => {
                content.classList.remove('active');
            });
            
            // Build target content ID: 'tab' + capitalized tab name (e.g., 'tabImages' for 'images')
            const targetContentId = 'tab' + targetTab.charAt(0).toUpperCase() + targetTab.slice(1);
            const targetContent = document.getElementById(targetContentId);
            if (targetContent) {
                targetContent.classList.add('active');
            }
        });
    });
    
    /**
     * Close lightbox
     */
    function closeLightbox() {
        const lightbox = document.getElementById('imageLightbox');
        if (lightbox) {
            lightbox.classList.remove('active');
        }
    }
    
    // Lightbox close handlers
    const lightboxClose = document.getElementById('lightboxClose');
    const lightbox = document.getElementById('imageLightbox');
    
    if (lightboxClose) {
        lightboxClose.addEventListener('click', closeLightbox);
    }
    
    if (lightbox) {
        // Close on background click
        lightbox.addEventListener('click', function(e) {
            if (e.target === lightbox) {
                closeLightbox();
            }
        });
        
        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && lightbox.classList.contains('active')) {
                closeLightbox();
            }
        });
    }
})();

// ===== PUBLISH SINGLE LANGUAGE VERSION MODAL =====
function openPublishSingleModal() {
    const modal = document.getElementById('publishSingleModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.classList.add('sf-modal-open');
    }
}

function closePublishSingleModal() {
    const modal = document.getElementById('publishSingleModal');
    if (modal) {
        modal.classList.add('hidden');
        const openModals = document.querySelectorAll('.sf-modal:not(.hidden)');
        if (openModals.length === 0) {
            document.body.classList.remove('sf-modal-open');
        }
    }
}
</script>

<!-- Image Captions Module -->
<script src="<?= sf_asset_url('assets/js/modules/image_captions.js', $base) ?>"></script>