# Changelog

## [1.2.0] - 2025-01-22
### Changed
- Switched payment data storage from custom tables to WordPress post meta
- Improved regular price synchronization with installment amounts in admin
- Removed database tables in favor of meta-based storage
- Updated admin UI to use meta-based data storage
- Enhanced performance by using native WordPress data structures

## [1.1.1] - 2024-01-25
### Changed
- Improved payment schedule display in cart
- Enhanced readability of payment dates and amounts
- Updated spacing and formatting in cart display
- Optimized payment information layout for better user experience

## [1.1.0] - 2024-01-24
### Added
- Initial release of WC Flex Pay
- Flexible payment schedules for WooCommerce products
- Custom payment schedule management in product admin
- Payment tracking and management system
- Email notifications for upcoming and overdue payments
- Admin dashboard for payment schedule management
- WooCommerce cart and checkout integration
- SCSS-based styling system with utility classes
- Margin and padding utility classes (m-0 through m-8, p-0 through p-8)
- Database table for payment schedules
- Automated email notifications system
- Order management integration
- Multi-language support
- Debug logging system

### Security
- WordPress nonce verification for forms
- Data sanitization and validation
- Proper capability checks for admin functions
- Direct file access prevention
