=== Plugin Name ===
Contributors: hakre
Donate link: http://www.prisonradio.org/donate.htm
Tags: HTTP, redirect
Requires at least: 2.9
Tested up to: 3.2-alpha
Stable tag: 1.2.2

Better HTTP Redirects makes your Blog's redirects to play more nicely the HTTP standards.

== Description ==

Better HTTP Redirects makes your Blog's redirects to play more nicely the HTTP standards. Dual Mode Plugin, can be used as a standard Plugin or as a Must-Use Plugin.

= Features =
*   Provide Hypertext Fragments on redirects where applicable.
*   Compatible with *any* current usage of the wp_redirect() API function.
*   Prevent unnecessary redirect loops on UTF-8 based sites.
*   Improved user and robot (search engines etc.) experience for your website.
*   No need for you to wait until core code get's fixed - if ever!
*   Helps you debugging redirect related issues with it's unique [Redirect Debug Mode](http://hakre.wordpress.com/2011/03/20/how-to-debug-redirect-problems-in-wordpress).
*   Free

== Installation ==

This Plugin can be used as a standard Plugin or as a Must-Use-Plugin. Please refer to your products documentation how to install plugins or contact your site administrator for that.

== Changelog ==

= 1.2.2 =
* Bugfix Release
* Fix of unprocessed boolean arguments in Backtrace.

= 1.2.1 =
* Warning on RFC violating location headers.
* Display of location added to Debug Mode.
* Display of Call Stack in Debug Mode improved.
* Display of classes in Debug Mode fixed.

= 1.2 =
* Debug Mode - Disables automatic redirect if WP_DEBUG is enabled to use the plugin for debugging purposes.
* Prevention of redirect loops for UTF-8 based canonical links (Ivrit, Japanese, Chinese etc.; Related: #14292)

= 1.1 =
* Increased usability of the default HTML fragment.

= 1.0 =
* Initial Release

= -/- =
* Setting up SVN

== Upgrade Notice ==

= 1.1 =
Maintenance Release.

= 1.0 =
Initial Release, don't miss it.

== TODO ==

* Verify that the plugin is stricly preventing redirects that redirect to the same URI (see RFC 2616 section 3.2.3).

== Integration ==

"Better HTTP Redirects" works out of the box but for a better integration you can make use of the following:

*    Hook **redirect_hypertext** `$hypertext = apply_filters('redirect_hypertext', $hypertext, $location, $status);`

== Document Scope ==

This file readme.txt has been created in the intention to provide data to be displayed [on the wordpress.org plugin repository website](http://wordpress.org/extend/plugins/better-http-redirects/). As always, for anything concrete and precise, refer to [the plugin sourcecode itself](http://plugins.trac.wordpress.org/browser/better-http-redirects/trunk/better-http-redirects.php), it ships with [phpdoc style comments](http://en.wikipedia.org/wiki/PHPDoc).