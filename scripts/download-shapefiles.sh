#!/bin/bash
#
# Download Natural Earth shapefiles for SimpleMappr
#
# This script downloads the required Natural Earth data files.
# Run from the project root: ./scripts/download-shapefiles.sh
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
MAPS_DIR="$PROJECT_ROOT/mapserver/maps"

# Natural Earth base URL
NE_BASE="https://naciscdn.org/naturalearth"

echo "========================================"
echo "SimpleMappr Shapefile Downloader"
echo "========================================"
echo ""
echo "Target directory: $MAPS_DIR"
echo ""

# Create directories
mkdir -p "$MAPS_DIR"
cd "$MAPS_DIR"

# Function to download and extract
download_extract() {
    local url="$1"
    local target_dir="$2"
    local filename=$(basename "$url")

    echo "Downloading: $filename"

    if [ ! -f "$filename" ]; then
        curl -L -O "$url" || wget "$url"
    else
        echo "  (already downloaded)"
    fi

    if [ -n "$target_dir" ]; then
        mkdir -p "$target_dir"
        echo "  Extracting to $target_dir..."
        unzip -o -q "$filename" -d "$target_dir"
    else
        echo "  Extracting..."
        unzip -o -q "$filename"
    fi

    rm -f "$filename"
    echo "  Done."
    echo ""
}

echo "========================================"
echo "Downloading 10m Cultural Data"
echo "========================================"

# Cultural vectors
download_extract "$NE_BASE/10m/cultural/ne_10m_admin_0_map_units.zip" "10m_cultural/10m_cultural"
download_extract "$NE_BASE/10m/cultural/ne_10m_admin_1_states_provinces.zip" "10m_cultural/10m_cultural"
download_extract "$NE_BASE/10m/cultural/ne_10m_admin_1_states_provinces_lines.zip" "10m_cultural/10m_cultural"
download_extract "$NE_BASE/10m/cultural/ne_10m_populated_places_simple.zip" "10m_cultural/10m_cultural"
download_extract "$NE_BASE/10m/cultural/ne_10m_roads.zip" "10m_cultural/10m_cultural"
download_extract "$NE_BASE/10m/cultural/ne_10m_railroads.zip" "10m_cultural/10m_cultural"

echo "========================================"
echo "Downloading 10m Physical Data"
echo "========================================"

# Physical vectors
download_extract "$NE_BASE/10m/physical/ne_10m_land.zip" "10m_physical"
download_extract "$NE_BASE/10m/physical/ne_10m_ocean.zip" "10m_physical"
download_extract "$NE_BASE/10m/physical/ne_10m_lakes.zip" "10m_physical"
download_extract "$NE_BASE/10m/physical/ne_10m_rivers_lake_centerlines.zip" "10m_physical"
download_extract "$NE_BASE/10m/physical/ne_10m_geography_regions_polys.zip" "10m_physical"
download_extract "$NE_BASE/10m/physical/ne_10m_geography_marine_polys.zip" "10m_physical"

echo "========================================"
echo "Downloading Raster Data (Optional)"
echo "========================================"
echo ""
echo "NOTE: Raster files are large (100MB-500MB each)."
echo "Skip with Ctrl+C if not needed."
echo ""

read -p "Download relief rasters? (y/N) " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "Downloading Natural Earth rasters..."

    # Hypsometric tints with shaded relief
    mkdir -p "HYP_HR_SR_OB_DR"
    if [ ! -f "HYP_HR_SR_OB_DR/HYP_HR_SR_OB_DR.tif" ]; then
        echo "Downloading HYP_HR_SR_OB_DR (hypsometric relief)..."
        curl -L -o "hyp_hr_sr_ob_dr.zip" "$NE_BASE/10m/raster/HYP_HR_SR_OB_DR.zip" || \
        wget -O "hyp_hr_sr_ob_dr.zip" "$NE_BASE/10m/raster/HYP_HR_SR_OB_DR.zip"
        unzip -o -q "hyp_hr_sr_ob_dr.zip" -d "HYP_HR_SR_OB_DR"
        rm -f "hyp_hr_sr_ob_dr.zip"
    fi

    # Greyscale shaded relief
    mkdir -p "GRAY_HR_SR_OB_DR"
    if [ ! -f "GRAY_HR_SR_OB_DR/GRAY_HR_SR_OB_DR.tif" ]; then
        echo "Downloading GRAY_HR_SR_OB_DR (greyscale relief)..."
        curl -L -o "gray_hr_sr_ob_dr.zip" "$NE_BASE/10m/raster/GRAY_HR_SR_OB_DR.zip" || \
        wget -O "gray_hr_sr_ob_dr.zip" "$NE_BASE/10m/raster/GRAY_HR_SR_OB_DR.zip"
        unzip -o -q "gray_hr_sr_ob_dr.zip" -d "GRAY_HR_SR_OB_DR"
        rm -f "gray_hr_sr_ob_dr.zip"
    fi

fi

echo ""
echo "========================================"
echo "Download Complete!"
echo "========================================"
echo ""
echo "Shapefile directory: $MAPS_DIR"
echo ""
echo "Directory structure:"
find "$MAPS_DIR" -maxdepth 2 -type d | head -20
echo ""
echo "Total size:"
du -sh "$MAPS_DIR"
echo ""
echo "You can now build the Docker containers:"
echo "  docker-compose build"
echo "  docker-compose up"
echo ""
