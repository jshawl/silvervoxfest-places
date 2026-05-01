#!/bin/bash

rm -rf build/*
mkdir -p build/

build_item() {
    local dir="$1"
    local file="$2"
    local zip_contents=("${@:3}")

    local base
    base=$(basename "$dir")
    local sha
    sha=$(git log --format="%h" -n 1 -- "$dir")
    local old_ver
    old_ver=$(sed -nE 's/( \* )?Version: ([0-9]+\.[0-9]+).*/\2/p' "$file" | head -1)
    local new_ver="1.0+$sha"

    echo "Building $base $new_ver"
    perl -pi -e "s/Version: $old_ver/Version: $new_ver/" "$file"
    (cd "$(dirname "$dir")" && zip -r "../build/$base.zip" "${zip_contents[@]}" > /dev/null) || true
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