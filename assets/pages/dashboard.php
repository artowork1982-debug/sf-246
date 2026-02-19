<?php
// assets/pages/dashboard.php
declare(strict_types=1);

require_once __DIR__ . '/../../app/includes/protect.php';
require_once __DIR__ . '/../../app/includes/statuses.php';
require_once __DIR__ . '/../../app/includes/helpers.php';

$baseUrl = rtrim($config['base_url'] ?? '', '/');
$uiLang = $_SESSION['ui_lang'] ?? 'fi';

$user = sf_current_user();
$isAdmin = $user && (int)($user['role_id'] ?? 0) === 1;
$userId = (int)($user['id'] ?? 0);

try {
    $pdo = Database::getInstance();
} catch (Throwable $e) {
    http_response_code(500);
    echo '<p>' . htmlspecialchars(sf_term('db_error', $uiLang), ENT_QUOTES, 'UTF-8') . '</p>';
    exit;
}

// --- Original Type Statistics (default: all time) ---
$originalStats = ['red' => 0, 'yellow' => 0, 'total' => 0];
try {
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(original_type, type) as original_type,
            COUNT(DISTINCT COALESCE(translation_group_id, id)) as count
        FROM sf_flashes 
        WHERE state = 'published'
        GROUP BY COALESCE(original_type, type)
    ");
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $type = $row['original_type'] ?? '';
        $count = (int)($row['count'] ?? 0);
        if (isset($originalStats[$type])) $originalStats[$type] = $count;
        if ($type !== 'green') { // Don't count green in original stats
            $originalStats['total'] += $count;
        }
    }
} catch (Throwable $e) {}

// --- Worksite Statistics (Top 15) - Default: All Time ---
$worksiteStats = [];
$maxCount = 0;
try {
    $stmt = $pdo->prepare("
        SELECT site, COUNT(DISTINCT COALESCE(translation_group_id, id)) as count 
        FROM sf_flashes 
        WHERE state = 'published' 
        AND site IS NOT NULL 
        AND site != '' 
        GROUP BY site 
        ORDER BY count DESC 
        LIMIT 15
    ");
    $stmt->execute();
    $worksiteStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($worksiteStats as $ws) {
        $maxCount = max($maxCount, (int)($ws['count'] ?? 0));
    }
} catch (Throwable $e) {}

// --- Active Statistics (Current Type, excluding archived) ---
$activeStats = ['red' => 0, 'yellow' => 0, 'green' => 0];
try {
    $stmt = $pdo->prepare("
        SELECT type, COUNT(DISTINCT COALESCE(translation_group_id, id)) as count 
        FROM sf_flashes 
        WHERE state = 'published' AND is_archived = 0 
        GROUP BY type
    ");
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $type = $row['type'] ?? '';
        $count = (int)($row['count'] ?? 0);
        if (isset($activeStats[$type])) $activeStats[$type] = $count;
    }
} catch (Throwable $e) {}

// --- Available Years (for dynamic year dropdown) ---
$availableYears = [];
$currentYear = (int)date('Y');
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT YEAR(created_at) as year 
        FROM sf_flashes 
        WHERE created_at IS NOT NULL 
        ORDER BY year DESC
    ");
    $stmt->execute();
    $availableYears = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'year');
} catch (Throwable $e) {}


// --- Archived Count ---
$archivedCount = 0;
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT COALESCE(translation_group_id, id)) as count 
        FROM sf_flashes 
        WHERE state = 'published' AND is_archived = 1
    ");
    $stmt->execute();
    $archivedCount = (int)($stmt->fetchColumn() ?: 0);
} catch (Throwable $e) {}

// --- Recent Publications (5 items) ---
$recentItems = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, type, title, title_short, site, updated_at 
        FROM sf_flashes 
        WHERE state = 'published' 
        ORDER BY updated_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recentItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// --- Waiting for You (items relevant to current user) ---
$waitingItems = [];
try {
    // Build query based on user role - only using hardcoded conditions
    $whereParts = [];
    $params = [];
    
    // For creators: show their drafts and request_info items
    // Note: $userId is already cast to int on line 14, so it's safe
    if ($userId > 0) {
        $whereParts[] = "(created_by = :user_id AND state IN ('draft', 'request_info'))";
        $params[':user_id'] = $userId;
    }
    
    // For admins/reviewers: show pending_review and pending_supervisor items
    if ($isAdmin) {
        $whereParts[] = "(state IN ('pending_review', 'pending_supervisor'))";
    }
    
    // Execute query if there are conditions
    // Note: $whereParts only contains hardcoded strings from above, no user input
    if (!empty($whereParts)) {
        $whereClause = implode(' OR ', $whereParts);
        $sql = "
            SELECT id, type, title, title_short, site, state, created_by, updated_at 
            FROM sf_flashes 
            WHERE (" . $whereClause . ")
            ORDER BY updated_at DESC 
            LIMIT 10
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $waitingItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {}

?>

<div class="sf-page-container">
    <div class="sf-page-header">
        <h1 class="sf-page-title"><?= htmlspecialchars(sf_term('dashboard_title', $uiLang), ENT_QUOTES, 'UTF-8') ?></h1>
    </div>

    <div class="sf-dashboard">

        <!-- ========== STATISTICS SECTION (MAIN) ========== -->
        <div class="sf-dashboard-stats-section">
            
            <!-- Statistics Card -->
            <div class="sf-content-card">
                <div class="sf-section-header">
                    <span class="sf-section-icon">
                        <img src="<?= htmlspecialchars($baseUrl) ?>/assets/img/icons/dashboard.svg" alt="" style="width: 2rem; height: 2rem;">
                    </span>
                    <span class="sf-section-title" style="font-size: 1.125rem;">
                        <?= htmlspecialchars(sf_term('dashboard_statistics', $uiLang), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>

            <!-- Time Filter -->
            <div class="sf-time-filter-container">
                <div class="sf-time-filter-dropdowns">
                    <div class="sf-time-filter-group">
                        <label for="sf-filter-month" class="sf-filter-label">
                            <?= htmlspecialchars(sf_term('dashboard_filter_month', $uiLang), ENT_QUOTES, 'UTF-8') ?>:
                        </label>
                        <select id="sf-filter-month" class="sf-filter-select">
                            <option value=""><?= htmlspecialchars(sf_term('dashboard_filter_select_month', $uiLang), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>"><?= htmlspecialchars(sf_term("month_$m", $uiLang), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="sf-time-filter-group">
                        <label for="sf-filter-year" class="sf-filter-label">
                            <?= htmlspecialchars(sf_term('dashboard_filter_year', $uiLang), ENT_QUOTES, 'UTF-8') ?>:
                        </label>
                        <select id="sf-filter-year" class="sf-filter-select">
                            <option value=""><?= htmlspecialchars(sf_term('dashboard_filter_all_years', $uiLang), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php foreach ($availableYears as $year): ?>
                                <option value="<?= (int)$year ?>">
                                    <?= (int)$year ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="sf-time-filter-quick">
                    <span class="sf-filter-quick-label">
                        <?= htmlspecialchars(sf_term('dashboard_filter_quick', $uiLang), ENT_QUOTES, 'UTF-8') ?>:
                    </span>
                    <div class="sf-time-filter-buttons">
                        <button class="sf-time-filter-btn" data-period="thismonth">
                            <?= htmlspecialchars(sf_term('dashboard_time_filter_thismonth', $uiLang), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button class="sf-time-filter-btn" data-period="3months">
                            <?= htmlspecialchars(sf_term('dashboard_time_filter_3months', $uiLang), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button class="sf-time-filter-btn" data-period="6months">
                            <?= htmlspecialchars(sf_term('dashboard_time_filter_6months', $uiLang), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button class="sf-time-filter-btn" data-period="thisyear">
                            <?= htmlspecialchars(sf_term('dashboard_time_filter_thisyear', $uiLang), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button class="sf-time-filter-btn sf-active" data-period="all">
                            <?= htmlspecialchars(sf_term('dashboard_time_filter_all', $uiLang), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- By Type Statistics (No Links) -->
            <h3 class="sf-subsection-title" style="font-size: 1.0625rem;">
                <?= htmlspecialchars(sf_term('dashboard_by_type', $uiLang), ENT_QUOTES, 'UTF-8') ?>
            </h3>

            <div class="sf-dashboard-stats sf-dashboard-stats--compact">
                <div class="sf-stat-card sf-stat-card--red sf-stat-card--no-link">
                    <span class="sf-stat-icon">
                        <img src="<?= htmlspecialchars($baseUrl) ?>/assets/img/icons/type-red.svg" alt="">
                    </span>
                    <span class="sf-stat-label"><?= htmlspecialchars(sf_term('dashboard_stat_red', $uiLang), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="sf-stat-count" data-stat="red"><?= (int)$originalStats['red'] ?></span>
                </div>

                <div class="sf-stat-card sf-stat-card--yellow sf-stat-card--no-link">
                    <span class="sf-stat-icon">
                        <img src="<?= htmlspecialchars($baseUrl) ?>/assets/img/icons/type-yellow.svg" alt="">
                    </span>
                    <span class="sf-stat-label"><?= htmlspecialchars(sf_term('dashboard_stat_yellow', $uiLang), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="sf-stat-count" data-stat="yellow"><?= (int)$originalStats['yellow'] ?></span>
                </div>

                <div class="sf-stat-card sf-stat-card--total sf-stat-card--no-link">
                    <span class="sf-stat-icon">
                        <img src="<?= htmlspecialchars($baseUrl) ?>/assets/img/icons/stats-total.svg" alt="">
                    </span>
                    <span class="sf-stat-label"><?= htmlspecialchars(sf_term('dashboard_stat_total', $uiLang), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="sf-stat-count" data-stat="total"><?= (int)$originalStats['total'] ?></span>
                </div>
            </div>
            
            </div> <!-- End Statistics Card -->
            
            <!-- Worksite Statistics Card -->
            <div class="sf-content-card">
                <div class="sf-section-header">
                    <span class="sf-section-icon">
                        <img src="<?= htmlspecialchars($baseUrl) ?>/assets/img/icons/worksite-stats.svg" alt="" style="width: 2rem; height: 2rem;">
                    </span>
                    <span class="sf-section-title" style="font-size: 1.125rem;">
                        <?= htmlspecialchars(sf_term('dashboard_by_worksite', $uiLang), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>


            <?php if (!empty($worksiteStats)): ?>
                <div class="sf-worksite-bars">
                    <?php foreach ($worksiteStats as $index => $ws): 
                        $siteName = $ws['site'] ?? '';
                        $siteCount = (int)($ws['count'] ?? 0);
                        $barWidth = $maxCount > 0 ? round(($siteCount / $maxCount) * 100) : 0;
                        $isHidden = $index >= 5 ? 'sf-worksite-hidden' : '';
                    ?>
                        <a href="<?= htmlspecialchars($baseUrl) ?>/index.php?page=list&site=<?= urlencode($siteName) ?>" class="sf-worksite-bar-row <?= $isHidden ?>" style="--bar-delay: <?= $index * 0.08 ?>s;">
                            <span class="sf-worksite-name"><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></span>
                            <div class="sf-worksite-bar-wrap">
                                <div class="sf-worksite-bar" style="--bar-width: <?= $barWidth ?>%;">
                                    <span class="sf-worksite-count"><?= $siteCount ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php if (count($worksiteStats) > 5): ?>
                    <button class="sf-worksite-show-all" 
                            data-show-text="<?= htmlspecialchars(sf_term('dashboard_show_all', $uiLang), ENT_QUOTES, 'UTF-8') ?>"
                            data-hide-text="<?= htmlspecialchars(sf_term('dashboard_show_less', $uiLang), ENT_QUOTES, 'UTF-8') ?>">
                        <span class="sf-toggle-text"><?= htmlspecialchars(sf_term('dashboard_show_all', $uiLang), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="sf-toggle-icon">▼</span>
                    </button>
                <?php endif; ?>
            <?php else: ?>
                <div class="sf-pending-empty">
                    <img src="<?= htmlspecialchars($baseUrl) ?>/assets/img/icons/empty-data.svg" alt="" style="width: 2rem; height: 2rem; opacity: 0.4;">
                    <span><?= htmlspecialchars(sf_term('dashboard_no_data', $uiLang), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>
            
            </div> <!-- End Worksite Statistics Card -->
            
        </div> <!-- End sf-dashboard-stats-section -->

        <!-- ========== COMPACT CARDS SECTION ========== -->
        <div class="sf-compact-cards">
            
            <!-- Waiting for You (Compact) -->
            <div class="sf-content-card">
                <div class="sf-section-header">
                    <span class="sf-section-icon">
                        <img src="<?= htmlspecialchars($baseUrl) ?>/assets/img/icons/pending.svg" alt="" style="width: 1.25rem; height: 1.25rem;">
                    </span>
                    <span class="sf-section-title"><?= htmlspecialchars(sf_term('dashboard_pending_title', $uiLang), ENT_QUOTES, 'UTF-8') ?></span>
                </div>

                <?php if (empty($waitingItems)): ?>
                    <div class="sf-pending-empty">
                        <img src="<?= htmlspecialchars($baseUrl) ?>/assets/img/icons/pending.svg" alt="" style="width: 2rem; height: 2rem; opacity: 0.4;">
                        <span><?= htmlspecialchars(sf_term('dashboard_pending_empty', $uiLang), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php else: ?>
                    <div class="sf-recent-compact-list">
                        <?php foreach ($waitingItems as $item): 
                            $itemTitle = !empty($item['title_short']) ? $item['title_short'] : $item['title'];
                            $itemType = $item['type'] ?? '';
                            $itemId = (int)($item['id'] ?? 0);
                            $itemSite = $item['site'] ?? '';
                            $itemState = $item['state'] ?? '';
                            $itemTime = $item['updated_at'] ?? '';
                            $stateLabel = sf_status_label($itemState, $uiLang);
                        ?>
                            <a href="<?= htmlspecialchars($baseUrl) ?>/index.php?page=view&id=<?= $itemId ?>" class="sf-recent-compact-item">
                                <span class="sf-type-dot sf-type-dot--<?= htmlspecialchars($itemType) ?>"></span>
                                <div class="sf-recent-compact-content">
                                    <div class="sf-recent-compact-title"><?= htmlspecialchars($itemTitle, ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="sf-recent-compact-meta">
                                        <span><?= htmlspecialchars($stateLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if (!empty($itemSite)): ?>
                                            <span>·</span>
                                            <span><?= htmlspecialchars($itemSite, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($itemTime)): ?>
                                            <span>·</span>
                                            <span class="sf-recent-compact-time"><?= htmlspecialchars(sf_time_ago($itemTime, $uiLang), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div style="margin-top: 1rem; text-align: center;">
                    <a href="<?= htmlspecialchars($baseUrl) ?>/index.php?page=list" class="sf-show-all-link">
                        <?= htmlspecialchars(sf_term('dashboard_show_all', $uiLang), ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </div>
            </div>

            <!-- Recent (Compact) -->
            <div class="sf-content-card">
                <div class="sf-section-header">
                    <span class="sf-section-icon">
                        <img src="<?= htmlspecialchars($baseUrl) ?>/assets/img/icons/recent.svg" alt="" style="width: 1.25rem; height: 1.25rem;">
                    </span>
                    <span class="sf-section-title"><?= htmlspecialchars(sf_term('dashboard_recent', $uiLang), ENT_QUOTES, 'UTF-8') ?></span>
                </div>

                <?php if (empty($recentItems)): ?>
                    <div class="sf-pending-empty">
                        <img src="<?= htmlspecialchars($baseUrl) ?>/assets/img/icons/recent.svg" alt="" style="width: 2rem; height: 2rem; opacity: 0.4;">
                        <span><?= htmlspecialchars(sf_term('dashboard_recent_empty', $uiLang), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php else: ?>
                    <div class="sf-recent-compact-list">
                        <?php foreach ($recentItems as $item): 
                            $itemTitle = !empty($item['title_short']) ? $item['title_short'] : $item['title'];
                            $itemType = $item['type'] ?? '';
                            $itemId = (int)($item['id'] ?? 0);
                            $itemSite = $item['site'] ?? '';
                            $itemTime = $item['updated_at'] ?? '';
                        ?>
                            <a href="<?= htmlspecialchars($baseUrl) ?>/index.php?page=view&id=<?= $itemId ?>" class="sf-recent-compact-item">
                                <span class="sf-type-dot sf-type-dot--<?= htmlspecialchars($itemType) ?>"></span>
                                <div class="sf-recent-compact-content">
                                    <div class="sf-recent-compact-title"><?= htmlspecialchars($itemTitle, ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="sf-recent-compact-meta">
                                        <?php if (!empty($itemSite)): ?>
                                            <span><?= htmlspecialchars($itemSite, ENT_QUOTES, 'UTF-8') ?></span>
                                            <span>·</span>
                                        <?php endif; ?>
                                        <?php if (!empty($itemTime)): ?>
                                            <span class="sf-recent-compact-time"><?= htmlspecialchars(sf_time_ago($itemTime, $uiLang), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div style="margin-top: 1rem; text-align: center;">
                    <a href="<?= htmlspecialchars($baseUrl) ?>/index.php?page=list" class="sf-show-all-link">
                        <?= htmlspecialchars(sf_term('dashboard_show_all', $uiLang), ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </div>
            </div>

        </div>

    </div>
</div>