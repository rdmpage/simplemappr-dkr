#!/bin/bash
#
# Generate projection thumbnail images for the editor UI.
# Calls the render service to produce a small PNG for each projection.
#
# Usage: bash scripts/generate-projection-thumbnails.sh
# The render service must be running (docker compose up -d).
#
# On a server where the render service is not exposed to the host,
# the script runs curl inside the app container automatically.
#

REPO_DIR="$(cd "$(dirname "$0")/.." && pwd)"
OUTPUT_DIR="$REPO_DIR/public/images/projections"

mkdir -p "$OUTPUT_DIR"

# Determine how to reach the render service.
# If localhost:8080 is reachable use it directly; otherwise run curl
# inside the app container (which shares the Docker network with render).
if curl -sf http://localhost:8080/health > /dev/null 2>&1; then
    RENDER_URL="http://localhost:8080/render"
    USE_DOCKER_EXEC=""
else
    RENDER_URL="http://render:8080/render"
    USE_DOCKER_EXEC="simplemappr_app"
fi

generate() {
    local projection="$1"
    local filename="$2"
    local outfile="$OUTPUT_DIR/$filename"

    echo -n "  $projection ... "

    local payload
    payload=$(printf '{
        "output": "png",
        "width": 150,
        "height": 100,
        "projection": "%s",
        "layers": ["outline"],
        "points": [],
        "line_thickness": 0.5,
        "options": {"border": false, "legend": false, "scalebar": false}
    }' "$projection")

    if [ -n "$USE_DOCKER_EXEC" ]; then
        http_status=$(docker exec "$USE_DOCKER_EXEC" \
            curl -s -o "/tmp/$filename" -w "%{http_code}" \
            -X POST "$RENDER_URL" \
            -H "Content-Type: application/json" \
            -d "$payload")
        if [ "$http_status" = "200" ]; then
            docker cp "$USE_DOCKER_EXEC:/tmp/$filename" "$outfile"
        fi
    else
        http_status=$(curl -s -o "$outfile" -w "%{http_code}" \
            -X POST "$RENDER_URL" \
            -H "Content-Type: application/json" \
            -d "$payload")
    fi

    if [ "$http_status" = "200" ]; then
        echo "ok"
    else
        echo "FAILED (HTTP $http_status)"
        rm -f "$outfile"
    fi
}

echo "Generating projection thumbnails → $OUTPUT_DIR"
echo ""

generate "epsg:4326"    "epsg-4326.png"
generate "esri:102009"  "esri-102009.png"
generate "esri:102015"  "esri-102015.png"
generate "esri:102014"  "esri-102014.png"
generate "esri:102012"  "esri-102012.png"
generate "esri:102024"  "esri-102024.png"
generate "epsg:3112"    "epsg-3112.png"
generate "epsg:102017"  "epsg-102017.png"
generate "epsg:102019"  "epsg-102019.png"
generate "epsg:54030"   "epsg-54030.png"
generate "epsg:3395"    "epsg-3395.png"

echo ""
echo "Done."
