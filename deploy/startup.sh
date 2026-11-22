#!/bin/bash
# Azure App Service startup script for Lync.
#
# Set it once in Azure Portal -> Lyncpup -> Configuration -> General settings
# -> Startup Command:
#
#     bash /home/site/wwwroot/deploy/startup.sh
#
# If a Startup Command is already set there, copy anything it does that this
# script doesn't into this file before replacing it.

set -e

APP=/home/site/wwwroot

# 1. Use the repo's nginx config (document root, upload limit, caching, gzip).
cp "$APP/deploy/default" /etc/nginx/sites-available/default
service nginx reload

# 1b. Make sure Laravel's writable folders exist. In git they only hold a
#     hidden .gitignore, and GitHub's upload-artifact skips hidden files, so
#     a fresh deploy can arrive without them - Laravel then 500s on every
#     request ("Please provide a valid cache path").
mkdir -p "$APP/storage/framework/cache/data" "$APP/storage/framework/sessions" \
         "$APP/storage/framework/views" "$APP/storage/logs" \
         "$APP/storage/app/public" "$APP/storage/app/private" "$APP/bootstrap/cache"
chmod -R ug+rwX "$APP/storage" "$APP/bootstrap/cache" || true

# 2. Run Laravel's scheduler (bootstrap/app.php -> withSchedule) in the
#    background. App Service has no cron; without this, scheduled jobs such
#    as the expired-rejection purge and table pruning never run.
#    If the app is ever scaled out to more than one instance, run this on
#    only one of them (or move it to a WebJob).
cd "$APP"
nohup php artisan schedule:work > /home/LogFiles/lync-scheduler.log 2>&1 &
