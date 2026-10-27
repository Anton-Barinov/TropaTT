#!/usr/bin/env bash
# Reproducible build of the connector archive.
# Usage: bash build.sh   (produces dist/tropatt-shopify.zip)
set -euo pipefail
cd "$(dirname "$0")"

mkdir -p dist
rm -f dist/tropatt-shopify.zip

rm -rf .build
mkdir -p .build/tropatt-shopify
cp -pR lib public cron config.example.php README.md LICENSE .build/tropatt-shopify/

(cd .build && zip -r -D -X ../dist/tropatt-shopify.zip tropatt-shopify >/dev/null)
rm -rf .build

unzip -t dist/tropatt-shopify.zip >/dev/null
echo "Built dist/tropatt-shopify.zip"
