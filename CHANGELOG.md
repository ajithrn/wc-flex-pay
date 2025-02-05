# Changelog

## [1.6.9] - 2025-02-05
### Fixed
- Email notifications for flex pay orders:
  - Send both order details and payment complete emails for initial payment
  - Send updated order details after each installment payment
  - Include complete payment schedule in all notifications

## [1.6.8] - 2025-02-05
### Changed
- Enhanced payment link expiry logic to use extended period for future installments
- Improved payment reminder logic to handle near-due payments

## [1.6.7] - 2025-02-05
### Changed
- Removed "Upcoming Payments" notice from cart and checkout pages
- Simplified cart subtotal display by removing next payment information

## [1.6.6] - 2025-02-04
### Added
- Admin notification system for payment failures and overdue payments
- Enhanced payment history tracking in email templates
- Login requirement validation for payment links

### Changed
- Improved sub-order status handling and parent order completion logic
- Enhanced email templates with better formatting and organization:
  - Added payment history section
  - Improved payment summary display
  - Better visual hierarchy with styled notices
- Removed duplicate installment notice at checkout
- Removed "Upcoming Payments" notice from cart and checkout pages

### Enhanced
- Payment completion workflow with better sub-order tracking
- Email template system with comprehensive payment details
- Payment link handling with proper user authentication

## [1.6.5] - 2025-02-01
### Fixed
- Improved order validation and installment handling
- Enhanced payment processing reliability

## [1.6.4] - 2025-01-31
### Fixed
- Updated transaction ID handling for initial flex pay payments
- Enhanced payment schedule deposit notice in frontend

## [1.6.3] - 2025-01-30
### Fixed
- Added type check in notification system to ensure order object is valid WC_Order instance
- Resolved validation issues when all flex pay dates are in the past
- Improved installment date validation and ordering

### Enhanced
- Admin UI with automatic sorting of installments by date
- Automatic updating of installment numbers based on date order

[Rest of changelog remains the same...]
