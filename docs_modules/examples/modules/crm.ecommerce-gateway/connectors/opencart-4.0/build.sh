#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
CONNECTORS_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$SCRIPT_DIR"

mkdir -p dist
rm -f dist/tropatt-opencart-4.ocmod.zip
rm -rf .build
trap 'rm -rf .build' EXIT

mkdir -p .build
cp -pR extension README.md .build/
cp -p "$CONNECTORS_DIR/opencart-2.3/LICENSE" .build/LICENSE

(cd .build && zip -r -D -X ../dist/tropatt-opencart-4.ocmod.zip extension README.md LICENSE >/dev/null)
unzip -t dist/tropatt-opencart-4.ocmod.zip >/dev/null
echo "Built dist/tropatt-opencart-4.ocmod.zip"
