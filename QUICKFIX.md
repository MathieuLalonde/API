# 🔧 Quick Fix Applied - Vendor Directory Issue

## Problem
You were getting a 500 error because only `public/` was being deployed, but `index.php` needs `vendor/autoload.php` from the parent directory.

## Solution Applied
Updated the workflow to deploy the **entire project structure**:
- ✓ `vendor/` (Composer dependencies)
- ✓ `public/` (your document root with index.php)
- ✓ `composer.json`
- ✗ Excludes: .git, .github, .env, *.md, test files

## What Changed

### Workflow Updates:
1. **Rsync source**: Changed from `./public/` to `./` (entire project)
2. **Destination**: Changed from `.../public_html/` to `.../api.mathieulalonde.com/`
3. **Added exclusions**: `.git`, `.github`, `*.md`, `.env`, etc.

### SiteGround Structure:
```
~/www/api.mathieulalonde.com/
├── public/          ← Set this as document root
│   ├── index.php
│   └── .htaccess
├── vendor/          ← Now deployed!
│   └── autoload.php
└── composer.json
```

## ⚠️ IMPORTANT: SiteGround Configuration Required

You need to configure the domain's document root in SiteGround:

1. Go to **Site Tools → Domain → Manage**
2. Find `api.mathieulalonde.com`
3. Click **Edit**
4. Set **Document Root** to: `~/www/api.mathieulalonde.com/public`
   - OR: `/home/USERNAME/www/api.mathieulalonde.com/public`

This tells the web server that `public/` is where the web files are, while keeping `vendor/` accessible to PHP but not to the web.

## Deploy the Fix

```bash
git add .
git commit -m "Fix vendor/ deployment for Slim 4 autoloader"
git push origin main
```

## After Deployment

Test the API:
```bash
curl https://api.mathieulalonde.com/
```

Expected:
```json
{"status":"ok","message":"API is running","timestamp":"...","version":"1.0.0"}
```

If you still get a 500 error after deployment:
1. Check SiteGround error logs: Site Tools → Logs → Error Log
2. Verify `vendor/` directory exists: SSH in and run `ls -la ~/www/api.mathieulalonde.com/`
3. Verify document root is set to `public/` subdirectory
4. Check PHP version is 8.0+ in Site Tools → Dev → PHP Manager
