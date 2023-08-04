GTmetrix WordPress Plugin
========================

GTmetrix can help you develop a faster, more efficient, and all-around improved website experience for your users. Your users will love you for it.

![banner.png](https://raw.githubusercontent.com/gtmetrix/gtmetrix-for-wordpress/master/images/banner.png)

Description
--------------------------

GTmetrix has created GTmetrix for WordPress - a WordPress plugin that actively keeps track of your WP install and sends you alerts if your site falls below certain criteria.

Run analyses, schedule reports on a daily, weekly or monthly basis, and receive alerts about the status of your site all from within your WordPress Admin!

Installation
--------------------------

1. Upload gtmetrix-for-wordpress to the /wp-content/plugins/ directory
2. Activate the plugin through the Plugins menu in WordPress
3. Click on the new menu item to enter your GTmetrix API details.

Requirements
--------------------------

**Requires:** 3.3.1 or higher

**Compatible up to:** 5.6.1

Version
--------------------------

0.4.8

Changelog
--------------------------

##### 0.4.8
* Minor bug fixes

##### 0.4.7
* Minor bug fixes

##### 0.4.6
* Minor bug fixes

##### 0.4.5
* Minor bug fixes

##### 0.4.4
* Fixed compatibility issues with other plugins using the Services_WTF_Test class

##### 0.4.3
* Updated icons and letter grade images
* Added fully loaded time for report preview
* Fixed dashboard widget style
* Fix Video widget

##### 0.4.2
* Fixed deprecated constructor call
* Fixed letter grade generation bug
* Added utm parameters to GTmetrix links
* Made some responsive layout tweaks

##### 0.4.1
* Added caching to Latest News
* Added error handling to Latest News
* Added Twitter link
* Updated Credits meta box content
* Updated jQuery UI theme
* Minor formatting changes
* Some refactoring for efficiency

##### 0.4
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

##### 0.3.4
* Fixed notifications bug

##### 0.3.3
* Fixed notifications bug

##### 0.3.2
* Fixed meta box bug
* Fixed issue causing low scores to display incorrect grades
* Added function to translate API messages
* Some refactoring for efficiency

##### 0.3.1
* Minor bug fix

##### 0.3
* Added access_gtmetrix capability
* Fixed scheduling bug
* Minor bug fixes

##### 0.2.1
* Minor bug fix

##### 0.2
* Added front-end widget
* Replaced custom function with size_format()
* Implemented caching of credit status
* Updated Services_WTF_Test.php

##### 0.1
* Initial release

License
--------------------------

GTmetrix for WordPress

Copyright (C) 2021 Carbon60 Operating Co. Ltd.

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
