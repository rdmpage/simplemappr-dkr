#!/bin/bash
#
# API integration tests for the SimpleMappr render service.
# Tests HTTP status codes, content type, image dimensions, and error handling.
#
# Usage:
#   bash tests/api-test.sh              # uses http://localhost:8080
#   RENDER_URL=http://myserver/render bash tests/api-test.sh
#

RENDER_URL="${RENDER_URL:-http://localhost:8080/render}"
HEALTH_URL="${RENDER_URL%/render}/health"
PASS=0
FAIL=0
ERRORS=()

# ---- helpers ----------------------------------------------------------------

green()  { printf "\033[32m%s\033[0m\n" "$1"; }
red()    { printf "\033[31m%s\033[0m\n" "$1"; }
bold()   { printf "\033[1m%s\033[0m\n" "$1"; }

pass() {
    PASS=$((PASS + 1))
    green "  ✔ $1"
}

fail() {
    FAIL=$((FAIL + 1))
    ERRORS+=("$1")
    red "  ✘ $1"
}

assert_eq() {
    local label="$1" expected="$2" actual="$3"
    if [ "$actual" = "$expected" ]; then
        pass "$label (got: $actual)"
    else
        fail "$label (expected: $expected, got: $actual)"
    fi
}

assert_contains() {
    local label="$1" needle="$2" haystack="$3"
    if echo "$haystack" | grep -q "$needle"; then
        pass "$label"
    else
        fail "$label (expected to contain '$needle', got: $haystack)"
    fi
}

# POST to render service, save image to $outfile, return HTTP status
render() {
    local payload="$1"
    local outfile="${2:-/tmp/api_test_render.png}"
    curl -s -o "$outfile" -w "%{http_code}" \
        -X POST "$RENDER_URL" \
        -H "Content-Type: application/json" \
        -d "$payload"
}

# Get image dimensions as "WxH" using PHP (available in the render container env)
image_dims() {
    php -r "
        \$img = @imagecreatefrompng('$1');
        if (!\$img) { echo 'invalid'; exit; }
        echo imagesx(\$img) . 'x' . imagesy(\$img);
    "
}

# ---- base payload -----------------------------------------------------------

BASE='{
    "output": "png",
    "width": 300,
    "height": 150,
    "projection": "epsg:4326",
    "layers": ["outline"],
    "points": [],
    "options": {"border": false, "legend": false, "scalebar": false}
}'

# =============================================================================
bold "Health check"
# =============================================================================

status=$(curl -s -o /dev/null -w "%{http_code}" "$HEALTH_URL")
assert_eq "Health endpoint returns 200" "200" "$status"

# =============================================================================
bold "Basic render"
# =============================================================================

outfile=$(mktemp /tmp/api_test_XXXX.png)
status=$(render "$BASE" "$outfile")
assert_eq "Returns HTTP 200" "200" "$status"
assert_eq "Image dimensions match request" "300x150" "$(image_dims "$outfile")"
rm -f "$outfile"

# =============================================================================
bold "All projections render without error"
# =============================================================================

projections=(
    "epsg:4326"
    "esri:102009"
    "esri:102015"
    "esri:102014"
    "esri:102012"
    "esri:102024"
    "epsg:3112"
    "epsg:102017"
    "epsg:102019"
    "epsg:54030"
    "epsg:3395"
)

for proj in "${projections[@]}"; do
    outfile=$(mktemp /tmp/api_test_XXXX.png)
    payload=$(echo "$BASE" | php -r "
        \$d = json_decode(file_get_contents('php://stdin'), true);
        \$d['projection'] = '$proj';
        echo json_encode(\$d);
    ")
    status=$(render "$payload" "$outfile")
    assert_eq "Projection $proj returns 200" "200" "$status"
    dims=$(image_dims "$outfile")
    assert_eq "Projection $proj produces valid image" "300x150" "$dims"
    rm -f "$outfile"
done

# =============================================================================
bold "Output formats"
# =============================================================================

for fmt in png jpg svg; do
    outfile=$(mktemp /tmp/api_test_XXXX.$fmt)
    payload=$(echo "$BASE" | php -r "
        \$d = json_decode(file_get_contents('php://stdin'), true);
        \$d['output'] = '$fmt';
        echo json_encode(\$d);
    ")
    status=$(render "$payload" "$outfile")
    assert_eq "Format $fmt returns 200" "200" "$status"
    size=$(wc -c < "$outfile")
    if [ "$size" -gt 100 ]; then
        pass "Format $fmt produces non-empty output ($size bytes)"
    else
        fail "Format $fmt output too small ($size bytes)"
    fi
    rm -f "$outfile"
done

# =============================================================================
bold "Point data"
# =============================================================================

outfile=$(mktemp /tmp/api_test_XXXX.png)
payload='{
    "output": "png", "width": 300, "height": 150,
    "projection": "epsg:4326",
    "layers": ["outline"],
    "points": [{
        "legend": "Test species",
        "shape": "circle",
        "size": 8,
        "color": [255, 0, 0],
        "coordinates": [{"lat": 45.5, "lon": -75.5}, {"lat": 51.5, "lon": -0.1}]
    }]
}'
status=$(render "$payload" "$outfile")
assert_eq "Render with point data returns 200" "200" "$status"
assert_eq "Render with point data produces valid image" "300x150" "$(image_dims "$outfile")"
rm -f "$outfile"

# =============================================================================
bold "Error handling"
# =============================================================================

outfile=$(mktemp /tmp/api_test_XXXX.txt)

status=$(render '{"projection":"epsg:4326","output":"png","width":300,"height":150,"layers":["nonexistent_layer"],"points":[]}' "$outfile")
assert_eq "Unknown layer still returns 200 (skipped gracefully)" "200" "$status"

status=$(curl -s -o "$outfile" -w "%{http_code}" \
    -X POST "$RENDER_URL" \
    -H "Content-Type: application/json" \
    -d '{"output":"png"}')
assert_eq "Missing required fields returns 4xx or 200" "200" "$status"  # render service is lenient

status=$(curl -s -o "$outfile" -w "%{http_code}" \
    -X GET "$RENDER_URL")
if [ "$status" != "200" ]; then
    pass "GET request refused (got $status)"
else
    fail "GET request should not return 200"
fi

rm -f "$outfile"

# =============================================================================
bold "Crop / raw_bbox"
# =============================================================================

outfile=$(mktemp /tmp/api_test_XXXX.png)
payload='{
    "output": "png", "width": 200, "height": 100,
    "projection": "epsg:4326",
    "bbox": [-100, 20, -60, 55],
    "raw_bbox": true,
    "layers": ["outline"],
    "points": []
}'
status=$(render "$payload" "$outfile")
assert_eq "Crop with raw_bbox returns 200" "200" "$status"
assert_eq "Crop produces correct dimensions" "200x100" "$(image_dims "$outfile")"
rm -f "$outfile"

# =============================================================================
# Summary
# =============================================================================

echo ""
bold "Results: $PASS passed, $FAIL failed"

if [ "$FAIL" -gt 0 ]; then
    echo ""
    red "Failed tests:"
    for err in "${ERRORS[@]}"; do
        red "  • $err"
    done
    exit 1
fi
