# API Key Authentication & Display Duration Implementation

## Overview

This implementation adds API key authentication, per-image display duration, and comprehensive Xibo Embedded Widget templates to the SafetyFlash Xibo integration.

## Completed Features

### 1. API Key Authentication

**Database**: `sf_display_api_keys` table
- Stores API keys with format: `sf_dk_` + 48 hex characters (24 random bytes)
- Tracks site, label, language, active status, expiry, and usage
- Indexes on api_key and site for fast lookups

**Management Endpoint**: `/app/api/display_api_keys_manage.php` (Admin only)
- Create new keys with site/label/lang configuration
- List all keys (metadata only, keys never returned after creation)
- Deactivate keys (set is_active = 0)
- CSRF protected, role_id = 1 required

**Display Playlist Endpoint**: `/app/api/display_playlist.php`
- Changed from public site parameter to API key authentication
- Validates key against database (is_active, expires_at)
- Updates last_used_at and last_used_ip on each request
- Returns 401 for missing key, 403 for invalid/expired/inactive
- Includes duration_seconds in JSON response items

### 2. Display Duration

**Database**: Added `display_duration_seconds INT DEFAULT 30` to `sf_flashes`
- Default: 30 seconds
- Range: 5-120 seconds (validated in publish action)

**UI Component**: `/assets/partials/publish_display_duration.php`
- Chip-style radio selector matching TTL selector design
- Options: 10s, 15s, 20s, 30s (default), 45s, 60s
- Clock icon SVG
- sf_term() localization with Finnish fallback

**Publish Action**: `/app/actions/publish.php`
- Reads $_POST['display_duration_seconds']
- Validates range (5-120 seconds)
- Saves to database alongside TTL

### 3. Enhanced Slideshow

**Visual Improvements**:
- Progress bar animation (4px white bar at bottom)
- 0.8s fade transitions (changed from 1s)
- Auto-refresh every 5 minutes
- Black background, object-fit: contain

**JavaScript**:
- Uses per-image duration_seconds from API
- Progress bar synced with image duration
- Handles empty playlists gracefully

### 4. Documentation

**New**: `docs/XIBO_EMBEDDED_WIDGET.md`
- Complete copy-paste templates for HTML, CSS, JavaScript
- Configuration instructions
- Webpage Widget alternative
- Troubleshooting guide
- Player compatibility notes

**Updated**: `XIBO_INTEGRATION.md`
- API key authentication documentation
- Error response codes (401, 403)
- Migration from site parameter
- Best practices for API key management

**Updated**: `README_XIBO.md`
- New features summary
- Updated file structure
- Security features list

## Migration Guide

### From Site Parameter to API Key

**Old URL**:
```
/app/api/display_playlist.php?site=SITE_ID&lang=fi
```

**New URL**:
```
/app/api/display_playlist.php?key=sf_dk_abc123...
```

The API key determines site and language automatically.

### Database Migrations

Run in order:
```bash
mysql -u user -p database < migrations/add_display_ttl.sql       # Already exists
mysql -u user -p database < migrations/add_display_duration.sql   # NEW
mysql -u user -p database < migrations/add_display_api_keys.sql   # NEW
```

### UI Integration

**Publish Modal** (add duration selector):
```php
<?php require_once __DIR__ . '/assets/partials/publish_display_ttl.php'; ?>
<?php require_once __DIR__ . '/assets/partials/publish_display_duration.php'; ?>
```

## Security

- ✅ API keys use cryptographically secure random_bytes()
- ✅ Admin-only key management (role_id = 1)
- ✅ CSRF protection on all mutations
- ✅ Prepared statements prevent SQL injection
- ✅ Input validation and sanitization
- ✅ Rate limiting (60 req/min per IP)
- ✅ API key expiry and deactivation support
- ✅ Last used tracking for audit

## Testing

All tests pass:
- ✅ PHP syntax validation
- ✅ File existence checks
- ✅ Integration test suite
- ✅ Code review (no issues)
- ✅ CodeQL security scan (no issues)

## API Examples

### Create API Key (Admin)
```bash
POST /app/api/display_api_keys_manage.php
Content-Type: application/x-www-form-urlencoded

csrf_token=xxx&site=SITE_ID&label=Helsinki+Office&lang=fi
```

Response:
```json
{
  "ok": true,
  "api_key": "sf_dk_abc123...",
  "xibo_url": "https://domain.com/app/api/display_playlist.php?key=sf_dk_abc123...",
  "site": "SITE_ID",
  "label": "Helsinki Office",
  "lang": "fi"
}
```

### Get Playlist
```bash
GET /app/api/display_playlist.php?key=sf_dk_abc123...&format=json
```

Response:
```json
{
  "ok": true,
  "site": "SITE_ID",
  "lang": "fi",
  "count": 2,
  "items": [
    {
      "id": 123,
      "title": "Safety Alert",
      "image_url": "https://domain.com/uploads/previews/preview_123.jpg",
      "duration_seconds": 30,
      "type": "yellow",
      "published_at": "2026-02-19 10:00:00",
      "sort_order": 0
    }
  ],
  "updated_at": "2026-02-19T12:00:00+02:00"
}
```

## Files Changed

### New Files (6)
- `migrations/add_display_api_keys.sql`
- `migrations/add_display_duration.sql`
- `app/api/display_api_keys_manage.php`
- `assets/partials/publish_display_duration.php`
- `docs/XIBO_EMBEDDED_WIDGET.md`

### Modified Files (5)
- `app/api/display_playlist.php` - API key auth, progress bar
- `app/actions/publish.php` - Display duration saving
- `XIBO_INTEGRATION.md` - API key docs
- `README_XIBO.md` - Feature list
- `test_xibo_integration.php` - Test updates

Total: +1015 insertions, -81 deletions

## Next Steps

1. Deploy database migrations to production
2. Create initial API keys for existing displays
3. Update Xibo widgets with new API key URLs
4. Monitor API key usage via last_used_at
5. Set expiry dates for temporary displays

## Support

For questions or issues:
- See troubleshooting guide in `docs/XIBO_EMBEDDED_WIDGET.md`
- Check API documentation in `XIBO_INTEGRATION.md`
- Review test suite in `test_xibo_integration.php`

---

**Implementation Date**: 2026-02-19
**Status**: ✅ Complete and tested
