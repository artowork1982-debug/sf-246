# Xibo Integration for SafetyFlash

## Quick Links

📖 **[Developer Integration Guide](DEVELOPER_GUIDE.md)** - Step-by-step integration instructions
📚 **[API Documentation](XIBO_INTEGRATION.md)** - Complete API reference and Xibo setup
🎨 **[Xibo Embedded Widget Templates](docs/XIBO_EMBEDDED_WIDGET.md)** - Ready-to-use HTML/CSS/JavaScript templates
📋 **[Deployment Checklist](DEPLOYMENT_CHECKLIST.md)** - Pre/post-deployment verification
📊 **[Implementation Summary](IMPLEMENTATION_SUMMARY.md)** - Technical details and features
🧪 **[Test Suite](test_xibo_integration.php)** - Automated validation script

## What's New

This integration adds Xibo information display support to SafetyFlash, enabling:

- 🔐 **API Key Authentication** - Secure authentication for display endpoints
- ⏱️ **Per-Image Duration** - Set individual display time for each flash (10s-60s)
- ⏱️ **TTL Management** - Set how long flashes appear on displays (7 days to 3 months)
- 📊 **Playlist Status** - Real-time visibility into display status
- 🎛️ **Manual Controls** - Remove/restore flashes from playlist
- 🌐 **Public API** - JSON/HTML/slideshow formats for Xibo
- 🎨 **Ready Templates** - Copy-paste Embedded Widget templates
- 🔒 **Secure** - CSRF protection, rate limiting, role-based access

## Quick Start (5 Minutes)

1. **Run Database Migrations**
   ```bash
   mysql -u user -p database < migrations/add_display_ttl.sql
   mysql -u user -p database < migrations/add_display_duration.sql
   mysql -u user -p database < migrations/add_display_api_keys.sql
   ```

2. **Create API Key** (Admin panel)
   - Navigate to API key management
   - Create key for your site/display
   - Copy the generated key

3. **Configure Xibo**
   - See [docs/XIBO_EMBEDDED_WIDGET.md](docs/XIBO_EMBEDDED_WIDGET.md) for ready-to-use templates
   - Or see [XIBO_INTEGRATION.md](XIBO_INTEGRATION.md) for detailed API documentation

4. **Run Tests** (Optional)
   ```bash
   php test_xibo_integration.php
   ```

## Files Structure

```
├── migrations/
│   ├── add_display_ttl.sql          # TTL columns
│   ├── add_display_duration.sql     # Duration column (NEW)
│   └── add_display_api_keys.sql     # API keys table (NEW)
├── app/
│   ├── actions/
│   │   └── publish.php              # Modified: TTL + duration saving
│   ├── api/
│   │   ├── display_playlist.php     # Public API endpoint (API key auth)
│   │   ├── display_playlist_manage.php  # Management API
│   │   └── display_api_keys_manage.php  # API key management (NEW)
│   └── config/
│       └── terms/
│           ├── _index.php           # Modified: Include display terms
│           └── display.php          # Localization strings
├── assets/
│   ├── partials/
│   │   ├── publish_display_ttl.php  # TTL selector component
│   │   ├── publish_display_duration.php  # Duration selector (NEW)
│   │   └── view_playlist_status.php # Status display component
│   ├── css/
│   │   └── display-ttl.css          # Styles
│   └── js/
│       └── display-playlist.js      # Client-side logic
├── docs/
│   └── XIBO_EMBEDDED_WIDGET.md      # Copy-paste templates (NEW)
├── DEVELOPER_GUIDE.md               # Integration instructions
├── XIBO_INTEGRATION.md              # API documentation
├── DEPLOYMENT_CHECKLIST.md          # Verification checklist
├── IMPLEMENTATION_SUMMARY.md        # Technical summary
└── test_xibo_integration.php        # Test suite
```

## API Endpoints

### API Key Management (Admin Only)
```
POST /app/api/display_api_keys_manage.php
GET  /app/api/display_api_keys_manage.php
```
Create, list, and deactivate API keys.

### Public Playlist API (API Key Required)
```
GET /app/api/display_playlist.php?key=sf_dk_xxx&format=json
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

✅ API key authentication for displays
✅ CSRF token validation
✅ Role-based access control
✅ SQL injection prevention (prepared statements)
✅ XSS protection
✅ Rate limiting (60 req/min)
✅ Input validation and sanitization
✅ API key expiry and deactivation support

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
