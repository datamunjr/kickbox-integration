#!/bin/bash

# Script to insert fake email verifications with various Kickbox results
# Usage: ./insert-fake-verifications.sh [number_of_verifications] [mysql_socket_path]

# Default number of verifications to insert
NUM_VERIFICATIONS=${1:-50}

# MySQL socket path (optional, for Flywheel Local, etc.)
MYSQL_SOCKET=${2:-""}

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}Inserting $NUM_VERIFICATIONS fake email verifications...${NC}"

# Get WordPress root directory (go up 4 levels from scripts directory)
WP_ROOT="$(cd "$(dirname "$0")/../../../../" && pwd)"
WP_CONFIG="$WP_ROOT/wp-config.php"

# Check if wp-config.php exists
if [ ! -f "$WP_CONFIG" ]; then
    echo -e "${RED}Error: wp-config.php not found at $WP_CONFIG${NC}"
    echo -e "${RED}Please ensure this script is in the correct scripts directory.${NC}"
    exit 1
fi

# Get WordPress database credentials
DB_NAME=$(grep "DB_NAME" "$WP_CONFIG" | cut -d"'" -f4)
DB_USER=$(grep "DB_USER" "$WP_CONFIG" | cut -d"'" -f4)
DB_PASSWORD=$(grep "DB_PASSWORD" "$WP_CONFIG" | cut -d"'" -f4)
DB_HOST=$(grep "DB_HOST" "$WP_CONFIG" | cut -d"'" -f4)

# Remove port from DB_HOST if present
DB_HOST=$(echo $DB_HOST | cut -d':' -f1)

# Set up MySQL connection parameters
if [ -n "$MYSQL_SOCKET" ]; then
    MYSQL_ARGS=("--user=$DB_USER" "--password=$DB_PASSWORD" "-S" "$MYSQL_SOCKET" "$DB_NAME")
    echo -e "${YELLOW}Connecting to database: $DB_NAME via socket: $MYSQL_SOCKET${NC}"
else
    MYSQL_ARGS=("-u$DB_USER" "-p$DB_PASSWORD" "-h$DB_HOST" "$DB_NAME")
    echo -e "${YELLOW}Connecting to database: $DB_NAME on $DB_HOST${NC}"
fi

# Arrays of fake data
DOMAINS=("example.com" "test.com" "demo.org" "sample.net" "fake.co" "mock.io" "dummy.biz" "temp.info")
USERS=("john" "jane" "bob" "alice" "charlie" "diana" "eve" "frank" "grace" "henry")
ORIGINS=("checkout" "registration" "contact" "newsletter")
VERIFICATION_ACTIONS=("review" "block")
ADMIN_DECISIONS=("pending" "allow" "block")

# Function to get reasons for a result type
get_reasons_for_result() {
    local result=$1
    case $result in
        "deliverable")
            echo "accepted_email"
            ;;
        "undeliverable")
            echo "invalid_email invalid_domain rejected_email"
            ;;
        "risky")
            echo "low_quality low_deliverability"
            ;;
        "unknown")
            echo "no_connect timeout invalid_smtp unavailable_smtp unexpected_error"
            ;;
        *)
            echo "unexpected_error"
            ;;
    esac
}

# Function to generate random email
generate_email() {
    local user=${USERS[$RANDOM % ${#USERS[@]}]}
    local domain=${DOMAINS[$RANDOM % ${#DOMAINS[@]}]}
    local random_num=$((RANDOM % 1000))
    echo "${user}${random_num}@${domain}"
}

# Function to generate random Kickbox result
generate_kickbox_result() {
    local results=("deliverable" "undeliverable" "risky" "unknown")
    local result=${results[$RANDOM % ${#results[@]}]}
    local reasons_string=$(get_reasons_for_result "$result")
    local reasons=($reasons_string)
    local reason=${reasons[$RANDOM % ${#reasons[@]}]}
    
    # Generate additional Kickbox data
    local sendex=$((RANDOM % 100))
    local role=$((RANDOM % 2))
    local free=$((RANDOM % 2))
    local disposable=$((RANDOM % 2))
    local accept_all=$((RANDOM % 2))
    
    echo "{\"result\":\"$result\",\"reason\":\"$reason\",\"sendex\":$sendex,\"role\":$role,\"free\":$free,\"disposable\":$disposable,\"accept_all\":$accept_all,\"domain\":\"$(echo $1 | cut -d'@' -f2)\",\"user\":\"$(echo $1 | cut -d'@' -f1)\"}"
}

# Function to insert verification log
insert_verification_log() {
    local email=$1
    local kickbox_result=$2
    local result=$(echo $kickbox_result | jq -r '.result')
    local user_id=$((RANDOM % 100 + 1))
    local order_id=$((RANDOM % 1000 + 1))
    local origin=${ORIGINS[$RANDOM % ${#ORIGINS[@]}]}
    local created_at=$(date -v-$((RANDOM % 30))d '+%Y-%m-%d %H:%M:%S')
    
    mysql "${MYSQL_ARGS[@]}" -e "
        INSERT INTO wp_kickbox_integration_verification_log 
        (email, verification_result, verification_data, user_id, order_id, origin, created_at) 
        VALUES ('$email', '$result', '$kickbox_result', $user_id, $order_id, '$origin', '$created_at');
    " 2>/dev/null
}

# Function to insert flagged email (only for some results)
insert_flagged_email() {
    local email=$1
    local kickbox_result=$2
    local result=$(echo $kickbox_result | jq -r '.result')
    local user_id=$((RANDOM % 100 + 1))
    local order_id=$((RANDOM % 1000 + 1))
    local origin=${ORIGINS[$RANDOM % ${#ORIGINS[@]}]}
    local verification_action=${VERIFICATION_ACTIONS[$RANDOM % ${#VERIFICATION_ACTIONS[@]}]}
    local admin_decision=${ADMIN_DECISIONS[$RANDOM % ${#ADMIN_DECISIONS[@]}]}
    local flagged_date=$(date -v-$((RANDOM % 30))d '+%Y-%m-%d %H:%M:%S')
    
    # Only flag some emails (about 30% chance)
    if [ $((RANDOM % 100)) -lt 30 ]; then
        mysql "${MYSQL_ARGS[@]}" -e "
            INSERT INTO wp_kickbox_integration_flagged_emails 
            (email, order_id, user_id, origin, kickbox_result, verification_action, admin_decision, flagged_date) 
            VALUES ('$email', $order_id, $user_id, '$origin', '$kickbox_result', '$verification_action', '$admin_decision', '$flagged_date');
        " 2>/dev/null
    fi
}

# Check if jq is installed
if ! command -v jq &> /dev/null; then
    echo -e "${RED}Error: jq is required but not installed. Please install jq first.${NC}"
    echo "On macOS: brew install jq"
    echo "On Ubuntu/Debian: sudo apt-get install jq"
    exit 1
fi

# Check if mysql client is installed
if ! command -v mysql &> /dev/null; then
    echo -e "${RED}Error: mysql client is required but not installed.${NC}"
    exit 1
fi

# Test database connection
echo -e "${YELLOW}Testing database connection...${NC}"
echo -e "${YELLOW}Command: mysql $MYSQL_CONNECTION -u\"$DB_USER\" -p\"[HIDDEN]\" \"$DB_NAME\" -e \"SELECT 1;\"${NC}"

# First, test if socket file exists
if [ -n "$MYSQL_SOCKET" ]; then
    if [ ! -S "$MYSQL_SOCKET" ]; then
        echo -e "${RED}Error: Socket file does not exist: $MYSQL_SOCKET${NC}"
        echo -e "${YELLOW}Please check if your Local app is running and the socket path is correct.${NC}"
        exit 1
    else
        echo -e "${GREEN}Socket file exists: $MYSQL_SOCKET${NC}"
    fi
fi

# Test connection with verbose output
echo "mysql ${MYSQL_ARGS[*]} -e \"SELECT 1;\""
mysql "${MYSQL_ARGS[@]}" -e "SELECT 1;" 2>&1
if [ $? -ne 0 ]; then
    echo -e "${RED}Error: Cannot connect to database.${NC}"
    echo -e "${YELLOW}Debug information:${NC}"
    echo -e "  Database: $DB_NAME"
    echo -e "  User: $DB_USER"
    echo -e "  Host: $DB_HOST"
    if [ -n "$MYSQL_SOCKET" ]; then
        echo -e "  Socket: $MYSQL_SOCKET"
    fi
    echo -e "${YELLOW}Please check:${NC}"
    echo -e "  1. Is your Local app running?"
    echo -e "  2. Are the database credentials correct in wp-config.php?"
    echo -e "  3. Is the socket path correct?"
    exit 1
fi

echo -e "${GREEN}Database connection successful!${NC}"

# Insert fake verifications
for ((i=1; i<=NUM_VERIFICATIONS; i++)); do
    email=$(generate_email)
    kickbox_result=$(generate_kickbox_result "$email")
    
    # Insert into verification log
    insert_verification_log "$email" "$kickbox_result"
    
    # Insert into flagged emails (some emails)
    insert_flagged_email "$email" "$kickbox_result"
    
    # Progress indicator
    if [ $((i % 10)) -eq 0 ]; then
        echo -e "${YELLOW}Progress: $i/$NUM_VERIFICATIONS${NC}"
    fi
done

echo -e "${GREEN}Successfully inserted $NUM_VERIFICATIONS fake email verifications!${NC}"

# Show summary
echo -e "${BLUE}Summary:${NC}"
mysql "${MYSQL_ARGS[@]}" -e "
    SELECT 
        verification_result, 
        COUNT(*) as count 
    FROM wp_kickbox_integration_verification_log 
    GROUP BY verification_result 
    ORDER BY count DESC;
" 2>/dev/null

echo -e "${BLUE}Flagged emails summary:${NC}"
mysql "${MYSQL_ARGS[@]}" -e "
    SELECT 
        admin_decision, 
        COUNT(*) as count 
    FROM wp_kickbox_integration_flagged_emails 
    GROUP BY admin_decision 
    ORDER BY count DESC;
" 2>/dev/null

echo -e "${GREEN}Done! Check your WordPress admin for the new verification data.${NC}"
