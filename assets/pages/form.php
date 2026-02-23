<?php
// assets/pages/form.php
// THE COMPLETE, UNTRUNCATED, AND CORRECTED FILE
declare(strict_types=1);

require_once __DIR__ . '/../../app/includes/protect.php';
require_once __DIR__ . '/../../app/includes/statuses.php';

$base = rtrim($config['base_url'] ?? '/', '/');

// --- Työmaat kannasta (sf_worksites) ---
$worksites = [];

try {
    $worksites = Database::fetchAll(
        "SELECT id, name FROM sf_worksites WHERE is_active = 1 ORDER BY name ASC"
    );
} catch (Throwable $e) {
    error_log('form.php worksites error: ' . $e->getMessage());
    $worksites = [];
}

// --- Tutkintatiedotteen pohjana olevat julkaistut ensitiedotteet / vaaratilanteet ---
$relatedOptions = [];

try {
    $relatedOptions = Database::fetchAll("
        SELECT id, type, title, title_short, site, site_detail, description, 
               occurred_at, image_main, image_2, image_3,
               annotations_data, image1_transform, image2_transform, image3_transform,
               grid_layout, grid_bitmap, lang
        FROM sf_flashes
        WHERE state = 'published' 
          AND type IN ('red', 'yellow')
          AND (translation_group_id IS NULL OR translation_group_id = id)
        ORDER BY occurred_at DESC
    ");
} catch (Throwable $e) {
    error_log('form.php load related flashes error: ' . $e->getMessage());
}

// --- Load flash for editing if id is provided ---
$editing = false;
$flash   = [];
$editId  = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($editId > 0) {
    try {
        $flash = Database::fetchOne(
            "SELECT * FROM sf_flashes WHERE id = :id LIMIT 1",
            [':id' => $editId]
        );
        if ($flash) {
            $editing = true;
        } else {
            $flash = [];
        }
    } catch (Throwable $e) {
        error_log('form.php load flash error: ' . $e->getMessage());
    }
}

// --- Detect if editing a translation child ---
// A translation child is identified when editing and translation_group_id is set and != id
$isTranslationChild = false;
$sourceFlash = null;

if ($editing && !empty($flash['translation_group_id']) && (int)$flash['translation_group_id'] !== (int)$flash['id']) {
    $isTranslationChild = true;
    
    // Load the source flash (original or parent translation)
    $sourceFlashId = (int)$flash['translation_group_id'];
    try {
        $sourceFlash = Database::fetchOne(
            "SELECT * FROM sf_flashes WHERE id = :id LIMIT 1",
            [':id' => $sourceFlashId]
        );
    } catch (Throwable $e) {
        error_log('form.php load source flash error: ' . $e->getMessage());
    }
}

// Check editing lock when loading form for editing
$editingWarning = null;
if ($editing && $editId > 0) {
    $currentUserId = $_SESSION['user_id'] ?? 0;
    
    try {
        $pdo = Database::getInstance();
        $lockStmt = $pdo->prepare("
            SELECT f.editing_user_id, f.editing_started_at,
                   u.first_name, u.last_name
            FROM sf_flashes f
            LEFT JOIN sf_users u ON f.editing_user_id = u.id
            WHERE f.id = ?
        ");
        $lockStmt->execute([$editId]);
        $lockRow = $lockStmt->fetch();
        
        if ($lockRow && $lockRow['editing_user_id'] && 
            (int)$lockRow['editing_user_id'] !== (int)$currentUserId &&
            $lockRow['editing_started_at']) {
            
            $startedTime = strtotime($lockRow['editing_started_at']);
            if ($startedTime === false) {
                // Invalid datetime, skip lock check
                error_log('form.php: Invalid editing_started_at datetime');
            } else {
                $isExpired = (time() - $startedTime) > (15 * 60); // 15 min expiry
                
                if (!$isExpired) {
                    $editorName = trim($lockRow['first_name'] . ' ' . $lockRow['last_name']);
                    $minutesAgo = round((time() - $startedTime) / 60);
                    $editingWarning = [
                        'editor_name' => $editorName,
                        'minutes_ago' => $minutesAgo
                    ];
                }
            }
        }
    } catch (Throwable $e) {
        error_log('form.php editing lock check error: ' . $e->getMessage());
    }
}

// Check if user has unfinished drafts
$userDrafts = [];
$currentUser = sf_current_user();
if ($currentUser && !$editing) {
    try {
        $pdo = Database::getInstance();
        $draftStmt = $pdo->prepare("
            SELECT id, flash_type, form_data, updated_at 
            FROM sf_drafts 
            WHERE user_id = ? 
            ORDER BY updated_at DESC
        ");
        $draftStmt->execute([(int)$currentUser['id']]);
        $userDrafts = $draftStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('form.php drafts check error: ' . $e->getMessage());
    }
}
$hasDrafts = !empty($userDrafts);

$uiLang    = $_SESSION['ui_lang'] ?? 'fi';
$flashLang = $flash['lang'] ?? 'fi';

$termsData = sf_get_terms_config();
$configLanguages = $termsData['languages'] ?? ['fi'];

if (!in_array($flashLang, $configLanguages, true)) {
    $flashLang = 'fi';
}
if (!in_array($uiLang, $configLanguages, true)) {
    $uiLang = 'fi';
}

$term = function (string $key) use ($termsData, $uiLang): string {
    $t = $termsData['terms'][$key][$uiLang] ?? $termsData['terms'][$key]['fi'] ?? $key;
    return (string) $t;
};

$sfI18n = [
    // Editor / yleiset (nämä avaimet löytyy zipin safetyflash_terms.php:stä sellaisenaan)
    'IMAGE_EDIT_MAIN' => $term('IMAGE_EDIT_MAIN'),
    'IMAGE_EDIT_EXTRA_PREFIX' => $term('IMAGE_EDIT_EXTRA_PREFIX'),
    'IMAGE_SAVED' => $term('IMAGE_SAVED'),
    'LABEL_PROMPT' => $term('LABEL_PROMPT'),

    // Grid-asettelut: terms-tiedostossa nämä ovat lowercase-avaimilla (zipin app/config/safetyflash_terms.php)
    'GRID_LAYOUT_1'  => $term('grid_layout_1'),
    'GRID_LAYOUT_2A' => $term('grid_layout_2a'),
    'GRID_LAYOUT_2B' => $term('grid_layout_2b'),
    'GRID_LAYOUT_3A' => $term('grid_layout_3a'),
    'GRID_LAYOUT_3B' => $term('grid_layout_3b'),
    'GRID_LAYOUT_3C' => $term('grid_layout_3c'),

    // Help-tekstit: zipin terms-tiedostossa on grid_help ja img_edit_help
    'GRID_HELP' => $term('grid_help'),
    'EDITOR_HELP_PLACE' => $term('img_edit_help'),
    
    // Progress-viestit
    'processing_flash' => $term('processing_flash'),
];
// --- Esitäytettävät arvot ---
$title            = $flash['title'] ?? '';
$title_short      = $flash['title_short'] ?? ($flash['summary'] ?? '');
$short_text       = $title_short;
$summary          = $flash['summary'] ?? '';
$description      = $flash['description'] ?? '';
$root_causes      = $flash['root_causes'] ?? '';
$actions          = $flash['actions'] ?? '';
$worksite_val     = $flash['site'] ?? '';
$site_detail_val  = $flash['site_detail'] ?? '';
$event_date_val   = !empty($flash['occurred_at']) ? date('Y-m-d\TH:i', strtotime($flash['occurred_at'])) : '';
$type_val         = $flash['type'] ?? '';
$state_val        = $flash['state'] ?? '';
$preview_filename = $flash['preview_filename'] ?? '';
$image_main       = $flash['image_main'] ?? '';

// Mahdolliset transform-arvot (JSON) kolmelle kuvalle
$image1_transform = $flash['image1_transform'] ?? '';
$image2_transform = $flash['image2_transform'] ?? '';
$image3_transform = $flash['image3_transform'] ?? '';

// initial step param (optional)
$initialStep = isset($_GET['step']) ? (int) $_GET['step'] : 1;

// Kuvapolku muokkaustilassa
$getImageUrl = function ($filename) use ($base) {
    $filename = is_string($filename) ? basename($filename) : '';
    if (empty($filename)) {
        return "{$base}/assets/img/camera-placeholder.png";
    }
    $path = "uploads/images/{$filename}";
    if (file_exists(__DIR__ . "/../../{$path}")) {
        return "{$base}/{$path}";
    }
    $oldPath = "img/{$filename}";
    if (file_exists(__DIR__ . "/../../{$oldPath}")) {
        return "{$base}/{$oldPath}";
    }
    return "{$base}/assets/img/camera-placeholder.png";
};
?>
<?php if ($hasDrafts && !$editing): ?>
<div id="sfDraftRecoveryOverlay" class="sf-draft-overlay">
    <div class="sf-draft-modal">
        <h2><?= htmlspecialchars(sf_term('draft_recovery_title', $uiLang)) ?></h2>
        <p><?= htmlspecialchars(sf_term('draft_recovery_message', $uiLang)) ?></p>
        
        <div class="sf-draft-list">
            <?php foreach ($userDrafts as $draft): 
                $draftData = json_decode($draft['form_data'], true);
                $draftType = $draft['flash_type'] ?? 'unknown';
                // Normalize: remove possible 'type_' prefix
                $draftType = preg_replace('/^type_/', '', $draftType);
                $draftDate = date('d.m.Y H:i', strtotime($draft['updated_at']));
            ?>
            <div class="sf-draft-item" data-draft-id="<?= (int)$draft['id'] ?>">
                <div class="sf-draft-info">
                    <span class="sf-draft-type sf-type-<?= in_array($draftType, ['red', 'yellow', 'green'], true) ? htmlspecialchars($draftType) : 'unknown' ?>">
<?php 
$typeLabels = [
    'red' => sf_term('first_release', $uiLang) ?: 'Ensitiedote',
    'yellow' => sf_term('dangerous_situation', $uiLang) ?: 'Vaaratilanne', 
    'green' => sf_term('investigation_report', $uiLang) ?: 'Tutkintatiedote',
];
$typeLabel = $typeLabels[$draftType] ?? ucfirst($draftType);
?>
<?= htmlspecialchars($typeLabel) ?>
                    </span>
                    <span class="sf-draft-date"><?= htmlspecialchars($draftDate) ?></span>
                </div>
                <div class="sf-draft-actions">
                    <button type="button" class="sf-btn sf-btn-primary sf-draft-continue" 
                            data-draft-id="<?= (int)$draft['id'] ?>">
                        <?= htmlspecialchars(sf_term('draft_continue', $uiLang)) ?>
                    </button>
                    <button type="button" class="sf-btn sf-btn-secondary sf-draft-discard"
                            data-draft-id="<?= (int)$draft['id'] ?>">
                        <?= htmlspecialchars(sf_term('draft_discard', $uiLang)) ?>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="sf-draft-new">
            <button type="button" class="sf-btn sf-btn-outline" id="sfDraftStartNew">
                <?= htmlspecialchars(sf_term('draft_start_new', $uiLang)) ?>
            </button>
        </div>
    </div>
</div>
<script>
window.SF_USER_DRAFTS = <?= json_encode($userDrafts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<?php endif; ?>

<?php if ($editing && $editId > 0): ?>
<script>
window.SF_FLASH_ID = <?= (int)$editId ?>;
</script>
<?php endif; ?>

<?php if ($editingWarning): ?>
<div id="editingWarningBanner" class="sf-editing-warning">
    <div class="sf-editing-warning-content">
        <img src="<?= $base ?>/assets/img/icons/warning.svg" alt="Warning" class="sf-editing-warning-icon" style="width: 24px; height: 24px; color: #ff9800;">
        <span class="sf-editing-warning-text">
            <?= htmlspecialchars($editingWarning['editor_name']) ?> 
            <?= htmlspecialchars(sf_term('editing_this_flash', $uiLang) ?? 'muokkaa tätä tiedotetta') ?>
            (<?= htmlspecialchars(sf_term('started', $uiLang) ?? 'aloitettu') ?> 
            <?= (int)$editingWarning['minutes_ago'] ?> min <?= htmlspecialchars(sf_term('ago', $uiLang) ?? 'sitten') ?>)
        </span>
        <div class="sf-editing-warning-actions">
            <button type="button" class="sf-btn sf-btn-warning" onclick="continueEditing()">
                <?= htmlspecialchars(sf_term('continue_anyway', $uiLang) ?? 'Jatka silti') ?>
            </button>
            <button type="button" class="sf-btn sf-btn-secondary" onclick="cancelEditing()">
                <?= htmlspecialchars(sf_term('btn_cancel', $uiLang) ?? 'Peruuta') ?>
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($isTranslationChild): ?>
<div class="sf-translation-mode-banner" style="background-color: #e3f2fd; border-left: 4px solid #2196f3; padding: 16px; margin-bottom: 20px; border-radius: 4px;">
    <div style="display: flex; align-items: center; gap: 12px;">
        <img src="<?= $base ?>/assets/img/icons/info.svg" alt="Information" style="width: 24px; height: 24px; flex-shrink: 0;">
        <div>
            <strong style="color: #1976d2; font-size: 16px;">
                <?= htmlspecialchars(sf_term('translation_mode_title', $uiLang) ?? 'Muokkaat tämän SafetyFlashin kieliversiota', ENT_QUOTES, 'UTF-8') ?>
            </strong>
            <p style="margin: 4px 0 0 0; color: #424242; font-size: 14px;">
                <?php 
                $supportedLangs = [
                    'fi' => 'Suomi',
                    'sv' => 'Ruotsi', 
                    'en' => 'Englanti',
                    'it' => 'Italia',
                    'el' => 'Kreikka',
                ];
                $langLabel = $supportedLangs[$flashLang] ?? strtoupper($flashLang);
                
                // Display type with emoji
                $typeEmojis = [
                    'red' => '🔴',
                    'yellow' => '🟡',
                    'green' => '🟢'
                ];
                $typeLabels = [
                    'red' => 'Ensitiedote',
                    'yellow' => 'Vaaratilanne',
                    'green' => 'Tutkintatiedote'
                ];
                
                $typeEmoji = $typeEmojis[$sourceFlash['type'] ?? ''] ?? '';
                $typeLabel = $typeLabels[$sourceFlash['type'] ?? ''] ?? ($sourceFlash['type'] ?? '');
                
                echo htmlspecialchars(sf_term('translation_mode_message', $uiLang) ?? 'Luot kieliversiota kielelle', ENT_QUOTES, 'UTF-8');
                ?>: <strong><?= htmlspecialchars($langLabel) ?></strong>
                <?php if ($sourceFlash): ?>
                    <br>
                    <span style="color: #666; font-size: 13px;">
                        Tiedotteesta: <strong>"<?= htmlspecialchars($sourceFlash['title'] ?? '') ?>"</strong>
                        (ID #<?= (int)$sourceFlash['id'] ?>, <?= $typeEmoji ?> <?= htmlspecialchars($typeLabel) ?>, <?= htmlspecialchars($sourceFlash['site'] ?? '') ?>)
                    </span>
                <?php endif; ?>
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

<form
  id="sf-form"
  method="post"
  action="<?php echo $base; ?>/app/api/save_flash.php"
  class="sf-form"
  enctype="multipart/form-data"
  novalidate
>
  <!-- Mobile close button (only visible on mobile) -->
  <a href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/index.php?page=list" 
     class="sf-form-close-mobile"
     aria-label="<?= htmlspecialchars(sf_term('btn_close_form', $uiLang) ?: 'Sulje lomake', ENT_QUOTES, 'UTF-8') ?>">
      <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round">
          <line x1="4.5" y1="4.5" x2="13.5" y2="13.5"/>
          <line x1="13.5" y1="4.5" x2="4.5" y2="13.5"/>
      </svg>
  </a>
  <?= sf_csrf_field() ?>
  <?php if ($editing): ?>
    <input type="hidden" name="id" value="<?= (int) $editId ?>">
  <?php endif; ?>
  <?php if ($isTranslationChild): ?>
    <input type="hidden" name="is_translation_child" value="1">
    <input type="hidden" name="type" value="<?= htmlspecialchars($flash['type'] ?? 'yellow', ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="lang" value="<?= htmlspecialchars($flash['lang'] ?? 'fi', ENT_QUOTES, 'UTF-8') ?>">
  <?php endif; ?>
  <input type="hidden" id="initialStep" value="<?= (int) $initialStep ?>">
  
  <!-- State field for request_info resubmission detection -->
  <?php if ($editing && $state_val === 'request_info'): ?>
  <input type="hidden" name="state" value="request_info">
  <?php endif; ?>
  
  <!-- Related flash ID tutkintatiedotteelle (päivittää alkuperäisen) -->
  <input type="hidden" id="sf-related-flash-id" value="<?= htmlspecialchars($flash['related_flash_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

  <!-- Modern Navigable Progress Bar (NO wrapper div) -->
  <nav class="sf-form-progress" aria-label="<?= htmlspecialchars(sf_term('form_progress_label', $uiLang) ?: 'Lomakkeen vaiheet', ENT_QUOTES, 'UTF-8') ?>">
      <div class="sf-form-progress__track" role="progressbar" aria-valuenow="<?= (int) $initialStep ?>" aria-valuemin="1" aria-valuemax="6">
        <div class="sf-form-progress__fill" id="sfProgressFill"></div>
      </div>
      <div class="sf-form-progress__steps">
        <button type="button" class="sf-form-progress__step" data-step="1" title="<?= htmlspecialchars(sf_term('step1_heading', $uiLang), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars(sf_term('step1_heading', $uiLang), ENT_QUOTES, 'UTF-8') ?>">
          <span class="sf-form-progress__number" aria-hidden="true" data-step-num="1"></span>
          <span class="sf-form-progress__label"><?= htmlspecialchars(sf_term('step1_short', $uiLang) ?: 'Tyyppi', ENT_QUOTES, 'UTF-8') ?></span>
        </button>
        <button type="button" class="sf-form-progress__step" data-step="2" title="<?= htmlspecialchars(sf_term('step2_heading', $uiLang), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars(sf_term('step2_heading', $uiLang), ENT_QUOTES, 'UTF-8') ?>">
          <span class="sf-form-progress__number" aria-hidden="true" data-step-num="2"></span>
          <span class="sf-form-progress__label"><?= htmlspecialchars(sf_term('step2_short', $uiLang) ?: 'Konteksti', ENT_QUOTES, 'UTF-8') ?></span>
        </button>
        <button type="button" class="sf-form-progress__step" data-step="3" title="<?= htmlspecialchars(sf_term('step3_heading', $uiLang), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars(sf_term('step3_heading', $uiLang), ENT_QUOTES, 'UTF-8') ?>">
          <span class="sf-form-progress__number" aria-hidden="true" data-step-num="3"></span>
          <span class="sf-form-progress__label"><?= htmlspecialchars(sf_term('step3_short', $uiLang) ?: 'Sisältö', ENT_QUOTES, 'UTF-8') ?></span>
        </button>
        <button type="button" class="sf-form-progress__step" data-step="4" title="<?= htmlspecialchars(sf_term('step4_heading', $uiLang), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars(sf_term('step4_heading', $uiLang), ENT_QUOTES, 'UTF-8') ?>">
          <span class="sf-form-progress__number" aria-hidden="true" data-step-num="4"></span>
          <span class="sf-form-progress__label"><?= htmlspecialchars(sf_term('step4_short', $uiLang) ?: 'Kuvat', ENT_QUOTES, 'UTF-8') ?></span>
        </button>
        <button type="button" class="sf-form-progress__step" data-step="5" title="<?= htmlspecialchars(sf_term('step5_heading', $uiLang) ?: 'Asettelu', ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars(sf_term('step5_heading', $uiLang) ?: 'Asettelu', ENT_QUOTES, 'UTF-8') ?>">
          <span class="sf-form-progress__number" aria-hidden="true" data-step-num="5"></span>
          <span class="sf-form-progress__label"><?= htmlspecialchars(sf_term('step5_short', $uiLang) ?: 'Asettelu', ENT_QUOTES, 'UTF-8') ?></span>
        </button>
        <button type="button" class="sf-form-progress__step" data-step="6" title="<?= htmlspecialchars(sf_term('step6_heading', $uiLang) ?: 'Esikatselu', ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars(sf_term('step6_heading', $uiLang) ?: 'Esikatselu', ENT_QUOTES, 'UTF-8') ?>">
          <span class="sf-form-progress__number" aria-hidden="true" data-step-num="6"></span>
          <span class="sf-form-progress__label">
            <?php if ($isTranslationChild): ?>
              <?= htmlspecialchars(sf_term('preview_label', $uiLang) ?: 'Esikatselu', ENT_QUOTES, 'UTF-8') ?>
            <?php else: ?>
              <?= htmlspecialchars(sf_term('step6_short', $uiLang) ?: 'Lähetä', ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
          </span>
        </button>
      </div>
    </nav>

  <!-- VAIHE 1: tyyppivalinta ja kieli (TYPE FIRST) -->
  <div class="sf-step-content active" data-step="1">
    <h2><?= htmlspecialchars(sf_term('step1_heading', $uiLang), ENT_QUOTES, 'UTF-8') ?></h2>
    
    <!-- Tyyppivalinta (NOW FIRST) -->
    <h3><?= htmlspecialchars(sf_term('type_selection_label', $uiLang), ENT_QUOTES, 'UTF-8') ?></h3>

    <div class="sf-type-selection" role="radiogroup" aria-label="Valitse tiedotteen tyyppi">

      <!-- RED -->
      <label class="sf-type-box" data-type="red">
        <input type="radio" name="type" value="red" <?= $type_val === 'red' ? 'checked' : '' ?> <?= $isTranslationChild ? 'disabled' : '' ?>>
        <div class="sf-type-box-content">
          <img src="<?= $base ?>/assets/img/icon-red.png" alt="" class="sf-type-icon" aria-hidden="true">
          <div class="sf-type-text">
            <h4 class="sf-type-title">
              <?= htmlspecialchars(sf_term('first_release', $uiLang), ENT_QUOTES, 'UTF-8') ?>
            </h4>
            <p><?= htmlspecialchars(sf_term('type_red_desc', $uiLang), ENT_QUOTES, 'UTF-8') ?></p>
          </div>
        </div>
        <img src="<?= $base ?>/assets/img/icons/checkmark_icon.png" alt="Valittu" class="sf-type-checkmark">
      </label>

      <!-- YELLOW -->
      <label class="sf-type-box" data-type="yellow">
        <input type="radio" name="type" value="yellow" <?= $type_val === 'yellow' ? 'checked' : '' ?> <?= $isTranslationChild ? 'disabled' : '' ?>>
        <div class="sf-type-box-content">
          <img src="<?= $base ?>/assets/img/icon-yellow.png" alt="" class="sf-type-icon" aria-hidden="true">
          <div class="sf-type-text">
            <h4 class="sf-type-title">
              <?= htmlspecialchars(sf_term('dangerous_situation', $uiLang), ENT_QUOTES, 'UTF-8') ?>
            </h4>
            <p><?= htmlspecialchars(sf_term('type_yellow_desc', $uiLang), ENT_QUOTES, 'UTF-8') ?></p>
          </div>
        </div>
        <img src="<?= $base ?>/assets/img/icons/checkmark_icon.png" alt="Valittu" class="sf-type-checkmark">
      </label>

      <!-- GREEN -->
      <label class="sf-type-box" data-type="green">
        <input type="radio" name="type" value="green" <?= $type_val === 'green' ? 'checked' : '' ?> <?= $isTranslationChild ? 'disabled' : '' ?>>
        <div class="sf-type-box-content">
          <img src="<?= $base ?>/assets/img/icon-green.png" alt="" class="sf-type-icon" aria-hidden="true">
          <div class="sf-type-text">
            <h4 class="sf-type-title">
              <?= htmlspecialchars(sf_term('investigation_report', $uiLang), ENT_QUOTES, 'UTF-8') ?>
            </h4>
            <p><?= htmlspecialchars(sf_term('type_green_desc', $uiLang), ENT_QUOTES, 'UTF-8') ?></p>
          </div>
        </div>
        <img src="<?= $base ?>/assets/img/icons/checkmark_icon.png" alt="Valittu" class="sf-type-checkmark">
      </label>

    </div>

    <hr class="sf-divider" id="sf-lang-divider">

    <!-- Kielivalinta (NOW SECOND, after type) -->
    <div class="sf-lang-selection" id="sf-lang-selection">
      <label class="sf-label"><?= htmlspecialchars(sf_term('lang_selection_label', $uiLang), ENT_QUOTES, 'UTF-8') ?></label>
      <div class="sf-lang-options">
<?php
        $langOptions = [
            'fi' => ['label' => 'Suomi',    'flag' => 'finnish-flag.png'],
            'sv' => ['label' => 'Svenska',  'flag' => 'swedish-flag.png'],
            'en' => ['label' => 'English',  'flag' => 'english-flag.png'],
            'it' => ['label' => 'Italiano', 'flag' => 'italian-flag.png'],
            'el' => ['label' => 'Ελληνικά', 'flag' => 'greece-flag.png'],
        ];
        $selectedLang = $flash['lang'] ?? 'fi';
        foreach ($langOptions as $langCode => $langData):
        ?>
          <label class="sf-lang-box" data-lang="<?php echo $langCode; ?>">
            <input type="radio" name="lang" value="<?php echo $langCode; ?>" <?php echo $selectedLang === $langCode ? 'checked' : ''; ?> <?= $isTranslationChild ? 'disabled' : '' ?>>
            <div class="sf-lang-box-content">
              <img src="<?php echo $base; ?>/assets/img/<?php echo $langData['flag']; ?>" alt="<?php echo $langData['label']; ?>" class="sf-lang-flag">
              <span class="sf-lang-label"><?php echo htmlspecialchars($langData['label']); ?></span>
            </div>
          </label>
        <?php endforeach; ?>
      </div>
      <p class="sf-help-text"><?= htmlspecialchars(sf_term('lang_selection_help', $uiLang), ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <!-- Vaihe 1 napit (alhaalla) -->
<div class="sf-step-actions sf-step-actions-bottom">
  <button
    type="button"
    id="sfNext"
    class="sf-btn sf-btn-primary sf-next-btn disabled"
    disabled
    aria-disabled="true"
  >
    <?= htmlspecialchars(sf_term('btn_next', $uiLang), ENT_QUOTES, 'UTF-8'); ?>
  </button>
</div>
  </div>

  <!-- VAIHE 2: konteksti -->
  <div class="sf-step-content" data-step="2">
    <h2><?= htmlspecialchars(sf_term('step2_heading', $uiLang), ENT_QUOTES, 'UTF-8') ?></h2>

    <div id="sf-step2-incident" class="sf-step2-section">
      <div class="sf-field">
        <label for="sf-related-flash" class="sf-label">
          <?= htmlspecialchars(sf_term('related_flash_label', $uiLang), ENT_QUOTES, 'UTF-8') ?>
        </label>
        <select name="related_flash_id" id="sf-related-flash" class="sf-select">
          <option value="">
            <?= htmlspecialchars(sf_term('related_flash_placeholder', $uiLang), ENT_QUOTES, 'UTF-8') ?>
          </option>
          <?php 
              // Kielilippujen määritys
              $langFlags = [
                  'fi' => '🇫🇮',
                  'sv' => '🇸🇪',
                  'en' => '🇬🇧',
                  'it' => '🇮🇹',
                  'el' => '🇬🇷',
              ];
              
              foreach ($relatedOptions as $opt):
              $optDate = !empty($opt['occurred_at'])
                  ? date('d.m.Y', strtotime($opt['occurred_at']))
                  : '–';

              $optSite  = $opt['site'] ?? '–';
              $optTitle = $opt['title'] ?? $opt['title_short'] ?? '–';

              // Väripallo tyypin mukaan
              $colorDot = ($opt['type'] === 'red') ? '🔴' :  '🟡';
              
              // Kielilippu
              $optLang = $opt['lang'] ?? 'fi';
              $langFlag = $langFlags[$optLang] ?? '🇫🇮';

              // Muoto: väripallo + kielilippu + päivämäärä + työmaa + otsikko
              $optLabel = "{$colorDot} {$langFlag} {$optDate} – {$optSite} – {$optTitle}";
          ?>
            <option
              value="<?= (int) $opt['id'] ?>"
              data-site="<?= htmlspecialchars($opt['site'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              data-site-detail="<?= htmlspecialchars($opt['site_detail'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              data-date="<?= htmlspecialchars($opt['occurred_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              data-title="<?= htmlspecialchars($opt['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              data-title-short="<?= htmlspecialchars($opt['title_short'] ??  '', ENT_QUOTES, 'UTF-8') ?>"
              data-description="<?= htmlspecialchars($opt['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              data-image-main="<?= htmlspecialchars($opt['image_main'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              data-image-2="<?= htmlspecialchars($opt['image_2'] ??  '', ENT_QUOTES, 'UTF-8') ?>"
              data-image-3="<?= htmlspecialchars($opt['image_3'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              data-annotations-data="<?= htmlspecialchars($opt['annotations_data'] ?? '{}', ENT_QUOTES, 'UTF-8') ?>"
              data-image1-transform="<?= htmlspecialchars($opt['image1_transform'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              data-image2-transform="<?= htmlspecialchars($opt['image2_transform'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              data-image3-transform="<?= htmlspecialchars($opt['image3_transform'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              data-grid-layout="<?= htmlspecialchars($opt['grid_layout'] ?? 'grid-1', ENT_QUOTES, 'UTF-8') ?>"
              data-grid-bitmap="<?= htmlspecialchars($opt['grid_bitmap'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              <?= (isset($flash['related_flash_id']) && (int) $flash['related_flash_id'] === (int) $opt['id']) ? 'selected' :  '' ?>
            >
              <?= htmlspecialchars($optLabel, ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
        <p class="sf-help-text" id="sf-related-flash-help">
          <?= htmlspecialchars(sf_term('related_flash_help', $uiLang), ENT_QUOTES, 'UTF-8') ?>
        </p>
      </div>

      <!-- Standalone investigation toggle -->
      <div class="sf-field sf-standalone-toggle-wrapper" id="sf-standalone-option">
        <div class="sf-toggle-container">
          <label class="sf-toggle" for="sf-standalone-investigation">
            <input type="checkbox" id="sf-standalone-investigation" name="standalone_investigation" value="1">
            <span class="sf-toggle-slider"></span>
          </label>
          <div class="sf-toggle-labels">
            <span class="sf-toggle-label-main"><?= htmlspecialchars(sf_term('standalone_investigation_label', $uiLang), ENT_QUOTES, 'UTF-8') ?></span>
            <span class="sf-toggle-label-help"><?= htmlspecialchars(sf_term('standalone_investigation_help', $uiLang), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Alkuperäisen tiedotteen kompakti näkymä (näkyy kun related flash valittu) -->
    <div id="sf-original-flash-preview" class="sf-original-flash-compact hidden">
      <img src="<?= $base ?>/assets/img/icon-yellow.png" alt="" class="sf-original-icon" id="sf-original-icon">
      <div class="sf-original-info">
        <span class="sf-original-title" id="sf-original-title">--</span>
        <span class="sf-original-meta">
          <span id="sf-original-site">--</span>
          <span id="sf-original-date">--</span>
        </span>
      </div>
    </div>

    <!-- Tutkintatiedotteen osio (ei tarvitse erillistä info-tekstiä) -->
    <div id="sf-step2-investigation-worksite" class="sf-step2-section"></div>

<!-- Työmaa ja päivämäärä - käytetään KAIKILLE tyypeille (red, yellow, green) -->
<!-- For green type (investigation), hidden by default until user selects base flash or standalone -->
<!-- In edit mode, always show the fields -->
<div id="sf-step2-worksite" class="sf-step2-section sf-investigation-fields<?= ($type_val === 'green' && !$editing) ? ' hidden' : '' ?>">
  <div class="sf-field-row">
    <div class="sf-field">
      <label for="sf-worksite" class="sf-label">
        <?= htmlspecialchars(sf_term('site_label', $flashLang), ENT_QUOTES, 'UTF-8') ?>
      </label>
      <select name="worksite" id="sf-worksite" class="sf-select" <?= $isTranslationChild ? 'disabled' : '' ?>>
        <option value="">
          <?= htmlspecialchars(sf_term('worksite_select_placeholder', $uiLang), ENT_QUOTES, 'UTF-8') ?>
        </option>
        <?php foreach ($worksites as $site): ?>
          <option
            value="<?= htmlspecialchars($site['name'], ENT_QUOTES, 'UTF-8') ?>"
            <?= $worksite_val === $site['name'] ? 'selected' : '' ?>
          >
            <?= htmlspecialchars($site['name'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="sf-field">
      <label for="sf-site-detail" class="sf-label">
        <?= htmlspecialchars(sf_term('site_detail_label', $uiLang), ENT_QUOTES, 'UTF-8') ?>
      </label>
      <input
        type="text"
        name="site_detail"
        id="sf-site-detail"
        class="sf-input"
        placeholder="<?= htmlspecialchars(sf_term('site_detail_placeholder', $uiLang), ENT_QUOTES, 'UTF-8') ?>"
        value="<?= htmlspecialchars($site_detail_val, ENT_QUOTES, 'UTF-8') ?>"
        <?= $isTranslationChild ? 'readonly' : '' ?>
      >
    </div>
  </div>

  <div class="sf-field-row">
    <div class="sf-field">
      <label for="sf-date" class="sf-label">
        <?= htmlspecialchars(sf_term('when_label', $uiLang), ENT_QUOTES, 'UTF-8') ?>
      </label>
      <input
        type="datetime-local"
        name="event_date"
        id="sf-date"
        class="sf-input"
        required
        max="<?= date('Y-m-d\TH:i') ?>"
        step=""
        value="<?= htmlspecialchars($event_date_val, ENT_QUOTES, 'UTF-8') ?>"
        <?= $isTranslationChild ? 'readonly' : '' ?>
      >
    </div>
  </div>

  <p class="sf-help-text">
    <?= htmlspecialchars(sf_term('step2_help', $uiLang), ENT_QUOTES, 'UTF-8') ?>
  </p>
</div>

    <!-- Vaihe 2 napit -->
    <div class="sf-step-actions sf-step-actions-bottom">
<button type="button" id="sfPrev" class="sf-btn sf-btn-secondary sf-prev-btn">
  <?= htmlspecialchars(sf_term('btn_prev', $uiLang), ENT_QUOTES, 'UTF-8'); ?>
</button>
<button type="button" class="sf-btn sf-btn-primary sf-next-btn">
  <?= htmlspecialchars(sf_term('btn_next', $uiLang), ENT_QUOTES, 'UTF-8'); ?>
</button>
    </div>
  </div>

  <!-- VAIHE 3: itse sisältö -->
  <div class="sf-step-content" data-step="3">
    <h2><?= htmlspecialchars(sf_term('step3_heading', $uiLang), ENT_QUOTES, 'UTF-8') ?></h2>

    <div class="sf-field">
      <label for="sf-title" class="sf-label">
        <?= htmlspecialchars(sf_term('title_internal_label', $uiLang), ENT_QUOTES, 'UTF-8') ?>
      </label>
      <input
        type="text"
        name="title"
        id="sf-title"
        class="sf-input"
        required
        placeholder="<?= htmlspecialchars(sf_term('title_internal_placeholder', $uiLang), ENT_QUOTES, 'UTF-8') ?>"
        value="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>"
      >
    </div>

    <div class="sf-field">
      <label for="sf-short-text" class="sf-label">
        <?= htmlspecialchars(sf_term('short_title_label', $uiLang), ENT_QUOTES, 'UTF-8') ?>
      </label>
      <textarea
        name="short_text"
        id="sf-short-text"
        class="sf-textarea"
        rows="2"
        required
        maxlength="85"
        placeholder="<?= htmlspecialchars(sf_term('short_text_placeholder', $uiLang), ENT_QUOTES, 'UTF-8') ?>"
      ><?= htmlspecialchars($short_text, ENT_QUOTES, 'UTF-8') ?></textarea>
      <p class="sf-char-count"><span id="sf-short-text-count">0</span>/85</p>
    </div>

    <div class="sf-field">
      <label for="sf-description" class="sf-label">
        <?= htmlspecialchars(sf_term('description_label', $uiLang), ENT_QUOTES, 'UTF-8') ?>
      </label>
      <textarea
        name="description"
        id="sf-description"
        class="sf-textarea"
        rows="8"
        required
        placeholder="<?= htmlspecialchars(sf_term('description_placeholder', $uiLang), ENT_QUOTES, 'UTF-8') ?>"
      ><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></textarea>
      <p class="sf-char-count"><span id="sf-description-count">0</span>/950</p>
    </div>

    <div id="sf-investigation-extra" class="sf-step3-investigation hidden">
      <div class="sf-field">
        <label for="sf-root-causes" class="sf-label">
          <?= htmlspecialchars(sf_term('root_cause_label', $uiLang), ENT_QUOTES, 'UTF-8') ?>
        </label>
        <textarea
          name="root_causes"
          id="sf-root-causes"
          class="sf-textarea"
          rows="4"
          maxlength="800"
          placeholder="<?= htmlspecialchars(sf_term('root_causes_placeholder', $uiLang), ENT_QUOTES, 'UTF-8') ?>"
        ><?= htmlspecialchars($root_causes, ENT_QUOTES, 'UTF-8') ?></textarea>
        <p class="sf-help-text">
          <?= htmlspecialchars(sf_term('root_causes_help', $uiLang), ENT_QUOTES, 'UTF-8') ?>
        </p>
      </div>

      <div class="sf-field">
        <label for="sf-actions" class="sf-label">
          <?= htmlspecialchars(sf_term('actions_label', $uiLang), ENT_QUOTES, 'UTF-8') ?>
        </label>
        <textarea
          name="actions"
          id="sf-actions"
          class="sf-textarea"
          rows="4"
          maxlength="800"
          placeholder="<?= htmlspecialchars(sf_term('actions_placeholder', $uiLang), ENT_QUOTES, 'UTF-8') ?>"
        ><?= htmlspecialchars($actions, ENT_QUOTES, 'UTF-8') ?></textarea>
        <p class="sf-help-text">
          <?= htmlspecialchars(sf_term('actions_help', $uiLang), ENT_QUOTES, 'UTF-8') ?>
        </p>
      </div>
    </div>

<div class="sf-two-slides-notice" id="sfTwoSlidesNotice" style="display: none;">
    <img src="<?= $base ?>/assets/img/icons/info.svg" alt="Information" class="sf-notice-icon" style="width: 20px; height: 20px;">
    <div class="sf-notice-text">
        <strong><?= sf_term('two_slides_notice_title', $uiLang) ?></strong>
        <span><?= sf_term('two_slides_notice_text', $uiLang) ?></span>
    </div>
</div>

    <!-- Vaihe 3 napit -->
    <div class="sf-step-actions sf-step-actions-bottom">
      <button type="button" id="sfPrev2" class="sf-btn sf-btn-secondary sf-prev-btn">
        <?= htmlspecialchars(sf_term('btn_prev', $uiLang), ENT_QUOTES, 'UTF-8'); ?>
      </button>
      <button type="button" id="sfNext2" class="sf-btn sf-btn-primary sf-next-btn">
        <?= htmlspecialchars(sf_term('btn_next', $uiLang), ENT_QUOTES, 'UTF-8'); ?>
      </button>
    </div>
  </div>

  <!-- VAIHE 4: Kuvat -->
  <div class="sf-step-content" data-step="4">
    <h2><?= htmlspecialchars(sf_term('step4_heading', $uiLang), ENT_QUOTES, 'UTF-8') ?></h2>

    <p class="sf-help-text">
      <?= htmlspecialchars(sf_term('step4_help', $uiLang), ENT_QUOTES, 'UTF-8') ?>
    </p>


    <div class="sf-image-upload-grid">
      <!-- Pääkuva -->
      <div class="sf-image-upload-card" data-slot="1">
        <label class="sf-image-upload-label">
          <?= htmlspecialchars(sf_term('img_main_label', $uiLang) ?? 'Pääkuva', ENT_QUOTES, 'UTF-8') ?> *
        </label>
        <div class="sf-image-upload-area">
          <div class="sf-image-preview" id="sfImagePreview1">
            <img
              src="<?= $getImageUrl($flash['image_main'] ?? null) ?>"
              alt="Pääkuva"
              id="sfImageThumb1"
              data-placeholder="<?= $base ?>/assets/img/camera-placeholder.png"
            >

            <span
              class="sf-image-edited-badge hidden"
              id="sfImageEditedBadge1"
            >
              <?= htmlspecialchars(sf_term('edited', $uiLang) ?? 'Muokattu', ENT_QUOTES, 'UTF-8') ?>
            </span>

            <button
              type="button"
              class="sf-image-remove-btn <?= empty($flash['image_main']) ? 'hidden' : '' ?>"
              data-slot="1"
              title="<?= htmlspecialchars(sf_term('btn_remove_image', $uiLang), ENT_QUOTES, 'UTF-8') ?>"
            >
              <svg width="16" height="16" viewBox="0 0 24 24"
                   fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 6L6 18M6 6l12 12"/>
              </svg>
            </button>
          </div>
<div class="sf-image-upload-actions">
            <label class="sf-image-upload-btn">
              <input type="file" name="image1" accept="image/*" id="sf-image1" class="sf-image-input">
              <img src="<?= $base ?>/assets/img/icons/upload.svg" alt="" class="sf-btn-icon">
                           <span><?= htmlspecialchars(sf_term('btn_upload', $uiLang), ENT_QUOTES, 'UTF-8') ?></span>
            </label>

            <label class="sf-image-upload-btn sf-image-camera-btn">
              <input type="file" name="image1_camera" accept="image/*" capture="environment" class="sf-image-input">
              <img src="<?= $base ?>/assets/img/icons/camera.svg" alt="" class="sf-btn-icon">
                           <span><?= htmlspecialchars(sf_term('btn_take_photo', $uiLang), ENT_QUOTES, 'UTF-8') ?></span>
            </label>

            <button type="button" class="sf-image-library-btn" data-slot="1">
              <img src="<?= $base ?>/assets/img/icons/image.svg" alt="" class="sf-btn-icon">
                            <span><?= htmlspecialchars(sf_term('image_library_btn', $uiLang), ENT_QUOTES, 'UTF-8') ?></span>
            </button>
          </div>
        </div>
      </div>

      <!-- Lisäkuva 1 -->
      <div class="sf-image-upload-card" data-slot="2">
        <label class="sf-image-upload-label">
          <?= htmlspecialchars(sf_term('img_2_label', $uiLang) ?? 'Lisäkuva 1', ENT_QUOTES, 'UTF-8') ?>
        </label>
        <div class="sf-image-upload-area">
          <div class="sf-image-preview" id="sfImagePreview2">
            <img
              src="<?= $getImageUrl($flash['image_2'] ?? null) ?>"
              alt="Lisäkuva 1"
              id="sfImageThumb2"
              data-placeholder="<?= $base ?>/assets/img/camera-placeholder.png"
            >

            <span
              class="sf-image-edited-badge hidden"
              id="sfImageEditedBadge2"
            >
              <?= htmlspecialchars(sf_term('edited', $uiLang) ?? 'Muokattu', ENT_QUOTES, 'UTF-8') ?>
            </span>

            <button
              type="button"
              class="sf-image-remove-btn <?= empty($flash['image_2']) ? 'hidden' : '' ?>"
              data-slot="2"
              title="Poista kuva"
            >
              <svg width="16" height="16" viewBox="0 0 24 24"
                   fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 6L6 18M6 6l12 12"/>
              </svg>
            </button>
          </div>
<div class="sf-image-upload-actions">
            <label class="sf-image-upload-btn">
              <input type="file" name="image2" accept="image/*" id="sf-image2" class="sf-image-input">
              <img src="<?= $base ?>/assets/img/icons/upload.svg" alt="" class="sf-btn-icon">
                            <span><?= htmlspecialchars(sf_term('btn_upload', $uiLang), ENT_QUOTES, 'UTF-8') ?></span>
            </label>

            <label class="sf-image-upload-btn sf-image-camera-btn">
              <input type="file" name="image2_camera" accept="image/*" capture="environment" class="sf-image-input">
              <img src="<?= $base ?>/assets/img/icons/camera.svg" alt="" class="sf-btn-icon">
                            <span><?= htmlspecialchars(sf_term('btn_take_photo', $uiLang), ENT_QUOTES, 'UTF-8') ?></span>
            </label>

            <button type="button" class="sf-image-library-btn" data-slot="2">
              <img src="<?= $base ?>/assets/img/icons/image.svg" alt="" class="sf-btn-icon">
                            <span><?= htmlspecialchars(sf_term('image_library_btn', $uiLang), ENT_QUOTES, 'UTF-8') ?></span>
            </button>
          </div>
        </div>
      </div>

      <!-- Lisäkuva 2 -->
      <div class="sf-image-upload-card" data-slot="3">
        <label class="sf-image-upload-label">
          <?= htmlspecialchars(sf_term('img_3_label', $uiLang) ?? 'Lisäkuva 2', ENT_QUOTES, 'UTF-8') ?>
        </label>
        <div class="sf-image-upload-area">
          <div class="sf-image-preview" id="sfImagePreview3">
            <img
              src="<?= $getImageUrl($flash['image_3'] ?? null) ?>"
              alt="Lisäkuva 2"
              id="sfImageThumb3"
              data-placeholder="<?= $base ?>/assets/img/camera-placeholder.png"
            >

            <span
              class="sf-image-edited-badge hidden"
              id="sfImageEditedBadge3"
            >
              <?= htmlspecialchars(sf_term('edited', $uiLang) ?? 'Muokattu', ENT_QUOTES, 'UTF-8') ?>
            </span>

            <button
              type="button"
              class="sf-image-remove-btn <?= empty($flash['image_3']) ? 'hidden' : '' ?>"
              data-slot="3"
              title="Poista kuva"
            >
              <svg width="16" height="16" viewBox="0 0 24 24"
                   fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 6L6 18M6 6l12 12"/>
              </svg>
            </button>
          </div>
<div class="sf-image-upload-actions">
            <label class="sf-image-upload-btn">
              <input type="file" name="image3" accept="image/*" id="sf-image3" class="sf-image-input">
              <img src="<?= $base ?>/assets/img/icons/upload.svg" alt="" class="sf-btn-icon">
                            <span><?= htmlspecialchars(sf_term('btn_upload', $uiLang), ENT_QUOTES, 'UTF-8') ?></span>
            </label>

            <label class="sf-image-upload-btn sf-image-camera-btn">
              <input type="file" name="image3_camera" accept="image/*" capture="environment" class="sf-image-input">
              <img src="<?= $base ?>/assets/img/icons/camera.svg" alt="" class="sf-btn-icon">
                            <span><?= htmlspecialchars(sf_term('btn_take_photo', $uiLang), ENT_QUOTES, 'UTF-8') ?></span>
            </label>

            <button type="button" class="sf-image-library-btn" data-slot="3">
              <img src="<?= $base ?>/assets/img/icons/image.svg" alt="" class="sf-btn-icon">
                            <span><?= htmlspecialchars(sf_term('image_library_btn', $uiLang), ENT_QUOTES, 'UTF-8') ?></span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- EXTRA IMAGES SECTION -->
    <div class="extra-images-section" id="extra-images-container">
      <div class="extra-images-header">
        <h3><?= htmlspecialchars(sf_term('extra_images_title', $uiLang) ?: 'Lisäkuvat', ENT_QUOTES, 'UTF-8') ?></h3>
        <span class="extra-images-count" id="extra-images-count">0/20</span>
      </div>
      <p class="extra-images-description">
        <?= htmlspecialchars(sf_term('extra_images_description', $uiLang) ?: 'Lisää tähän muita kuvia, jotka eivät näy PDF-tiedotteessa mutta ovat saatavilla tiedotteen katselunäkymässä.', ENT_QUOTES, 'UTF-8') ?>
      </p>
      <div class="extra-images-upload">
        <button type="button" id="extra-image-upload-btn" class="sf-btn sf-btn-primary">
          <?= htmlspecialchars(sf_term('extra_images_add_button', $uiLang) ?: 'Lisää kuvia', ENT_QUOTES, 'UTF-8') ?>
        </button>
        <input type="file" id="extra-image-input" accept="image/jpeg,image/png,image/gif,image/webp" multiple style="display: none;">
      </div>
      <div class="extra-images-grid" id="extra-images-grid">
        <!-- Images will be added here by JavaScript -->
      </div>
    </div>

<div class="sf-step-actions sf-step-actions-bottom">
<button type="button" class="sf-btn sf-btn-secondary sf-prev-btn">
          <?= htmlspecialchars(sf_term('btn_prev', $uiLang), ENT_QUOTES, 'UTF-8'); ?>
        </button>

        <button type="button" class="sf-btn sf-btn-primary sf-next-btn">
          <?= htmlspecialchars(sf_term('btn_next', $uiLang), ENT_QUOTES, 'UTF-8'); ?>
        </button>
      </div>
  </div>

<!-- VAIHE 5: Grid-asettelu -->
<div class="sf-step-content" data-step="5">
  <h2><?= htmlspecialchars(sf_term('grid_heading', $uiLang) ?? 'Kuvien asettelu', ENT_QUOTES, 'UTF-8') ?></h2>
  <p class="sf-help-text"><?= htmlspecialchars(sf_term('grid_help', $uiLang) ?? 'Valitse asettelu. Tämän jälkeen järjestelmä generoi lopullisen kuva-alueen.', ENT_QUOTES, 'UTF-8') ?></p>

  <!-- GRID-VALINTAKORTIT (JS täyttää sisällön) -->
  <div class="sf-grid-options" id="sfGridPicker"></div>

  <div class="sf-step-actions sf-step-actions-bottom">
    <button type="button" class="sf-btn sf-btn-secondary sf-prev-btn">
      <?= htmlspecialchars(sf_term('btn_prev', $uiLang), ENT_QUOTES, 'UTF-8'); ?>
    </button>
    <button type="button" class="sf-btn sf-btn-primary sf-next-btn">
      <?= htmlspecialchars(sf_term('btn_next', $uiLang), ENT_QUOTES, 'UTF-8'); ?>
    </button>
  </div>
</div>

  <!-- Piilotetut transform-kentät (ennen vaihetta 5, lomakkeen sisällä) -->
  <input
    type="hidden"
    id="sf-image1-transform"
    name="image1_transform"
    value="<?= htmlspecialchars($image1_transform, ENT_QUOTES, 'UTF-8') ?>"
  >
  <input
    type="hidden"
    id="sf-image2-transform"
    name="image2_transform"
    value="<?= htmlspecialchars($image2_transform, ENT_QUOTES, 'UTF-8') ?>"
  >
  <input
    type="hidden"
    id="sf-image3-transform"
    name="image3_transform"
    value="<?= htmlspecialchars($image3_transform, ENT_QUOTES, 'UTF-8') ?>"
  >

  <!-- Piilotetut existing image -kentät (tiedostonimet jotka ovat jo tietokannassa) -->
  <input
    type="hidden"
    id="sf-existing-image-1"
    name="existing_image_1"
    value="<?= htmlspecialchars($flash['image_main'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
  >
  <input
    type="hidden"
    id="sf-existing-image-2"
    name="existing_image_2"
    value="<?= htmlspecialchars($flash['image_2'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
  >
  <input
    type="hidden"
    id="sf-existing-image-3"
    name="existing_image_3"
    value="<?= htmlspecialchars($flash['image_3'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
  >

  <!-- Piilotetut editoidut kuvat (dataURL) - täytetään kuvaeditorissa -->
  <input
    type="hidden"
    id="sf-image1-edited-data"
    name="image1_edited_data"
    value="<?= htmlspecialchars($flash['image1_edited_data'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
  >
  <input
    type="hidden"
    id="sf-image2-edited-data"
    name="image2_edited_data"
    value="<?= htmlspecialchars($flash['image2_edited_data'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
  >
  <input
    type="hidden"
    id="sf-image3-edited-data"
    name="image3_edited_data"
    value="<?= htmlspecialchars($flash['image3_edited_data'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
  >

  <input
    type="hidden"
    id="sf-edit-annotations-data"
    name="annotations_data"
    value="<?= htmlspecialchars($flash['annotations_data'] ?? '{}', ENT_QUOTES, 'UTF-8') ?>"
  >
  <input type="hidden" id="sf-grid-layout" name="grid_layout" value="<?= htmlspecialchars($flash['grid_layout'] ?? 'grid-1', ENT_QUOTES, 'UTF-8') ?>">
  <input type="hidden" id="sf-grid-bitmap" name="grid_bitmap" value="<?= htmlspecialchars($flash['grid_bitmap'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
  <input
    type="hidden"
    id="sf-annotations-data"
    value="[]"
  >

<!-- VAIHE 6: Esikatselu -->
  <div class="sf-step-content" data-step="6">
    <div class="sf-preview-section">

  <?php
    $lblRefresh = [
      'fi' => 'Päivitä esikatselu',
      'sv' => 'Uppdatera förhandsvisning',
      'en' => 'Refresh preview',
      'it' => 'Aggiorna anteprima',
      'el' => 'Ανανέωση προεπισκόπησης',
    ][$uiLang] ?? 'Refresh preview';

    $lblRefreshing = [
      'fi' => 'Päivitetään…',
      'sv' => 'Uppdaterar…',
      'en' => 'Refreshing…',
      'it' => 'Aggiornamento…',
      'el' => 'Ανανέωση…',
    ][$uiLang] ?? 'Refreshing…';
  ?>

<style>
  .sf-preview-toolbar{
    display:flex;
    justify-content:flex-end;
    margin-bottom:12px;
  }
  .sf-icon-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:40px;
    height:40px;
    border-radius:12px;
    border:1px solid rgba(0,0,0,.12);
    background:#fff;
    cursor:pointer;
    position:relative;
  }
  .sf-icon-btn:active{ transform:scale(.98); }
  .sf-icon-btn[disabled]{ opacity:.65; cursor:not-allowed; }
  .sf-sr-only{
    position:absolute;
    width:1px; height:1px;
    padding:0; margin:-1px;
    overflow:hidden;
    clip:rect(0,0,0,0);
    white-space:nowrap;
    border:0;
  }
</style>

<div class="sf-preview-toolbar">
  <button
    type="button"
    class="sf-icon-btn"
    id="sfRefreshPreviewBtn"
    data-label="<?= htmlspecialchars($lblRefresh, ENT_QUOTES, 'UTF-8') ?>"
    data-loading-label="<?= htmlspecialchars($lblRefreshing, ENT_QUOTES, 'UTF-8') ?>"
    aria-busy="false"
    title="<?= htmlspecialchars($lblRefresh, ENT_QUOTES, 'UTF-8') ?>"
  >
    <span class="sf-btn-spinner" aria-hidden="true" style="display:none;">
      <svg width="18" height="18" viewBox="0 0 50 50">
        <circle cx="25" cy="25" r="20" fill="none" stroke="currentColor" stroke-width="6" stroke-linecap="round" stroke-dasharray="90 35">
          <animateTransform attributeName="transform" type="rotate" from="0 25 25" to="360 25 25" dur="0.8s" repeatCount="indefinite"/>
        </circle>
      </svg>
    </span>

    <span class="sf-btn-icon" aria-hidden="true" style="display:inline-flex;">
      <!-- refresh icon -->
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
        <path d="M21 12a9 9 0 1 1-2.64-6.36" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        <path d="M21 3v6h-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </span>

    <span class="sf-btn-label sf-sr-only"><?= htmlspecialchars($lblRefresh, ENT_QUOTES, 'UTF-8') ?></span>
  </button>
</div>

  <?php require __DIR__ . '/../partials/preview_server.php'; ?>
</div>
    
<script>
(function () {
  function initSFServerPreview() {
    const container = document.getElementById('sfServerPreviewWrapper');
    const form = document.getElementById('sf-form');
    if (!container || !form) return;

    // Estä tupla-init (PJAX voi ajaa tämän useasti)
    if (window.sfServerPreview && window.sfServerPreview.__sf_inited) return;

    import('<?= $base ?>/assets/js/modules/preview-server.js').then(module => {
      const preview = new module.ServerPreview({
        endpoint: '<?= $base ?>/app/api/preview.php',
        container,
        form,
        debounce: 500
      });
      preview.init();
      preview.__sf_inited = true;
      window.sfServerPreview = preview;
    });
  }

  // Normaali lataus
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSFServerPreview);
  } else {
    initSFServerPreview();
  }

  // PJAX-lataus (teillä pjax.js dispatchaa tämän)
  document.addEventListener('sf:page:loaded', initSFServerPreview);
})();
</script>

  <?php if ($isTranslationChild): ?>
    <!-- Translation child mode: save button only (preview is rendered above, supervisor selection not needed) -->
    <div class="sf-preview-actions">
      <button type="button" class="sf-btn sf-btn-primary" id="sf-save-translation-btn">
        <?= htmlspecialchars(sf_term('btn_save_translation', $uiLang) ?? 'Tallenna kieliversio', ENT_QUOTES, 'UTF-8'); ?>
      </button>
    </div>
  <?php else: ?>
    <!-- Normal mode: Full workflow with supervisor selection and review/draft buttons -->
    <!-- SUPERVISOR SELECTION SECTION - Modern Chip-Style UI -->
    <?php if (!$editing || $state_val === 'draft' || $state_val === 'request_info' || $state_val === ''): ?>
    <div class="sf-supervisor-section" id="sfSupervisorApprovalSection" style="display: none;">
      <h3 class="sf-supervisor-title"><?= htmlspecialchars(sf_term('select_inspector_title', $uiLang) ?: 'Valitse tarkistaja', ENT_QUOTES, 'UTF-8') ?></h3>
      
      <!-- Worksite Supervisors Section -->
      <div class="sf-supervisor-worksite">
        <p class="sf-supervisor-worksite-label">
          <?= htmlspecialchars(sf_term('worksite_supervisors_label_prefix', $uiLang) ?: 'Työmaan', ENT_QUOTES, 'UTF-8') ?>
          "<span id="sfSelectedWorksiteName">-</span>" 
          <?= htmlspecialchars(sf_term('worksite_supervisors_label_suffix', $uiLang) ?: 'vastuuhenkilöt:', ENT_QUOTES, 'UTF-8') ?>
        </p>
        
        <div class="sf-supervisor-chips" id="sfWorksiteSupervisors">
          <!-- JavaScript will populate this automatically -->
          <div class="sf-supervisor-chips-loading">
            <span class="sf-spinner-small"></span>
            <?= htmlspecialchars(sf_term('loading_text', $uiLang) ?: 'Ladataan...', ENT_QUOTES, 'UTF-8') ?>
          </div>
        </div>
        
        <p class="sf-supervisor-empty" id="sfNoSupervisors" style="display: none;">
          <?= htmlspecialchars(sf_term('no_supervisors_for_worksite', $uiLang) ?: 'Tälle työmaalle ei ole määritetty vastuuhenkilöitä.', ENT_QUOTES, 'UTF-8') ?>
        </p>
      </div>
      
      <!-- Search Section for Other Worksites -->
      <div class="sf-supervisor-search">
        <p class="sf-supervisor-search-label"><?= htmlspecialchars(sf_term('search_other_worksites_label', $uiLang) ?: 'Hae muilta työmailta:', ENT_QUOTES, 'UTF-8') ?></p>
        <div class="sf-search-input-wrap">
          <svg class="sf-search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"></circle>
            <path d="m21 21-4.35-4.35"></path>
          </svg>
          <input type="text" 
                 id="sfSupervisorSearch" 
                 class="sf-search-input"
                 placeholder="<?= htmlspecialchars(sf_term('search_name_or_worksite_placeholder', $uiLang) ?: 'Hae nimellä tai työmaalla...', ENT_QUOTES, 'UTF-8') ?>">
          <button type="button" id="sfClearSearch" class="sf-clear-search" style="display: none;">×</button>
        </div>
        
        <div class="sf-supervisor-search-results" id="sfSearchResults" style="display: none;">
          <!-- Search results will be populated here -->
        </div>
      </div>
      
      <!-- Selected Counter -->
      <div class="sf-supervisor-counter">
        <span id="sfSelectedCount">0</span> <?= htmlspecialchars(sf_term('selected_label', $uiLang) ?: 'valittu', ENT_QUOTES, 'UTF-8') ?>
      </div>
      
      <!-- Hidden input for selected IDs -->
      <input type="hidden" name="approver_ids" id="approverIds" value="">
      <input type="hidden" name="selected_approvers" id="selectedApprovers" value="">
    </div>
    <?php endif; ?>

    <!-- Submit-painikkeet (lomakkeen sisällä) -->
    <div class="sf-preview-actions">
      <?php 
      // Määritä näytettävä painike tilan mukaan
      // - draft ja request_info: näytä "Tallenna luonnos" + "Lähetä tarkistettavaksi"
      // - muut tilat (pending_supervisor, pending_review, reviewed, to_comms, published): näytä vain "Tallenna"
      $showSendToReview = ! $editing 
          || $state_val === 'draft' 
          || $state_val === 'request_info'
          || $state_val === '';
      
      // All updates now go through save_flash.php (uses FlashSaveService)
      $actionUrl = $base . '/app/api/save_flash.php';
      
      if ($editing && ! $showSendToReview): ?>
        <!-- Muokkaus tilassa joka EI ole draft/request_info - vain tallenna -->
        <button
          type="button"
          id="sfSaveInline"
          class="sf-btn sf-btn-primary"
          data-action-url="<?= htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') ?>"
          data-flash-id="<?= (int)$editId ?>"
        >
          <?= htmlspecialchars(sf_term('btn_save', $uiLang) ?? 'Tallenna', ENT_QUOTES, 'UTF-8') ?>
        </button>
      <?php else: ?>
        <!-- Uusi tai draft/request_info - näytä molemmat painikkeet -->
        <button
          type="submit"
          name="submission_type"
          value="draft"
          id="sfSaveDraft"
          class="sf-btn sf-btn-secondary"
        >
          <?= htmlspecialchars(sf_term('btn_save_draft', $uiLang), ENT_QUOTES, 'UTF-8') ?>
        </button>
        <button
          type="submit"
          name="submission_type"
          value="review"
          id="sfSubmitReview"
          class="sf-btn sf-btn-primary"
        >
          <?= htmlspecialchars(sf_term('btn_send_review', $uiLang), ENT_QUOTES, 'UTF-8') ?>
        </button>
      <?php endif; ?>
    </div>
  <?php endif; // $isTranslationChild ?>
    <!-- Vaihe 6 napit -->
    <div class="sf-step-actions sf-step-actions-bottom">
      <button type="button" class="sf-btn sf-btn-secondary sf-prev-btn">
        <?= htmlspecialchars(sf_term('btn_prev', $uiLang), ENT_QUOTES, 'UTF-8'); ?>
      </button>
    </div>
  </div>
  <!-- Lopullinen preview-kuva base64:na -->
  <input type="hidden" name="preview_image_data" id="sf-preview-image-data" value="">
  <input type="hidden" name="preview_image_data_2" id="sf-preview-image-data-2" value="">

  <div id="sfTextModal" class="sf-modal hidden">
  <div class="sf-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="sfTextModalTitle">
    <div class="sf-modal-header">
      <h3 id="sfTextModalTitle"><?= htmlspecialchars(sf_term('anno_text', $uiLang) ?? 'Teksti', ENT_QUOTES, 'UTF-8') ?></h3>
      <button type="button" class="sf-modal-close" data-modal-close>×</button>
    </div>

    <div class="sf-modal-body">
      <label class="sf-label" for="sfTextModalInput">
        <?= htmlspecialchars(sf_term('LABEL_PROMPT', $uiLang) ?? 'Kirjoita merkintä:', ENT_QUOTES, 'UTF-8') ?>
      </label>
<textarea
  id="sfTextModalInput"
  class="sf-textarea"
  rows="5"
  placeholder="<?= htmlspecialchars(sf_term('anno_text_placeholder', $uiLang) ?? 'Kirjoita teksti… (Enter = uusi rivi)', ENT_QUOTES, 'UTF-8') ?>"
></textarea>
    </div>

    <div class="sf-modal-footer">
      <button type="button" class="sf-btn sf-btn-secondary" data-modal-close>
        <?= htmlspecialchars(sf_term('btn_cancel', $uiLang) ?? 'Peruuta', ENT_QUOTES, 'UTF-8') ?>
      </button>
      <button type="button" id="sfTextModalSave" class="sf-btn sf-btn-primary">
        <?= htmlspecialchars(sf_term('btn_save', $uiLang) ?? 'Tallenna', ENT_QUOTES, 'UTF-8') ?>
      </button>
    </div>
  </div>
</div>

<!-- KUVAEDITORI MODAL (ei osa steppejä) -->
<div id="sfEditStep" class="hidden sf-edit-modal" aria-hidden="true">
  <div class="sf-edit-modal-card sf-edit-compact">
    
    <!-- Header:  otsikko + close -->
    <div class="sf-edit-modal-header-compact">
      <div class="sf-edit-header-left">
        <h2 data-sf-edit-title><?= htmlspecialchars(sf_term('img_edit_heading', $uiLang), ENT_QUOTES, 'UTF-8') ?></h2>
      </div>
      <div class="sf-edit-header-actions">
        <button type="button" id="sf-edit-crop-info-btn" class="sf-edit-close-compact" aria-label="<?= htmlspecialchars(sf_term('crop_guide_label', $uiLang) ?? 'Rajausopas', ENT_QUOTES, 'UTF-8') ?>">
          <img src="<?= $base ?>/assets/img/icons/info.svg" alt="">
        </button>
        <button type="button" id="sf-edit-close" class="sf-edit-close-compact" aria-label="<?= htmlspecialchars(sf_term('btn_close', $uiLang) ?? 'Sulje', ENT_QUOTES, 'UTF-8') ?>">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 6L6 18M6 6l12 12"/>
          </svg>
        </button>
      </div>
    </div>

    <!-- Body: canvas + sivupaneeli samalla rivillä -->
    <div class="sf-edit-modal-body-compact">
      
      <!-- Vasen:  Canvas -->
      <div class="sf-edit-canvas-area">
        <div class="sf-edit-crop-guide" id="sfCropGuide">
          <svg class="sf-edit-crop-guide-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <path d="M12 16v-4M12 8h.01"/>
          </svg>
          <div class="sf-edit-crop-guide-text">
            <strong><?= htmlspecialchars(sf_term('crop_guide_label', $uiLang) ?? 'Rajausopas', ENT_QUOTES, 'UTF-8') ?>:</strong>
            <span class="sf-crop-guide-main"><?= htmlspecialchars(sf_term('crop_guide_text', $uiLang) ?? 'Katkoviiva näyttää neliökuvissa (1:1) näkyvän alueen. Koko vaaka-alue näkyy vaakakuvissa.', ENT_QUOTES, 'UTF-8') ?></span>
            <span class="sf-crop-guide-hint">💡 <?= htmlspecialchars(sf_term('crop_guide_annotations_hint', $uiLang) ?? 'Merkintöjä voi lisätä myös tummennetulle alueelle — ne näkyvät vaaka-asettelussa.', ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <button type="button" class="sf-edit-crop-guide-close" onclick="this.parentElement.classList.add('hidden')" aria-label="<?= htmlspecialchars(sf_term('btn_close', $uiLang) ?? 'Sulje', ENT_QUOTES, 'UTF-8') ?>">×</button>
        </div>
        <div id="sf-edit-img-canvas-wrap" class="sf-edit-canvas-wrap">
          <canvas id="sf-edit-img-canvas" width="1920" height="1080" class="sf-edit-canvas"></canvas>
        </div>
        
        <!-- Zoom/pan kontrollit canvasin alla -->
        <div class="sf-edit-canvas-controls">
          <div class="sf-edit-control-group">
            <button type="button" id="sf-edit-img-zoom-out" class="sf-edit-ctrl-btn" title="<?= htmlspecialchars(sf_term('edit_zoom_out', $uiLang), ENT_QUOTES, 'UTF-8') ?>">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </button>
            <button type="button" id="sf-edit-img-zoom-in" class="sf-edit-ctrl-btn" title="<?= htmlspecialchars(sf_term('edit_zoom_in', $uiLang), ENT_QUOTES, 'UTF-8') ?>">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </button>
            <span class="sf-edit-ctrl-divider"></span>
            <button type="button" id="sf-edit-img-move-left" class="sf-edit-ctrl-btn" title="<?= htmlspecialchars(sf_term('edit_move_left', $uiLang), ENT_QUOTES, 'UTF-8') ?>">←</button>
            <button type="button" id="sf-edit-img-move-up" class="sf-edit-ctrl-btn" title="<?= htmlspecialchars(sf_term('edit_move_up', $uiLang), ENT_QUOTES, 'UTF-8') ?>">↑</button>
            <button type="button" id="sf-edit-img-move-down" class="sf-edit-ctrl-btn" title="<?= htmlspecialchars(sf_term('edit_move_down', $uiLang), ENT_QUOTES, 'UTF-8') ?>">↓</button>
            <button type="button" id="sf-edit-img-move-right" class="sf-edit-ctrl-btn" title="<?= htmlspecialchars(sf_term('edit_move_right', $uiLang), ENT_QUOTES, 'UTF-8') ?>">→</button>
            <span class="sf-edit-ctrl-divider"></span>
            <button type="button" id="sf-edit-img-reset" class="sf-edit-ctrl-btn sf-edit-ctrl-text" title="<?= htmlspecialchars(sf_term('edit_reset', $uiLang), ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars(sf_term('btn_reset', $uiLang) ?? 'Reset', ENT_QUOTES, 'UTF-8') ?>
            </button>
          </div>
        </div>
      </div>

      <!-- Oikea: Merkinnät paneeli -->
      <div class="sf-edit-sidebar">
        <div class="sf-edit-sidebar-section">
          <h3 class="sf-edit-sidebar-title"><?= htmlspecialchars(sf_term('anno_title', $uiLang) ?? 'Merkinnät', ENT_QUOTES, 'UTF-8') ?></h3>
          <p class="sf-edit-sidebar-hint">
            <?= htmlspecialchars(sf_term('anno_help_short', $uiLang) ?? 'Valitse ikoni ja klikkaa kuvaa', ENT_QUOTES, 'UTF-8') ?>
          </p>
          
          <!-- Ikonivalitsin -->
          <div class="sf-edit-anno-grid">
            <button type="button" class="sf-edit-anno-btn" data-sf-tool="arrow" title="<?= htmlspecialchars(sf_term('anno_arrow', $uiLang) ?? 'Nuoli', ENT_QUOTES, 'UTF-8') ?>">
              <img src="<?= $base ?>/assets/img/annotations/arrow-red.png" alt="" class="sf-anno-icon">
            </button>
            <button type="button" class="sf-edit-anno-btn" data-sf-tool="circle" title="<?= htmlspecialchars(sf_term('anno_circle', $uiLang) ?? 'Ympyrä', ENT_QUOTES, 'UTF-8') ?>">
              <img src="<?= $base ?>/assets/img/annotations/circle-red.png" alt="" class="sf-anno-icon">
            </button>
            <button type="button" class="sf-edit-anno-btn" data-sf-tool="crash" title="<?= htmlspecialchars(sf_term('anno_crash', $uiLang) ?? 'Törmäys', ENT_QUOTES, 'UTF-8') ?>">
              <img src="<?= $base ?>/assets/img/annotations/crash.png" alt="" class="sf-anno-icon">
            </button>
            <button type="button" class="sf-edit-anno-btn" data-sf-tool="warning" title="<?= htmlspecialchars(sf_term('anno_warning', $uiLang) ?? 'Varoitus', ENT_QUOTES, 'UTF-8') ?>">
              <img src="<?= $base ?>/assets/img/annotations/warning.png" alt="" class="sf-anno-icon">
            </button>
            <button type="button" class="sf-edit-anno-btn" data-sf-tool="injury" title="<?= htmlspecialchars(sf_term('anno_injury', $uiLang) ?? 'Vamma', ENT_QUOTES, 'UTF-8') ?>">
              <img src="<?= $base ?>/assets/img/annotations/injury.png" alt="" class="sf-anno-icon">
            </button>
            <button type="button" class="sf-edit-anno-btn" data-sf-tool="cross" title="<?= htmlspecialchars(sf_term('anno_cross', $uiLang) ?? 'Risti', ENT_QUOTES, 'UTF-8') ?>">
              <img src="<?= $base ?>/assets/img/annotations/cross-red.png" alt="" class="sf-anno-icon">
            </button>
          </div>
          
          <!-- Valitun merkinnän kontrollit -->
          <div class="sf-edit-selected-controls" id="sfEditSelectedControls">
            <div class="sf-edit-selected-row">
              <button type="button" id="sf-edit-anno-rotate" class="sf-edit-sel-btn" disabled title="<?= htmlspecialchars(sf_term('anno_rotate_45', $uiLang), ENT_QUOTES, 'UTF-8') ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
              </button>
              <button type="button" id="sf-edit-anno-size-down" class="sf-edit-sel-btn" disabled title="<?= htmlspecialchars(sf_term('anno_size_down', $uiLang), ENT_QUOTES, 'UTF-8') ?>">−</button>
              <button type="button" id="sf-edit-anno-size-up" class="sf-edit-sel-btn" disabled title="<?= htmlspecialchars(sf_term('anno_size_up', $uiLang), ENT_QUOTES, 'UTF-8') ?>">+</button>
              <button type="button" id="sf-edit-anno-delete" class="sf-edit-sel-btn sf-edit-sel-danger" disabled title="<?= htmlspecialchars(sf_term('anno_delete_selected', $uiLang), ENT_QUOTES, 'UTF-8') ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
              </button>
            </div>
          </div>
          
          <!-- Teksti-nappi -->
          <button type="button" id="sf-edit-img-add-label" class="sf-edit-text-btn" disabled>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7V4h16v3M9 20h6M12 4v16"/></svg>
            <?= htmlspecialchars(sf_term('anno_text', $uiLang) ?? 'Lisää teksti', ENT_QUOTES, 'UTF-8') ?>
          </button>
        </div>
      </div>
    </div>

    <!-- Footer: Tallenna -->
    <div class="sf-edit-modal-footer-compact">
      <button type="button" id="sf-edit-img-save" class="sf-btn sf-btn-primary">
        <?= htmlspecialchars(sf_term('btn_save', $uiLang) ?? 'Tallenna', ENT_QUOTES, 'UTF-8'); ?>
      </button>
    </div>

  </div>
</div>
<script>
window.SF_I18N = <?= json_encode(array_merge($sfI18n, [
    // Lomakkeen perus-termit
    'saving_flash' => sf_term('saving_flash', $uiLang),
    'generating_preview' => sf_term('generating_preview', $uiLang),
    'btn_cancel' => sf_term('btn_cancel', $uiLang),
    'btn_save' => sf_term('btn_save', $uiLang),
    'error_prefix' => sf_term('error_prefix', $uiLang),
    
    // Lähetys-termit (submit. js: lle)
    'please_wait' => sf_term('please_wait', $uiLang),
    'sending_for_review' => sf_term('sending_for_review', $uiLang),
    'processing_continues' => sf_term('processing_continues', $uiLang),
    'data_received_processing' => sf_term('data_received_processing', $uiLang),
    'saving_draft' => sf_term('saving_draft', $uiLang),
    'draft_saved' => sf_term('draft_saved', $uiLang),
    'save_failed' => sf_term('save_failed', $uiLang),
    'saving_changes' => sf_term('saving_changes', $uiLang),
    'changes_saved' => sf_term('changes_saved', $uiLang),
]), JSON_UNESCAPED_UNICODE) ?>;
window.SF_BASE_URL = "<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>";
</script>
<script src="<?= sf_asset_url('assets/js/SFEditImage.js', $base) ?>"></script>
<script src="<?= sf_asset_url('assets/js/sf-image-edit-flow.js', $base) ?>"></script>
<script src="<?= sf_asset_url('assets/js/sf-grid-step.js', $base) ?>"></script>

<?php if ($editing && !$showSendToReview): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const saveBtn = document.getElementById('sfSaveInline');
    const form = document.getElementById('sf-form');
    
    if (!saveBtn || !form) return;

    saveBtn.addEventListener('click', async function(e) {
        e.preventDefault();
        
        console.log('[Inline Save] Using window.sfFormSubmit');
        
        // Temporarily swap form action to inline edit endpoint
        const originalAction = form.action;
        const inlineActionUrl = saveBtn.dataset.actionUrl;
        
        if (!inlineActionUrl) {
            console.error('[Inline Save] Missing data-action-url on save button');
            alert('<?= htmlspecialchars(sf_term('error_save', $uiLang), ENT_QUOTES, 'UTF-8') ?>');
            return;
        }
        
        try {
            // Set form action to inline edit endpoint
            form.action = inlineActionUrl;
            
            // CRITICAL: Sync annotations from editor to hidden input before submission
            // The image editor stores annotations in memory but doesn't auto-save to form input
            if (window.SFImageEditor && typeof window.SFImageEditor.getAllAnnotations === 'function') {
                const allAnnotations = window.SFImageEditor.getAllAnnotations();
                const annotationsInput = document.getElementById('sf-edit-annotations-data');
                if (annotationsInput) {
                    annotationsInput.value = JSON.stringify(allAnnotations || {});
                    console.log('[Inline Save] Synced annotations to form:', allAnnotations);
                    
                    // CRITICAL: Also convert to preview format for immediate preview capture
                    // Convert from {"image1": [...], "image2": [...]} to [{...ann, frameId: "sfPreviewImageFrame1"}, ...]
                    const previewAnnotations = [];
                    [1, 2, 3].forEach(slot => {
                        const key = `image${slot}`;
                        const slotAnnotations = allAnnotations[key];
                        if (Array.isArray(slotAnnotations) && slotAnnotations.length > 0) {
                            slotAnnotations.forEach(ann => {
                                if (ann) {
                                    previewAnnotations.push({
                                        ...ann,
                                        frameId: `sfPreviewImageFrame${slot}`, // Always use slot-based frameId for consistency
                                        slot: slot
                                    });
                                }
                            });
                        }
                    });
                    
                    const previewInput = document.getElementById('sf-annotations-data');
                    if (previewInput) {
                        previewInput.value = JSON.stringify(previewAnnotations);
                        console.log('[Inline Save] Converted annotations to preview format:', previewAnnotations);
                        
                        // Re-initialize annotations with delay to ensure DOM is ready (same as bootstrap.js)
                        if (window.Annotations && typeof window.Annotations.init === 'function') {
                            setTimeout(() => {
                                window.Annotations.init();
                            }, 100);
                        }
                    }
                }
            }
            
            // Tyhjennä preview-kentät - palvelin generoi kuvan
            const p1 = document.querySelector('input[name="preview_image_data"]');
            const p2 = document.querySelector('input[name="preview_image_data_2"]');
            if (p1) p1.value = '';
            if (p2) p2.value = '';
            
            // Käytä sfFormSubmit jos saatavilla
            if (typeof window.sfFormSubmit === 'function') {
                await window.sfFormSubmit(form, false, true);
            } else {
                console.error('[Inline Save] submit.js not loaded');
                throw new Error('Submit function not available');
            }
        } catch (err) {
            console.error('[Inline Save] Error:', err);
            alert('<?= htmlspecialchars(sf_term('error_save', $uiLang), ENT_QUOTES, 'UTF-8') ?>: ' + err.message);
        } finally {
            // Always restore original form action
            form.action = originalAction;
        }
    });
});
</script>
<?php endif; ?>

<script>
// Välitä PHP:stä JavaScriptille olemassa olevat kuvat
window.SF_EXISTING_IMAGES = {
    slot1: {
        filename: <?= json_encode($flash['image_main'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
        url: <?= json_encode($getImageUrl($flash['image_main'] ??  null), JSON_UNESCAPED_UNICODE) ?>,
        transform: <?= json_encode($image1_transform, JSON_UNESCAPED_UNICODE) ?>
    },
    slot2: {
        filename: <?= json_encode($flash['image_2'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
        url: <?= json_encode($getImageUrl($flash['image_2'] ?? null), JSON_UNESCAPED_UNICODE) ?>,
        transform:  <?= json_encode($image2_transform, JSON_UNESCAPED_UNICODE) ?>
    },
    slot3: {
        filename: <?= json_encode($flash['image_3'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
        url: <?= json_encode($getImageUrl($flash['image_3'] ?? null), JSON_UNESCAPED_UNICODE) ?>,
        transform: <?= json_encode($image3_transform, JSON_UNESCAPED_UNICODE) ?>
    },
    annotations: <?= json_encode($flash['annotations_data'] ?? '{}', JSON_UNESCAPED_UNICODE) ?>,
    gridLayout: <?= json_encode($flash['grid_layout'] ?? 'grid-1', JSON_UNESCAPED_UNICODE) ?>,
    gridBitmap: <?= json_encode($flash['grid_bitmap'] ?? '', JSON_UNESCAPED_UNICODE) ?>
};

// Lataa kuvat kun sivu on valmis
document.addEventListener('DOMContentLoaded', function() {
    if (window.SF_EXISTING_IMAGES) {
        ['slot1', 'slot2', 'slot3'].forEach(function(slot) {
            const slotNum = slot.replace('slot', '');
            const data = window.SF_EXISTING_IMAGES[slot];
            
            if (data.filename) {
                const thumb = document.getElementById('sfImageThumb' + slotNum);
                const removeBtn = document.querySelector(`[data-slot="${slotNum}"].sf-image-remove-btn`);
                const editBtn = document.querySelector(`[data-slot="${slotNum}"].sf-image-edit-inline-btn`);
                
                if (thumb) {
                    thumb.src = data.url;
                    thumb.dataset.filename = data.filename;
                }
                
                if (removeBtn) {
                    removeBtn.classList.remove('hidden');
                }
                
                if (editBtn) {
                    editBtn.classList.remove('hidden');
                    editBtn.disabled = false;
                }
                
                console.log(`[Form] Loaded existing image for ${slot}: `, data.filename);
            }
        });
    }
});
</script>

</form>

<!-- VAHVISTUSMODAL - Lomakkeen ulkopuolella jotta JS löytää sen -->
<div
  class="sf-modal hidden"
  id="sfConfirmModal"
  role="dialog"
  aria-modal="true"
  aria-labelledby="sfConfirmModalTitle"
>
  <div class="sf-modal-content">
    <h2 id="sfConfirmModalTitle">
      <?= htmlspecialchars(sf_term('confirm_submit_title', $uiLang), ENT_QUOTES, 'UTF-8') ?>
    </h2>
    <p><?= htmlspecialchars(sf_term('confirm_submit_text', $uiLang), ENT_QUOTES, 'UTF-8') ?></p>
    <p class="sf-help-text">
      <?= htmlspecialchars(sf_term('confirm_submit_help', $uiLang), ENT_QUOTES, 'UTF-8') ?>
    </p>
    <div class="sf-modal-actions">
      <button
        type="button"
        class="sf-btn sf-btn-secondary"
        data-modal-close="sfConfirmModal"
      >
        <?= htmlspecialchars(sf_term('btn_cancel', $uiLang), ENT_QUOTES, 'UTF-8') ?>
      </button>
      <button type="button" class="sf-btn sf-btn-primary" id="sfConfirmSubmit">
        <?= htmlspecialchars(sf_term('btn_confirm_yes', $uiLang), ENT_QUOTES, 'UTF-8') ?>
      </button>
    </div>
  </div>
</div>

<!-- Confirmation Modal for Supervisor Selection -->
<div id="sfSubmitConfirmModal" class="sf-modal hidden">
    <div class="sf-modal-overlay" onclick="window.sfCloseSubmitModal()"></div>
    <div class="sf-modal-content">
        <div class="sf-modal-header">
            <h3>Vahvista lähetys</h3>
            <button type="button" class="sf-modal-close" onclick="window.sfCloseSubmitModal()">×</button>
        </div>
        
        <div class="sf-modal-body">
            <p style="margin: 0 0 16px 0; color: #64748b;">
                SafetyFlash lähetetään seuraavalle työmaavastaavalle tarkistettavaksi:
            </p>
            
            <!-- Selected Supervisors Summary -->
            <div class="sf-selected-supervisors" id="sfModalSupervisorsSummary">
                <!-- Populated by JavaScript -->
            </div>
            
            <!-- Workflow Visualization -->
            <div class="sf-send-flow">
                <div class="sf-flow-step">Työmaavastaava</div>
                <div class="sf-flow-arrow">→</div>
                <div class="sf-flow-step">Turvatiimi</div>
                <div class="sf-flow-arrow">→</div>
                <div class="sf-flow-step">Viestintä</div>
                <div class="sf-flow-arrow">→</div>
                <div class="sf-flow-step">Julkaistu</div>
            </div>
            
            <!-- Submission Comment Field -->
            <div class="sf-field" style="margin-top: 1rem;">
                <label for="submissionComment" class="sf-label">
                    <?= sf_term('submission_comment_label', $uiLang) ?>
                </label>
                <textarea 
                    id="submissionComment" 
                    name="submission_comment" 
                    class="sf-textarea"
                    rows="3"
                    maxlength="1000"
                    placeholder="<?= sf_term('submission_comment_placeholder', $uiLang) ?>"
                ></textarea>
                <div class="sf-help-text"><?= sf_term('submission_comment_help', $uiLang) ?></div>
            </div>
            
            <p style="font-size: 0.9rem; color: #64748b; margin: 16px 0 0 0;">
                Voit muokata valintoja palaamalla takaisin.
            </p>
        </div>
        
        <div class="sf-modal-footer">
            <button type="button" class="sf-btn sf-btn-secondary" onclick="window.sfEditSupervisors()">
                ← Muokkaa
            </button>
            <button type="button" class="sf-btn sf-btn-secondary" onclick="window.sfCloseSubmitModal()">
                Peruuta
            </button>
            <button type="button" class="sf-btn sf-btn-primary" onclick="window.sfConfirmSubmit()">
                Lähetä
            </button>
        </div>
    </div>
</div>

<?php
// Kuvapankki-modaali
$currentUiLang = $uiLang;
include __DIR__ . '/../partials/image_library_modal.php';
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
  if (window.ImageLibrary) {
    ImageLibrary.init('<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>');
  }
});
</script>

<div id="sfConfirmRemoveModal" class="sf-modal hidden">
  <div class="sf-modal-dialog sf-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="sfConfirmRemoveTitle">
    <div class="sf-modal-header">
      <h3 id="sfConfirmRemoveTitle">
        <?= htmlspecialchars(sf_term('confirm_remove_image_title', $uiLang) ?? 'Poista kuva', ENT_QUOTES, 'UTF-8') ?>
      </h3>
      <button type="button" class="sf-modal-close" id="sfConfirmRemoveClose" aria-label="<?= htmlspecialchars(sf_term('btn_close', $uiLang) ?? 'Sulje', ENT_QUOTES, 'UTF-8') ?>">×</button>
    </div>

    <div class="sf-modal-body">
      <p id="sfConfirmRemoveText" class="sf-confirm-text">
        <?= htmlspecialchars(sf_term('confirm_remove_image_text', $uiLang) ?? 'Haluatko poistaa tämän kuvan? Kuva ja sen säädöt poistetaan.', ENT_QUOTES, 'UTF-8') ?>
      </p>
    </div>

    <div class="sf-modal-footer">
      <button type="button" id="sfConfirmRemoveNo" class="sf-btn sf-btn-secondary">
        <?= htmlspecialchars(sf_term('btn_cancel', $uiLang) ?? 'Peruuta', ENT_QUOTES, 'UTF-8') ?>
      </button>
      <button type="button" id="sfConfirmRemoveYes" class="sf-btn sf-btn-danger">
        <?= htmlspecialchars(sf_term('btn_delete', $uiLang) ?? 'Poista', ENT_QUOTES, 'UTF-8') ?>
      </button>
    </div>
  </div>
</div>