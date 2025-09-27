#!/usr/bin/env bash

if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation] [woocommerce-version]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}
SKIP_DB_CREATE=${6-false}
WC_VERSION=${7-}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo $TMPDIR | sed -e "s/\/$//")
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress}

download() {
    if [ `which curl` ]; then
        curl -s "$1" > "$2";
    elif [ `which wget` ]; then
        wget -nv -O "$2" "$1"
    else
        echo "Error: Neither curl nor wget is installed."
        exit 1
    fi
}

# Check if svn is installed
check_svn_installed() {
    if ! command -v svn > /dev/null; then
        echo "Error: svn is not installed. Please install svn and try again."
        exit 1
    fi
}

if [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+\-(beta|RC)[0-9]+$ ]]; then
	WP_BRANCH=${WP_VERSION%\-*}
	WP_TESTS_TAG="branches/$WP_BRANCH"

elif [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+$ ]]; then
	WP_TESTS_TAG="branches/$WP_VERSION"
elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0-9]+ ]]; then
	if [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0] ]]; then
		# version x.x.0 means the first release of the major version, so strip off the .0 and download version x.x
		WP_TESTS_TAG="tags/${WP_VERSION%??}"
	else
		WP_TESTS_TAG="tags/$WP_VERSION"
	fi
elif [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
	WP_TESTS_TAG="trunk"
else
	# http serves a single offer, whereas https serves multiple. we only want one
	download http://api.wordpress.org/core/version-check/1.7/ /tmp/wp-latest.json
	grep '[0-9]+\.[0-9]+(\.[0-9]+)?' /tmp/wp-latest.json
	LATEST_VERSION=$(grep -o '"version":"[^"]*' /tmp/wp-latest.json | sed 's/"version":"//')
	if [[ -z "$LATEST_VERSION" ]]; then
		echo "Latest WordPress version could not be found"
		exit 1
	fi
	WP_TESTS_TAG="tags/$LATEST_VERSION"
fi
set -ex

install_wp() {

	if [ -d $WP_CORE_DIR ]; then
		return;
	fi

	mkdir -p $WP_CORE_DIR

	if [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
		mkdir -p $TMPDIR/wordpress-trunk
		rm -rf $TMPDIR/wordpress-trunk/*
        check_svn_installed
		svn export --quiet https://core.svn.wordpress.org/trunk $TMPDIR/wordpress-trunk/wordpress
		mv $TMPDIR/wordpress-trunk/wordpress/* $WP_CORE_DIR
	else
		if [ $WP_VERSION == 'latest' ]; then
			local ARCHIVE_NAME='latest'
		elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+ ]]; then
			# https serves multiple offers, whereas http serves single.
			download https://api.wordpress.org/core/version-check/1.7/ $TMPDIR/wp-latest.json
			if [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0] ]]; then
				# version x.x.0 means the first release of the major version, so strip off the .0 and download version x.x
				LATEST_VERSION=${WP_VERSION%??}
			else
				# otherwise, scan the releases and get the most up to date minor version of the major release
				local VERSION_ESCAPED=`echo $WP_VERSION | sed 's/\./\\\\./g'`
				LATEST_VERSION=$(grep -o '"version":"'$VERSION_ESCAPED'[^"]*' $TMPDIR/wp-latest.json | sed 's/"version":"//' | head -1)
			fi
			if [[ -z "$LATEST_VERSION" ]]; then
				local ARCHIVE_NAME="wordpress-$WP_VERSION"
			else
				local ARCHIVE_NAME="wordpress-$LATEST_VERSION"
			fi
		else
			local ARCHIVE_NAME="wordpress-$WP_VERSION"
		fi
		download https://wordpress.org/${ARCHIVE_NAME}.tar.gz  $TMPDIR/wordpress.tar.gz
		tar --strip-components=1 -zxmf $TMPDIR/wordpress.tar.gz -C $WP_CORE_DIR
	fi

	download https://raw.githubusercontent.com/markoheijnen/wp-mysqli/master/db.php $WP_CORE_DIR/wp-content/db.php
}

install_test_suite() {
	# portable in-place argument for both GNU sed and Mac OSX sed
	if [[ $(uname -s) == 'Darwin' ]]; then
		local ioption='-i.bak'
	else
		local ioption='-i'
	fi

	# set up testing suite if it doesn't yet exist
	if [ ! -d $WP_TESTS_DIR ]; then
		# set up testing suite
		mkdir -p $WP_TESTS_DIR
		rm -rf $WP_TESTS_DIR/{includes,data}
        check_svn_installed
		svn export --quiet --ignore-externals https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/ $WP_TESTS_DIR/includes
		svn export --quiet --ignore-externals https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/ $WP_TESTS_DIR/data
	fi

	if [ ! -f wp-tests-config.php ]; then
		download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php "$WP_TESTS_DIR"/wp-tests-config.php
		# remove all forward slashes in the end
		WP_CORE_DIR=$(echo $WP_CORE_DIR | sed "s:/\+$::")
		sed $ioption "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR/':" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s:__DIR__ . '/src/':'$WP_CORE_DIR/':" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR"/wp-tests-config.php
	fi

}

recreate_db() {
	shopt -s nocasematch
	if [[ $1 =~ ^(y|yes)$ ]]
	then
		mysqladmin drop $DB_NAME -f --user="$DB_USER" --password="$DB_PASS"$EXTRA
		create_db
		echo "Recreated the database ($DB_NAME)."
	else
		echo "Leaving the existing database ($DB_NAME) in place."
	fi
	shopt -u nocasematch
}

create_db() {
	mysqladmin create $DB_NAME --user="$DB_USER" --password="$DB_PASS"$EXTRA
}

install_db() {

	if [ ${SKIP_DB_CREATE} = "true" ]; then
		return 0
	fi

	# parse DB_HOST for port or socket references
	local PARTS=(${DB_HOST//\:/ })
	local DB_HOSTNAME=${PARTS[0]};
	local DB_SOCK_OR_PORT=${PARTS[1]};
	local EXTRA=""

	if ! [ -z $DB_HOSTNAME ] ; then
		if [ $(echo $DB_SOCK_OR_PORT | grep -e '^[0-9]\{1,\}$') ]; then
			EXTRA=" --host=$DB_HOSTNAME --port=$DB_SOCK_OR_PORT --protocol=tcp"
		elif ! [ -z $DB_SOCK_OR_PORT ] ; then
			EXTRA=" --socket=$DB_SOCK_OR_PORT"
		elif ! [ -z $DB_HOSTNAME ] ; then
			EXTRA=" --host=$DB_HOSTNAME --protocol=tcp"
		fi
	fi

	# create database
	if [ $(mysql --user="$DB_USER" --password="$DB_PASS"$EXTRA --execute='show databases;' | grep ^$DB_NAME$) ]
	then
		echo "Reinstalling will delete the existing test database ($DB_NAME)"
		read -p 'Are you sure you want to proceed? [y/N]: ' DELETE_EXISTING_DB
		recreate_db $DELETE_EXISTING_DB
	else
		create_db
	fi
}

create_wp_config() {
	echo "Creating WordPress configuration..."

	# Check if WP-CLI is available
	if ! command -v wp &> /dev/null; then
		echo "WP-CLI not found. Please install WP-CLI to use WordPress configuration creation."
		echo "Visit: https://wp-cli.org/#installing"
		return 1
	fi

	# Create wp-config.php using WP-CLI
	cd "$WP_CORE_DIR"
	wp config create --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASS" --dbhost="$DB_HOST" --allow-root
	cd - > /dev/null
	
	echo "WordPress configuration created successfully"
}

install_wp_core() {
	echo "Installing WordPress core..."

	# Check if WP-CLI is available
	if ! command -v wp &> /dev/null; then
		echo "WP-CLI not found. Please install WP-CLI to use WordPress core installation."
		echo "Visit: https://wp-cli.org/#installing"
		return 1
	fi

	# Install WordPress core using WP-CLI
	cd "$WP_CORE_DIR"
	wp core install --url="http://example.org" --title="Test Site" --admin_user="admin" --admin_password="password" --admin_email="admin@example.org" --skip-email --allow-root
	cd - > /dev/null
	
	echo "WordPress core installed successfully"
}

install_woocommerce() {
	if [ -z "$WC_VERSION" ]; then
		echo "Skipping WooCommerce installation (no version specified)"
		return
	fi

	echo "Installing WooCommerce version $WC_VERSION..."

	# Check if WP-CLI is available
	if ! command -v wp &> /dev/null; then
		echo "WP-CLI not found. Please install WP-CLI to use WooCommerce installation."
		echo "Visit: https://wp-cli.org/#installing"
		return 1
	fi

	# Install WordPress core and WooCommerce using WP-CLI
	cd "$WP_CORE_DIR"
	
	# Install WooCommerce
	if [[ $WC_VERSION == 'latest' ]]; then
		wp plugin install woocommerce --activate --allow-root
	else
		# Try the exact version first, then try with .0 suffix if it fails
		if ! wp plugin install woocommerce --version="$WC_VERSION" --activate --allow-root 2>/dev/null; then
			echo "Version $WC_VERSION not found, trying ${WC_VERSION}.0..."
			if ! wp plugin install woocommerce --version="${WC_VERSION}.0" --activate --allow-root 2>/dev/null; then
				echo "Version ${WC_VERSION}.0 not found, installing latest version..."
				wp plugin install woocommerce --activate --allow-root
			fi
		fi
	fi
	
	cd - > /dev/null
	
	echo "WooCommerce $WC_VERSION installed successfully"
}

update_woocommerce_database() {
	if [ -z "$WC_VERSION" ]; then
		return
	fi

	echo "Updating WooCommerce database schema..."

	# Check if WP-CLI is available
	if ! command -v wp &> /dev/null; then
		echo "WP-CLI not found. Skipping WooCommerce database update."
		return 1
	fi

	cd "$WP_CORE_DIR"

	# Run WooCommerce database updates
	wp wc update --allow-root
	
	cd - > /dev/null
	
	echo "WooCommerce database schema updated successfully"
}

install_woocommerce_test_data() {
	if [ -z "$WC_VERSION" ]; then
		return
	fi

	echo "Setting up WooCommerce test data..."

	# Check if WP-CLI is available
	if ! command -v wp &> /dev/null; then
		echo "WP-CLI not found. Skipping WooCommerce test data setup."
		return 1
	fi

	cd "$WP_CORE_DIR"

	# Set up basic WooCommerce settings using WP-CLI
	wp option update woocommerce_default_country 'US:CA' --allow-root
	wp option update woocommerce_currency 'USD' --allow-root
	wp option update woocommerce_currency_pos 'left' --allow-root
	wp option update woocommerce_price_thousand_sep ',' --allow-root
	wp option update woocommerce_price_decimal_sep '.' --allow-root
	wp option update woocommerce_price_num_decimals '2' --allow-root
	wp option update woocommerce_enable_guest_checkout 'yes' --allow-root
	wp option update woocommerce_enable_checkout_login_reminder 'no' --allow-root

	# Create WooCommerce pages
	wp post create --post_type=page --post_title='Shop' --post_status=publish --allow-root
	wp post create --post_type=page --post_title='Cart' --post_content='[woocommerce_cart]' --post_status=publish --allow-root
	wp post create --post_type=page --post_title='Checkout' --post_content='[woocommerce_checkout]' --post_status=publish --allow-root
	wp post create --post_type=page --post_title='My Account' --post_content='[woocommerce_my_account]' --post_status=publish --allow-root

	# Create a simple test product using WP-CLI
	wp post create --post_type=product --post_title='Test Product' --post_content='This is a test product for WooCommerce testing.' --post_status=publish --allow-root

	# Get the product ID and set meta data
	PRODUCT_ID=$(wp post list --post_type=product --post_title='Test Product' --format=ids --allow-root | head -1)
	
	if [ ! -z "$PRODUCT_ID" ]; then
		wp post meta set "$PRODUCT_ID" _price '19.99' --allow-root
		wp post meta set "$PRODUCT_ID" _regular_price '19.99' --allow-root
		wp post meta set "$PRODUCT_ID" _manage_stock 'yes' --allow-root
		wp post meta set "$PRODUCT_ID" _stock '100' --allow-root
		wp post meta set "$PRODUCT_ID" _stock_status 'instock' --allow-root
		wp post meta set "$PRODUCT_ID" _visibility 'visible' --allow-root
		wp post meta set "$PRODUCT_ID" _featured 'no' --allow-root
		wp post meta set "$PRODUCT_ID" _virtual 'no' --allow-root
		wp post meta set "$PRODUCT_ID" _downloadable 'no' --allow-root
		wp post meta set "$PRODUCT_ID" _sku 'TEST-PRODUCT-001' --allow-root
		wp post meta set "$PRODUCT_ID" _weight '1.5' --allow-root
		wp post meta set "$PRODUCT_ID" _length '10' --allow-root
		wp post meta set "$PRODUCT_ID" _width '5' --allow-root
		wp post meta set "$PRODUCT_ID" _height '3' --allow-root
		
		echo "Test product created with ID: $PRODUCT_ID"
	else
		echo "Failed to create test product"
	fi

	cd - > /dev/null
	
	echo "WooCommerce test data setup complete"
}

install_wp
install_test_suite
install_db
create_wp_config
install_wp_core
install_woocommerce
update_woocommerce_database
install_woocommerce_test_data
