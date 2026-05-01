#!/bin/bash

set -eou pipefail

rm -rf build/*
mkdir -p build/

build_item() {
    local dir="$1"
    local file="$2"
    local zip_contents=("${@:3}")
    local base=$(basename "$dir")
    local sha d
    read -r sha d < <(git log --format="%h %ai" -n 1 -- "$dir")
    local timestamp="${d:5:2}${d:8:2}${d:11:2}"
    local old_ver=$(sed -nE 's/( \* )?Version: ([0-9]+\.[0-9]+).*/\2/p' "$file" | head -1)
    local new_ver="1.0+$timestamp-$sha"

    echo "Building $base v$new_ver"
    perl -pi -e "s/Version: $old_ver/Version: $new_ver/" "$file"
    (cd "$(dirname "$dir")" && zip -r "../build/$base-${new_ver//+/-}.zip" "${zip_contents[@]}" > /dev/null)
    perl -pi -e "s/Version: \Q$new_ver\E/Version: $old_ver/" "$file"
}

for plugin in plugins/*; do
    base=$(basename "$plugin")
    build_item "$plugin" "$plugin/$base.php" "$base/includes/" "$base/$base.php"
done

for theme in themes/*; do
    base=$(basename "$theme")
    build_item "$theme" "$theme/style.css" "$base/"
done