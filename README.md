# WC Flex Pay

Enable flexible payment schedules for WooCommerce products with scheduled partial payments.

## Features

- **Flexible Payment Schedules**: Set up custom payment schedules for products with multiple installments
- **Payment Tracking**: Track and manage partial payments for orders
- **Automated Notifications**: Email notifications for upcoming and overdue payments
- **Admin Dashboard**: Comprehensive dashboard to manage payment schedules and track payments
- **WooCommerce Integration**: Seamlessly integrates with WooCommerce cart, checkout, and order management

## Requirements

- WordPress 5.8 or higher
- WooCommerce 7.0 or higher
- PHP 7.4 or higher

## Installation

1. Upload the plugin files to `/wp-content/plugins/wc-flex-pay`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. WooCommerce must be installed and activated

## Usage

### Setting Up Payment Schedules

1. Edit any product in WooCommerce
2. Enable Flex Pay in the product data panel
3. Add payment schedule with amounts and due dates
4. Save the product

### Managing Orders

- View payment schedules in order details
- Track payment status for each installment
- Process payments manually or automatically
- Send payment reminders to customers

## Development

### Project Structure

```
wc-flex-pay/
├── assets/
│   ├── css/                  # Compiled CSS files
│   ├── js/                   # JavaScript files
│   └── scss/                 # SCSS source files
│       ├── abstracts/
│       │   ├── _variables.scss   # Variables
│       │   └── _utilities.scss   # Utility classes
│       ├── admin/           # Admin styles
│       ├── components/      # Shared components
│       ├── admin.scss      # Main admin stylesheet
│       └── frontend.scss   # Main frontend stylesheet
├── includes/               # PHP classes
├── languages/             # Translation files
└── templates/             # Template files
```

### Styling System

#### SCSS Organization

The plugin uses SCSS for styling, organized into:
- **Abstracts**: Variables, mixins, and utility classes
- **Components**: Reusable component styles
- **Admin**: Admin-specific styles
- **Frontend**: Customer-facing styles

#### Utility Classes

Spacing utilities are available for quick styling:

```html
<!-- Margin utilities (0-32px, increments of 4) -->
m-[0-8]    <!-- All sides -->
mt-[0-8]   <!-- Top -->
mb-[0-8]   <!-- Bottom -->
ml-[0-8]   <!-- Left -->
mr-[0-8]   <!-- Right -->
mx-[0-8]   <!-- Left & Right -->
my-[0-8]   <!-- Top & Bottom -->

<!-- Padding utilities (0-32px, increments of 4) -->
p-[0-8]    <!-- All sides -->
pt-[0-8]   <!-- Top -->
pb-[0-8]   <!-- Bottom -->
pl-[0-8]   <!-- Left -->
pr-[0-8]   <!-- Right -->
px-[0-8]   <!-- Left & Right -->
py-[0-8]   <!-- Top & Bottom -->
```

Values correspond to pixels:
- 1 = 4px
- 2 = 8px
- 3 = 12px
- 4 = 16px
- 5 = 20px
- 6 = 24px
- 7 = 28px
- 8 = 32px

### Development Workflow

1. Install dependencies:
```bash
npm install
```

2. Compile SCSS:
```bash
npm run sass
```

3. Watch for changes:
```bash
npm run watch
```

### Database Tables

The plugin creates custom tables for payment schedules:
- `{prefix}wcfp_payment_schedules`: Stores product payment schedules

### Hooks and Filters

Documentation for available hooks and filters coming soon.

## Support

For support, please visit [https://kwirx.com/support](https://kwirx.com/support)

## License

GPL-2.0+
