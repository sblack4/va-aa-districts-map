=== Virginia AA Districts Map ===
Contributors: virginiaaa
Tags: aa, map, districts, virginia, interactive, meetings
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.1.0
License: GPLv2 or later

Interactive map of Virginia Area AA districts with meeting information.

== Description ==

This plugin provides an interactive SVG map of all Virginia Area Alcoholics Anonymous districts. Visitors can click on districts to view meeting schedules, locations, contact information, and virtual meeting details.

Features:

* Interactive SVG map of Virginia with Voronoi-based district boundaries derived from actual meeting locations
* Click-to-zoom with district detail panel
* Search/filter districts by name or number
* Meeting schedule, location, email, and virtual meeting information for each district
* Fully responsive — works on desktop, tablet, and mobile
* Per-district form editing in the WordPress admin (no JSON required)
* Map preview in admin
* Sync meeting counts from aavirginia.org
* Translation-ready — all public-facing text is server-rendered for compatibility with translation plugins

== Installation ==

1. Upload the `va-aa-districts-map` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Add the shortcode `[va_aa_map]` to any page or post

== Usage ==

**Basic:** `[va_aa_map]`

**Custom height:** `[va_aa_map height="800px"]`

== Editing District Data ==

1. Go to **AA Districts Map** in the WordPress admin sidebar
2. Use the **Districts** tab to edit individual districts (name, meeting schedule, location, email, virtual meeting info)
3. Use the **Map Preview** tab to see how the map looks
4. Use the **Advanced** tab for JSON import/export, syncing meeting counts from the website, or resetting to defaults

== Changelog ==

= 2.0.0 =
* Voronoi-based district boundaries derived from 2,600+ meeting locations
* Improved county-to-district mapping (32 corrections, 12 shared counties)
* Per-district form editing in admin (replaces raw JSON textarea)
* Tabbed admin interface with Districts, Map Preview, and Advanced tabs
* Sync meeting counts from aavirginia.org API
* Server-rendered detail panels for translation plugin compatibility
* Zoom-aware label collision detection
* District data synced with current aavirginia.org website
* Outlier filtering for mistagged meetings
* Uninstall cleanup

= 1.0.0 =
* Initial release
