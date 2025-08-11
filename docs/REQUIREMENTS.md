# DUPR Rating

The DUPR Rating plugin includes a custom WordPress block designed to display the DUPR rating for a specific player. It allows users to easily embed the player's rating within their content, enhancing the visibility and accessibility of player performance data.

At a high level, the plugin provides these pieces:
- Custom block for displaying a player's DUPR ratings.
- Settings page to manage things like DUPR API authentication and caching settings.
- A couple of pattern options for the block to display ratings in a horizontal vs vertically oriented layout.

Target users are pickleball players for their profile sites, pickleball clubs, and tournament sites. The block could be used in a personal site's footer, or alongside player profile information on a team site.

Suggested slug: `pickleball-ratings`

## Functional requirements
The main feature of the block is to display the DUPR rating for a specified player, which can be customized through the block settings. The block supports both frontend and backend rendering, ensuring that the rating and styling is displayed consistently in the WordPress editor and the live site. The block supports all standard features, including styles, attributes for dynamic content, responsive design, and accessibility standards.

To use the block, add the block to the editor. The block settings will include a field for the DUPR ID, which is a 6-digit alphanumeric identifier, unique to each player. Once an ID is entered, the editor should query the associated ratings data, and display it in the block dynamically. DUPR data for the vast majority of people doesn't update that often, so the info should be cached with a reasonable TTL, which should also be configurable in the plugin's settings. The block should work out-of-the-box in this manner, which no further customization except to publish the page/post.

## Technical specs
- The plugin should adhere to all modern WordPress practices and code standards, and support PHP 7.4 and higher.
- Plugin structure should be consistent with plugin handbook best practices, including use of namespaces, autoloading, and proper file organization.
- Include unit tests where applicable.
- Plugin is dependent on the DUPR API, which is documented at https://backend.mydupr.com/swagger-ui/index.html. The plugin will make authenticated requests to the API to retrieve player ratings data. I couldn't find documentation other than this site, but if some exists, make sure to let me know.
- The authentication process should be as easy as possible, and it's okay to use or base off an existing open source option if it makes the process smoother for admin users.
- The DUPR API includes private info like phone number and birthdate, so all API requests should be made in the backend, never on the frontend of the site.
- Plugin data retrieved from the API should be cached to improve performance and reduce API calls. The caching mechanism should be configurable in the plugin settings, allowing admins to set a time-to-live (TTL) for cached data.
- Cached values should also track the corresponding DUPR ID so that multiple instances of the block can display different players' data.
- Cached info should NOT include private data such as phone number, DOB, gender, or email. Only data pertinent to displaying ratings is needed.
- A good standard for new plugins is to use the WP-CLI command `wp scaffold plugin <slug>` to generate the initial plugin structure, and then build upon that. If using a different baseline, be sure to let me know why.
- Code should include good inline documentation per WordPress coding standards, since this plugin is open source and should be easily understandable and updatable by other developers.

## Display requirements
The default display of the block should include the following information (based on what is typically displayed on the official DUPR website):
- DUPR ID
- player profile picture (the API references these on AWS); toggle display on/off
- doubles rating (accompanied by an icon for two people)
- singles rating (icon for a single person)

In presenting this info, the block should prioritize display of the ratings numbers (like h3/h4). The DUPR ID should be less prominent, but clearly visible.

The layout for the block could lean on design of things like Google reviews (where it shows the profile picture, name, and ratings), or a social media profile (like Twitter or Facebook).

For future planning, optional block features may include:
- gender, age, and location based on their display preferences in DUPR (e.g. `M &bull; 51 &bull; Portland, OR, US`, though it's common to hide age and location, which in that case would show a closed eye for "private")
- number of players followed/following
- wins (👍)
- losses (👎)
- avg points percentage (📊 or somehow show "points" or "percent")
- avg partner rating (icon for two people)
- avg opponent rating (icon for single person)
- half-life (⏳ -- and not sure what this is, but it's in the API)
- reliability scores (`singlesReliabilityScore`, `doublesReliabilityScore`)
- verified ratings only toggle, e.g. `singles` vs `singlesVerified` value, (a star or official badge icon, like twitter verified or similar); this option should affect both the doubles and singles ratings displayed; the "verified only" indicator should come after the ratings numbers (this toggle should affect both ratings)

These future planning items are only provided for context to help ensure minimal rewriting for future updates, and should not be included in the initial release of the plugin.

### Exceptions
- if there is no rating available for a particular field, display "NR" (not rated), which the API provides
- if an invalid DUPR ID is entered, provide clear feedback to the user in the editor, but allow the block to be saved (this might prevent a publishing issue if the API is temporarily broken?)
- for saved blocks with invalid DUPR IDs, or if there is no data cached/received for the ID, the block should display the DUPR ID, but with hyphens for selected fields' values

### Styling customizations
- The block should be styled to match the WordPress editor's design, using the default styles for headings, paragraphs, and icons. It should default to whatever is provided in the active theme.
- Standard paragraph style options should be available for the block.
- There is currently no need to customize individual fields' styles; e.g. a single font for the block is sufficient. It should not be configurable to be ugly.
- Regarding patterns, I'm open to architectural options here, but feeling that the initial release should have a couple different block variations or patterns, whichever is easier to build/customize. (Maybe variations are theme-level, in which case this becomes an easier decision.)

### Demonstration samples
My favorite player is JW Johnson (DUPR ID 8WZ4ML), so use his ID as a good example of a player with a lot of history and reliable ratings data. My own DUPR ID is PW24RQ, which can be used as an example of a player with very little history and low reliability scores.

## Backend and settings
In the backend, the plugin utilizes DUPR's API, which is documented at https://backend.mydupr.com/swagger-ui/index.html. Because player data includes private info (like phone number), the API is not anonymously accessible, so after plugin installation, an admin must navigate to the plugin's settings and authenticate with the DUPR API to obtain an API key/bearer token. Presume that the admin will authenticate with their own DUPR account login, which they might already be logged-in to. Once authenticated, the key is stored securely (and renewed if necessary) in the WordPress database and used to fetch player ratings dynamically. The ID used for authenticating API requests does not need to match the ID used in blocks. An option to disconnect from the API is also available in the plugin' settings.

A setting to modify the default TTL for cached data is available in the plugin's settings. The default TTL should be 24 hours, but can be adjusted by the admin as needed. This setting allows the admin to balance performance and data freshness based on their site's needs.

## Roadmap
- Add support for additional player statistics (listed above).
- Option to display matches (by specific match/date, most recent, etc).
