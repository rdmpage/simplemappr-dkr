#!/bin/bash
#
# Visual regression tests for the SimpleMappr render service.
# Re-renders each test case and compares against stored reference images.
#
# Usage: bash tests/visual/visual-test.sh
# The render service must be running (docker compose up -d).
#
# If a test fails, a diff image is saved to tests/visual/diffs/ for inspection.
#
# To update references after an intentional rendering change, run:
#   bash tests/visual/generate-references.sh
#

RENDER_URL="${RENDER_URL:-http://localhost:8080/render}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REF_DIR="$SCRIPT_DIR/reference"
DIFF_DIR="$SCRIPT_DIR/diffs"
PASS=0
FAIL=0
ERRORS=()

# Maximum percentage of pixels allowed to differ (0.5 = half a percent)
THRESHOLD=0.5

mkdir -p "$DIFF_DIR"

# ---- helpers ----------------------------------------------------------------

green() { printf "\033[32m%s\033[0m\n" "$1"; }
red()   { printf "\033[31m%s\033[0m\n" "$1"; }
bold()  { printf "\033[1m%s\033[0m\n" "$1"; }

pass() { PASS=$((PASS + 1)); green "  ✔ $1"; }
fail() { FAIL=$((FAIL + 1)); ERRORS+=("$1"); red "  ✘ $1"; }

compare_images() {
    local name="$1"
    local actual="$2"
    local reference="$REF_DIR/$name.png"
    local diff_file="$DIFF_DIR/$name-diff.png"

    if [ ! -f "$reference" ]; then
        fail "$name: no reference image found (run generate-references.sh first)"
        return
    fi

    # Count differing pixels using ImageMagick
    diff_pixels=$(magick compare -metric AE "$reference" "$actual" "$diff_file" 2>&1)
    exit_code=$?

    if [ $exit_code -eq 2 ]; then
        fail "$name: images have different dimensions (reference vs actual)"
        return
    fi

    # Get total pixels from reference image
    total_pixels=$(magick identify -format "%[fx:w*h]" "$reference")
    diff_pct=$(php -r "echo round(($diff_pixels / $total_pixels) * 100, 2);")

    if (( $(echo "$diff_pct <= $THRESHOLD" | bc -l) )); then
        pass "$name (${diff_pct}% pixels differ)"
        rm -f "$diff_file"
    else
        fail "$name (${diff_pct}% pixels differ — diff saved to diffs/$name-diff.png)"
    fi
}

render_and_compare() {
    local name="$1"
    local payload="$2"
    local actual
    actual=$(mktemp /tmp/visual_test_XXXX.png)

    http_status=$(curl -s -o "$actual" -w "%{http_code}" \
        -X POST "$RENDER_URL" \
        -H "Content-Type: application/json" \
        -d "$payload")

    if [ "$http_status" != "200" ]; then
        fail "$name: render returned HTTP $http_status"
        rm -f "$actual"
        return
    fi

    compare_images "$name" "$actual"
    rm -f "$actual"
}

# =============================================================================
bold "Visual regression tests (threshold: ${THRESHOLD}% pixel difference)"
echo ""

bold "Geographic projection"
render_and_compare "geographic" '{
    "output":"png","width":600,"height":300,
    "projection":"epsg:4326",
    "layers":["outline","countries"],
    "points":[]
}'

bold "Lambert projections (previously had horizontal line artifacts)"
render_and_compare "sa-lambert" '{
    "output":"png","width":600,"height":300,
    "projection":"esri:102015",
    "layers":["outline","countries"],
    "points":[]
}'

render_and_compare "africa-lambert" '{
    "output":"png","width":600,"height":300,
    "projection":"esri:102024",
    "layers":["outline","countries"],
    "points":[]
}'

render_and_compare "na-lambert" '{
    "output":"png","width":600,"height":300,
    "projection":"esri:102009",
    "layers":["outline","countries"],
    "points":[]
}'

render_and_compare "europe-lambert" '{
    "output":"png","width":600,"height":300,
    "projection":"esri:102014",
    "layers":["outline","countries"],
    "points":[]
}'

bold "Polar projections (previously South Pole was blank/truncated)"
render_and_compare "south-pole" '{
    "output":"png","width":400,"height":400,
    "projection":"epsg:102019",
    "layers":["outline","countries"],
    "points":[]
}'

render_and_compare "north-pole" '{
    "output":"png","width":400,"height":400,
    "projection":"epsg:102017",
    "layers":["outline","countries"],
    "points":[]
}'

bold "World projections"
render_and_compare "robinson" '{
    "output":"png","width":600,"height":300,
    "projection":"epsg:54030",
    "layers":["outline","countries"],
    "points":[]
}'

bold "Point data"
render_and_compare "points" '{
    "output":"png","width":600,"height":300,
    "projection":"epsg:4326",
    "layers":["outline","countries"],
    "points":[{
        "legend":"Species A",
        "shape":"circle",
        "size":8,
        "color":[255,0,0],
        "coordinates":[
            {"lat":45.5,"lon":-75.5},
            {"lat":51.5,"lon":-0.1},
            {"lat":35.6,"lon":139.7},
            {"lat":-33.9,"lon":151.2}
        ]
    }]
}'

# =============================================================================
echo ""
bold "Results: $PASS passed, $FAIL failed"

if [ "$FAIL" -gt 0 ]; then
    echo ""
    red "Failed tests:"
    for err in "${ERRORS[@]}"; do
        red "  • $err"
    done
    echo ""
    red "To inspect failures, check tests/visual/diffs/"
    red "To update references after an intentional change, run:"
    red "  bash tests/visual/generate-references.sh"
    exit 1
fi
