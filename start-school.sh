#!/usr/bin/env bash
# Starts the SmartSchool on this computer and opens it in the browser.
cd "$(dirname "$0")"
PORT=8000
if ! curl -s -o /dev/null "http://127.0.0.1:$PORT/login"; then
  export PHP_CLI_SERVER_WORKERS=6
  nohup php artisan serve --host=127.0.0.1 --port=$PORT > storage/logs/server.log 2>&1 &
  nohup php artisan schedule:work > storage/logs/scheduler.log 2>&1 &
  for i in $(seq 1 20); do curl -s -o /dev/null "http://127.0.0.1:$PORT/login" && break; sleep 0.5; done
fi
xdg-open "http://127.0.0.1:$PORT" >/dev/null 2>&1 &
