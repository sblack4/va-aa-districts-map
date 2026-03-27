#!/bin/bash
# Build a clean distribution ZIP of the plugin.
# Usage: ./scripts/build-zip.sh
# Output: ../va-aa-districts-map.zip (in the vac/ directory)

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_DIR="$SCRIPT_DIR/../va-aa-districts-map"
OUTPUT_DIR="$SCRIPT_DIR/../.."
ZIP_NAME="va-aa-districts-map.zip"

cd "$OUTPUT_DIR"

# Remove old ZIP if exists
rm -f "$ZIP_NAME"

# Build ZIP excluding dev files
zip -r "$ZIP_NAME" \
  va-aa-districts-map-plugin/va-aa-districts-map/ \
  -x "*.DS_Store" \
  -x "*/__MACOSX/*"

# Rename the inner path so the ZIP extracts as va-aa-districts-map/
# (WordPress expects the plugin folder name to match)
python3 -c "
import zipfile, os, sys
src = '$ZIP_NAME'
tmp = src + '.tmp'
with zipfile.ZipFile(src, 'r') as zin, zipfile.ZipFile(tmp, 'w') as zout:
    for item in zin.infolist():
        data = zin.read(item.filename)
        item.filename = item.filename.replace('va-aa-districts-map-plugin/', '', 1)
        if item.filename:
            zout.writestr(item, data)
os.replace(tmp, src)
"

echo ""
echo "Built: $OUTPUT_DIR/$ZIP_NAME"
echo "Size: $(du -h "$OUTPUT_DIR/$ZIP_NAME" | cut -f1)"
echo ""
echo "Contents:"
unzip -l "$OUTPUT_DIR/$ZIP_NAME" | grep "va-aa-districts-map/"
