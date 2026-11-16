#!/usr/bin/env bash
# Build only the runtime allowlist; never include development files or local secrets.
set -euo pipefail
cd "$(dirname "$0")/.."
php bin/build-release.php "${1:-$PWD/dist}"
