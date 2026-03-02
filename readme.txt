=== Bracket Post Order ===
Contributors: bracket
Tags: post order, custom order, drag and drop, taxonomy order, reorder
Requires at least: 6.2
Tested up to: 6.9
Stable tag: 1.2.3
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Drag-and-drop ordering for posts, pages, custom post types, and taxonomy terms — with per-category post ordering.

== Description ==

**Bracket Post Order** gives you complete control over how your content is sorted — directly from the native WordPress admin screens you already use. No new interfaces to learn, no separate reorder pages. Just drag and drop.

A key feature is **per-term post ordering**: the ability to define a different post order for each individual category, tag, or custom taxonomy term. Show your products in one order on "Summer Collection" and a completely different order on "Best Sellers" — each term maintains its own independent sort.

= Three Types of Ordering =

**1. Global Post Ordering**
Drag-and-drop to reorder posts, pages, and custom post types on the standard admin list table. The new order is saved to `menu_order` and automatically applied on the front end.

**2. Per-Term Post Ordering**
Filter your admin list by a category or taxonomy term, and the interface switches to per-term mode. Drag posts into the order you want for *that specific term*. Assign the same post to multiple categories — each one keeps its own sort. New posts added to a term automatically appear first (newest on top).

**3. Taxonomy Term Ordering**
Reorder categories, tags, and custom taxonomy terms themselves via drag-and-drop on the native `edit-tags.php` screen. The new term order is applied to `get_terms()` queries and navigation menus on the front end.

= Key Features =

* **Reset Order** — Reset post order by date or title with one click. Most requested feature across all ordering plugins.
* **Undo** — "Order saved. [Undo]" link appears for 8 seconds after every reorder. Click to revert instantly.
* **Mobile/Touch Support** — Full touch drag-and-drop on phones and tablets via jQuery UI Touch Punch.
* **Keyboard Accessibility** — Tab to a row, Enter to activate, Arrow keys to move, Enter to save, Escape to cancel. WCAG compliant.
* **Order Column** — "#" column shows each post's position number at a glance.
* **WPML & Polylang Support** — Per-term ordering works correctly across languages.
* **Admin Bar Indicator** — Shows current ordering mode (Global or Per-Term) in the admin bar.
* **Settings Link** — Quick access from the Plugins page.

= How It Works =

1. Go to **Settings > Bracket Post Order**
2. Toggle on the post types you want to reorder
3. Toggle on taxonomies for per-term post ordering
4. Toggle on taxonomies for term reordering
5. Visit your admin list pages and start dragging

Changes save automatically via AJAX — no page refresh needed.

= Built for WordPress, Not Against It =

* Works directly inside native admin list tables (`edit.php` and `edit-tags.php`)
* Uses standard `menu_order` for global ordering — compatible with any theme
* Uses `term_order` for taxonomy terms — the same column WordPress defines
* Per-term order stored as term meta — clean, portable, conflict-free
* Front-end queries are modified transparently via `pre_get_posts` and `posts_clauses`
* Explicit `orderby` parameters (date, title, etc.) are never overridden

= Works With =

* Any public custom post type (portfolios, team members, testimonials, events, FAQs, services)
* WooCommerce products and product categories
* Any registered taxonomy with a UI
* Page builders that use standard `WP_Query` (Elementor, Divi, Beaver Builder)
* Themes that follow WordPress template hierarchy
* WPML and Polylang multilingual plugins

= For Developers =

Bracket Post Order provides hooks so you can extend or control its behavior:

`// Filter: skip per-term ordering for a specific query
add_filter( 'bracket_po_apply_term_post_order', function( $apply, $term_id, $query ) {
    // Return false to skip
    return $apply;
}, 10, 3 );`

`// Filter: modify the retrieved term post order
add_filter( 'bracket_po_get_term_post_order', function( $ordered_ids, $term_id ) {
    return $ordered_ids;
}, 10, 2 );`

`// Actions: fired after order is saved via drag-and-drop
do_action( 'bracket_po_global_order_updated', $post_ids );
do_action( 'bracket_po_term_post_order_updated', $term_id, $post_ids );
do_action( 'bracket_po_term_order_updated', $term_ids );`

`// Actions: fired after order is reset
do_action( 'bracket_po_global_order_reset', $post_type, $sort_by );
do_action( 'bracket_po_term_post_order_reset', $term_id );`

To apply per-term order in custom queries, set `orderby` to `menu_order` and include a `tax_query` with a single term:

`$query = new WP_Query( [
    'post_type' => 'product',
    'orderby'   => 'menu_order',
    'order'     => 'ASC',
    'tax_query' => [ [
        'taxonomy' => 'product-category',
        'field'    => 'term_id',
        'terms'    => 42,
    ] ],
] );`

The plugin will automatically apply the saved per-term order via `FIELD()` SQL — posts not in the saved order appear first (newest on top).

== Installation ==

= Automatic Installation =

1. In your WordPress admin, go to **Plugins > Add New**
2. Search for "Bracket Post Order"
3. Click **Install Now**, then **Activate**
4. Go to **Settings > Bracket Post Order** to configure

= Manual Installation =

1. Download the plugin ZIP file
2. Upload the `bracket-post-order` folder to `/wp-content/plugins/`
3. Activate the plugin through the **Plugins** menu
4. Go to **Settings > Bracket Post Order** to configure

= Configuration =

1. Navigate to **Settings > Bracket Post Order**
2. In the **Post Types** card, toggle on each post type you want to reorder
3. In the **Per-Term Post Ordering** card, toggle on taxonomies (taxonomies appear automatically based on your enabled post types)
4. In the **Term Ordering** card, toggle on taxonomies whose terms you want to reorder
5. Click **Save Changes**

That's it. Visit any enabled post type list and start dragging rows.

== Frequently Asked Questions ==

= Does this work with custom post types? =

Yes. Any post type with `show_ui => true` appears in the settings. This includes WooCommerce products, portfolio items, team members, testimonials, events, and any custom post type registered by your theme or plugins.

= How does per-term ordering work? =

When you filter the admin post list by a taxonomy term (e.g., selecting a specific category from the dropdown), the plugin switches to per-term mode. An info notice appears confirming the mode. Drag posts into the order you want — that order is saved exclusively for that term.

The same post can belong to multiple terms, and each term maintains its own independent order.

= Does the custom order appear on the front end? =

Yes. The plugin hooks into `pre_get_posts` and `posts_clauses` to automatically apply the saved order to front-end queries. For global ordering, it sets `orderby => menu_order`. For per-term ordering, it modifies the SQL `ORDER BY` clause using `FIELD()` so your saved order is respected while keeping pagination and other query parameters intact.

= What if I set an explicit orderby in my query? =

The plugin never overrides explicit `orderby` parameters other than `menu_order`. If your query uses `orderby => date`, `title`, `meta_value`, or anything else, the plugin leaves it alone. This is by design — your code always takes priority.

= Does it work with WooCommerce? =

Yes. Enable the "Products" post type and relevant product taxonomies in the settings. The plugin works with WooCommerce product categories and tags for both global and per-term ordering.

= Can I reorder taxonomy terms (categories, tags)? =

Yes. Enable term ordering for any taxonomy in the settings. Then go to that taxonomy's admin page (e.g., Posts > Categories) and drag terms into your preferred order. The new order is applied to `get_terms()` queries on the front end.

= How do I reset the order? =

Click the "Reset Order" button above the post list table. Choose a sort method (Date newest/oldest, Title A-Z/Z-A) and confirm. For per-term mode, resetting removes the custom order for that term.

= Does it work on mobile/tablet? =

Yes. Version 1.1.0 includes full touch support. Drag-and-drop works on phones and tablets with properly sized touch targets.

= Is it accessible via keyboard? =

Yes. Tab to any row, press Enter or Space to activate reorder mode, use Arrow Up/Down to move the row, Enter to save, or Escape to cancel. Screen reader announcements are provided via ARIA live regions.

= Does it work with WPML or Polylang? =

Yes. The plugin automatically detects WPML and Polylang and maps per-term ordering to work correctly across languages.

= Does clicking a column header disable drag-and-drop? =

Yes. When you click a column header to sort by title, date, etc., the plugin detects the `orderby` URL parameter and disables drag-and-drop. Removing the sort (clicking the post type menu link again) re-enables it.

= Does it affect search results? =

No. The plugin only applies its ordering to queries for enabled post types that don't have an explicit `orderby` set. WordPress search queries use relevance-based sorting, which the plugin does not interfere with.

= Is it compatible with page builders? =

Yes. Elementor, Divi, Beaver Builder, and other page builders that use standard `WP_Query` will respect the custom order. If the builder lets you set `orderby` to `menu_order`, the per-term ordering also works automatically.

= What happens when I add a new post to a category that has a custom order? =

New posts that aren't part of the saved per-term order automatically appear first (sorted by date, newest on top). You can then go to the admin, filter by that term, and drag the new post into position.

= Is it compatible with Simple Custom Post Order (SCPO)? =

The plugin detects SCPO and displays an admin notice recommending deactivation. If both are active, Bracket Post Order dequeues SCPO's scripts on pages where it is active to prevent conflicts. For best results, deactivate SCPO before using this plugin.

= Does it support multisite? =

Yes. The plugin works on individual sites within a multisite network. Each site maintains its own settings and post order.

== Screenshots ==

1. Settings page — modern card-based UI with toggle switches for post types, taxonomies, and term ordering
2. Global ordering — drag-and-drop rows on the standard post list table with order position column
3. Per-term ordering — filter by a category, then drag posts into a term-specific order
4. Term ordering — drag-and-drop taxonomy terms on the edit-tags screen
5. Reset order — sort dropdown and reset button above the post list
6. Undo — "Order saved. Undo" notice after every reorder

== Changelog ==

= 1.2.3 =
* Fixed: New posts added to a term with per-term ordering now appear first instead of last

= 1.1.0 =
* New: Settings link on the Plugins page for quick access
* New: Admin bar indicator showing current ordering mode (Global or Per-Term)
* New: Order position "#" column on admin post list tables
* New: Reset Order button with sort options (Date DESC/ASC, Title A-Z/Z-A) for both global and per-term modes
* New: Undo last reorder — "Order saved. [Undo]" link with 8-second timeout
* New: Green highlight animation on rows after save
* New: Drag handle (vertical dots) visible on row hover
* New: Enhanced sortable placeholder and helper styles
* New: 800ms debounce on AJAX saves to prevent rapid drag spam
* New: Mobile/touch drag-and-drop support via jQuery UI Touch Punch
* New: Touch-friendly CSS — 48px min-height targets on coarse pointer devices
* New: Full keyboard accessibility — Enter/Space to activate, Arrow keys to move, Enter to save, Escape to cancel
* New: ARIA live region for screen reader announcements (WCAG compliance)
* New: WPML compatibility — per-term ordering works across languages
* New: Polylang compatibility — per-term ordering works across languages
* Performance: Optimized admin_init refresh — only recalculates menu_order when posts actually change (transient-based staleness detection)
* Improved: Sortable helper has larger shadow and subtle scale
* Improved: Focus ring styles for keyboard navigation
* Updated: uninstall.php cleans up transients
* Developer: New `bracket_po_global_order_reset` action
* Developer: New `bracket_po_term_post_order_reset` action

= 1.0.2 =
* Fixed table layout during drag-and-drop on some environments
* Improved first-time post ordering initialization
* Auto-detection and repair of menu_order gaps

= 1.0.1 =
* Improved WordPress coding standards compliance
* Settings page UX improvements
* Minor code quality and compatibility fixes

= 1.0.0 =
* Initial release
* Global post ordering via `menu_order` with drag-and-drop on admin list tables
* Per-taxonomy-term post ordering — independent sort order for each category/tag/term
* Taxonomy term ordering via drag-and-drop on `edit-tags.php`
* Modern settings page with card layout and toggle switches
* Dynamic taxonomy visibility — toggling a post type instantly shows its taxonomies
* Transparent `WP_Query` integration via `pre_get_posts` and `posts_clauses`
* Per-term ordering uses SQL `FIELD()` for native pagination support
* Automatic conflict detection and script dequeuing for Simple Custom Post Order
* Developer hooks: `bracket_po_apply_term_post_order`, `bracket_po_get_term_post_order`, `bracket_po_global_order_updated`, `bracket_po_term_post_order_updated`, `bracket_po_term_order_updated`
* Clean uninstall — removes options and term meta on plugin deletion
* Full internationalization support with `.pot` template

== Upgrade Notice ==

= 1.1.0 =
Major feature update: reset order, undo, mobile/touch support, keyboard accessibility, WPML/Polylang compatibility, and performance optimizations. All free.

= 1.0.0 =
Initial release. If migrating from Simple Custom Post Order, your existing `menu_order` values are preserved — just enable the same post types in the Bracket Post Order settings.
