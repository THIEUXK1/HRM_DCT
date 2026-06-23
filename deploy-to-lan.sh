#!/usr/bin/env bash
set -e

SERVER="bp_admin@10.0.60.91"
REMOTE_PATH="/var/www/hrm"

echo "== Sync code to LAN server =="
rsync -avz \
  --exclude='.git' \
  --exclude='.env' \
  --exclude='vendor' \
  --exclude='node_modules' \
  --exclude='storage/logs/*' \
  --exclude='storage/framework/cache/*' \
  --exclude='storage/framework/sessions/*' \
  --exclude='storage/framework/views/*' \
  ./ "$SERVER:$REMOTE_PATH/"

echo "== Run deploy commands on server =="
ssh "$SERVER" << 'EOF'
set -e
cd /var/www/hrm

if [ ! -f .env ]; then
  echo "== Initializing .env on remote server =="
  cp .env.example .env
  echo "========================================================================="
  echo " SUCCESS: Code synced and remote .env file initialized."
  echo " Please SSH into the server now, edit database settings in:"
  echo "   /var/www/hrm/.env"
  echo " And then run this deploy script again to finish building and launching."
  echo "========================================================================="
  exit 0
fi

docker compose up -d --build

# Check database and perform backup before migrating
if docker compose ps db | grep -qE "Up|running"; then
  # Extract credentials dynamically from the remote .env to be 100% accurate
  DB_USER=$(grep "^DB_USERNAME=" .env | cut -d '=' -f2 | tr -d '\r' | tr -d '"' | tr -d "'")
  DB_NAME=$(grep "^DB_DATABASE=" .env | cut -d '=' -f2 | tr -d '\r' | tr -d '"' | tr -d "'")
  DB_USER=${DB_USER:-hrm_user}
  DB_NAME=${DB_NAME:-hrm}

  echo "== Waiting for PostgreSQL database to be ready... =="
  for i in {1..15}; do
    if docker compose exec -T db pg_isready -U "$DB_USER" -d "$DB_NAME" >/dev/null 2>&1; then
      break
    fi
    echo "   Database is starting up... please wait..."
    sleep 2
  done

  echo "== Backing up database on server =="
  mkdir -p backups
  BACKUP_FILE="backups/hrm_backup_$(date +%Y%m%d_%H%M%S).sql"
  
  if docker compose exec -T db pg_dump -U "$DB_USER" "$DB_NAME" > "$BACKUP_FILE"; then
    echo "== Backup successful: $BACKUP_FILE =="
  else
    echo "== ERROR: Database backup failed! Aborting deployment to protect data. =="
    exit 1
  fi
else
  echo "== Warning: Database container is not running. Skipping auto-backup. =="
fi

docker compose exec app composer install --no-dev --optimize-autoloader

if [ -f package.json ]; then
  docker compose exec app npm install
  docker compose exec app npm run build
fi

docker compose exec app php artisan migrate --force
docker compose exec app php artisan storage:link || true
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache

docker compose ps
EOF

echo "== Deploy done =="
