#!/usr/bin/env bash
# Reproducible build of the addon archive.
# Usage: bash build.sh   (produces dist/tropatt-cs-cart.zip)
set -euo pipefail
cd "$(dirname "$0")"

mkdir -p dist
rm -f dist/tropatt-cs-cart.zip

rm -rf .build
mkdir -p .build
cp -pR app README.md LICENSE .build/

(cd .build && zip -r -D -X ../dist/tropatt-cs-cart.zip app README.md LICENSE >/dev/null)
rm -rf .build

unzip -t dist/tropatt-cs-cart.zip >/dev/null
echo "Built dist/tropatt-cs-cart.zip"
