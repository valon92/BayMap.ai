#!/usr/bin/env bash
# Prints the Mac's Wi‑Fi LAN IPv4 (en0, then en1). Empty if offline.
set -euo pipefail

ipconfig getifaddr en0 2>/dev/null \
  || ipconfig getifaddr en1 2>/dev/null \
  || true
