#!/bin/bash

# =============================================================================
# Initialize New Plugin from Boilerplate
# =============================================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

# Generate random port between min and max
generate_random_port() {
    local min=$1
    local max=$2
    echo $(( RANDOM % (max - min + 1) + min ))
}

# Check if port is available
is_port_available() {
    local port=$1
    if command -v lsof &> /dev/null; then
        ! lsof -i :$port &> /dev/null
    elif command -v netstat &> /dev/null; then
        ! netstat -tuln | grep -q ":$port "
    else
        # Assume available if we can't check
        true
    fi
}

# Get available random port
get_available_port() {
    local min=${1:-10000}
    local max=${2:-60000}
    local port
    local attempts=0

    while [ $attempts -lt 100 ]; do
        port=$(generate_random_port $min $max)
        if is_port_available $port; then
            echo $port
            return 0
        fi
        attempts=$((attempts + 1))
    done

    # Fallback to random port if all attempts fail
    echo $(generate_random_port $min $max)
}

echo -e "${CYAN}"
echo "========================================"
echo "  WordPress Plugin Initializer"
echo "========================================"
echo -e "${NC}"

# Get plugin info
read -p "Plugin name (e.g., awesome-seo): " PLUGIN_SLUG
read -p "Plugin display name (e.g., Awesome SEO): " PLUGIN_NAME
read -p "Namespace suffix (e.g., AwesomeSeo): " NAMESPACE
read -p "Author name (default: ThachPN165): " AUTHOR
AUTHOR=${AUTHOR:-ThachPN165}

# Validate input
if [ -z "$PLUGIN_SLUG" ] || [ -z "$PLUGIN_NAME" ] || [ -z "$NAMESPACE" ]; then
    echo -e "${RED}Error: All fields are required!${NC}"
    exit 1
fi

# Generate variations
PLUGIN_SLUG_UNDERSCORE=$(echo "$PLUGIN_SLUG" | tr '-' '_')
PLUGIN_SLUG_UPPER=$(echo "$PLUGIN_SLUG_UNDERSCORE" | tr '[:lower:]' '[:upper:]')

echo ""
echo -e "${YELLOW}Will replace:${NC}"
echo "  cloudflare-r2-offload-cdn      → $PLUGIN_SLUG"
echo "  cloudflare_r2_offload_cdn      → $PLUGIN_SLUG_UNDERSCORE"
echo "  CLOUDFLARE_R2_OFFLOAD_CDN      → $PLUGIN_SLUG_UPPER"
echo "  CFR2OffLoad       → $NAMESPACE"
echo "  CloudFlare R2 Offload & CDN      → $PLUGIN_NAME"
echo ""

read -p "Continue? (y/n): " CONFIRM
if [ "$CONFIRM" != "y" ]; then
    echo "Aborted."
    exit 0
fi

echo ""
echo -e "${GREEN}Starting replacement...${NC}"

# Function to replace in files
replace_in_files() {
    local search=$1
    local replace=$2

    # macOS and Linux compatible sed
    if [[ "$OSTYPE" == "darwin"* ]]; then
        find . -type f \( -name "*.php" -o -name "*.json" -o -name "*.xml" -o -name "*.txt" -o -name "*.md" -o -name "*.js" -o -name "*.scss" -o -name "*.yml" -o -name "*.yaml" -o -name "*.sh" \) \
            -not -path "./vendor/*" \
            -not -path "./node_modules/*" \
            -not -path "./.git/*" \
            -not -path "./dist/*" \
            -not -path "./svn/*" \
            -not -path "./.claude/*" \
            -not -path "./.opencode/*" \
            -exec sed -i '' "s/${search}/${replace}/g" {} \;
    else
        find . -type f \( -name "*.php" -o -name "*.json" -o -name "*.xml" -o -name "*.txt" -o -name "*.md" -o -name "*.js" -o -name "*.scss" -o -name "*.yml" -o -name "*.yaml" -o -name "*.sh" \) \
            -not -path "./vendor/*" \
            -not -path "./node_modules/*" \
            -not -path "./.git/*" \
            -not -path "./dist/*" \
            -not -path "./svn/*" \
            -not -path "./.claude/*" \
            -not -path "./.opencode/*" \
            -exec sed -i "s/${search}/${replace}/g" {} \;
    fi
}

# Perform replacements (order matters!)
echo "Replacing strings..."

# Replace underscored version first (more specific)
replace_in_files "cloudflare_r2_offload_cdn" "$PLUGIN_SLUG_UNDERSCORE"

# Replace uppercase version
replace_in_files "CLOUDFLARE_R2_OFFLOAD_CDN" "$PLUGIN_SLUG_UPPER"

# Replace hyphenated version
replace_in_files "cloudflare-r2-offload-cdn" "$PLUGIN_SLUG"

# Replace namespace
replace_in_files "CFR2OffLoad" "$NAMESPACE"

# Replace display name
replace_in_files "CloudFlare R2 Offload & CDN" "$PLUGIN_NAME"

# Rename main plugin file
if [ -f "cloudflare-r2-offload-cdn.php" ]; then
    echo "Renaming main plugin file..."
    mv "cloudflare-r2-offload-cdn.php" "${PLUGIN_SLUG}.php"
fi

# Rename language files
if [ -f "languages/cloudflare-r2-offload-cdn.pot" ]; then
    mv "languages/cloudflare-r2-offload-cdn.pot" "languages/${PLUGIN_SLUG}.pot"
fi

# Update docker-compose container names
if [ -f "docker-compose.yml" ]; then
    echo "Updating Docker container names..."
    if [[ "$OSTYPE" == "darwin"* ]]; then
        sed -i '' "s/cloudflare-r2-offload-cdn-wp/${PLUGIN_SLUG}-wp/g" docker-compose.yml
        sed -i '' "s/cloudflare-r2-offload-cdn-mysql/${PLUGIN_SLUG}-mysql/g" docker-compose.yml
        sed -i '' "s/cloudflare-r2-offload-cdn-pma/${PLUGIN_SLUG}-pma/g" docker-compose.yml
        sed -i '' "s/cloudflare-r2-offload-cdn-network/${PLUGIN_SLUG}-network/g" docker-compose.yml
    else
        sed -i "s/cloudflare-r2-offload-cdn-wp/${PLUGIN_SLUG}-wp/g" docker-compose.yml
        sed -i "s/cloudflare-r2-offload-cdn-mysql/${PLUGIN_SLUG}-mysql/g" docker-compose.yml
        sed -i "s/cloudflare-r2-offload-cdn-pma/${PLUGIN_SLUG}-pma/g" docker-compose.yml
        sed -i "s/cloudflare-r2-offload-cdn-network/${PLUGIN_SLUG}-network/g" docker-compose.yml
    fi
fi

# Generate random ports for this project
echo "Generating unique ports..."
WP_PORT=$(get_available_port 10000 59000)
PMA_PORT=$(get_available_port 10000 59000)

# Ensure ports are different
while [ "$WP_PORT" = "$PMA_PORT" ]; do
    PMA_PORT=$(get_available_port 10000 59000)
done

# Create .env file with unique ports
echo "Creating .env with unique ports..."
cat > .env << EOF
# Docker MySQL
MYSQL_ROOT_PASSWORD=root
MYSQL_DATABASE=wordpress
MYSQL_USER=wordpress
MYSQL_PASSWORD=wordpress

# WordPress ports (unique for this project)
WP_PORT=$WP_PORT
PMA_PORT=$PMA_PORT

# Plugin slug
PLUGIN_SLUG=$PLUGIN_SLUG
EOF

echo -e "${GREEN}Ports assigned: WordPress=$WP_PORT, phpMyAdmin=$PMA_PORT${NC}"

# Install dependencies
echo ""
echo -e "${GREEN}Installing dependencies...${NC}"

if [ -f "composer.json" ]; then
    composer install
fi

if [ -f "package.json" ]; then
    npm install
fi

# Build assets
if [ -f "package.json" ]; then
    echo "Building assets..."
    npm run build
fi

# Initialize git
echo ""
read -p "Initialize new git repository? (y/n): " INIT_GIT
if [ "$INIT_GIT" = "y" ]; then
    rm -rf .git
    git init
    git add .
    git commit -m "Initial commit: ${PLUGIN_NAME} plugin"
    echo -e "${GREEN}Git repository initialized!${NC}"
fi

echo ""
echo -e "${GREEN}========================================"
echo "  Plugin initialized successfully!"
echo "========================================${NC}"
echo ""
echo -e "${CYAN}Plugin Info:${NC}"
echo "  Name: $PLUGIN_NAME"
echo "  Slug: $PLUGIN_SLUG"
echo "  Namespace: ThachPN165\\$NAMESPACE"
echo ""
echo -e "${CYAN}Docker Ports:${NC}"
echo "  WordPress:  http://localhost:$WP_PORT"
echo "  phpMyAdmin: http://localhost:$PMA_PORT"
echo ""
echo -e "${CYAN}Next steps:${NC}"
echo "  1. docker-compose up -d"
echo "  2. Open http://localhost:$WP_PORT"
echo "  3. Complete WordPress installation"
echo "  4. Activate the '$PLUGIN_NAME' plugin"
echo ""
