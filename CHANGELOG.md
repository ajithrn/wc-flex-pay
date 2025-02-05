# Changelog

## [1.6.6] - 2025-02-05
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
