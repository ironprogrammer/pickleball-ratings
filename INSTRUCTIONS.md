The following coding and design instructions pertain to the WordPress plugin described in @REQUIREMENTS.md.

## **Phase 1: Core Foundation (MVP)**

**Goal**: Basic functional plugin with minimal styling

**Tasks**:

- Set up and initialize git repo for a WordPress plugin.
- Set up WordPress plugin structure (main plugin file, directory structure)
- Create basic Gutenberg block registration
- Implement block editor interface (input field for 6-digit code)
- Add basic input validation (6-character alphanumeric check)
- Create simple block render function with static placeholder data
- Test block appears in editor and renders on frontend
- Basic error handling for invalid codes

**Deliverable**: Working block that accepts input and displays placeholder content

---

## **Phase 2: DUPR Integration**

**Goal**: Connect to actual DUPR data

**Tasks**:

- Research DUPR API or data source (web scraping if no API)
- Implement data fetching mechanism
- Add caching system to avoid repeated API calls
- Handle API errors and rate limiting
- Parse and structure player data
- Replace placeholder with real DUPR data
- Add loading states and error messages

**Deliverable**: Block displays real DUPR ratings for valid player codes

---

## **Phase 3: Styling & Design**

**Goal**: Professional appearance with customization options

**Tasks**:

- Create default attractive card design
- Implement responsive CSS
- Add block style variations (card, minimal, badge, etc.)
- Create color scheme options
- Add typography controls
- Ensure accessibility compliance (WCAG)
- Test across different themes

**Deliverable**: Visually polished rating display with style options

---

## **Phase 4: Block Editor Enhancements**

**Goal**: Better user experience in the editor

**Tasks**:

- Add block settings sidebar panel
- Implement live preview in editor
- Add alignment options
- Create block patterns/templates
- Add help text and validation feedback
- Implement block transforms (if applicable)
- Add example/placeholder states

**Deliverable**: Intuitive editor experience with full customization

---

## **Phase 5: Advanced Features**

**Goal**: Extended functionality and polish

**Tasks**:

- Add shortcode support for non-block themes
- Implement bulk player display options
- Add rating history/trends (if data available)
- Create player comparison blocks
- Add export/sharing features
- Implement admin settings page
- Add usage analytics/tracking

**Deliverable**: Feature-rich plugin with admin controls

---

## **Phase 6: Testing & Distribution**

**Goal**: Production-ready plugin

**Tasks**:

- Comprehensive testing across WordPress versions
- Security audit and sanitization review
- Performance optimization
- Create documentation and screenshots
- Set up automated testing
- Prepare for WordPress.org submission
- Create user documentation/tutorials

**Deliverable**: Distribution-ready plugin
