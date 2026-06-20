#!/usr/bin/env bash
set -euo pipefail

PORT="${LAN_PORT:-8000}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

LAN_IP="$(bash "$ROOT/scripts/detect-lan-ip.sh" 2>/dev/null || true)"

print_iphone_url() {
  if [[ -n "${LAN_IP}" ]]; then
    echo ""
    echo "  iPhone (same Wi‑Fi, Safari): http://${LAN_IP}:${PORT}"
    echo ""
  else
    echo ""
    echo "  Could not detect LAN IP. Check System Settings → Network → Wi‑Fi."
    echo "  Then open: http://YOUR_MAC_IP:${PORT}"
    echo ""
  fi
}

stop_vite_dev() {
  rm -f public/hot
  if lsof -nP -iTCP:5173 -sTCP:LISTEN >/dev/null 2>&1; then
    echo "Stopping Vite dev (npm run dev) — it breaks iPhone (loads localhost:5173)."
    lsof -ti :5173 | xargs kill 2>/dev/null || true
    sleep 0.5
  fi
}

health_ok() {
  curl -sf "http://127.0.0.1:${PORT}/api/health" 2>/dev/null \
    | grep -q '"app":"BuyMap.ai"'
}

uses_vite_dev_urls() {
  curl -sf "http://127.0.0.1:${PORT}/" 2>/dev/null | grep -q 'localhost:5173'
}

has_production_build() {
  [[ -f public/build/manifest.json ]]
}

stop_lan_server() {
  if lsof -nP -iTCP:"${PORT}" -sTCP:LISTEN >/dev/null 2>&1; then
    lsof -ti :"${PORT}" | xargs kill 2>/dev/null || true
    sleep 0.5
  fi
}

# LAN must use built assets, not Vite HMR
stop_vite_dev

if ! has_production_build; then
  echo "No production build found. Run: npm run build"
  exit 1
fi

if lsof -nP -iTCP:"${PORT}" -sTCP:LISTEN >/dev/null 2>&1; then
  if health_ok && ! uses_vite_dev_urls; then
    echo "BuyMap is already running on port ${PORT}."
    print_iphone_url
    exit 0
  fi
  if health_ok && uses_vite_dev_urls; then
    echo "Restarting LAN server (was serving Vite dev URLs — broken on iPhone)."
  fi
  stop_lan_server
fi

echo "Starting BuyMap on 0.0.0.0:${PORT} ..."
print_iphone_url

PHP_OPTS=(
  -d "max_execution_time=${SEARCH_MAX_EXECUTION_SECONDS:-300}"
  -d "memory_limit=512M"
  -d "default_socket_timeout=25"
)

# Parallel workers when pcntl is available (much faster on iPhone/LAN searches).
if php -r 'exit(function_exists("pcntl_fork") ? 0 : 1);' 2>/dev/null; then
  export ORCHESTRATION_ENABLE_FORK=true
fi

exec php "${PHP_OPTS[@]}" artisan serve --host=0.0.0.0 --port="${PORT}"
