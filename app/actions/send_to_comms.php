<?php
// app/actions/send_to_comms.php
declare(strict_types=1);

// Convert warnings/notices to exceptions
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    require_once __DIR__ . '/../includes/protect.php';
    require_once __DIR__ . '/../includes/log_app.php';
    require_once __DIR__ . '/../includes/statuses.php';
    require_once __DIR__ . '/../includes/log.php';

    if (is_file(__DIR__ . '/helpers.php')) {
        require_once __DIR__ . '/helpers.php';
    }

    require_once __DIR__ . '/../../assets/lib/Database.php';
    require_once __DIR__ . '/../../assets/services/email_services.php';

    $base = rtrim($config['base_url'] ?? '', '/');

    // Only POST
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        header("Location: {$base}/index.php?page=list");
        exit;
    }

    // ID from URL
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) {
        sf_app_log("send_to_comms.php: Invalid ID");
        http_response_code(400);
        echo 'Virheellinen ID.';
        exit;
    }

    // Message from form
    $message = trim((string) ($_POST['message'] ?? ''));
    if ($message !== '') {
        $message = mb_substr($message, 0, 2000);
    }

    // Capture new multi-step form data
    $languages = $_POST['languages'] ?? [];
    if (!is_array($languages)) {
        $languages = [];
    }
    
    // Simplified distribution - just a toggle now
    $widerDistribution = !empty($_POST['wider_distribution']) ? (int)$_POST['wider_distribution'] : 0;
    
    $screensOption = trim((string) ($_POST['screens_option'] ?? 'all'));
    
    // Get selected countries and worksites from POST
    $selectedCountries = isset($_POST['countries']) ? (array)$_POST['countries'] : [];
    $selectedWorksites = isset($_POST['worksites']) ? (array)$_POST['worksites'] : [];
    
    // For backward compatibility
    $worksites = $selectedWorksites;

    sf_app_log("send_to_comms.php: Processing flash {$id}");
    sf_app_log("send_to_comms.php: Languages: " . json_encode($languages));
    sf_app_log("send_to_comms.php: Wider distribution: " . ($widerDistribution ? 'Yes' : 'No'));
    sf_app_log("send_to_comms.php: Screens option: {$screensOption}");
    sf_app_log("send_to_comms.php: Selected countries: " . json_encode($selectedCountries));
    sf_app_log("send_to_comms.php: Selected worksites: " . json_encode($selectedWorksites));

    // DB connection
    $pdo = Database::getInstance();

    // Fetch flash
    $stmt = $pdo->prepare("SELECT id, translation_group_id, state FROM sf_flashes WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $flash = $stmt->fetch();

    if (!$flash) {
        sf_app_log("send_to_comms.php: Flash not found, id={$id}");
        
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        
        if ($isAjax) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'message' => 'Tiedotetta ei löytynyt.'
            ]);
            exit;
        }
        
        header("Location: {$base}/index.php?page=list&notice=error");
        exit;
    }

    $logFlashId = !empty($flash['translation_group_id'])
        ? (int) $flash['translation_group_id']
        : (int) $flash['id'];

$oldState = (string) ($flash['state'] ?? '');

// --- Permission + state guard ---
$user = function_exists('sf_current_user') ? sf_current_user() : null;
$roleId = (int)($user['role_id'] ?? ($_SESSION['role_id'] ?? 0));

$isAdmin  = ($roleId === 1);
$isSafety = ($roleId === 3);

if (!$isAdmin && !$isSafety) {
    sf_app_log("send_to_comms.php: Forbidden (role_id={$roleId})");
    
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if ($isAjax) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'message' => 'Ei oikeuksia.'
        ]);
        exit;
    }
    
    http_response_code(403);
    echo 'Ei oikeuksia.';
    exit;
}

// sallitaan lähetys viestintään vain järkevistä tiloista
if (!in_array($oldState, ['pending_review', 'pending_supervisor'], true)) {
    sf_app_log("send_to_comms.php: Invalid state '{$oldState}' for sending to comms (id={$id})");
    
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if ($isAjax) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'message' => 'Tiedotetta ei voi lähettää viestintään tässä tilassa.'
        ]);
        exit;
    }
    
    header("Location: {$base}/index.php?page=view&id={$id}&notice=error");
    exit;
}

// Update state for all languages
$newState = 'to_comms';
$updatedCount = sf_update_state_all_languages($pdo, $id, $newState);

    sf_app_log("send_to_comms.php: Flash {$id} state updated to {$newState} for {$updatedCount} language version(s)");

    $currentUiLang = $_SESSION['ui_lang'] ?? 'fi';

    // Build list of selected worksite names
    $selectedWorksiteNames = [];

    if ($screensOption === 'all') {
        $selectedWorksiteNames[] = sf_term('comms_screens_all', $currentUiLang) ?? 'Kaikki näytöt';
    } else {
        // Get worksites by country
        if (!empty($selectedCountries)) {
            $countryNames = [
                'fi' => '🇫🇮 ' . (sf_term('country_finland', $currentUiLang) ?? 'Suomi'),
                'it' => '🇮🇹 ' . (sf_term('country_italy', $currentUiLang) ?? 'Italia'),
                'el' => '🇬🇷 ' . (sf_term('country_greece', $currentUiLang) ?? 'Kreikka')
            ];
            
            foreach ($selectedCountries as $country) {
                if (isset($countryNames[$country])) {
                    $selectedWorksiteNames[] = $countryNames[$country];
                }
            }
        }
        
        // Get individual worksites by ID
        if (!empty($selectedWorksites)) {
            try {
                // Ensure we have valid positive integer IDs only
                $validIds = array_filter($selectedWorksites, function($id) {
                    if (is_string($id)) {
                        return ctype_digit($id) && intval($id) > 0;
                    }
                    return is_int($id) && $id > 0;
                });
                $validIds = array_map('intval', $validIds);
                
                if (!empty($validIds)) {
                    $placeholders = implode(',', array_fill(0, count($validIds), '?'));
                    $stmt = $pdo->prepare("
                        SELECT name FROM sf_worksites WHERE id IN ($placeholders)
                    ");
                    $stmt->execute($validIds);
                    $names = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    $selectedWorksiteNames = array_merge($selectedWorksiteNames, $names);
                }
            } catch (Exception $e) {
                error_log('Error fetching worksite names: ' . $e->getMessage());
            }
        }
    }

    // Create text for log
    $worksitesText = !empty($selectedWorksiteNames) 
        ? implode(', ', array_unique($selectedWorksiteNames))
        : (sf_term('comms_summary_none', $currentUiLang) ?? 'Ei valintoja');

    $desc = "log_status_set|status:{$newState}";
    if ($message !== '') {
        $desc .= "\nlog_message_to_comms_label: " . $message;
    }
    if (!empty($languages)) {
        $langLabels = array_map('strtoupper', $languages);
        $desc .= "\nemail_selected_languages: " . implode(', ', $langLabels);
    }
    if ($widerDistribution) {
        $desc .= "\nemail_wider_distribution_yes";
    } else {
        $desc .= "\nemail_no_distribution";
    }
    // Include selected worksites in log
    $desc .= "\nemail_selected_worksites: " . $worksitesText;

    // User ID
    $userId = null;
    if (function_exists('sf_current_user')) {
        $user = sf_current_user();
        $userId = isset($user['id']) ? (int) $user['id'] : null;
    } else {
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    // Log event
    if (function_exists('sf_log_event')) {
        sf_log_event($logFlashId, 'sent_to_comms', $desc);
    } else {
        $log = $pdo->prepare("
            INSERT INTO safetyflash_logs (flash_id, user_id, event_type, description, created_at)
            VALUES (:flash_id, :user_id, :event_type, :description, NOW())
        ");
        $log->execute([
            ':flash_id'   => $logFlashId,
            ':user_id'    => $userId,
            ':event_type' => 'sent_to_comms',
            ':description'=> $desc,
        ]);
    }

    // Separate state_changed event
    if ($oldState !== $newState) {
        require_once __DIR__ . '/../../assets/lib/sf_terms.php';

        $oldStateLabel = sf_status_label($oldState, $currentUiLang);
        $newStateLabel = sf_status_label($newState, $currentUiLang);
        $stateChangeDesc = sf_term('log_state_changed', $currentUiLang) . ": {$oldStateLabel} → {$newStateLabel}";

        if (function_exists('sf_log_event')) {
            sf_log_event($logFlashId, 'state_changed', $stateChangeDesc);
        } else {
            $logStateChange = $pdo->prepare("
                INSERT INTO safetyflash_logs (flash_id, user_id, event_type, description, created_at)
                VALUES (:flash_id, :user_id, :event_type, :description, NOW())
            ");
            $logStateChange->execute([
                ':flash_id'   => $logFlashId,
                ':user_id'    => $userId,
                ':event_type' => 'state_changed',
                ':description'=> $stateChangeDesc,
            ]);
        }
    }

    // Email sending (don't fail whole request if email fails)
    try {
        if (function_exists('sf_mail_to_comms')) {
            sf_app_log("send_to_comms.php: Attempting to send email for flash {$id}");
            // Pass countries and worksitesText for email body
            sf_mail_to_comms($pdo, $id, $message, true, $languages, $widerDistribution, $screensOption, $worksites, $selectedCountries, $worksitesText);
            sf_app_log("send_to_comms.php: Email sent successfully for flash {$id}");
        } else {
            sf_app_log("send_to_comms.php: sf_mail_to_comms function not found");
        }
    } catch (Throwable $emailError) {
        sf_app_log(
            "send_to_comms.php: EMAIL ERROR for flash {$id}: " . $emailError->getMessage(),
            LOG_LEVEL_ERROR
        );
        error_log(
            "send_to_comms.php EMAIL ERROR: " . $emailError->getMessage() . "\n" . $emailError->getTraceAsString()
        );
    }

    // Audit log
    require_once __DIR__ . '/../includes/audit_log.php';
    $user = function_exists('sf_current_user') ? sf_current_user() : null;

    sf_audit_log(
        'flash_to_comms',
        'flash',
        (int) $id,
        [
            'new_status'   => $newState,
            'has_message'  => ($message !== ''),
        ],
        $user ? (int) $user['id'] : null
    );

    // Return JSON for AJAX, else redirect
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'       => true,
            'message' => sf_term('notice_sent_to_comms', $currentUiLang),
            'redirect' => "{$base}/index.php?page=view&id=" . (int) $id,
        ]);
        exit;
    }

    header("Location: {$base}/index.php?page=view&id=" . (int) $id . "&notice=comms_sent");
    exit;

} catch (Throwable $e) {
    if (function_exists('sf_app_log')) {
        sf_app_log(
            basename(__FILE__) . ' FATAL ERROR: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(),
            LOG_LEVEL_ERROR
        );
    } else {
        error_log(basename(__FILE__) . ' FATAL ERROR: ' . $e->getMessage());
    }

    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($isAjax) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'    => false,
            'error' => $e->getMessage(),
            'debug' => $e->getFile() . ':' . $e->getLine(),
            'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 3),
        ]);
        exit;
    }

    $base = rtrim($config['base_url'] ?? '', '/');
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if ($base !== '') {
        header("Location: {$base}/index.php?page=view&id={$id}&notice=error");
    } else {
        header("Location: /index.php?page=view&id={$id}&notice=error");
    }
    exit;

} finally {
    restore_error_handler();
}