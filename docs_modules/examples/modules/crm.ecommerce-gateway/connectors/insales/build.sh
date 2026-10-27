#!/usr/bin/env bash
# Reproducible build of the connector archive.
# Usage: bash build.sh   (produces dist/tropatt-insales.zip)
set -euo pipefail
cd "$(dirname "$0")"
mkdir -p dist
rm -f dist/tropatt-insales.zip

rm -rf .build
mkdir -p .build/tropatt-insales
cp -pR lib public cron config.example.php README.md LICENSE .build/tropatt-insales/

(cd .build && zip -r -D -X ../dist/tropatt-insales.zip tropatt-insales >/dev/null)
rm -rf .build

unzip -t dist/tropatt-insales.zip >/dev/null
echo "Built dist/tropatt-insales.zip"
