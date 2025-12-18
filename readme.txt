=== RCP Content Filter Utility ===
Contributors: samybaxy
Tags: restrict-content-pro, membership, content-filter, access-control
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically filters restricted content from post grids and archives based on Restrict Content Pro membership levels.

== Description ==

RCP Content Filter Utility is a companion plugin for Restrict Content Pro that automatically hides restricted content from post grids, archives, and listing pages based on user membership levels. Instead of showing restricted items with access denial messages, this plugin completely removes them from view for users who don't have access.

= Key Features =

* Automatically filters restricted content from archive pages, home pages, and post grids
* Works with custom post types (including your "resource" post type)
* Respects all Restrict Content Pro restriction settings
* Performance optimized with intelligent caching
* Two filtering methods for maximum compatibility
* Admin settings page for easy configuration

= How It Works =

1. When a page loads that displays multiple posts (archives, grids, etc.)
2. The plugin checks each post against RCP restrictions
3. Posts the current user cannot access are filtered out
4. Only accessible content is displayed in the grid

= Requirements =

* WordPress 5.0 or higher
* PHP 7.0 or higher
* Restrict Content Pro plugin (active)

== Installation ==

1. Upload the `rcp-content-filter-utility` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Restrict Content > Content Filter to configure settings
4. Select which post types should be filtered
5. Choose your preferred filtering method

== Configuration ==

= Filter Method =

* **Exclude from Query**: Most efficient method that prevents restricted posts from being included in database queries
* **Remove from Results**: Filters posts after the query, works with more themes/plugins

= Post Types =

Select which post types should have restricted content filtered. Common selections:
* Posts
* Pages
* Custom post types (like "resource")

= Filter Priority =

Adjust the filter priority if you experience conflicts with other plugins. Lower numbers = higher priority.

== Frequently Asked Questions ==

= Does this affect single post/page views? =

No, this plugin only filters content on archive pages, home pages, and post listing pages. Single post/page views still show the restriction message if a user tries to access content they don't have permission for.

= Will admins still see all content? =

Yes, administrators with the `manage_options` capability can always see all content regardless of restrictions.

= How does caching work? =

The plugin caches restriction checks for 5 minutes per user to improve performance. The cache is automatically cleared when:
* A user's membership status changes
* Settings are updated
* You manually clear the cache from the settings page

= Can I use this with custom queries? =

The plugin primarily works with main WordPress queries and archive pages. For custom queries, you may need to implement additional filtering using the provided functions.

== Changelog ==

= 1.0.0 =
* Initial release
* Post type filtering configuration
* Two filtering methods
* Caching system for performance
* Admin settings interface

== Developer Information ==

= Available Hooks =

The plugin integrates with RCP's existing hooks:
* `rcp_membership_post_activate`
* `rcp_membership_post_cancel`
* `rcp_membership_post_expire`

= Functions Used =

* `rcp_user_can_access()` - Check if user can access specific content
* `rcp_is_restricted_content()` - Check if content has restrictions

== Support ==

For support, please ensure:
1. Both RCP and this utility plugin are up to date
2. Check for conflicts with other plugins by deactivating them temporarily
3. Clear the cache after making changes to restrictions