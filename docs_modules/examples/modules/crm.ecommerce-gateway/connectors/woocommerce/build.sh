#!/usr/bin/env bash
# Reproducible build of the distribution archive.
# Usage: bash build.sh   (produces dist/<archive>.zip from the repository root)
set -euo pipefail
cd "$(dirname "$0")"
mkdir -p dist
rm -f "dist/tropatt-woocommerce.zip"
rm -rf .build && mkdir -p .build/tropatt-ecommerce
cp -pR tropatt-ecommerce.php includes .build/tropatt-ecommerce/
if [ -d languages ]; then
  cp -pR languages .build/tropatt-ecommerce/
fi
(cd .build && zip -r -D -X "../dist/tropatt-woocommerce.zip" tropatt-ecommerce >/dev/null)
rm -rf .build
unzip -t "dist/tropatt-woocommerce.zip" >/dev/null
echo "Built dist/tropatt-woocommerce.zip"
