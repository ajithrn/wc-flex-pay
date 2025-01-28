# Contributing to WC Flex Pay

Thank you for considering contributing to WC Flex Pay! This document outlines the guidelines and process for contributing to the project.

## Code of Conduct

By participating in this project, you agree to abide by our Code of Conduct. Please be respectful and constructive in your interactions with other contributors.

## How to Contribute

### Reporting Bugs

1. Check if the bug has already been reported in the [Issues](https://github.com/ajithrn/wc-flex-pay/issues)
2. If not, create a new issue with:
   - Clear title and description
   - Steps to reproduce
   - Expected vs actual behavior
   - WordPress and WooCommerce versions
   - Any relevant error messages or screenshots

### Suggesting Enhancements

1. Check existing [Issues](https://github.com/ajithrn/wc-flex-pay/issues) for similar suggestions
2. Create a new issue with:
   - Clear description of the enhancement
   - Use cases and benefits
   - Any potential implementation details
   - Mock-ups or examples if applicable

### Pull Requests

1. Fork the repository
2. Create a new branch from `main`:
   ```bash
   git checkout -b feature/your-feature-name
   ```
3. Make your changes following our coding standards
4. Write clear commit messages:
   ```
   type: Brief description of change

   - Detailed bullet points
   - of significant changes
   ```
   Types: feat, fix, docs, style, refactor, test, chore

5. Push to your fork and submit a pull request

## Development Setup

1. Clone the repository:
   ```bash
   git clone https://github.com/ajithrn/wc-flex-pay.git
   cd wc-flex-pay
   ```

2. Install dependencies:
   ```bash
   npm install
   ```

3. Set up WordPress development environment:
   - Install WordPress
   - Install WooCommerce
   - Symlink or copy plugin to wp-content/plugins/

4. Enable development mode in wp-config.php:
   ```php
   define('WCFP_TEMPLATE_DEBUG', true);
   ```

## Coding Standards

### PHP
- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- Use PHP 7.4+ features appropriately
- Document classes and methods with PHPDoc blocks

### JavaScript
- Use ES6+ features
- Follow ESLint configuration
- Document functions and complex logic

### SCSS
- Follow BEM methodology
- Use existing utility classes where possible
- Maintain modular structure

### Templates
- Follow WordPress template hierarchy
- Use proper escaping functions
- Support template overrides in themes

## Testing

### Manual Testing Checklist
- Test with latest WordPress and WooCommerce
- Test with different themes
- Verify responsive design
- Check email template rendering
- Test payment flows and notifications
- Verify template overrides
- Check responsive layouts
- Test email templates in various clients

## Documentation

- Update README.md for new features
- Document hooks and filters
- Update inline documentation
- Add changelog entries

## Release Process

1. Update version numbers:
   - wc-flex-pay.php
   - package.json
   - readme.txt

2. Update changelog:
   - Add version section
   - List all changes
   - Credit contributors

3. Create release PR:
   - Update documentation
   - Run all tests
   - Review changes

4. After merge:
   - Tag release
   - Create GitHub release
   - Update WordPress.org

## Questions?

Feel free to reach out if you have questions about contributing. We're here to help!

## License

By contributing to WC Flex Pay, you agree that your contributions will be licensed under the GPL-2.0+ license.
