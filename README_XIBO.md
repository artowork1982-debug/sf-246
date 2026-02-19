# Xibo Integration for SafetyFlash

## Quick Links

📖 **[Developer Integration Guide](DEVELOPER_GUIDE.md)** - Step-by-step integration instructions
📚 **[API Documentation](XIBO_INTEGRATION.md)** - Complete API reference and Xibo setup
📋 **[Deployment Checklist](DEPLOYMENT_CHECKLIST.md)** - Pre/post-deployment verification
📊 **[Implementation Summary](IMPLEMENTATION_SUMMARY.md)** - Technical details and features
🧪 **[Test Suite](test_xibo_integration.php)** - Automated validation script

## What's New

This integration adds Xibo information display support to SafetyFlash, enabling:

- ⏱️ **TTL Management** - Set how long flashes appear on displays (7 days to 3 months)
- 📊 **Playlist Status** - Real-time visibility into display status
- 🎛️ **Manual Controls** - Remove/restore flashes from playlist
- 🌐 **Public API** - JSON/HTML/slideshow formats for Xibo
- 🔒 **Secure** - CSRF protection, rate limiting, role-based access

## Quick Start (5 Minutes)

1. **Run Database Migration**
   ```bash
   mysql -u user -p database < migrations/add_display_ttl.sql
   ```

2. **Run Tests**
   ```bash
   php test_xibo_integration.php
   ```

3. **Follow Integration Guide**
   See [DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md) for detailed steps

4. **Configure Xibo**
   See [XIBO_INTEGRATION.md](XIBO_INTEGRATION.md) for Xibo setup

## Files Structure

```
├── migrations/
│   └── add_display_ttl.sql          # Database schema changes
├── app/
│   ├── actions/
│   │   └── publish.php              # Modified: TTL saving logic
│   ├── api/
│   │   ├── display_playlist.php     # Public API endpoint
│   │   └── display_playlist_manage.php  # Management API
│   └── config/
│       └── terms/
│           ├── _index.php           # Modified: Include display terms
│           └── display.php          # Localization strings
├── assets/
│   ├── partials/
│   │   ├── publish_display_ttl.php  # TTL selector component
│   │   └── view_playlist_status.php # Status display component
│   ├── css/
│   │   └── display-ttl.css          # Styles
│   └── js/
│       └── display-playlist.js      # Client-side logic
├── DEVELOPER_GUIDE.md               # Integration instructions
├── XIBO_INTEGRATION.md              # API documentation
├── DEPLOYMENT_CHECKLIST.md          # Verification checklist
├── IMPLEMENTATION_SUMMARY.md        # Technical summary
└── test_xibo_integration.php        # Test suite
```

## API Endpoints

### Public Playlist API
```
GET /app/api/display_playlist.php?site=SITE_ID&lang=fi&format=json
```
Returns active flashes in JSON, HTML, or slideshow format.

### Management API (Authenticated)
```
POST /app/api/display_playlist_manage.php
{
  "flash_id": 123,
  "action": "remove",
  "csrf_token": "..."
}
```
Remove or restore flashes from playlist.

## Security Features

✅ CSRF token validation
✅ Role-based access control
✅ SQL injection prevention
✅ XSS protection
✅ Rate limiting (60 req/min)
✅ Input validation

## Localization

Full support for:
- 🇫🇮 Finnish (fi)
- 🇸🇪 Swedish (sv)
- 🇬🇧 English (en)
- ��🇹 Italian (it)
- 🇬🇷 Greek (el)

## Requirements

- PHP 7.4+
- MySQL 5.7+
- SafetyFlash v2.0+
- Xibo CMS (optional, for display integration)

## Support

**Documentation**: All guides included in repository
**Testing**: Run `php test_xibo_integration.php`
**Issues**: Check troubleshooting sections in guides

## License

Part of SafetyFlash system. © 2026

---

**Status**: ✅ Production Ready
**Version**: 1.0.0
**Last Updated**: 2026-02-19
