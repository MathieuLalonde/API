# API Deployment Guide

## Quick Start - Minimal Test

### 1. Pre-flight Check (Local)
Before pushing to GitHub, test your SSH connection:

```bash
# Update the credentials in test-deploy.sh first, then run:
bash test-deploy.sh
```

This will:
- ✓ Test SSH connectivity
- ✓ Verify remote directory exists
- ✓ Show what rsync would transfer (dry-run)

### 2. Push to Deploy
Once the local test passes:

```bash
git add .
git commit -m "Initial PHP API setup"
git push origin main
```

The GitHub Action will automatically:
1. Setup PHP 8.0 and install Composer dependencies
2. Validate `REMOTE_PATH` matches `.com` domain
3. Verify remote directory exists via SSH
4. Run dry-run rsync to show pending changes
5. Deploy `./public/` → `~/www/api.mathieulalonde.com/public_html/`
6. Tag the deployment as `staging`

### 3. Verify Deployment
Visit: `https://api.mathieulalonde.com/`

Expected response:
```json
{
  "status": "ok",
  "message": "API is running",
  "timestamp": "2025-12-19 10:30:00",
  "version": "1.0.0"
}
```

Health check: `https://api.mathieulalonde.com/health`

## Safety Features

The workflow now includes:
- ✓ **REMOTE_PATH validation** - Must be a `.com` domain
- ✓ **Remote directory check** - Fails if `~/www/api.mathieulalonde.com/public_html` doesn't exist
- ✓ **Dry-run preview** - Shows all changes before actual deployment
- ✓ **No accidental deploys** - Multiple guard checks prevent wrong-folder deploys

## Project Structure

```
API/
├── .github/workflows/deploy.yaml  # Auto-deploy on push to main
├── public/
│   ├── index.php     # Slim 4 app entry point
│   └── .htaccess     # URL rewriting for clean routes
├── vendor/           # Composer dependencies (generated)
├── composer.json     # PHP dependencies (Slim 4)
└── probe.txt         # Deployment validation file
```

## Local Development

1. Install dependencies:
```bash
composer install
```

2. Run locally with PHP built-in server:
```bash
php -S localhost:8000 -t public
```

3. Test the endpoint:
```bash
curl http://localhost:8000/
```

## Next Steps

1. **Add MySQL Connection**: Update `public/index.php` to connect to SiteGround MySQL
2. **Create API Routes**: Add endpoints for your data in `public/index.php` or separate route files
3. **Environment Config**: Add `.env` file for database credentials (excluded from deployment)
4. **Error Handling**: Add Slim error middleware for production
5. **CORS**: Add CORS headers if accessed from frontend

## Troubleshooting

**SSH Connection Failed**
- Verify secrets are set in GitHub: Settings → Secrets → Actions
  - `SSH_USER`, `SSH_HOST`, `SSH_PRIVATE_KEY`
- Check SiteGround SSH is enabled: Site Tools → Dev → SSH Keys

**Remote Directory Not Found**
- Check domain spelling: `REMOTE_PATH: api.mathieulalonde.com`
- Verify domain exists in SiteGround: Sites → Manage → Domains
- Check folder: `~/www/api.mathieulalonde.com/public_html/`

**Deployment Successful but 500 Error**
- Check PHP error logs in SiteGround: Site Tools → Logs → Error Log
- Verify PHP version: Site Tools → Dev → PHP Manager (must be 8.0+)
- Check `vendor/` was deployed (composer install ran)
