# WC Flex Pay

Enable flexible payment schedules for WooCommerce products with scheduled partial payments. Allow customers to purchase products through installment plans while maintaining full control over payment schedules and tracking.

![WC Flex Pay](assets/images/banner.png)

## 🚀 Features

### Payment Management
- **Custom Payment Schedules**: Create flexible payment plans per product
- **Installment Control**: Set custom amounts and due dates for each payment
- **Payment Tracking**: Monitor payment status and history
- **Sub-order System**: Individual orders for each installment
- **Status Management**: Track orders with specialized statuses:
  - Flex Pay Pending
  - Flex Pay Partial
  - Flex Pay Overdue
  - Flex Pay Completed
  - Flex Pay Failed

### Admin Features
- **Product Integration**: Enable/disable flex pay per product
- **Schedule Management**: Visual interface for payment schedule creation
- **Order Dashboard**: Track and manage flex pay orders with sub-orders
- **Payment History**: Enhanced view of complete payment records
- **Visual Timeline**: See payment schedules and progress at a glance
- **Sub-order Management**: Create and manage individual installment orders

### Customer Experience
- **Clear Schedules**: Visual payment timeline with installment tracking
- **Cart Integration**: Detailed installment breakdown during checkout
- **Status Tracking**: Monitor payment progress and upcoming payments
- **Payment Links**: Receive unique payment links for each installment
- **Email Updates**: Automated notifications for payments

### Notification System
- **Automated Emails** for:
  - Payment completion
  - Payment failures
  - Payment reminders
  - Overdue payments
  - Upcoming installments
  - Payment links
- **Customizable Templates**: Modify email content and styling
- **Status-Based Triggers**: Automatic notifications based on payment status

## 📋 Requirements

- WordPress 5.8 or higher
- WooCommerce 7.0 or higher
- PHP 7.4 or higher

## 💽 Installation

1. Upload the plugin files to `/wp-content/plugins/wc-flex-pay`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Ensure WooCommerce is installed and activated

## 🔧 Configuration

### Setting Up Payment Schedules

1. Edit any product in WooCommerce
2. Navigate to the "Flex Pay" tab in product data
3. Enable Flex Pay for the product
4. Add payment schedule:
   - Set installment amounts
   - Configure due dates
   - Review total calculation
5. Save the product

### Managing Orders

1. Access WooCommerce orders
2. View flex pay orders with custom statuses
3. Track payment progress
4. Process payments manually if needed
5. Create sub-orders for installments
6. Send payment reminders and links

## 🎨 Development

### Project Structure
```
wc-flex-pay/
├── assets/
│   ├── css/                  # Compiled CSS
│   ├── js/                   # JavaScript files
│   └── scss/                 # SCSS source files
│       ├── abstracts/        # Variables & utilities
│       ├── admin/           # Admin styles
│       ├── components/      # Shared components
│       └── frontend/        # Customer-facing styles
├── includes/
│   ├── admin/              # Admin classes
│   ├── emails/             # Email notification classes
│   └── core classes        # Core functionality
├── languages/             # Translation files
└── templates/             # Template files
```

### Styling System

#### SCSS Architecture
- Modular SCSS files
- Component-based structure
- Shared variables and utilities
- Responsive design patterns
- Table component system
- Status badge system

#### Utility Classes
```html
<!-- Margin utilities -->
m-[0-8]    <!-- All sides -->
mt-[0-8]   <!-- Top -->
mb-[0-8]   <!-- Bottom -->
ml-[0-8]   <!-- Left -->
mr-[0-8]   <!-- Right -->
mx-[0-8]   <!-- Left & Right -->
my-[0-8]   <!-- Top & Bottom -->

<!-- Padding utilities -->
p-[0-8]    <!-- All sides -->
pt-[0-8]   <!-- Top -->
pb-[0-8]   <!-- Bottom -->
pl-[0-8]   <!-- Left -->
pr-[0-8]   <!-- Right -->
px-[0-8]   <!-- Left & Right -->
py-[0-8]   <!-- Top & Bottom -->
```

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

### Data Storage

The plugin uses WordPress post meta for data storage:
- `_wcfp_payments`: Payment and installment data
- `_wcfp_payment_status`: Payment status tracking
- `_wcfp_payment_logs`: Payment activity logs
- `_wcfp_parent_order`: Sub-order parent reference
- `_wcfp_installment_number`: Sub-order installment number

## 🔌 Hooks and Filters

Documentation for available hooks and filters coming soon.

## 🔒 Security

- WordPress nonce verification
- Data sanitization and validation
- Capability checks
- Direct file access prevention
- Secure payment processing
- Sub-order access control

## 📝 Changelog

### [1.3.0] - 2025-01-23
- Added sub-order system for installments
- Enhanced payment tracking and management
- Improved admin interface and responsive design
- Added payment link generation system

[View full changelog](CHANGELOG.md)

## 🆘 Support

For support, please visit [https://kwirx.com/support](https://kwirx.com/support)

## 📄 License

GPL-2.0+

## 👨‍💻 Contributors

- Ajith R N ([@ajith](https://github.com/ajith))

## 🤝 Contributing

Contributions are welcome! Please read our [contributing guidelines](CONTRIBUTING.md) to get started.
