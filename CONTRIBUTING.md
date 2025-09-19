# Contributing to Pickleball Ratings

Thanks for your interest in contributing! This project follows standard open-source workflows.

- Code style: follow WordPress coding standards (PHP, JS/CSS) and keep edits focused.
- Issues: include steps to reproduce, expected/actual behavior, and environment details.
- Pull requests: small, well-scoped changes with a clear description and screenshots when UI changes.
- Security: do not open public issues for security reports; instead, contact the maintainer privately.
- Local dev: WordPress + this plugin; `npm install` then `npm run build` for assets.

## Development Setup

1. **Install dependencies:**
   ```bash
   npm install
   composer install
   ```

2. **Build assets:**
   ```bash
   npm run build
   ```

## Code Quality & Testing

### Coding Standards

- Follow WordPress Coding Standards (WPCS) for PHP, JavaScript, and CSS
- Use PHP 7.4+ features when appropriate
- Implement proper error handling and logging
- Use WordPress hooks (actions and filters) for extensibility
- Follow WordPress security best practices (nonces, sanitization, etc)
- Extra credit: scan the updated plugin with [PCP](https://wordpress.org/plugins/plugin-check/) to ensure it still meets the reqs for WordPress.org

### Linting and Formatting

This project uses multiple tools to ensure code quality:

#### PHP Linting & Formatting
- **PHPCS (PHP CodeSniffer) and WPCS:** Checks PHP code against PHPCS and WordPress Coding Standards
- **PHPCBF (PHP Code Beautifier and Fixer):** Auto-fixes PHP formatting issues

```bash
# Run PHP linting
npm run lint:php
# or directly:
composer run phpcs

# Auto-fix PHP formatting issues
npm run format:php
# or directly:
composer run phpcbf
```

If you don't have WPCS installed globally, you can install it via Composer, or use a local PHPCS binary in your environment.

#### JavaScript/CSS Linting
- **ESLint:** JavaScript linting using WordPress standards
- **Stylelint:** CSS linting using WordPress standards

```bash
# Run all linting (PHP, JS, CSS)
npm run lint

# Run individual linters
npm run lint:js
npm run lint:css
```

#### Formatting
```bash
# Format all code (PHP, JS, CSS)
npm run format
```

### Unit Testing

The project uses PHPUnit for unit testing with WordPress test utilities:

```bash
# Set up WordPress test environment (first time only)
npm run test:setup

# Run all tests
npm run test
```

### Pre-commit Checklist

Before committing your changes, ensure you've run:

1. **Linting:** `npm run lint`
2. **Formatting:** `npm run format` (if needed)
3. **Testing:** `npm run test`
4. **Build:** `npm run build` (if you modified assets)

## Releases

Here's a checklist to go through on every release:

- Before the release:
   - Bump the version numbers in:
      - `pickleball-ratings.php`
      - `readme.txt`
   - Confirm the metadata in `pickleball-ratings.php` is still up to date:
      - `Requires at least:`
      - `Requires PHP:`
   - Update the changelog in `readme.txt`, including a `== Upgrade Notice ==` remark if appropriate.
- Create a new tag and push it to the repo.
- Create a new release with the version of the plugin on GitHub. The title should be “Version x.y.z” and a summary of the changed content as the description.

Releases are finally approved via the [release management](https://wordpress.org/plugins/developers/releases/) page in the plugin directory.
