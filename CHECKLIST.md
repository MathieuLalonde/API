# ✅ Pre-Push Checklist

## Before pushing to GitHub:

### 1. Verify GitHub Secrets (Settings → Secrets → Actions)
- [ ] `SSH_USER` - Your SiteGround SSH username
- [ ] `SSH_HOST` - Your SiteGround host (e.g., `server123.web-hosting.com`)
- [ ] `SSH_PRIVATE_KEY` - Your private SSH key (full content)

### 2. Verify SiteGround Setup
- [ ] SSH access is enabled (Site Tools → Dev → SSH Keys)
- [ ] Domain exists: `api.mathieulalonde.com`
- [ ] Directory exists: `~/www/api.mathieulalonde.com/public_html/`

### 3. Test Locally (Optional but Recommended)
```bash
# Update credentials in test-deploy.sh first
bash test-deploy.sh
```

Expected output:
- ✓ SSH connection successful
- ✓ Directory exists
- Shows rsync dry-run results

### 4. Deploy
```bash
git add .
git commit -m "Initial PHP API setup with safe deployment"
git push origin main
```

### 5. Monitor GitHub Actions
1. Go to: https://github.com/MathieuLalonde/API/actions
2. Watch the workflow run
3. Check the "Validate REMOTE_PATH" and "Dry-run deployment" steps
4. Verify files transferred in "Deploy via Rsync" step

### 6. Test Live API
```bash
# Basic test
curl https://api.mathieulalonde.com/

# Expected response:
# {"status":"ok","message":"API is running","timestamp":"...","version":"1.0.0"}

# Health check
curl https://api.mathieulalonde.com/health
```

## Safety Features Enabled ✓

✓ Domain validation (must end with `.com`)  
✓ Remote directory existence check  
✓ Dry-run preview before deployment  
✓ No deployment to empty/missing REMOTE_PATH  
✓ Composer dependencies installed in CI  
✓ Only `./public/` folder is deployed  

## Troubleshooting

**Workflow fails at "Validate REMOTE_PATH"**
→ Check that `~/www/api.mathieulalonde.com/public_html/` exists on SiteGround

**Workflow fails at "Setup SSH"**
→ Verify secrets are correctly set in GitHub

**500 Error on site after deployment**
→ Check PHP error logs in SiteGround Site Tools

**Can't access the site**
→ Verify domain DNS is pointing to SiteGround
→ Check that domain is active in SiteGround Sites

---

## Next Development Steps

After successful deployment:
1. Add MySQL database connection
2. Create API endpoints for your data
3. Add `.env` file for configuration (local only, not deployed)
4. Test locally with `php -S localhost:8000 -t public`
5. Implement error handling and logging
