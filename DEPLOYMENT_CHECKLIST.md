# Xibo Integration - Final Verification Checklist

## ✅ Pre-Deployment Checklist

### Database
- [ ] Run migration: `mysql -u user -p database < migrations/add_display_ttl.sql`
- [ ] Verify columns added: `SHOW COLUMNS FROM sf_flashes LIKE 'display_%';`
- [ ] Verify index created: `SHOW INDEX FROM sf_flashes WHERE Key_name = 'idx_display_active';`

### File Verification
- [ ] All 13 new files exist (run test_xibo_integration.php)
- [ ] PHP syntax valid for all files
- [ ] CSS and JS files accessible from web server

### Code Integration
- [ ] TTL selector included in publish modal
- [ ] Playlist status card included in view page
- [ ] CSS link added to header
- [ ] JavaScript + globals added to footer
- [ ] Terms configuration loaded properly

### Permissions
- [ ] Web server can read all new files (chmod 644)
- [ ] API directory accessible (check .htaccess)
- [ ] Preview images directory readable

## ✅ Post-Deployment Testing

### Functional Tests
- [ ] Publish a flash with default TTL (30 days)
- [ ] Verify TTL saved in database
- [ ] View published flash, see status card
- [ ] Test "Remove from playlist" button
- [ ] Test "Restore to playlist" button
- [ ] Change TTL options in publish modal
- [ ] Verify preview date updates

### API Tests
- [ ] JSON: `curl "http://domain/app/api/display_playlist.php?site=test&lang=fi"`
- [ ] HTML: Visit in browser with `format=html`
- [ ] Slideshow: Test in iframe
- [ ] Rate limiting: Test with 61+ requests
- [ ] CORS: Test from different origin

### Security Tests
- [ ] CSRF validation working on management API
- [ ] Unauthorized users cannot remove/restore
- [ ] SQL injection protection (test with malicious input)
- [ ] XSS protection (test with script tags)

### UI/UX Tests
- [ ] TTL chips clickable and responsive
- [ ] Mobile view works properly
- [ ] Status colors correct (green/yellow/gray)
- [ ] All text in correct language
- [ ] Buttons show proper state

### Xibo Integration
- [ ] Webpage widget configured
- [ ] Displays showing flashes
- [ ] Auto-refresh working
- [ ] Slideshow transitions smooth
- [ ] Expired flashes disappear

## ✅ Monitoring

### First Week
- [ ] Check API logs for errors
- [ ] Monitor database performance
- [ ] Verify rate limiting effectiveness
- [ ] User feedback collected

### Ongoing
- [ ] Flash expiration working correctly
- [ ] Manual remove/restore working
- [ ] New flashes appear on displays
- [ ] No performance degradation

## 📊 Success Metrics

- [ ] All published flashes have TTL set
- [ ] Xibo displays update within 5 minutes
- [ ] No 500 errors in API logs
- [ ] User adoption of TTL feature
- [ ] Zero security incidents

## 🐛 Common Issues

### Issue: TTL not saving
**Fix**: Check publish.php includes TTL code after line 224

### Issue: API 404
**Fix**: Verify app/api/ directory accessible, check .htaccess

### Issue: JavaScript not working
**Fix**: Check console, verify globals set (SF_BASE_URL, SF_CSRF_TOKEN)

### Issue: Database errors
**Fix**: Verify migration ran, check column names match

### Issue: Display not updating
**Fix**: Check Xibo refresh interval, verify API returns data

## 📝 Rollback Plan

If critical issues occur:

1. Revert publish.php changes:
   ```bash
   git checkout HEAD~4 app/actions/publish.php
   ```

2. Remove database columns (optional):
   ```sql
   ALTER TABLE sf_flashes 
   DROP COLUMN display_expires_at,
   DROP COLUMN display_removed_at,
   DROP COLUMN display_removed_by,
   DROP INDEX idx_display_active;
   ```

3. Remove includes from templates

## 🎉 Sign-Off

- [ ] Developer tested locally
- [ ] QA approved
- [ ] Product owner approved
- [ ] Documentation reviewed
- [ ] Xibo admin trained

**Deployed by**: _______________
**Date**: _______________
**Time**: _______________

---

**All checks passed?** Great! The Xibo integration is ready for production use. 🚀
