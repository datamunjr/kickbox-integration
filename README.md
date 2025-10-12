# Kickbox Integration

A WordPress plugin that integrates Kickbox email verification service with WooCommerce for real-time email validation during checkout and batch verification for existing customers.

## Features

- **Real-time Email Verification**: Verify customer emails during checkout process
- **Batch Verification**: Verify existing customer emails in bulk
- **Flexible Action Configuration**: Configure different actions for different verification results
- **Admin Dashboard**: Comprehensive settings page with statistics
- **Customer Management Integration**: View verification status in user management
- **React-powered Interface**: Modern, responsive admin interface
- **Sandbox Mode**: Test the integration without using credits

## Requirements

- WordPress 6.2 or higher
- WooCommerce 10.2 or higher
- PHP 7.4 or higher
- Valid Kickbox API key

## Installation

1. Clone or download this repository
2. Install dependencies: `npm install`
3. Build the assets: `npm run build`
4. Upload the plugin to your WordPress site
5. Activate the plugin
6. Configure your Kickbox API key in WooCommerce > Settings > Kickbox

## Development

### Setup

```bash
npm install
```

### Build

```bash
# Production build
npm run build

# Development build with watch
npm run dev
```

### Project Structure

```
kickbox-integration/
├── kickbox-integration.php                        # Main plugin file & Kickbox_Integration class
├── includes/                                      # PHP classes
│   ├── class-kickbox-integration.php              # Main plugin class (singleton)
│   ├── class-kickbox-integration-installer.php    # Installation & database setup
│   ├── class-kickbox-integration-verification.php # Core verification logic
│   ├── class-kickbox-integration-admin.php        # Admin functionality
│   ├── class-kickbox-integration-checkout.php     # Checkout integration
│   ├── class-kickbox-integration-registration.php # Registration integration
│   ├── class-kickbox-integration-dashboard-widget.php # Dashboard widget
│   └── class-kickbox-integration-flagged-emails.php   # Flagged emails management
├── src/                                           # Source files
│   ├── js/                                        # JavaScript/React components
│   │   ├── admin/                                 # Admin interface
│   │   ├── checkout/                              # Checkout verification
│   │   └── dashboard/                             # Dashboard widget
│   └── scss/                                      # Stylesheets
├── assets/                                        # Built assets (generated)
│   ├── css/                                       # Compiled CSS
│   └── js/                                        # Compiled JavaScript
├── tests/                                         # PHPUnit tests
│   ├── bootstrap.php                              # Test bootstrap
│   ├── Test_Kickbox_Setup.php                     # Setup tests
│   └── Test_Kickbox_Integration.php               # Main class tests
├── bin/                                           # Utility scripts
│   └── install-wp-tests.sh                        # WordPress test suite installer
├── webpack.config.js                              # Webpack configuration
├── package.json                                   # Node.js dependencies
├── composer.json                                  # PHP dependencies
└── phpunit.xml.dist                               # PHPUnit configuration
```

## Configuration

### Admin Settings Interface

The admin settings page features a tabbed interface with URL parameter support:

- **API Settings** (`?tab=api`) - Configure your Kickbox API key and basic settings (default tab)
- **Verification Actions** (`?tab=actions`) - Set actions for different verification results
- **Statistics** (`?tab=stats`) - View verification statistics and analytics

When you first visit the settings page, it automatically defaults to the API Settings tab and adds
`?tab=api` to the URL. You can bookmark and share direct links to specific tabs, and the interface supports browser back/forward navigation.

### API Settings

1. Get your Kickbox API key from [kickbox.com](https://kickbox.com)
2. Go to WooCommerce > Settings > Kickbox
3. Enter your API key
4. Enable sandbox mode for testing
5. Test the API connection

### Verification Actions

Configure what action to take for each verification result:

- **Deliverable**: Emails confirmed to be valid
- **Undeliverable**: Emails confirmed to be invalid
- **Risky**: Emails that may be suspicious
- **Unknown**: Emails that couldn't be verified

Actions available:

- Allow checkout
- Block checkout
- Allow but flag for review

## Usage

### Checkout Verification

When enabled, the plugin will automatically verify customer emails during checkout. The verification happens in real-time as the customer enters their email address.

### Batch Verification

1. Go to Users in WordPress admin
2. Select the users you want to verify
3. Choose "Verify Emails" from the bulk actions dropdown
4. Click "Apply"

### Customer Management

The plugin adds a "Verified" column to the users table showing the verification status of each customer's email address.

## API Integration

The plugin integrates with Kickbox's API endpoints:

- Single verification: `GET https://api.kickbox.com/v2/verify`
- Batch verification: `PUT https://api.kickbox.com/v2/verify-batch`

## Plugin Architecture

### Main Classes

#### `Kickbox_Integration` (Singleton)
The main plugin class that manages all components and dependencies.

**Access the instance:**
```php
$kickbox = KICKBOX();
```

**Available components:**
```php
$kickbox->verification      // Kickbox_Integration_Verification
$kickbox->admin             // Kickbox_Integration_Admin
$kickbox->checkout          // Kickbox_Integration_Checkout
$kickbox->registration      // Kickbox_Integration_Registration
$kickbox->dashboard_widget  // Kickbox_Integration_Dashboard_Widget
$kickbox->flagged_emails    // Kickbox_Integration_Flagged_Emails
```

#### `Kickbox_Integration_Installer`
Handles plugin installation, database table creation, and default option setup.

**Static methods:**
```php
Kickbox_Integration_Installer::install()              // Run full installation
Kickbox_Integration_Installer::create_tables()         // Create database tables
Kickbox_Integration_Installer::add_performance_indexes() // Add database indexes
Kickbox_Integration_Installer::set_default_options()   // Set default options
```

### Dependency Checking

```php
// Check if all dependencies are met
if ( Kickbox_Integration::check_dependencies() ) {
    // WordPress and WooCommerce requirements met
}

// Check if WooCommerce is active
if ( Kickbox_Integration::is_woocommerce_active() ) {
    // WooCommerce is active and WC_VERSION is defined
}
```

## Error Handling

The plugin includes comprehensive error handling:

- API connection failures
- Invalid API keys
- Rate limiting
- Network timeouts

## Security

- All API calls are made server-side
- API keys are stored securely in WordPress options
- Nonce verification for all AJAX requests
- Input sanitization and validation

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

GPL v2 or later

## Support

For support, please visit the plugin support forum or contact the plugin author.
