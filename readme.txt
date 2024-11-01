=== Trunc Logging - Auditing syslog for WordPress ===
Contributors: dcid@noc.org
Donate Link: https://trunc.org/wordpress
Tags: logging, syslog, wp-logging, ossec
Requires at least: 3.6
Tested up to: 6.3.1
Stable tag: 1.0.6
License: GPLv3

The Trunc Logging plugin gives you visibility to all the activity inside WordPress. Logins, logouts, failed logins, new posts, new
plugins, etc. Every major activity from WordPress is stored for analysis and forensics purposes.

== Description ==

The Trunc Logging plugin gives you visibility to all the activity inside WordPress. Logins, logouts, failed logins, new posts, new
plugins, etc. Every major activity from WordPress is stored for analysis and forensics purposes.

Tracked actions:

* Logins
* Failed logins
* Posts / pages published
* Posts / pages trashed
* Posts / pages removed
* Plugin installed
* Plugin activated
* Themes installed
* Themes activated

And much more.


== Installation  ==

Installation is pretty simple.


1. Log into your WordPress administration panel,
2. In the sidebar, choose "Plugins" and then "Add New",
3. Type "trunc" in the search box,
4. Install the option with the "By Trunc" at the foot,
5. Once activated, you will find a new icon in the sidebar with the Trunc logo. Go to the plugin's dashboard.

Visit the [Support Forum](https://wordpress.org/support/plugin/trunc-logging) to ask questions, suggest new features, or report bugs.


== Frequently Asked Questions ==

More info here: [Trunc WordPress](https://trunc.org/wordpress).

= What is Logging / syslog? =

Every server and application generate logs of their internal activities. WordPress doesn't do it by default and this plugin provides
this data.

= Does your plugin conflict with other security plugins? =

It should not conflict with any other plugin.


= Will this plugin impact the performance of my website? =

It should not. It runs on the backend and only on certain admin actions. 


= Do the logs get stored to my database? =

They are stored to a log file - and optionally sent to a remote syslog server.


== Changelog ==

= 1.0.3 =
* Fixing error on the password reset process + proper versions.

= 1.0.1 =
* First version.

