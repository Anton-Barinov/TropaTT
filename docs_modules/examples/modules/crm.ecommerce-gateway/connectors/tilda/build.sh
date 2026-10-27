#!/usr/bin/env bash
# Reproducible build of the connector archive.
# Usage: bash build.sh   (produces dist/tropatt-tilda.zip)
set -euo pipefail
cd "$(dirname "$0")"
mkdir -p dist
rm -f dist/tropatt-tilda.zip

rm -rf .build
mkdir -p .build/tropatt-tilda
cp -pR lib public cron config.example.php README.md LICENSE .build/tropatt-tilda/

(cd .build && zip -r -D -X ../dist/tropatt-tilda.zip tropatt-tilda >/dev/null)
rm -rf .build

unzip -t dist/tropatt-tilda.zip >/dev/null
echo "Built dist/tropatt-tilda.zip"
