# Contributing to Pickleball Ratings

Thanks for your interest in contributing! This project follows standard open-source workflows.

- Code style: follow WordPress coding standards (PHP, JS/CSS) and keep edits focused.
- Issues: include steps to reproduce, expected/actual behavior, and environment details.
- Pull requests: small, well-scoped changes with a clear description and screenshots when UI changes.
- Security: do not open public issues for security reports; instead, contact the maintainer privately.
- Local dev: WordPress + this plugin; `npm install` then `npm run build` for assets.

## Coding standards

- Follow WordPress Coding Standards (WPCS) for PHP, JS, and CSS.
- Run PHPCS before committing changes:

```
./vendor/bin/phpcs --standard=.phpcs.xml.dist
```

If you don’t have WPCS installed globally, you can install it via Composer, or use a local PHPCS binary in your environment.

