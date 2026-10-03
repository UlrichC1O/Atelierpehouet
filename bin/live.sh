#!/usr/bin/env bash
# Put Ateliers Pehouet live from this machine (built for GitHub Codespaces).
#
#   bin/live.sh start    production mode (APP_DEBUG=false via the process environment), migrate, start the Python
#                        art engine (127.0.0.1:8765) and Laravel (0.0.0.0:$PORT), make the port public
#   bin/live.sh stop     stop both processes and clear the production caches (back to dev mode)
#   bin/live.sh status   show processes, health and the public URL
#
# Logs: storage/logs/live-web.log, storage/logs/live-art.log
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

PORT="${PORT:-8000}"
ART_PORT="${ART_PORT:-8765}"
PIDS="$ROOT/storage/framework"
LOGS="$ROOT/storage/logs"
export XDEBUG_MODE=off

if [ -n "${CODESPACE_NAME:-}" ]; then
    PUBLIC_URL="https://${CODESPACE_NAME}-${PORT}.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-app.github.dev}"
else
    PUBLIC_URL="${LIVE_URL:-http://127.0.0.1:${PORT}}"
fi

stop_proc() {
    local name="$1" file="$PIDS/live-$1.pid"
    if [ -f "$file" ]; then
        local pid
        pid="$(cat "$file")"
        # Each process was started with setsid: kill its whole group (artisan serve spawns php -S workers).
        kill -TERM -- "-$pid" 2>/dev/null || kill -TERM "$pid" 2>/dev/null || true
        rm -f "$file"
        echo "stopped $name"
    fi
}

wait_for() {
    local url="$1" tries=60
    until curl -fsS -o /dev/null "$url" 2>/dev/null; do
        tries=$((tries - 1))
        if [ "$tries" -le 0 ]; then return 1; fi
        sleep 0.5
    done
}

start() {
    stop_proc web
    stop_proc art

    local secure=false
    case "$PUBLIC_URL" in https://*) secure=true ;; esac

    # Production settings live in the server's process environment (they beat .env), not in a
    # shared config cache: clearing caches or running the tests elsewhere can't flip the public
    # site back to debug mode.
    export APP_ENV=production APP_DEBUG=false APP_URL="$PUBLIC_URL" SESSION_SECURE_COOKIE="$secure" LOG_LEVEL=warning
    php artisan config:clear --no-interaction >/dev/null
    php artisan migrate --force --no-interaction >/dev/null

    echo "→ starting the Python art engine on 127.0.0.1:$ART_PORT"
    setsid nohup bash -c 'cd python && exec python3 -m art_engine serve --host 127.0.0.1 --port "$0"' "$ART_PORT" \
        >"$LOGS/live-art.log" 2>&1 < /dev/null &
    echo $! >"$PIDS/live-art.pid"
    disown || true

    echo "→ starting Laravel on 0.0.0.0:$PORT"
    # PHP's built-in server with several workers, through bin/live-router.php (trusts the HTTPS proxy).
    PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}" setsid nohup php -S "0.0.0.0:$PORT" -t public bin/live-router.php \
        >"$LOGS/live-web.log" 2>&1 < /dev/null &
    echo $! >"$PIDS/live-web.pid"
    disown || true

    wait_for "http://127.0.0.1:$ART_PORT/health" || echo "! art engine not answering (the site falls back to the CLI / built-in art)"
    wait_for "http://127.0.0.1:$PORT/up" || { echo "! Laravel did not start — see $LOGS/live-web.log"; exit 1; }

    if [ -n "${CODESPACE_NAME:-}" ] && command -v gh >/dev/null 2>&1; then
        if timeout 60 gh codespace ports visibility "$PORT:public" -c "$CODESPACE_NAME" >/dev/null 2>&1; then
            echo "→ port $PORT is public"
        else
            echo "! could not change the port visibility automatically: in VS Code open the PORTS panel,"
            echo "  right-click port $PORT → Port Visibility → Public"
        fi
    fi
    echo "✓ Ateliers Pehouet is live: $PUBLIC_URL"
}

stop() {
    stop_proc web
    stop_proc art
    php artisan optimize:clear --no-interaction >/dev/null 2>&1 || true
    echo "✓ stopped"
}

status() {
    for name in web art; do
        if [ -f "$PIDS/live-$name.pid" ] && kill -0 "$(cat "$PIDS/live-$name.pid")" 2>/dev/null; then
            echo "$name: running (pid $(cat "$PIDS/live-$name.pid"))"
        else
            echo "$name: stopped"
        fi
    done
    curl -fsS -o /dev/null -w "web /up: HTTP %{http_code}\n" "http://127.0.0.1:$PORT/up" 2>/dev/null || echo "web /up: down"
    curl -fsS "http://127.0.0.1:$ART_PORT/health" 2>/dev/null && echo || echo "art /health: down"
    echo "public URL: $PUBLIC_URL"
}

case "${1:-status}" in
    start) start ;;
    stop) stop ;;
    restart) stop; start ;;
    status) status ;;
    *) echo "usage: bin/live.sh start|stop|restart|status" >&2; exit 2 ;;
esac
