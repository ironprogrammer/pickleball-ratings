## Current Phase: 3
Focus only on this phase's tasks. Don't implement features from later phases. Make sure to commit changes so we can track code history, and easily roll back if need be.

Unit testing can be done with phpunit. I'm using a separate local site with this plugin symlinked and activated for testing. Let's use debug.log for tracking things on the PHP side, and console logging in the browser as needed. Keep this logging in place for now, since i'd like to be able to test with other people for debugging.

Do not modify the REQUIREMENTS or INSTRUCTIONS files. It's okay to mark the items below as complete after the chnages have been validated by me and committed to git.

i'd like to start with these things, which came up as we tried implementing phase 3 the first time:

1. remove the "last updated" section from the block and instead make it a title attr (?) on the player name
2. change the "dupr settings" block attr section to "Display Settings".
3. add a toggle to that section for showing the player profile pic. if no pic is available and the toggle is ON, then display a "person" icon (from wordpress's own icon set is fine) inside a profile pic circle
4. move the dupr id so that it comes after the player name
5. remove "rating" from the doubles/singles fields, and precede "Doubles" and "Singles" with icons for 'two people' and 'a single person'
6. add a title attr to "NR" rating values that reads "Not Rated"

do these one at a time, letting me build and test each change! i want to commit each change after they're ironed out (something i missed doing on phase 3 attempt 1 and am now sorry for forgetting). once we've gone thru this list, i'll provide additional steps that we'll tackle one at a time as well.


## Phase Goals:
Refer to additional phase details in @INSTRUCTIONS.md.

## Definition of Done:
- All tasks completed
- Code tested and working
- No errors in browser console
- Meets WordPress coding standards
