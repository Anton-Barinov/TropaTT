#!/usr/bin/env bash
# Reproducible build of the distribution archive.
# Usage: bash build.sh   (produces dist/tropatt-opencart-2.0-2.2.ocmod.zip)
set -euo pipefail
cd "$(dirname "$0")"
mkdir -p dist
rm -f "dist/tropatt-opencart-2.0-2.2.ocmod.zip"
zip -r -D -X "dist/tropatt-opencart-2.0-2.2.ocmod.zip" upload install.xml README.md LICENSE >/dev/null
unzip -t "dist/tropatt-opencart-2.0-2.2.ocmod.zip" >/dev/null
echo "Built dist/tropatt-opencart-2.0-2.2.ocmod.zip"
