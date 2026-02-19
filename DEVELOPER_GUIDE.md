# Developer Integration Guide

## Quick Start

This guide shows exactly where and how to integrate the Xibo display components into existing SafetyFlash pages.

## Step 1: Database Migration

**IMPORTANT**: Run this first before testing any features.

```bash
# Using MySQL command line
mysql -u your_username -p your_database < migrations/add_display_ttl.sql

# Or using phpMyAdmin
# 1. Open phpMyAdmin
# 2. Select your database
# 3. Go to SQL tab
# 4. Copy-paste contents of migrations/add_display_ttl.sql
# 5. Click "Go"
```

## Step 2: Publish Modal Integration

Find your publish modal page (likely `assets/pages/publish.php` or similar that contains the publish form).

### Location to Add
After the distribution settings section, before the submit buttons.

### Code to Add
```php
<?php 
// Display TTL Selector
require_once __DIR__ . '/../partials/publish_display_ttl.php'; 
?>
```

### Example Context
```php
<!-- Existing publish modal content -->
<div class="modal-section">
    <h3>Distribution Settings</h3>
    <!-- Distribution checkboxes, country selection, etc. -->
</div>

<!-- ADD THIS SECTION -->
<?php require_once __DIR__ . '/../partials/publish_display_ttl.php'; ?>
<!-- END NEW SECTION -->

<div class="modal-footer">
    <button type="submit">Publish</button>
</div>
```

## Step 3: View Page Integration

Find your view page (likely `assets/pages/view.php` or the file that displays flash details).

### Location to Add
In the right column/sidebar, after the metadata box or approval section.

### Code to Add
```php
<?php 
// Playlist Status Card
// Required variables: $flash, $currentUiLang, $id, $isAdmin, $isSafety, $isComms
require_once __DIR__ . '/../partials/view_playlist_status.php'; 
?>
```

### Example Context
```php
<div class="sf-view-sidebar">
    <!-- Existing sidebar content -->
    <div class="sf-meta-box">
        <h4>Status</h4>
        <!-- Status info -->
    </div>
    
    <!-- ADD THIS SECTION -->
    <?php require_once __DIR__ . '/../partials/view_playlist_status.php'; ?>
    <!-- END NEW SECTION -->
</div>
```

### Required Variables Setup

Make sure these variables are available before including the partial:

```php
// At the top of your view page, ensure these are defined:
$flash = /* your flash data from database */;
$currentUiLang = $_SESSION['ui_lang'] ?? 'fi';
$id = /* flash ID from URL */;

// Role checks
$user = sf_current_user();
$isAdmin = ($user && (int)$user['role_id'] === 1);
$isSafety = ($user && (int)$user['role_id'] === 3);
$isComms = ($user && (int)$user['role_id'] === 4);
```

## Step 4: CSS Integration

Find your main header file (likely `app/includes/header.php` or `assets/includes/header.php`).

### Location to Add
In the `<head>` section, after other CSS imports.

### Code to Add
```html
<!-- Display TTL Styles -->
<link rel="stylesheet" href="<?= $config['base_url'] ?>/assets/css/display-ttl.css">
```

### Example Context
```html
<head>
    <meta charset="UTF-8">
    <title>SafetyFlash</title>
    
    <!-- Existing styles -->
    <link rel="stylesheet" href="<?= $config['base_url'] ?>/assets/css/global.css">
    <link rel="stylesheet" href="<?= $config['base_url'] ?>/assets/css/form.css">
    
    <!-- ADD THIS LINE -->
    <link rel="stylesheet" href="<?= $config['base_url'] ?>/assets/css/display-ttl.css">
</head>
```

## Step 5: JavaScript Integration

Find your footer file (likely `app/includes/footer.php` or `assets/includes/footer.php`).

### Location to Add
Before the closing `</body>` tag, after other JavaScript files.

### Code to Add
```html
<!-- Display Playlist Management -->
<script>
    // Global variables for API
    window.SF_BASE_URL = '<?= $config['base_url'] ?>';
    window.SF_CSRF_TOKEN = '<?= sf_csrf_token() ?>';
    window.SF_TERMS = {
        confirm_remove_from_playlist: '<?= sf_term('confirm_remove_from_playlist', $currentUiLang) ?>'
    };
</script>
<script src="<?= $config['base_url'] ?>/assets/js/display-playlist.js"></script>
```

### Example Context
```html
    <!-- Existing scripts -->
    <script src="<?= $config['base_url'] ?>/assets/js/modals.js"></script>
    <script src="<?= $config['base_url'] ?>/assets/js/form.js"></script>
    
    <!-- ADD THIS SECTION -->
    <script>
        window.SF_BASE_URL = '<?= $config['base_url'] ?>';
        window.SF_CSRF_TOKEN = '<?= sf_csrf_token() ?>';
        window.SF_TERMS = {
            confirm_remove_from_playlist: '<?= sf_term('confirm_remove_from_playlist', $currentUiLang) ?>'
        };
    </script>
    <script src="<?= $config['base_url'] ?>/assets/js/display-playlist.js"></script>
    <!-- END NEW SECTION -->
</body>
</html>
```

## Step 6: Verify Integration

### Test Publish Modal
1. Create or edit a flash
2. Click "Publish"
3. You should see the "Näkyvyysaika infonäytöillä" section with chip-style options
4. Select different TTL options and verify the preview date updates
5. Publish the flash

### Test View Page
1. View a published flash
2. You should see a colored status card showing playlist status
3. If you're an admin/safety/comms, you should see management buttons
4. Click "Poista playlistasta" (should prompt for confirmation)
5. Click "Palauta playlistaan" (should restore without confirmation)

### Test API
```bash
# Test JSON format
curl "http://your-domain.com/app/api/display_playlist.php?site=test&lang=fi"

# Test HTML format
curl "http://your-domain.com/app/api/display_playlist.php?site=test&format=html" > test.html
# Open test.html in browser
```

## Common Issues & Solutions

### Issue: TTL selector not showing
**Solution**: Verify the path to the partial is correct and the file exists.

### Issue: Playlist status card not showing
**Solution**: 
1. Check that flash is published (`state = 'published'`)
2. Verify all required variables are set ($flash, $id, $isAdmin, etc.)

### Issue: JavaScript not working
**Solution**:
1. Check browser console for errors
2. Verify SF_BASE_URL, SF_CSRF_TOKEN are set
3. Ensure display-playlist.js is loaded

### Issue: API returns 404
**Solution**: 
1. Verify file exists at `/app/api/display_playlist.php`
2. Check web server configuration (mod_rewrite, etc.)

### Issue: Database errors
**Solution**: 
1. Verify migration was run successfully
2. Check that columns exist: `SHOW COLUMNS FROM sf_flashes LIKE 'display_%';`

## File Checklist

Before going live, verify all these files exist:

```
☐ migrations/add_display_ttl.sql
☐ app/actions/publish.php (modified)
☐ app/api/display_playlist.php
☐ app/api/display_playlist_manage.php
☐ assets/partials/publish_display_ttl.php
☐ assets/partials/view_playlist_status.php
☐ assets/css/display-ttl.css
☐ assets/js/display-playlist.js
☐ app/config/terms/display.php
☐ app/config/terms/_index.php (modified)
```

## Testing Checklist

Before deploying to production:

```
☐ Database migration completed
☐ TTL selector appears in publish modal
☐ TTL options are clickable and responsive
☐ Preview date updates when selecting options
☐ Flash saves with correct TTL on publish
☐ Playlist status card appears on view page
☐ Status card shows correct state (active/expired/removed)
☐ Remove button works (with confirmation)
☐ Restore button works
☐ Page reloads after remove/restore
☐ API responds with JSON
☐ API responds with HTML slideshow
☐ Rate limiting works (test with 61+ requests)
☐ All text appears in correct language
```

## Xibo Configuration

Once integration is complete, set up Xibo:

1. **Create Webpage Widget**
   - Layout → Add Widget → Webpage
   - URL: `https://your-domain.com/app/api/display_playlist.php?site=SITE_ID&format=html&duration=10`
   - Duration: 300 seconds (5 minutes)
   - Enable "Open Natively"

2. **Schedule to Layout**
   - Add widget to layout
   - Schedule layout to displays
   - Verify displays show flashes

3. **Monitor**
   - Check display rotation
   - Verify new flashes appear after publishing
   - Confirm expired flashes disappear

## Support

- **Documentation**: See `XIBO_INTEGRATION.md` for full API documentation
- **Testing**: Run `php test_xibo_integration.php` to verify installation
- **Summary**: See `IMPLEMENTATION_SUMMARY.md` for complete overview

## Quick Commands

```bash
# Test all PHP syntax
find . -name "*.php" -exec php -l {} \;

# Run integration tests
php test_xibo_integration.php

# Test API
curl "http://localhost/app/api/display_playlist.php?site=test&lang=fi"

# Watch API logs (if logging enabled)
tail -f app/logs/app.log
```

---

**Need help?** Check the troubleshooting section or review the complete documentation in `XIBO_INTEGRATION.md`.
