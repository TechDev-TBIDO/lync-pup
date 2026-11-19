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

# 2. Run Laravel's scheduler (bootstrap/app.php -> withSchedule) in the
#    background. App Service has no cron; without this, scheduled jobs such
#    as the expired-rejection purge and table pruning never run.
#    If the app is ever scaled out to more than one instance, run this on
#    only one of them (or move it to a WebJob).
cd "$APP"
nohup php artisan schedule:work > /home/LogFiles/lync-scheduler.log 2>&1 &
