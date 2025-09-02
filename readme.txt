=== Pickleball Ratings ===
Contributors: ironprogrammer
Donate link: https://github.com/ironprogrammer/pickleball-ratings
Tags: pickleball, ratings, sports, gutenberg, blocks
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display DUPR ratings for pickleball players using a customizable block. Uses the official DUPR API.

== Description ==

The DUPR Rating plugin provides a custom Gutenberg block that displays DUPR (Dynamic Universal Pickleball Rating) information for pickleball players. This plugin connects to the real DUPR API to fetch live player data, including ratings, verification status, and player names. Perfect for pickleball clubs, tournament sites, and player profile pages.

## Features

* **Custom Gutenberg Block**: Add DUPR ratings to any post or page using the block editor
* **Input Validation**: Validates 6-character alphanumeric DUPR IDs
* **Responsive Design**: Works seamlessly across all device sizes
* **Real DUPR Data**: Connects to the actual DUPR API for live data
* **Authentication**: Secure API token management
* **Caching**: Configurable caching to improve performance
* **Error Handling**: Clear error messages for invalid inputs and API issues

## How to Use

1. Add the "DUPR Player Rating" block to your post or page
2. Enter a 6-character DUPR ID in the block settings
3. The block will display the player's rating information

## Example DUPR IDs

* JW Johnson: `8WZ4ML`
* Your ID: `PW24RQ`

## Phase 2 Status

This is Phase 2 of the plugin development, which includes:
- Real DUPR API integration with authentication
- Admin settings page for API configuration
- Caching system to reduce API calls
- Loading states and error handling
- Display of real player data (name, ratings, verification status)
- Cache management tools
- API connection testing

Future phases will include advanced styling options, block editor enhancements, and additional features.

A few notes about the sections above:

*   "Contributors" is a comma separated list of wp.org/wp-plugins.org usernames
*   "Tags" is a comma separated list of tags that apply to the plugin
*   "Requires at least" is the lowest version that the plugin will work on
*   "Tested up to" is the highest version that you've *successfully used to test the plugin*. Note that it might work on
higher versions... this is just the highest one you've verified.
*   Stable tag should indicate the Subversion "tag" of the latest stable version, or "trunk," if you use `/trunk/` for
stable.

    Note that the `readme.txt` of the stable tag is the one that is considered the defining one for the plugin, so
if the `/trunk/readme.txt` file says that the stable tag is `4.3`, then it is `/tags/4.3/readme.txt` that'll be used
for displaying information about the plugin.  In this situation, the only thing considered from the trunk `readme.txt`
is the stable tag pointer.  Thus, if you develop in trunk, you can update the trunk `readme.txt` to reflect changes in
your in-development version, without having that information incorrectly disclosed about the current stable version
that lacks those changes -- as long as the trunk's `readme.txt` points to the correct stable tag.

    If no stable tag is provided, it is assumed that trunk is stable, but you should specify "trunk" if that's where
you put the stable version, in order to eliminate any doubt.

== Installation ==

1. Upload the `pickleball-ratings` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Add the "Pickleball Player Ratings" block to any post or page using the Gutenberg editor
4. Enter a DUPR ID in the block settings to display player ratings

## Development Setup

If you're developing this plugin:

1. Clone the repository
2. Run `npm install` to install dependencies
3. Run `npm run build` to build the JavaScript and CSS files
4. Activate the plugin in WordPress

== Frequently Asked Questions ==

= What is a DUPR ID? =

A DUPR ID is a unique 6-character alphanumeric identifier assigned to each player in the DUPR system. You can find your DUPR ID on your DUPR profile page.

= How do I find my DUPR ID? =

Log into your DUPR account at mydupr.com and look for your ID on your profile page. It will be a 6-character code like "8WZ4ML".

= How do I configure the DUPR API? =

Go to Settings > DUPR Rating in your WordPress admin panel. You'll need to obtain an authentication token from the DUPR API documentation at https://backend.mydupr.com/swagger-ui/index.html and enter it in the settings.

= What if I enter an invalid DUPR ID? =

The plugin will show an error message if the ID format is incorrect. The ID must be exactly 6 characters long and contain only letters and numbers.

== Screenshots ==

1. This screen shot description corresponds to screenshot-1.(png|jpg|jpeg|gif). Note that the screenshot is taken from
the /assets directory or the directory that contains the stable readme.txt (tags or trunk). Screenshots in the /assets
directory take precedence. For example, `/assets/screenshot-1.png` would win over `/tags/4.3/screenshot-1.png`
(or jpg, jpeg, gif).
2. This is the second screen shot

== Changelog ==

= 0.2.0 =
* Phase 2: DUPR API Integration
* Real DUPR API integration with authentication
* Admin settings page for API configuration
* Caching system to reduce API calls
* Loading states and error handling
* Display of real player data (name, ratings, verification status)
* Cache management tools
* API connection testing

= 0.1.0 =
* Initial release (Phase 1)
* Basic Gutenberg block with DUPR ID input
* Input validation for 6-character alphanumeric codes
* Placeholder data display
* Basic error handling
* Responsive design

== Upgrade Notice ==

= 0.2.0 =
Major update with real DUPR API integration! This version includes live data fetching, authentication, caching, and admin settings. Configure your API token in Settings > DUPR Rating.

= 0.1.0 =
Initial release of the DUPR Rating plugin. This version includes basic functionality with placeholder data. Real DUPR API integration will be available in future versions.

== Arbitrary section ==

You may provide arbitrary sections, in the same format as the ones above.  This may be of use for extremely complicated
plugins where more information needs to be conveyed that doesn't fit into the categories of "description" or
"installation."  Arbitrary sections will be shown below the built-in sections outlined above.

== A brief Markdown Example ==

Ordered list:

1. Some feature
1. Another feature
1. Something else about the plugin

Unordered list:

* something
* something else
* third thing

Here's a link to [WordPress](https://wordpress.org/ "Your favorite software") and one to [Markdown's Syntax Documentation][markdown syntax].
Titles are optional, naturally.

[markdown syntax]: https://daringfireball.net/projects/markdown/syntax
            "Markdown is what the parser uses to process much of the readme file"

Markdown uses email style notation for blockquotes and I've been told:
> Asterisks for *emphasis*. Double it up  for **strong**.

`<?php code(); // goes in backticks ?>`
