=== Pickleball Ratings ===
Contributors: ironprogrammer
Donate link: https://github.com/ironprogrammer/pickleball-ratings
Tags: pickleball, ratings, sports, blocks
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Show a pickleball player's doubles and singles ratings in a customizable block.

== Description ==

Pickleball Ratings adds a block that shows a player's doubles and singles ratings, with optional reliability scores, a profile image, and a DUPR ID visitors can copy. You enter the ratings in the block settings, and hovering over them shows when they were last changed.

The plugin also includes a Round Robin block for generating and tracking doubles schedules.

## Features

* Show a doubles rating, a singles rating, or both.
* Add a reliability score from 0 to 100 to each rating, shown as a ring that goes from red to green.
* Choose the Gradient, White, or Black style, or use the editor's color and font size settings.
* Add a profile image from the media library.
* Add a DUPR ID to show a copy button.

## How to use

1. Add the "Pickleball Player Ratings" block to a post, page, or template.
2. In the block settings, enter the player's name and at least one rating.
3. Pick a style in the block's Styles panel.

== Installation ==

1. Download the plugin.
2. In WP admin, go to *Plugins > Add Plugin* and click the **Upload Plugin** button.
3. Upload the ZIP file and activate the plugin.

== Frequently Asked Questions ==

= Where do the ratings come from? =

You enter them. The plugin doesn't connect to any rating service, so edit the block when a player's rating changes. The "Updated" date, shown when hovering over the ratings, changes whenever you edit a rating or reliability score.

= How do I find a player's DUPR ID? =

Players can find their 6-character ID on their DUPR profile.

== Changelog ==

= 1.0.1 =
* Fix: The ratings card uses the surrounding text size, and the block's Typography settings can change it.

= 1.0.0 =
* Breaking: Removed the DUPR API connection and settings page. Ratings are now entered in the block settings.
* Feature: Gradient, White, and Black block styles.
* Feature: Reliability meter for each rating.
* Feature: Profile image from the media library.
* Feature: "Updated" date that changes automatically when ratings are edited.
* Enhancement: The copy button works with a keyboard and announces when the ID is copied.
* Upgrade: Existing blocks keep their last cached ratings and profile images, and stored DUPR credentials are deleted.

= 0.4.0 =
* Enhancement: Replaced Dashicons with new pickleball icon SVGs for better looks, performance, and scalability.
* Enhancement: Migrated all AJAX functionality to the WordPress REST API for improved reliability and performance.
* Enhancement: Updated block to API version 3 and migrated rendering to `block.json` conventions.

= 0.3.0 =
* Feature: Added a new "Round Robin" block for displaying tournament brackets.

= 0.2.0 =
* Initial public release
