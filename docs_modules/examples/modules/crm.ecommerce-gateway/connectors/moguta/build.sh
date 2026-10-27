#!/usr/bin/env bash
# Reproducible build of the plugin archive.
# Usage: bash build.sh   (produces dist/tropatt-moguta.zip)
set -euo pipefail
cd "$(dirname "$0")"

mkdir -p dist
rm -f dist/tropatt-moguta.zip

rm -rf .build
mkdir -p .build
cp -pR mg-plugins README.md LICENSE .build/

(cd .build && zip -r -D -X ../dist/tropatt-moguta.zip mg-plugins README.md LICENSE >/dev/null)
rm -rf .build

unzip -t dist/tropatt-moguta.zip >/dev/null
echo "Built dist/tropatt-moguta.zip"
