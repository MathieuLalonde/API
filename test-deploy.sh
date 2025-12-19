# Local SSH & Rsync Test Script
# Test your SiteGround connection before pushing to GitHub

# Configuration (update these with your values)
SSH_USER="your-username"
SSH_HOST="your-host.sitegroundhost.com"
SSH_PORT=18765
REMOTE_PATH="api.mathieulalonde.com"

echo "========================================="
echo "Testing SiteGround SSH Connection"
echo "========================================="

# Test 1: SSH connectivity
echo -e "\n[1/4] Testing SSH connectivity..."
ssh -p $SSH_PORT ${SSH_USER}@${SSH_HOST} 'echo "✓ SSH connection successful"' || {
    echo "✗ SSH connection failed"
    exit 1
}

# Test 2: Verify remote directory exists
echo -e "\n[2/4] Checking if remote directory exists..."
ssh -p $SSH_PORT ${SSH_USER}@${SSH_HOST} \
    "test -d ~/www/${REMOTE_PATH} && echo '✓ Directory exists: ~/www/${REMOTE_PATH}/' || echo '✗ Directory not found'" || exit 1

# Test 3: List current remote directory contents
echo -e "\n[3/4] Current remote directory contents:"
ssh -p $SSH_PORT ${SSH_USER}@${SSH_HOST} \
    "ls -lah ~/www/${REMOTE_PATH}/" || echo "(Directory is empty or inaccessible)"

# Test 4: Dry-run rsync to see what would be transferred
echo -e "\n[4/4] Dry-run rsync (no files will be changed):"
echo "Source: ./ (entire project)"
echo "Destination: ${SSH_USER}@${SSH_HOST}:~/www/${REMOTE_PATH}/"
echo "-----------------------------------------"

rsync -chavzn --delete --keep-dirlinks \
    -e "ssh -p ${SSH_PORT}" \
    --exclude='.git' --exclude='.github' --exclude='*.md' \
    ./ \
    ${SSH_USER}@${SSH_HOST}:~/www/${REMOTE_PATH}/

echo "========================================="
echo "Test complete!"
echo ""
echo "If all tests passed, you can safely push to GitHub."
echo "The workflow will automatically deploy on push to main/master."
echo "========================================="
