
## Summary
This WordPress block generates a round robin grid for doubles player matchups based on A) the number of players and B) the number of courts available. The block doesn't require configuration when added to a page/post, as all logic is handled on the frontend.

Example use: User enters 9 players and 2 courts. The block generates a grid with 8 rounds of matchups, where each round has 2 courts (2 matches) and 1 player gets a bye each round. The first round is sequential (1-2 vs 3-4, 5-6 vs 7-8, 9 bye), and subsequent rounds are randomized while avoiding repeat partnerships and ensuring fair byes.
## Requirements
### Core Functionality
- **Block Type:** WordPress Gutenberg block for pickleball doubles round robin scheduling
- **Inputs:** Number of players (4-32), Number of courts (1-8)
- **Output:** Visual grid showing 8 rounds of matchups
- **Interface:** Generate button that randomly seeds rounds 2-8; round 1 is assumed sequential
### Visual Design
- **Layout:** Traditional scrollable grid (rows = courts, columns = rounds)
- **Styling:** Bootstrap-inspired, clean modern look, mobile-responsive
- **Colors:** Blue headers (`#007bff`), gray court labels (`#6c757d`), light gray bye row (`#e9ecef`)
- **Cell Format:** `1 2` (top line) `vs` (italicized middle) `3 4` (bottom line)
- **Completion Tracking:** ○/✓ buttons in headers, completed rounds get gray diagonal stripes
- **Mobile:** Larger text (18px), larger buttons (28px), horizontal scroll
- **Stats:** Display statistics of unique partnerships and byes below the grid
### Algorithm Specifications
- **Round 1:** Sequential pairing (1-2 vs 3-4, 5-6 vs 7-8, highest numbers bye first)
- **Rounds 2+:** Randomized partnership assignment avoiding recent repeats
- **Partnership Priority:** Unique partnerships first, track within session only
- **Bye System:** Latest numbers sit first (round 1), then fair rotation with randomization
- **Court Assignment:** Partnerships assigned to any court, repeat partnerships can swap serve/receive (i.e. top vs bottom numbers)
### Validation & Smart Features
- **Smart Court Limiting:** Auto-reduce courts to maximum possible (don't error)
- **Player Exclusion Warning:** Warn if some players would get zero games; though this isn't expected in v1 based on current rules of max 32 players and 8 courts
- **Client-side Rendering:** Does not rely on server-side processing; all logic in React/JS
- **Persistence:** Store user selected player/court counts, generated matchups, and completed rounds in local storage to be resilient across refresh for browser session
