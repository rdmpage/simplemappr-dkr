#!/bin/bash
#
# Generate reference (golden) images for visual regression tests.
# Run this once when the rendering is known-good, then commit the results.
#
# Usage: bash tests/visual/generate-references.sh
# The render service must be running (docker compose up -d).
#
# DO NOT run this to "fix" failing tests — only run it when a rendering
# change is intentional and you want to update the baseline.
#

RENDER_URL="${RENDER_URL:-http://localhost:8080/render}"
REF_DIR="$(cd "$(dirname "$0")/reference" && pwd)"

render() {
    local name="$1"
    local payload="$2"
    local outfile="$REF_DIR/$name.png"

    echo -n "  $name ... "
    http_status=$(curl -s -o "$outfile" -w "%{http_code}" \
        -X POST "$RENDER_URL" \
        -H "Content-Type: application/json" \
        -d "$payload")

    if [ "$http_status" = "200" ]; then
        echo "ok ($(wc -c < "$outfile" | tr -d ' ') bytes)"
    else
        echo "FAILED (HTTP $http_status)"
        rm -f "$outfile"
    fi
}

echo "Generating visual regression reference images → $REF_DIR"
echo ""

# Geographic baseline
render "geographic" '{
    "output":"png","width":600,"height":300,
    "projection":"epsg:4326",
    "layers":["outline","countries"],
    "points":[]
}'

# Lambert projections — previously had horizontal line artifacts
render "sa-lambert" '{
    "output":"png","width":600,"height":300,
    "projection":"esri:102015",
    "layers":["outline","countries"],
    "points":[]
}'

render "africa-lambert" '{
    "output":"png","width":600,"height":300,
    "projection":"esri:102024",
    "layers":["outline","countries"],
    "points":[]
}'

render "na-lambert" '{
    "output":"png","width":600,"height":300,
    "projection":"esri:102009",
    "layers":["outline","countries"],
    "points":[]
}'

render "europe-lambert" '{
    "output":"png","width":600,"height":300,
    "projection":"esri:102014",
    "layers":["outline","countries"],
    "points":[]
}'

# Polar projections — previously South Pole was blank/truncated
render "south-pole" '{
    "output":"png","width":400,"height":400,
    "projection":"epsg:102019",
    "layers":["outline","countries"],
    "points":[]
}'

render "north-pole" '{
    "output":"png","width":400,"height":400,
    "projection":"epsg:102017",
    "layers":["outline","countries"],
    "points":[]
}'

# World projections
render "robinson" '{
    "output":"png","width":600,"height":300,
    "projection":"epsg:54030",
    "layers":["outline","countries"],
    "points":[]
}'

# Point data rendering
render "points" '{
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

echo ""
echo "Done. Commit the reference images:"
echo "  git add tests/visual/reference/ && git commit -m 'Update visual regression references'"
