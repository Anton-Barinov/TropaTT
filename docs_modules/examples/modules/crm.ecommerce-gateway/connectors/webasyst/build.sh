#!/usr/bin/env bash
# Reproducible build of the plugin archive.
# Usage: bash build.sh   (produces dist/tropatt-webasyst.zip)
set -euo pipefail
cd "$(dirname "$0")"

mkdir -p dist
rm -f dist/tropatt-webasyst.zip

rm -rf .build
mkdir -p .build
cp -pR wa-apps README.md LICENSE .build/

(cd .build && zip -r -D -X ../dist/tropatt-webasyst.zip wa-apps README.md LICENSE >/dev/null)
rm -rf .build

unzip -t dist/tropatt-webasyst.zip >/dev/null
echo "Built dist/tropatt-webasyst.zip"
