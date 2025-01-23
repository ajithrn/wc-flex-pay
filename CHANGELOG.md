# Changelog

## [1.3.0] - 2025-01-23
### Added
- Sub-order system for individual installment management
- Payment link generation for each installment
- Enhanced payment tracking with detailed status updates
- New table component system for consistent data display
- Responsive design improvements across admin and frontend

### Changed
- Switched from schedule-based to installment-based payment system
- Improved payment history interface with better organization
- Enhanced status badge system with new visual indicators
- Updated payment schedule display with better visualization
- Refined cart and order displays for better user experience

### Enhanced
- Admin interface with new sub-order management
- Order list with Flex Pay information column
- Payment tracking with detailed installment status
- Frontend payment selection interface
- Email notification system for payment reminders

## [1.2.1] - 2025-01-22
### Added
- New order statuses for better payment tracking:
  - wc-flex-pay-pending: Flex Pay Pending
  - wc-flex-pay-partial: Flex Pay Partial
  - wc-flex-pay-overdue: Flex Pay Overdue
  - wc-flex-pay-completed: Flex Pay Completed
  - wc-flex-pay-failed: Flex Pay Failed

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
