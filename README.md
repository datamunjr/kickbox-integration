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

- WordPress 5.0 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- Valid Kickbox API key

## Installation

1. Clone or download this repository
2. Install dependencies: `npm install`
3. Build the assets: `npm run build`
4. Upload the plugin to your WordPress site
5. Activate the plugin
6. Configure your Kickbox API key in WooCommerce > Kickbox Integration

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
wckb/
├── wckb.php                          # Main plugin file
├── includes/                         # PHP classes
│   ├── class-wckb-verification.php   # Core verification logic
│   ├── class-wckb-admin.php          # Admin functionality
│   ├── class-wckb-checkout.php       # Checkout integration
│   └── class-wckb-customer-management.php # Customer management
├── src/                              # Source files
│   ├── js/                           # JavaScript/React components
│   │   ├── admin/                    # Admin interface
│   │   ├── checkout/                 # Checkout verification
│   │   └── customer/                 # Customer management
│   └── css/                          # Stylesheets
├── assets/                           # Built assets (generated)
├── webpack.config.js                 # Webpack configuration
└── package.json                      # Dependencies
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
2. Go to WooCommerce > Kickbox Integration
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
