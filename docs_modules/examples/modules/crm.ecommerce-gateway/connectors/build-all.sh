#!/usr/bin/env bash
set -euo pipefail

CONNECTORS_DIR="$(cd "$(dirname "$0")" && pwd)"
DIST_DIR="$CONNECTORS_DIR/dist"
mkdir -p "$DIST_DIR"

# Build every connector archive from its adjacent source tree. This includes
# OpenCart 3/4, whose README already promises reproducible build scripts.
for build_script in "$CONNECTORS_DIR"/*/build.sh; do
    [ -f "$build_script" ] || continue
    (cd "$(dirname "$build_script")" && bash ./build.sh)
done

BITRIX_VERSION="$(php -r '$arModuleVersion = array(); include $argv[1]; echo $arModuleVersion["VERSION"] ?? "";' "$CONNECTORS_DIR/bitrix/install/version.php")"
if [ -z "$BITRIX_VERSION" ]; then
    echo "Could not read the Bitrix connector version" >&2
    exit 1
fi
BITRIX_PACKAGE="tropatt.crm-${BITRIX_VERSION}.zip"
if [ ! -f "$CONNECTORS_DIR/bitrix/dist/$BITRIX_PACKAGE" ]; then
    echo "The current Bitrix package was not built: $BITRIX_PACKAGE" >&2
    exit 1
fi

# The central dist directory is the download location documented for users.
# Copy only release archives; omit stale versioned Bitrix archives.
for connector_dir in "$CONNECTORS_DIR"/*; do
    [ -d "$connector_dir" ] || continue
    [ "$(basename "$connector_dir")" = "dist" ] && continue
    if [ "$(basename "$connector_dir")" = "bitrix" ]; then
        cp "$CONNECTORS_DIR/bitrix/dist/$BITRIX_PACKAGE" "$DIST_DIR/$BITRIX_PACKAGE"
        continue
    fi
    for package in "$connector_dir"/dist/*.zip; do
        [ -f "$package" ] || continue
        cp "$package" "$DIST_DIR/$(basename "$package")"
    done
done

for stale_package in "$DIST_DIR"/tropatt.crm-*.zip; do
    [ -f "$stale_package" ] || continue
    [ "$(basename "$stale_package")" = "$BITRIX_PACKAGE" ] || rm -f "$stale_package"
done

package_count=0
for package in "$DIST_DIR"/*.zip; do
    [ -f "$package" ] || continue
    unzip -t "$package" >/dev/null
    package_count=$((package_count + 1))
done
if [ "$package_count" -ne 15 ]; then
    echo "Expected 15 connector archives in dist, found $package_count" >&2
    exit 1
fi

for connector_dir in "$CONNECTORS_DIR"/*; do
    [ -d "$connector_dir" ] || continue
    [ "$(basename "$connector_dir")" = "dist" ] && continue
    if [ "$(basename "$connector_dir")" = "bitrix" ]; then
        source_package="$CONNECTORS_DIR/bitrix/dist/$BITRIX_PACKAGE"
        release_package="$DIST_DIR/$BITRIX_PACKAGE"
        cmp -s "$source_package" "$release_package"
        continue
    fi
    for source_package in "$connector_dir"/dist/*.zip; do
        [ -f "$source_package" ] || continue
        release_package="$DIST_DIR/$(basename "$source_package")"
        if [ ! -f "$release_package" ] || ! cmp -s "$source_package" "$release_package"; then
            echo "Central release archive differs from its connector build: $(basename "$source_package")" >&2
            exit 1
        fi
    done
done

echo "Built and verified $package_count connector archives in connectors/dist"
