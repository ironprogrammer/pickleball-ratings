=== Pickleball Ratings ===
Contributors: ironprogrammer
Donate link: https://github.com/ironprogrammer/pickleball-ratings
Tags: pickleball, ratings, sports, blocks
Requires at least: 5.6
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display pickleball player ratings using a customizable block. Uses the official DUPR API.

== Description ==

Pickleball Ratings provides a customizable block to display player DUPR (Dynamic Universal Pickleball Rating) ratings information. It uses the official DUPR API and requires a DUPR account to authenticate API requests. Perfect for pickleball clubs, tournament sites, and player profile pages.

## Features

* **Customizable Block**: Add player ratings to any post or page using the block editor.
* **Responsive Design**: Works across any device size.
* **DUPR API Integration**: Connects to the official DUPR API for the latest data.
* **Caching**: Configurable caching of ratings data to improve performance.

## How to Use

1. Add the "Pickleball Player Ratings" block to any post, page, or template/section using the block or site editor.
2. Enter a player's 6-character DUPR ID in the block settings.
3. The block will retrieve and display the player's ratings info.
4. Save and publish!

## Contribute

Have feature feedback or a bug you'd like to fix? [See `CONTRIBUTE.md` in GitHub for more information.](https://github.com/ironprogrammer/pickleball-ratings)

== Installation ==

1. Download the plugin.
2. In WP admin, go to *Plugins > Add Plugin* and click the **Upload Plugin** button.
3. Once uploaded, activate the plugin and go to *Settings > Pickleball Ratings*.
4. Enter your DUPR login email and password, then click **Connect to DUPR**. The settings page should refect your connection status.

== Frequently Asked Questions ==

= How do I connect to the DUPR API? =

Go to *Settings > Pickleball Ratings* in WP admin. Enter your DUPR login email and password to connect. The plugin will store your authentication token, but not your password.

= How do I find my DUPR ID? =

If you've connected to the API, then you'll see this on the plugin's settings page. Otherwise, you can find it by logging in to your DUPR account at [dupr.com](https://dupr.com). Look for the 6-character ID on your performance dashboard.

= How do I find another player's DUPR ID? =

On the DUPR site, navigate to the Players page and search for a player by name. Their DUPR ID should be visible in the search results as well as on their profile page.

== Changelog ==

= 0.2.0 =
* Initial public release
