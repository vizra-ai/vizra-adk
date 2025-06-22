#!/bin/bash
# Vizra ADK Release Script

set -e

# Configuration
PACKAGE_DIR="."
ROOT_DIR=$(pwd)

# Color codes
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

# Function to update version in file
update_version() {
    local file=$1
    local old_version=$2
    local new_version=$3
    
    if [[ "$OSTYPE" == "darwin"* ]]; then
        sed -i '' "s/$old_version/$new_version/g" "$file"
    else
        sed -i "s/$old_version/$new_version/g" "$file"
    fi
}

# Check if we're in the right directory
if [ ! -f "composer.json" ]; then
    echo -e "${RED}Error: Not in package root directory${NC}"
    echo "Please run this script from the vizra-adk package root"
    exit 1
fi

# Check for uncommitted changes
if ! git diff-index --quiet HEAD --; then
    echo -e "${RED}Error: You have uncommitted changes${NC}"
    echo ""
    echo "Please commit or stash your changes before creating a release."
    echo "You can use one of these commands:"
    echo "  git add . && git commit -m 'Your commit message'"
    echo "  git stash"
    echo ""
    echo "To see what's changed:"
    echo "  git status"
    exit 1
fi

# Get current version from composer.json (default to 0.0.0 if not set)
CURRENT_VERSION=$(grep -o '"version":[[:space:]]*"[^"]*"' "composer.json" 2>/dev/null | grep -o '[0-9]\+\.[0-9]\+\.[0-9]\+' || echo "0.0.0")

# Parse command line arguments
RELEASE_TYPE="${1:-patch}"  # Default to patch if not specified

echo -e "${BLUE}=== Vizra ADK Release Tool ===${NC}"
echo ""

# Show current version
echo -e "${YELLOW}Current version: $CURRENT_VERSION${NC}"

# Calculate next version based on release type
IFS='.' read -ra VERSION_PARTS <<< "$CURRENT_VERSION"
MAJOR=${VERSION_PARTS[0]}
MINOR=${VERSION_PARTS[1]}
PATCH=${VERSION_PARTS[2]}

case "$RELEASE_TYPE" in
    "major")
        MAJOR=$((MAJOR + 1))
        MINOR=0
        PATCH=0
        ;;
    "minor")
        MINOR=$((MINOR + 1))
        PATCH=0
        ;;
    "patch")
        PATCH=$((PATCH + 1))
        ;;
    *)
        echo -e "${RED}Error: Invalid release type '$RELEASE_TYPE'${NC}"
        echo "Usage: $0 [patch|minor|major]"
        echo "  patch - Bug fixes and minor changes (0.0.X)"
        echo "  minor - New features, backwards compatible (0.X.0)"
        echo "  major - Breaking changes (X.0.0)"
        exit 1
        ;;
esac

NEW_VERSION="$MAJOR.$MINOR.$PATCH"
echo -e "${GREEN}Release type: $RELEASE_TYPE${NC}"
echo -e "${GREEN}New version: $NEW_VERSION${NC}"
echo ""

# Confirm the version
read -p "Proceed with v$NEW_VERSION? (y/n): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}Release cancelled${NC}"
    exit 0
fi

# Prompt for release notes
echo ""
echo "Enter release notes (press Ctrl+D when done):"
RELEASE_NOTES=$(cat)

# Run tests first
echo ""
echo -e "${BLUE}Running tests...${NC}"
cd "$ROOT_DIR"
composer test

if [ $? -ne 0 ]; then
    echo -e "${RED}✗ Tests failed! Aborting release.${NC}"
    exit 1
fi

echo -e "${GREEN}✓ All tests passed${NC}"

# Update composer.json version
echo ""
echo -e "${BLUE}Updating version in composer.json...${NC}"
if grep -q '"version"' "composer.json"; then
    update_version "composer.json" "\"version\": \"$CURRENT_VERSION\"" "\"version\": \"$NEW_VERSION\""
else
    # Add version after name field if it doesn't exist
    if [[ "$OSTYPE" == "darwin"* ]]; then
        sed -i '' '/"name":/a\
  "version": "'$NEW_VERSION'",
' "composer.json"
    else
        sed -i '/"name":/a\  "version": "'$NEW_VERSION'",' "composer.json"
    fi
fi

# Update CHANGELOG.md
echo -e "${BLUE}Updating CHANGELOG.md...${NC}"
TODAY=$(date +%Y-%m-%d)
CHANGELOG_ENTRY="## [$NEW_VERSION] - $TODAY"

# Create temp file with new changelog entry
cat > /tmp/changelog_entry.tmp << EOF
$CHANGELOG_ENTRY

$RELEASE_NOTES

EOF

# Insert after [Unreleased] section
if [[ "$OSTYPE" == "darwin"* ]]; then
    sed -i '' '/## \[Unreleased\]/r /tmp/changelog_entry.tmp' "CHANGELOG.md"
else
    sed -i '/## \[Unreleased\]/r /tmp/changelog_entry.tmp' "CHANGELOG.md"
fi

rm /tmp/changelog_entry.tmp

# Stage changes
echo ""
echo -e "${BLUE}Staging changes...${NC}"
git add "composer.json" "CHANGELOG.md"

# Show diff
echo ""
echo -e "${BLUE}Changes to be committed:${NC}"
git diff --cached

# Confirm
echo ""
read -p "Proceed with release? (y/n): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}Release cancelled${NC}"
    git reset HEAD "$PACKAGE_DIR/composer.json" "$PACKAGE_DIR/CHANGELOG.md"
    exit 0
fi

# Commit changes
echo ""
echo -e "${BLUE}Committing version bump...${NC}"
git commit -m "chore: release v$NEW_VERSION"

# Create and push tag
echo -e "${BLUE}Creating tag v$NEW_VERSION...${NC}"
git tag -a "v$NEW_VERSION" -m "Release v$NEW_VERSION

$RELEASE_NOTES"

# Push changes
echo -e "${BLUE}Pushing to GitHub...${NC}"
git push origin master
git push origin "v$NEW_VERSION"

# Create GitHub release if gh CLI is available
if command -v gh &> /dev/null; then
    echo ""
    echo -e "${BLUE}Creating GitHub release...${NC}"
    
    # Create release using gh CLI
    gh release create "v$NEW_VERSION" \
        --title "v$NEW_VERSION" \
        --notes "$RELEASE_NOTES" \
        --verify-tag
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ GitHub release created successfully!${NC}"
    else
        echo -e "${YELLOW}⚠ Failed to create GitHub release. You can create it manually at:${NC}"
        echo "  https://github.com/vizra-ai/vizra-adk/releases/new?tag=v$NEW_VERSION"
    fi
else
    echo ""
    echo -e "${YELLOW}GitHub CLI (gh) not found. To create releases automatically, install it:${NC}"
    echo "  brew install gh  # macOS"
    echo "  # or see: https://cli.github.com/manual/installation"
fi

echo ""
echo -e "${GREEN}✅ Release v$NEW_VERSION completed successfully!${NC}"
echo ""
echo -e "${BLUE}Next steps:${NC}"
if ! command -v gh &> /dev/null || [ $? -ne 0 ]; then
    echo "1. Create GitHub release: https://github.com/vizra-ai/vizra-adk/releases/new?tag=v$NEW_VERSION"
fi
echo "2. Submit to Packagist (if not auto-synced): https://packagist.org/packages/vizra/vizra-adk"
echo "3. Update documentation if needed"
echo "4. Announce the release"