#!/usr/bin/env bash

# ==============================================================================
# AMAN BOOKING — Production Deployment Script for cPanel / Shared Hosting
# ==============================================================================
# Usage:
#   ./deploy.sh             -> Run full zero-downtime timestamped deployment
#   ./deploy.sh --rollback  -> Instant rollback to the previous release
# ==============================================================================

set -euo pipefail

# Configuration
DEPLOY_ROOT="${DEPLOY_ROOT:-$(pwd)}"
RELEASES_DIR="$DEPLOY_ROOT/releases"
SHARED_DIR="$DEPLOY_ROOT/shared"
CURRENT_DIR="$DEPLOY_ROOT/current"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
NEW_RELEASE_DIR="$RELEASES_DIR/$TIMESTAMP"
KEEP_RELEASES=5

# PHP Binary Detection
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

echo "==================================================================="
echo "🚀 AMAN BOOKING — cPanel / Shared Hosting Deployment Pipeline"
echo "==================================================================="
echo "Timestamp: $TIMESTAMP"
echo "Deploy Root: $DEPLOY_ROOT"
echo "PHP Binary: $("$PHP_BIN" -v | head -n 1)"
echo "-------------------------------------------------------------------"

# ------------------------------------------------------------------------------
# 1. Rollback Mode
# ------------------------------------------------------------------------------
if [[ "${1:-}" == "--rollback" ]]; then
    echo "⚠️  Initiating Instant Rollback..."
    
    if [[ ! -d "$RELEASES_DIR" ]]; then
        echo "❌ No releases directory found at $RELEASES_DIR."
        exit 1
    fi

    # Find previous release (excluding current target)
    CURRENT_TARGET=$(readlink -f "$CURRENT_DIR" 2>/dev/null || true)
    PREVIOUS_RELEASE=$(find "$RELEASES_DIR" -mindepth 1 -maxdepth 1 -type d | sort -r | grep -v "^$CURRENT_TARGET$" | head -n 1 || true)

    if [[ -z "$PREVIOUS_RELEASE" || ! -d "$PREVIOUS_RELEASE" ]]; then
        echo "❌ No previous release found to rollback to."
        exit 1
    fi

    echo "Switching current link to: $PREVIOUS_RELEASE"
    ln -sfn "$PREVIOUS_RELEASE" "$CURRENT_DIR"

    # Refresh caches on previous release
    echo "Refreshing application caches..."
    "$PHP_BIN" "$CURRENT_DIR/artisan" config:cache
    "$PHP_BIN" "$CURRENT_DIR/artisan" route:cache
    "$PHP_BIN" "$CURRENT_DIR/artisan" view:cache
    "$PHP_BIN" "$CURRENT_DIR/artisan" queue:restart || true

    echo "✅ Rollback completed successfully to: $(basename "$PREVIOUS_RELEASE")"
    exit 0
fi

# ------------------------------------------------------------------------------
# 2. Shared Directories & Environment Preparation
# ------------------------------------------------------------------------------
echo "📁 Step 1/6: Ensuring shared directories and .env..."
mkdir -p "$RELEASES_DIR"
mkdir -p "$SHARED_DIR"
mkdir -p "$SHARED_DIR/storage/app/public"
mkdir -p "$SHARED_DIR/storage/framework/cache/data"
mkdir -p "$SHARED_DIR/storage/framework/sessions"
mkdir -p "$SHARED_DIR/storage/framework/views"
mkdir -p "$SHARED_DIR/storage/logs"

# Ensure .env exists in shared
if [[ ! -f "$SHARED_DIR/.env" ]]; then
    if [[ -f "$DEPLOY_ROOT/.env" ]]; then
        echo "Found .env at root, moving to shared/.env..."
        cp "$DEPLOY_ROOT/.env" "$SHARED_DIR/.env"
    else
        echo "❌ Missing $SHARED_DIR/.env! Please create it before deploying."
        exit 1
    fi
fi

# ------------------------------------------------------------------------------
# 3. Create New Timestamped Release
# ------------------------------------------------------------------------------
echo "📦 Step 2/6: Creating release directory: $NEW_RELEASE_DIR..."
mkdir -p "$NEW_RELEASE_DIR"

# Copy or archive files to release directory (excluding git, tests, node_modules)
echo "Synchronizing project files..."
rsync -a --exclude='.git' \
         --exclude='node_modules' \
         --exclude='tests' \
         --exclude='storage' \
         --exclude='.env' \
         --exclude='releases' \
         --exclude='shared' \
         "$DEPLOY_ROOT/" "$NEW_RELEASE_DIR/"

# Link shared .env and storage
echo "Linking shared .env and storage..."
ln -sfn "$SHARED_DIR/.env" "$NEW_RELEASE_DIR/.env"
rm -rf "$NEW_RELEASE_DIR/storage"
ln -sfn "$SHARED_DIR/storage" "$NEW_RELEASE_DIR/storage"

# ------------------------------------------------------------------------------
# 4. Dependency Installation & Migrations
# ------------------------------------------------------------------------------
echo "⚙️  Step 3/6: Installing production dependencies..."
cd "$NEW_RELEASE_DIR"

if command -v "$COMPOSER_BIN" &> /dev/null; then
    "$COMPOSER_BIN" install --no-dev --prefer-dist --optimize-autoloader --no-interaction --quiet
else
    echo "⚠️  Composer binary not found in PATH; skipping composer install."
fi

echo "🗄️  Step 4/6: Running database migrations (--force)..."
"$PHP_BIN" artisan migrate --force

echo "🔗 Creating storage symlink..."
"$PHP_BIN" artisan storage:link || true

# ------------------------------------------------------------------------------
# 5. Production Optimization Caches
# ------------------------------------------------------------------------------
echo "⚡ Step 5/6: Optimizing configuration and route caches..."
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan event:cache || true

# ------------------------------------------------------------------------------
# 6. Atomic Symlink Switch & Queue Restart
# ------------------------------------------------------------------------------
echo "🔄 Step 6/6: Activating release via atomic symlink..."
ln -sfn "$NEW_RELEASE_DIR" "$CURRENT_DIR"

# Notify queue worker to restart gracefully
"$PHP_BIN" "$CURRENT_DIR/artisan" queue:restart || true

# Clean old releases, keeping the most recent $KEEP_RELEASES
echo "🧹 Purging old releases (keeping last $KEEP_RELEASES)..."
cd "$RELEASES_DIR"
# shellcheck disable=SC2012
ls -dt ./* | tail -n +$((KEEP_RELEASES + 1)) | xargs -r rm -rf

echo "==================================================================="
echo "🎉 AMAN BOOKING deployed successfully!"
echo "Current release: $TIMESTAMP"
echo "Symlink: $CURRENT_DIR -> $NEW_RELEASE_DIR"
echo "==================================================================="
