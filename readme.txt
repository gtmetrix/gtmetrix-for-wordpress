=== GTmetrix for WordPress ===
Contributors: GTmetrix
Tags: analytics, gtmetrix, monitoring, optimization, page speed, performance, speed, statistics, yslow
Requires at least: 3.3.1
Tested up to: 5.2
Stable tag: 0.4.4

GTmetrix can help you develop a faster, more efficient, and all-around improved website experience for your users. Your users will love you for it.

== Description ==

GTmetrix has created GTmetrix for WordPress - a WordPress plugin that actively keeps track of your WP install and sends you alerts if your site falls below certain criteria.

Run analyses, schedule reports on a daily, weekly or monthly basis, and receive alerts about the status of your site all from within your WordPress Admin!

== Installation ==

1. Upload `gtmetrix-for-wordpress` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Click on the new menu item to enter your GTmetrix API details.

== Frequently Asked Questions ==

= How do I get a GTmetrix API account? =

Go to [GTmetrix.com](http://gtmetrix.com/) and register. It's free.

= How can I get more API Credits? =

You can purchase more API credits under the [GTmetrix PRO](https://gtmetrix.com/pro/) tab on GTmetrix.com.

= Only administrators can use the plugin. How can I grant access to other roles? =

GTmetrix for WordPress creates a new capability called access_gtmetrix. Using a plugin such as [Members](http://wordpress.org/extend/plugins/members/) or [User Role Editor](http://wordpress.org/extend/plugins/user-role-editor/), you can assign this capability to any role.

= Async JavaScript Problems? =

The Async JavaScript plugin is not written by or affiliated with GTmetrix. It does use our API to generate GTmetrix reports however.

If you're running into issues with this plugin, please reach out for support on their [forum](https://wordpress.org/support/plugin/async-javascript/).

== Screenshots ==

1. Dashboard Widget
2. Tests Page
3. Settings Page
4. Video Analysis

== Changelog ==

= 0.4.4 =
* Fixed compatibility issues with other plugins using the Services_WTF_Test class

= 0.4.3 =
* Updated icons and letter grade images
* Added fully loaded time for report preview
* Fixed dashboard widget style
* Fix Video widget

= 0.4.2 =
* Fixed deprecated constructor call
* Fixed letter grade generation bug
* Added utm parameters to GTmetrix links
* Made some responsive layout tweaks

= 0.4.1 =
* Added caching to Latest News
* Added error handling to Latest News
* Added Twitter link
* Updated Credits meta box content
* Updated jQuery UI theme
* Minor formatting changes
* Some refactoring for efficiency

= 0.4 =
* Added video functionality
* Added page load time to test list
* Added front page options to settings (for subdirectory installs)
* Added reset function to settings
* Added tooltips
* Made toolbar link optional
* Added pause control to scheduled events
* Added automatic pausing on multiple failures of scheduled events
* Replaced get_posts with WP_Query for efficiency
* Removed some illogical Ajax calls
* Forced front page check to http (to override FORCE_SSL_ADMIN)
* Fixed trailing slash bug for front page tests
* Fixed cascading deletes when scheduled items deleted
* Replaced jQuery Tools with jQuery UI
* Updated Services_WTF_Test.php
* Minor bug fixes
* Some refactoring for efficiency

= 0.3.4 =
* Fixed notifications bug

= 0.3.3 =
* Fixed notifications bug

= 0.3.2 =
* Fixed meta box bug
* Fixed issue causing low scores to display incorrect grades
* Added function to translate API messages
* Some refactoring for efficiency

= 0.3.1 =
* Minor bug fix

= 0.3 =
* Added access_gtmetrix capability
* Fixed scheduling bug
* Minor bug fixes

= 0.2.1 =
* Minor bug fix

= 0.2 =
* Added front-end widget
* Replaced custom function with size_format()
* Implemented caching of credit status
* Updated Services_WTF_Test.php

= 0.1 =
* Initial release
