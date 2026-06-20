#!/usr/bin/env bash
# Hot-reload dev for iPhone on the same Wi‑Fi (Mac + phone).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

PORT="${LAN_PORT:-8000}"
VITE_PORT="${VITE_PORT:-5173}"
LAN_IP="$(bash "$ROOT/scripts/detect-lan-ip.sh")"

stop_port() {
  local port="$1"
  if lsof -nP -iTCP:"${port}" -sTCP:LISTEN >/dev/null 2>&1; then
    echo "Stopping process on port ${port}…"
    lsof -ti :"${port}" | xargs kill 2>/dev/null || true
    sleep 0.4
  fi
}

if [[ -z "${LAN_IP}" ]]; then
  echo "Could not detect Wi‑Fi IP. Connect to Wi‑Fi or set LAN_IP manually."
  exit 1
fi

# Free ports from a previous lan / dev:lan / vite session
stop_port "${PORT}"
stop_port "${VITE_PORT}"
rm -f public/hot

export VITE_HMR_HOST="${LAN_IP}"
export VITE_DEV_SERVER_TARGET="http://127.0.0.1:${PORT}"

echo ""
echo "  Mac (Safari):     http://127.0.0.1:${PORT}"
echo "  iPhone (Wi‑Fi):   http://${LAN_IP}:${PORT}"
echo ""
echo "  Same Wi‑Fi only — not mobile data. Stop with Ctrl+C."
echo "  Or run: npm run lan:stop"
echo ""

cleanup() {
  kill "${PHP_PID:-}" "${VITE_PID:-}" 2>/dev/null || true
}
trap cleanup EXIT INT TERM

php -d max_execution_time=300 artisan serve --host=0.0.0.0 --port="${PORT}" &
PHP_PID=$!

npm run dev -- --host 0.0.0.0 --port "${VITE_PORT}" &
VITE_PID=$!

wait
