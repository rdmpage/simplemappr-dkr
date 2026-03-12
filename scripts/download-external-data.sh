#!/bin/bash
# Download external map data for SimpleMappr
# These datasets are NOT from Natural Earth and have different licenses
#
# Sources:
# - Conservation International Biodiversity Hotspots
#   DOI: 10.5281/zenodo.3261807
#   License: CC-BY-SA 4.0
#   URL: https://zenodo.org/records/3261807
#
# - WWF Terrestrial Ecoregions
#   URL: https://www.worldwildlife.org/publications/terrestrial-ecoregions-of-the-world
#   License: Contact WWF for terms
#
# - WWF Marine Ecoregions of the World (MEOW)
#   URL: https://www.worldwildlife.org/publications/marine-ecoregions-of-the-world-a-bioregionalization-of-coastal-and-shelf-areas
#   License: Contact WWF for terms

set -e

DATA_DIR="${1:-/app/mapserver/maps}"

echo "Downloading external map data to ${DATA_DIR}..."
echo ""

# ============================================================================
# Conservation International - Biodiversity Hotspots
# ============================================================================
echo "=== Conservation International Biodiversity Hotspots ==="
echo "DOI: 10.5281/zenodo.3261807"
echo "License: CC-BY-SA 4.0"
echo ""

CI_DIR="${DATA_DIR}/conservation_international"
mkdir -p "${CI_DIR}"

if [ -f "${CI_DIR}/hotspots_2016_1.shp" ]; then
    echo "Biodiversity Hotspots already downloaded, skipping..."
else
    echo "Downloading Biodiversity Hotspots from Zenodo..."
    # The Zenodo record contains the shapefile
    curl -sL "https://zenodo.org/records/3261807/files/hotspots_2016_1.zip?download=1" -o "/tmp/hotspots.zip"
    unzip -q -o "/tmp/hotspots.zip" -d "${CI_DIR}"
    rm "/tmp/hotspots.zip"
    echo "Biodiversity Hotspots downloaded successfully"
fi

echo ""

# ============================================================================
# WWF Terrestrial Ecoregions
# ============================================================================
echo "=== WWF Terrestrial Ecoregions ==="
echo "Note: This dataset requires manual download from WWF website"
echo "URL: https://www.worldwildlife.org/publications/terrestrial-ecoregions-of-the-world"
echo ""

WWF_TERR_DIR="${DATA_DIR}/wwf_terr_ecos"
mkdir -p "${WWF_TERR_DIR}"

if [ -f "${WWF_TERR_DIR}/wwf_terr_ecos.shp" ]; then
    echo "WWF Terrestrial Ecoregions already present"
else
    echo "WWF Terrestrial Ecoregions NOT FOUND"
    echo "Please download manually from the WWF website and place in:"
    echo "  ${WWF_TERR_DIR}/wwf_terr_ecos.shp"
fi

echo ""

# ============================================================================
# WWF Marine Ecoregions of the World (MEOW)
# ============================================================================
echo "=== WWF Marine Ecoregions of the World (MEOW) ==="
echo "Note: This dataset requires manual download from WWF/TNC"
echo "URL: https://www.worldwildlife.org/publications/marine-ecoregions-of-the-world-a-bioregionalization-of-coastal-and-shelf-areas"
echo ""

WWF_MEOW_DIR="${DATA_DIR}/wwf_meow"
mkdir -p "${WWF_MEOW_DIR}"

if [ -f "${WWF_MEOW_DIR}/meow_ecos.shp" ]; then
    echo "WWF Marine Ecoregions already present"
else
    echo "WWF Marine Ecoregions NOT FOUND"
    echo "Please download manually from the WWF website and place in:"
    echo "  ${WWF_MEOW_DIR}/meow_ecos.shp"
fi

echo ""
echo "============================================"
echo "External data download complete!"
echo ""
echo "Summary:"
ls -la "${CI_DIR}" 2>/dev/null | head -5 || echo "  Conservation International: not downloaded"
ls -la "${WWF_TERR_DIR}" 2>/dev/null | head -5 || echo "  WWF Terrestrial: not downloaded"
ls -la "${WWF_MEOW_DIR}" 2>/dev/null | head -5 || echo "  WWF Marine: not downloaded"
