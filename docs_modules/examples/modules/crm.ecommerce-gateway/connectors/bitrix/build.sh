#!/usr/bin/env bash
# Reproducible build of the module archive.
# Usage: bash build.sh   (produces dist/tropatt.crm-<version>.zip)
set -euo pipefail
cd "$(dirname "$0")"

VERSION="$(php -r '$arModuleVersion = array(); include "install/version.php"; echo $arModuleVersion["VERSION"];')"
mkdir -p dist
rm -f "dist/tropatt.crm-${VERSION}.zip"

rm -rf .build
mkdir -p ".build/tropatt.crm"
cp -pR install include.php options.php webhook.php lib lang README.md LICENSE ".build/tropatt.crm/"

(cd .build && zip -r -D -X "../dist/tropatt.crm-${VERSION}.zip" tropatt.crm >/dev/null)
rm -rf .build

unzip -t "dist/tropatt.crm-${VERSION}.zip" >/dev/null
echo "Built dist/tropatt.crm-${VERSION}.zip"
