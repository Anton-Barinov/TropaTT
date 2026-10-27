#!/usr/bin/env bash
# Reproducible build of the module archive.
# Usage: bash build.sh   (produces dist/tropatt-prestashop.zip)
set -euo pipefail
cd "$(dirname "$0")"

mkdir -p dist
rm -f dist/tropatt-prestashop.zip

rm -rf .build
mkdir -p .build
cp -pR tropatt README.md LICENSE .build/

(cd .build && zip -r -D -X ../dist/tropatt-prestashop.zip tropatt README.md LICENSE >/dev/null)
rm -rf .build

unzip -t dist/tropatt-prestashop.zip >/dev/null
echo "Built dist/tropatt-prestashop.zip"
