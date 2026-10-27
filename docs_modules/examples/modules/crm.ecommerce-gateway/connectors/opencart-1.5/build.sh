#!/usr/bin/env bash
# Reproducible build of the distribution archive.
# Usage: bash build.sh   (produces dist/tropatt-opencart-1.5.ocmod.zip)
set -euo pipefail
cd "$(dirname "$0")"
mkdir -p dist
rm -f "dist/tropatt-opencart-1.5.ocmod.zip"
zip -r -D -X "dist/tropatt-opencart-1.5.ocmod.zip" upload vqmod README.md LICENSE >/dev/null
unzip -t "dist/tropatt-opencart-1.5.ocmod.zip" >/dev/null
echo "Built dist/tropatt-opencart-1.5.ocmod.zip"
