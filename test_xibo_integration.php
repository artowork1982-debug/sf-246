<?php
/**
 * Simple test script for Xibo integration APIs
 * 
 * This script performs basic validation checks without requiring
 * a full database setup.
 */

echo "=== Xibo Integration Test Suite ===\n\n";

// Test 1: Check file existence
echo "1. Checking file existence...\n";
$files = [
    'app/actions/publish.php',
    'app/api/display_playlist.php',
    'app/api/display_playlist_manage.php',
    'assets/partials/publish_display_ttl.php',
    'assets/partials/view_playlist_status.php',
    'assets/css/display-ttl.css',
    'assets/js/display-playlist.js',
    'app/config/terms/display.php',
    'migrations/add_display_ttl.sql',
];

$allExist = true;
foreach ($files as $file) {
    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        echo "   ✓ {$file}\n";
    } else {
        echo "   ✗ {$file} NOT FOUND\n";
        $allExist = false;
    }
}

if ($allExist) {
    echo "   All files exist!\n\n";
} else {
    echo "   Some files are missing!\n\n";
    exit(1);
}

// Test 2: PHP Syntax Check
echo "2. Checking PHP syntax...\n";
$phpFiles = [
    'app/actions/publish.php',
    'app/api/display_playlist.php',
    'app/api/display_playlist_manage.php',
    'assets/partials/publish_display_ttl.php',
    'assets/partials/view_playlist_status.php',
    'app/config/terms/display.php',
];

$syntaxOk = true;
foreach ($phpFiles as $file) {
    $fullPath = __DIR__ . '/' . $file;
    $output = [];
    $returnCode = 0;
    exec("php -l " . escapeshellarg($fullPath) . " 2>&1", $output, $returnCode);
    
    if ($returnCode === 0) {
        echo "   ✓ {$file}\n";
    } else {
        echo "   ✗ {$file} has syntax errors:\n";
        echo "      " . implode("\n      ", $output) . "\n";
        $syntaxOk = false;
    }
}

if ($syntaxOk) {
    echo "   All PHP files have valid syntax!\n\n";
} else {
    echo "   Some PHP files have syntax errors!\n\n";
    exit(1);
}

// Test 3: Check terms configuration
echo "3. Checking terms configuration...\n";
$termsFile = __DIR__ . '/app/config/terms/display.php';
$terms = require $termsFile;

$expectedKeys = [
    'display_ttl_heading',
    'ttl_no_limit',
    'ttl_1_week',
    'ttl_1_month',
    'playlist_status_active',
    'btn_remove_from_playlist',
    'btn_restore_to_playlist',
];

$termsOk = true;
foreach ($expectedKeys as $key) {
    if (isset($terms[$key])) {
        echo "   ✓ {$key}\n";
    } else {
        echo "   ✗ {$key} missing\n";
        $termsOk = false;
    }
}

if ($termsOk) {
    echo "   All expected terms exist!\n\n";
} else {
    echo "   Some terms are missing!\n\n";
}

// Test 4: Check SQL migration
echo "4. Checking SQL migration...\n";
$sqlFile = __DIR__ . '/migrations/add_display_ttl.sql';
$sqlContent = file_get_contents($sqlFile);

$expectedColumns = [
    'display_expires_at',
    'display_removed_at',
    'display_removed_by',
];

$sqlOk = true;
foreach ($expectedColumns as $column) {
    if (strpos($sqlContent, $column) !== false) {
        echo "   ✓ Column: {$column}\n";
    } else {
        echo "   ✗ Column: {$column} missing\n";
        $sqlOk = false;
    }
}

if (strpos($sqlContent, 'idx_display_active') !== false) {
    echo "   ✓ Index: idx_display_active\n";
} else {
    echo "   ✗ Index: idx_display_active missing\n";
    $sqlOk = false;
}

if ($sqlOk) {
    echo "   SQL migration looks good!\n\n";
} else {
    echo "   SQL migration has issues!\n\n";
}

// Test 5: Check CSS classes
echo "5. Checking CSS classes...\n";
$cssFile = __DIR__ . '/assets/css/display-ttl.css';
$cssContent = file_get_contents($cssFile);

$expectedClasses = [
    'sf-ttl-chips',
    'sf-ttl-chip',
    'sf-playlist-status-card',
    'sf-btn-outline-danger',
    'sf-btn-outline-primary',
];

$cssOk = true;
foreach ($expectedClasses as $class) {
    if (strpos($cssContent, '.' . $class) !== false) {
        echo "   ✓ Class: .{$class}\n";
    } else {
        echo "   ✗ Class: .{$class} missing\n";
        $cssOk = false;
    }
}

if ($cssOk) {
    echo "   CSS classes look good!\n\n";
} else {
    echo "   Some CSS classes are missing!\n\n";
}

// Test 6: Check JavaScript functions
echo "6. Checking JavaScript functions...\n";
$jsFile = __DIR__ . '/assets/js/display-playlist.js';
$jsContent = file_get_contents($jsFile);

$expectedFunctions = [
    'initTtlChips',
    'initPlaylistButtons',
    'handleRemoveFromPlaylist',
    'handleRestoreToPlaylist',
    'sendPlaylistAction',
];

$jsOk = true;
foreach ($expectedFunctions as $func) {
    if (strpos($jsContent, 'function ' . $func) !== false) {
        echo "   ✓ Function: {$func}\n";
    } else {
        echo "   ✗ Function: {$func} missing\n";
        $jsOk = false;
    }
}

if ($jsOk) {
    echo "   JavaScript functions look good!\n\n";
} else {
    echo "   Some JavaScript functions are missing!\n\n";
}

// Summary
echo "\n=== Test Summary ===\n";
if ($allExist && $syntaxOk && $termsOk && $sqlOk && $cssOk && $jsOk) {
    echo "✓ All tests passed! Implementation looks good.\n\n";
    echo "Next steps:\n";
    echo "1. Run database migration: mysql -u user -p database < migrations/add_display_ttl.sql\n";
    echo "2. Include partials in publish modal and view page\n";
    echo "3. Include CSS and JS in your HTML templates\n";
    echo "4. Test with actual database and user sessions\n";
    exit(0);
} else {
    echo "✗ Some tests failed. Please review the issues above.\n";
    exit(1);
}
