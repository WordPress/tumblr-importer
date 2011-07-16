=== Tumblr Importer ===
Contributors: Otto42
Tags: tumblr, import
Requires at least: 3.2
Tested up to: 3.2
Stable tag: trunk

== Description ==

Imports a Tumblr blog into a WordPress blog.

* Correctly handles post formats
* WP-Cron based background importing: start it up, then come back later to see how far it's gotten
* Duplicate checking, will not create duplicate imported posts
* Imports posts, drafts, and pages
* Media Sideloading (for audio, video, and image posts)

== Installation ==

1. Upload the files to the `/wp-content/plugins/tumblr-importer/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Go to Tools->Import and use the new importer.

== Upgrade Notice ==

= 0.2 =
Now handles titles properly for audio/video/image formats by leaving them empty. Note that you will need to delete existing posts and empty trash and run the importer again to re-import any already imported posts.

== Changelog ==

= 0.2 = 
* The audio, video, and image formats no longer use the caption for the titles. Tumblr seems to facilitate putting all sorts of crazy stuff into the caption fields as part of their reblogging system. So instead, these types of posts will have no titles at all. Sorry, but Tumblr simply doesn't have any sort of title fields here to work with, and no data that can be used to "create" a title for them.
* Minor debug error cleanup.
* Sideloading now done on drafts and pages as well.

= 0.1 =
* First version, not meant to be used except for testing.
