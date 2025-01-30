#!/bin/bash

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Function to print colored messages
print_message() {
    echo -e "${2}${1}${NC}"
}

# Function to wait for MySQL to be ready
wait_for_mysql() {
    print_message "Waiting for MySQL to be ready..." "$YELLOW"
    for i in {1..60}; do
        if docker compose exec db mysqladmin ping -h localhost -u root --password=root_password --silent > /dev/null 2>&1; then
            print_message "MySQL is ready!" "$GREEN"
            return 0
        fi
        echo -n "."
        sleep 2
    done
    print_message "\nMySQL did not become ready in time" "$RED"
    return 1
}

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    print_message "Docker is not running. Please start Docker first." "$RED"
    exit 1
fi

# Stop all running containers
print_message "Stopping any running containers..." "$YELLOW"
docker compose down

# Build and start containers
print_message "Building and starting containers..." "$YELLOW"
docker compose up -d --build

# Wait for MySQL to be ready
if ! wait_for_mysql; then
    print_message "Failed to connect to MySQL. Exiting..." "$RED"
    exit 1
fi

# Wait for WordPress container to be ready
print_message "Waiting for WordPress container..." "$YELLOW"
sleep 10

# Install WordPress
print_message "Setting up WordPress..." "$YELLOW"
docker compose exec wordpress bash -c "
    # Install WP-CLI
    if [ ! -f /usr/local/bin/wp ]; then
        curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
        chmod +x wp-cli.phar
        mv wp-cli.phar /usr/local/bin/wp
    fi

    # Check if WordPress is already installed
    if ! wp core is-installed --allow-root; then
        wp core install --allow-root \
            --url=http://localhost:8000 \
            --title='DeepBlogger Development' \
            --admin_user=admin \
            --admin_password=admin \
            --admin_email=admin@example.com
        
        # Activate debug mode
        wp config set WP_DEBUG true --raw --allow-root
        wp config set WP_DEBUG_LOG true --raw --allow-root
        wp config set WP_DEBUG_DISPLAY true --raw --allow-root
    fi

    # Ensure the plugin directory exists
    mkdir -p wp-content/plugins

    # Activate the plugin
    wp plugin activate deepblogger --allow-root
"

# Print success message
print_message "\nDevelopment environment is ready!" "$GREEN"
print_message "WordPress: http://localhost:8000" "$GREEN"
print_message "PHPMyAdmin: http://localhost:8080" "$GREEN"
print_message "MailHog: http://localhost:8025" "$GREEN"
print_message "\nWordPress Admin:" "$GREEN"
print_message "URL: http://localhost:8000/wp-admin" "$GREEN"
print_message "Username: admin" "$GREEN"
print_message "Password: admin" "$GREEN" 