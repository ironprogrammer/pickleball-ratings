# Pickleball Ratings

This plugin provides a customizable block to display player DUPR (Dynamic Universal Pickleball Rating) ratings information. It uses the official DUPR API and requires a DUPR account to authenticate API requests.

In addition to the plugin itself, the `docs/` directory includes the requirements and agent implementation instructions that were used to assist the development of the plugin. See the `.cursor/rules` directory for additional Cursor-specific rules. This plugin was designed and built with the assistance of Claude, Cursor, and cursor-agent.

## Installation

Until the plugin shows up in the WordPress.org plugins directory, just head over to the [Releases page](https://github.com/ironprogrammer/pickleball-ratings/releases), and for the latest release, expand the "Assets" section and download the **pickleball-ratings.zip** file. Upload and install to WordPress like you would any other plugin.

## How to Use

After installing and activating the plugin:
1. Head to *Settings > Pickleball Ratings* and enter your DUPR login email and password, then click **Connect to DUPR**.
2. On any post, page, or site template/section, add the "Pickleball Player Ratings" block.
3. Enter a player's DUPR ID, and the plugin will pull in their latest data.
4. Style the block as you wish -- check out the background gradient style that's included for an authentic DUPR look.

## Feature Requests ⭐️ and Bug Reports 🪳

Provide feedback through [the Issues page](https://github.com/ironprogrammer/pickleball-ratings/issues/new/choose), and use the appropriate template (bug or feature request).

## Contributing

Your contributions are welcome! See [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

[As with all things WordPress](https://developer.wordpress.org/plugins/plugin-basics/including-a-software-license/), this plugin is licensed under GPL v2 or later.
