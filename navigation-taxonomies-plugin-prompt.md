## Prompt: WordPress Plugin for Auto-Synced Taxonomy Navigation

You are a senior WordPress plugin developer. Build a standalone plugin that creates and maintains a native WordPress Navigation block containing all terms from a selected taxonomy. The navigation must behave exactly like a normal Navigation block (including overlay settings and all block controls).

### Core Requirements

- Create a plugin with WordPress Coding Standards and proper namespaces.
- Provide a settings screen where the admin can:
  - Enable/disable auto-sync.
  - Select any taxonomy (built-in or custom).
  - Choose whether to include empty terms.
  - Choose sort order (name, count, term order, etc.).
  - Choose parent/child nesting behavior.
- The plugin must not replace any existing navigation. It must create (and update) its own navigation.
- The navigation must be a native `wp_navigation` post containing a `core/navigation` block with `core/navigation-link` child blocks.
- The output must behave like a standard Navigation block, including overlay options.
- It must always stay in sync when terms are created/updated/deleted.

### Technical Details

- Store settings in the options table.
- Register a `wp_navigation` post (create on first run, then update on changes).
- Hook into taxonomy term CRUD (`created_term`, `edited_term`, `delete_term`) and settings changes to rebuild the navigation.
- Use block markup in the `post_content` of the `wp_navigation` post.
- Only rebuild when needed to avoid unnecessary updates.
- Include a manual “Sync now” button in the settings page.

### UX Details

- Settings page should live under **Settings** in wp-admin.
- Provide clear admin notices after manual sync.

### Deliverables

- Main plugin file with header.
- Admin settings page.
- Sync service class (or equivalent) responsible for generating navigation blocks.
- Proper capability checks and nonces.

### Notes

- Use English for UI strings.
- Follow WordPress Coding Standards.
- Provide clear inline comments only where logic is complex.
