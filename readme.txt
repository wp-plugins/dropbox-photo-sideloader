=== Dropbox Photo Sideloader === 
Contributors: Audrey, Otto42
Tags: dropbox, sideload, image, photo, media
Requires at least: 3.4
Tested up to: 3.4
Stable Tag: trunk
License: GPLv2
License URI: http://www.opensource.org/licenses/GPL-2.0

== Description ==

Adds a new tab to the Add media screen, allowing you to pull images from Dropbox into WordPress.

"Sideloading" is a term given to differeniate from "uploading" or "downloading". When you sideload an image, you're copying it directly from Dropbox to WordPress. So if you keep your images in Dropbox, this plugin allows you to easily copy those images into WordPress.

After sideloading an image into WordPress, you'll find it in the Media Library and available for use in Galleries, or as images in the Post, or just whatever you like. It's just as if you uploaded them manually.

Note that sideloading many images at once may take more time than your webserver will allow. If this happens, just sideload them in smaller groups.


Credit:
This plugin uses the Dropbox PHP code (albeit modified slightly) from https://github.com/Dropbox-PHP/dropbox-php.


== Installation ==

Setup:

* Install the plugin.
* Create a new post (or edit an old one), and click the Upload Media icon.
* In the uploader popup, click the new "Dropbox Images" tab on the right.
* If you're logged in as an administrator, you'll find configuration instructions. These only need to be followed one time.

Those configuration instructions are reproduced here, for clarity.

* Visit https://www.dropbox.com/developers/apps and Click "Create an App".
* Give it a name and description.
* Select "Full Dropbox" so that it can access all your files.
* After the app has been created, copy the App Key and App Secret into the plugin config screen.
* Note: You can leave the App in "Development" status unless more people than just you need to access their Dropboxes using the plugin.


== Frequently Asked Questions ==

= Why does it look so simplistic and ugly? =

It's a new plugin. C'mon.

That said, any nice file-like browsing and selection Javascript library suggestions would be most appreciated. :)


== Screenshots ==

1. Authorization screen
2. Files ready for sideloading


== Upgrade Notice ==

== Changelog ==

= 0.3 =

* Add error handling and error checking to the setup process (test for an invalid key/secret before saving them).
* Change to use mobile version of the auth screen (looks better, IMO)
* Add check for HTTPS/SSL support (Dropbox requires it, won't work without it).

= 0.2 =

* Fix issues with spaces and other url-encoded characters in image filenames not being sideloaded properly.
* Add config screen

= 0.1 =

* First version

