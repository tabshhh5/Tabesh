# Contributing to Tabesh

Thank you for your interest in contributing to Tabesh! This document provides guidelines for contributing to the project.

## Code of Conduct

By participating in this project, you agree to maintain a respectful and inclusive environment for all contributors.

## How to Contribute

### Reporting Bugs

If you find a bug, please create an issue with:
- A clear, descriptive title
- Steps to reproduce the issue
- Expected behavior
- Actual behavior
- WordPress version, PHP version, and plugin version
- Screenshots (if applicable)

### Suggesting Features

Feature suggestions are welcome! Please create an issue with:
- A clear description of the feature
- Why it would be useful
- Any implementation ideas

### Pull Requests

1. **Fork the repository**
2. **Create a feature branch** (`git checkout -b feature/AmazingFeature`)
3. **Make your changes**
4. **Test thoroughly**
5. **Commit your changes** (`git commit -m 'Add some AmazingFeature'`)
6. **Push to the branch** (`git push origin feature/AmazingFeature`)
7. **Open a Pull Request**

### Coding Standards

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- Use meaningful variable and function names
- Comment your code where necessary
- Write secure code (sanitize inputs, escape outputs)
- Test on PHP 8.2.2+ and WordPress 6.8+

### Code Style

- **PHP**: Follow WordPress PHP Coding Standards
- **CSS**: Use BEM naming convention where applicable
- **JavaScript**: Use ES6+ features, modern JavaScript practices
- **Database**: Use WordPress $wpdb class, never direct SQL

### Commit Messages

Write clear, concise commit messages:
- Use the present tense ("Add feature" not "Added feature")
- Use the imperative mood ("Move cursor to..." not "Moves cursor to...")
- Reference issues and pull requests when applicable

### Testing

- Test all functionality before submitting PR
- Ensure compatibility with WordPress 6.8+
- Test on different PHP versions (8.2.2+)
- Check RTL layout rendering
- Verify responsive design on mobile devices

### Documentation

- Update README.md if adding new features
- Document new functions and classes
- Update inline code comments

## Project Structure

```
Tabesh/
├── assets/
│   ├── css/          # Stylesheets
│   └── js/           # JavaScript files
├── includes/         # PHP classes
├── templates/        # Template files
├── languages/        # Translation files
├── tabesh.php        # Main plugin file
└── README.md         # Documentation
```

## Development Setup

1. Install WordPress locally
2. Install WooCommerce
3. Clone this repository to `wp-content/plugins/`
4. Activate the plugin
5. Configure settings

## Security

- Never commit sensitive data (API keys, passwords)
- Always sanitize user input
- Always escape output
- Use WordPress nonces for form submissions
- Follow WordPress security best practices

## Questions?

If you have questions, feel free to:
- Create an issue for discussion
- Contact the maintainers

Thank you for contributing to Tabesh! 🎉
