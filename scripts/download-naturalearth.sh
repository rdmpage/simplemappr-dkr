#!/bin/bash
# Download Natural Earth data for SimpleMappr
# This script downloads the required shapefiles from Natural Earth

set -e

DATA_DIR="${1:-/app/mapserver/maps}"
NATURAL_EARTH_URL="https://naciscdn.org/naturalearth"

echo "Downloading Natural Earth data to ${DATA_DIR}..."

# Create directories
mkdir -p "${DATA_DIR}/10m_physical"
mkdir -p "${DATA_DIR}/10m_cultural/10m_cultural"

cd "${DATA_DIR}"

# Function to download and extract
download_extract() {
    local category=$1
    local filename=$2
    local target_dir=$3

    echo "Downloading ${filename}..."
    curl -sL "${NATURAL_EARTH_URL}/10m/${category}/${filename}.zip" -o "/tmp/${filename}.zip"
    unzip -q -o "/tmp/${filename}.zip" -d "${target_dir}"
    rm "/tmp/${filename}.zip"
}

# Physical layers
echo "Downloading physical layers..."
download_extract "physical" "ne_10m_land" "10m_physical"
download_extract "physical" "ne_10m_ocean" "10m_physical"
download_extract "physical" "ne_10m_lakes" "10m_physical"
download_extract "physical" "ne_10m_rivers_lake_centerlines" "10m_physical"
download_extract "physical" "ne_10m_geography_marine_polys" "10m_physical"
download_extract "physical" "ne_10m_geography_regions_polys" "10m_physical"

# Cultural layers
echo "Downloading cultural layers..."
download_extract "cultural" "ne_10m_admin_0_map_units" "10m_cultural/10m_cultural"
download_extract "cultural" "ne_10m_admin_1_states_provinces" "10m_cultural/10m_cultural"
download_extract "cultural" "ne_10m_admin_1_states_provinces_lines" "10m_cultural/10m_cultural"
download_extract "cultural" "ne_10m_populated_places_simple" "10m_cultural/10m_cultural"
download_extract "cultural" "ne_10m_roads" "10m_cultural/10m_cultural"
download_extract "cultural" "ne_10m_railroads" "10m_cultural/10m_cultural"

echo "Natural Earth data download complete!"
echo "Total size: $(du -sh ${DATA_DIR} | cut -f1)"
