# Pickleball Ratings WordPress Plugin

A WordPress plugin that provides a custom Gutenberg block to display DUPR (Dynamic Universal Pickleball Rating) information for pickleball players using a customizable block. Uses the official DUPR API; not affiliated with DUPR.

## Phase 1 Status

This is Phase 1 of the plugin development, which includes:
- ✅ Basic plugin structure and Gutenberg block registration
- ✅ Input field for 6-digit DUPR codes with validation
- ✅ Static placeholder data display
- ✅ Basic error handling for invalid codes
- ✅ Responsive design
- ✅ WordPress coding standards compliance

## Development Setup

1. Clone the repository
2. Install dependencies:
   ```bash
   npm install
   ```
3. Build the plugin:
   ```bash
   npm run build
   ```
4. Activate the plugin in WordPress

## Development Commands

- `npm run build` - Build the plugin for production
- `npm run start` - Start development mode with hot reloading
- `npm run lint:js` - Lint JavaScript files
- `npm run lint:css` - Lint CSS files
- `npm run format` - Format code with Prettier
- `npm run test:setup` - One-time WordPress test suite setup (creates test DB and installs WP tests)
- `npm test` - Build assets and run PHPUnit tests

## Plugin Structure

```
pickleball-ratings/
├── src/
│   ├── index.js          # Main Gutenberg block JavaScript
│   └── style.css         # Block styles
├── build/                # Built files (generated)
├── pickleball-ratings.php # Main plugin file
├── package.json          # NPM dependencies
└── readme.txt           # WordPress.org readme
```

## Testing

Run tests locally:

1. One-time setup (installs WP test suite and creates DB):
   ```bash
   npm run test:setup
   ```
2. Build assets and run PHPUnit:
   ```bash
   npm test
   ```

The test suite includes coverage for:
- Plugin registration and asset handles
- Render callback validation and CSS classes/styles
- DUPR API basic error handling and cache salt behavior
- Admin settings TTL sanitizer and registration

## Future Phases

- **Phase 2**: DUPR API integration with real data
- **Phase 3**: Advanced styling and customization options
- **Phase 4**: Enhanced block editor features
- **Phase 5**: Additional features and admin settings
- **Phase 6**: Testing and distribution preparation

## Example DUPR IDs

- JW Johnson: `8WZ4ML`
- Your ID: `PW24RQ`

## License

GPL v2 or later 