# SEO Marketing Tools - Setup Guide

Complete step-by-step guide to deploying the plugin on saasmarketing.ca.

## Pre-Deployment Checklist

### 1. Get API Keys

#### ✅ Google Gemini API Key
1. Visit: https://aistudio.google.com/app/apikey
2. Sign in with Google account
3. Click "Create API Key"
4. Select: **Gemini 2.0 Flash-Lite** (best for free tier)
5. Copy and save the API key securely

**Important:** Keep this key private! Never commit to Git.

#### ✅ Google reCAPTCHA v2 Keys
You mentioned you already have reCAPTCHA set up. Locate:
- Site Key (public key)
- Secret Key (private key)

If you need new keys for these tools:
1. Visit: https://www.google.com/recaptcha/admin
2. Add saasmarketing.ca domain
3. Select: **reCAPTCHA v2 → "I'm not a robot" Checkbox**
4. Get Site Key and Secret Key

---

## Deployment Steps

### Step 1: Upload Plugin

**Option A: Via WordPress Admin (Easiest)**
1. Create ZIP file of the `seo-marketing-tools` folder
2. Go to saasmarketing.ca/wp-admin
3. Navigate to: Plugins → Add New → Upload Plugin
4. Upload the ZIP file
5. Click "Install Now"
6. Click "Activate Plugin"

**Option B: Via FTP/File Manager**
1. Connect to your hosting via FTP or cPanel File Manager
2. Navigate to: `/wp-content/plugins/`
3. Upload the entire `seo-marketing-tools` folder
4. Go to WP Admin → Plugins
5. Find "SEO Marketing Tools" and click "Activate"

---

### Step 2: Configure Plugin

1. **Go to Settings → SEO Tools**

2. **Enter Gemini API Key:**
   - Paste your Gemini API key
   - Click "Test API Connection" to verify
   - Should see ✓ Success message

3. **Enter reCAPTCHA Keys:**
   - Version: Select "v2 - Checkbox"
   - Site Key: [Your site key]
   - Secret Key: [Your secret key]

4. **Configure Rate Limits:**
   - Meta Generator: **5** per day (recommended)
   - Broken Link Checker: **5** per day
   - Keyword Density (URL): **20** per day

5. **Other Settings:**
   - Cache Duration: **86400** seconds (24 hours)
   - Enable Logging: ✓ **Yes**
   - Log Retention: **30** days
   - Max Links per Check: **50**

6. **Click "Save Settings"**

---

### Step 3: Create WordPress Pages

Create these 4 pages in WordPress:

#### Page 1: SEO Tools Hub
- **URL:** `/seo-tools/`
- **Title:** Free SEO Tools
- **Content:** `[seo_tools_hub]`
- **Template:** Default
- **Publish**

#### Page 2: Meta Generator
- **URL:** `/seo-tools/meta-generator/`
- **Title:** AI Meta Title & Description Generator
- **Content:** `[seo_meta_generator]`
- **Parent Page:** SEO Tools Hub
- **Template:** Default
- **Publish**

#### Page 3: Keyword Density Checker
- **URL:** `/seo-tools/keyword-density/`
- **Title:** Keyword Density Checker
- **Content:** `[seo_keyword_density]`
- **Parent Page:** SEO Tools Hub
- **Template:** Default
- **Publish**

#### Page 4: Broken Link Checker
- **URL:** `/seo-tools/broken-link-checker/`
- **Title:** Broken Link Checker
- **Content:** `[seo_broken_link_checker]`
- **Parent Page:** SEO Tools Hub
- **Template:** Default
- **Publish**

---

### Step 4: Add to Navigation (Optional)

1. Go to: **Appearance → Menus**
2. Add "SEO Tools Hub" page to your main menu
3. Save menu

---

### Step 5: Testing

#### Test 1: Meta Generator
1. Visit: `https://saasmarketing.ca/seo-tools/meta-generator/`
2. Fill in the form:
   - Keyword: "WordPress SEO"
   - Business Name: "SaaS Marketing"
   - Description: "Test description"
3. Complete reCAPTCHA
4. Click "Generate Meta Tags"
5. **Expected:** Should generate title & description within 2-3 seconds
6. **Check:** Character counts show green ✓

#### Test 2: Keyword Density
1. Visit: `https://saasmarketing.ca/seo-tools/keyword-density/`
2. Paste some text (at least 100 words)
3. Click "Analyze Keyword Density"
4. **Expected:** See table with keywords, counts, and density percentages

#### Test 3: Broken Link Checker
1. Visit: `https://saasmarketing.ca/seo-tools/broken-link-checker/`
2. Enter a URL (e.g., your homepage)
3. Complete reCAPTCHA
4. Click "Check Links"
5. **Expected:** See list of links with status codes (may take 10-30 seconds)

#### Test 4: Rate Limiting
1. Try using Meta Generator 6 times
2. **Expected:** After 5th use, should show "Daily limit reached"

---

### Step 6: Monitor Performance

#### Check Admin Dashboard
1. Go to: **Settings → SEO Tools**
2. View "Today's Statistics"
3. Check:
   - Total requests
   - Unique users
   - Cache hit rate
   - Error rate

#### Watch for Issues
- No PHP errors in browser console
- No 500 errors on tool pages
- reCAPTCHA loads properly
- Mobile responsiveness works

---

## Post-Launch Monitoring

### Daily (First Week)
- Check: **Settings → SEO Tools** for usage stats
- Monitor: API quota (should stay under 200/day)
- Watch: Error logs in cPanel

### Weekly
- Review usage statistics
- Check cache hit rate (should be >60%)
- Monitor user feedback

### Monthly
- Export logs for analysis
- Adjust rate limits if needed
- Consider upgrading to paid API tier if traffic increases

---

## Troubleshooting

### Issue: "API key not configured"
**Solution:** 
- Go to Settings → SEO Tools
- Enter Gemini API key
- Click "Test API Connection"

### Issue: reCAPTCHA not showing
**Solution:**
- Check Site Key is correct
- Verify domain matches (saasmarketing.ca)
- Clear browser cache
- Check browser console for JavaScript errors

### Issue: "Daily limit reached" immediately
**Solution:**
- Clear rate limits in database (ask hosting)
- Or wait 24 hours for reset
- Check if multiple users sharing same IP

### Issue: Tools are slow
**Solution:**
- Check cache is enabled (Settings → SEO Tools)
- Increase PHP memory limit (512MB recommended)
- Enable object caching (Redis/Memcached)

### Issue: Broken link checker times out
**Solution:**
- Reduce max links to check (Settings → 25 instead of 50)
- Increase PHP execution time (already 300s on your server)
- Check if target website is slow

---

## Backup & Safety

### Before Making Changes
1. **Backup Database:**
   - cPanel → phpMyAdmin → Export
   - Or use UpdraftPlus plugin

2. **Backup Files:**
   - Download `/wp-content/plugins/seo-marketing-tools/`

### If Something Breaks
1. **Deactivate Plugin:**
   - WP Admin → Plugins → Deactivate "SEO Marketing Tools"

2. **Delete if Needed:**
   - Plugins → Delete
   - Reinstall from backup

---

## Upgrading to Paid Tier

### When to Upgrade Gemini API?

**Consider upgrading if:**
- Consistently hitting 800+ requests/day
- >100 unique users/day
- Users complaining about limits

**Paid Tier Costs:**
- Gemini Flash-Lite: ~$0.00015 per request
- 10,000 requests/month = ~$1.50/month
- Very affordable!

---

## Support Contacts

**Hosting Issues:**
- Your hosting provider support

**Plugin Issues:**
- Check README.md for common issues
- Review code comments for technical details

**API Issues:**
- Gemini: https://ai.google.dev/support
- reCAPTCHA: https://support.google.com/recaptcha/

---

## Success Checklist

Before going live, verify:

- [ ] Plugin activated successfully
- [ ] API keys configured and tested
- [ ] All 4 pages created and published
- [ ] Meta generator works (generates valid meta tags)
- [ ] Keyword density works (analyzes text)
- [ ] Broken link checker works (scans pages)
- [ ] reCAPTCHA displays and verifies
- [ ] Rate limiting works (blocks after limit)
- [ ] Mobile responsive (test on phone)
- [ ] No console errors
- [ ] "Powered by SaaS Marketing" shows on all tools
- [ ] Links to other tools work
- [ ] Cache is working (check admin stats)
- [ ] Logs are recording (check admin panel)

**Once all checked, you're ready to announce the tools to your users!** 🚀

---

## Quick Reference

### Important URLs
- Admin Settings: `/wp-admin/options-general.php?page=seo-marketing-tools`
- Hub Page: `/seo-tools/`
- Gemini API Console: https://aistudio.google.com/
- reCAPTCHA Admin: https://www.google.com/recaptcha/admin

### Default Rate Limits
- Meta Generator: 5/day per IP
- Broken Link Checker: 5/day per IP
- Keyword Density (URL): 20/day per IP
- Keyword Density (Text): Unlimited

### File Locations
- Plugin: `/wp-content/plugins/seo-marketing-tools/`
- Logs: WordPress database (wp_seo_tools_logs table)
- Cache: WordPress transients

### PHP Requirements (Your Server ✓)
- PHP: 8.3.28 ✓
- WordPress: 6.9 ✓
- Memory: 512M ✓
- Execution Time: 300s ✓
- HTTPS: Yes ✓

**All requirements met! You're good to go!** 💯
