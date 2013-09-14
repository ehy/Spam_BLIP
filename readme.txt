=== Spam_BLIP ===
Contributors: EdHynan
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick%DONATE_LINK%hosted_button_id=4Q2Y8ZUG8HXLC
Tags: %TAGS_README%
Requires at least: 3.0.2
Tested up to: 3.6.1
Stable tag: 1.0.0
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Text Domain: spambl_l10n

Spam_BLIP provides a flash video player for posts and pages, and a widget, and forms to configure display and video playback.

== Description ==

Spam_BLIP helps place flash video within posts, on pages,
and in the sidebar or other widget areas (by providing a
widget). Video objects are placed and configured with
forms, so the user doesn't need to learn a shortcode or
maintain one with hand-editing. A shortcode will be visible
in the editor for posts and pages; it can be considered a
visual indication that the video is in place. The widget
does not use a shortcode. If you don't know what a shortcode
is, that's okay, Spam_BLIP does not require you to know.

Here are some features of Spam_BLIP to consider if you wonder
whether it will suit your purpose:

*	Spam_BLIP includes and uses its own
	video player program (that runs in the
	web browser's flash plugin).
	It is not designed to work
	with other flash video player programs.

*	Spam_BLIP works directly with media file (.flv, .mp4)
	URLs; that is, Spam_BLIP does *not* embed
	the video players of providers such as YouTube or Vimeo.
	Spam_BLIP is for video files which are accessible by URL,
	whether hosted at your site or off-site.
	The setup form provides two media lists:
	one offers media files (.flv, .mp4) that you can
	upload with the WordPress 'Add Media' feature,
	and one offer media files (.flv, .mp4) that are
	found in a search under the "uploads" directory
	(this allows you upload media files without using
	the WordPress PHP upload, which might have a size
	limit too low for audio/visual material). Of course,
	a URL may be placed directly in a text input field.

*	An initial image (sometimes called a "poster") that
	will display until the play button is clicked can
	(and should) be provided. The setup form provides for
	this in the same way as described above.

*	Spam_BLIP should not interfere with the appearance of
	a site: a video is presented much like an image
	(such as .png or .jpg) is, with the same sort of
	border and optional caption.

*	Spam_BLIP allows you to set the size of the
	video player window. Generally, you would want the
	aspect ratio of the window to match that of the video
	(but that is not required). The size of the player
	window does not need to match the display size of
	the video frames; the video will be scaled to fit
	the player window, maintaining the video aspect ratio
	as set by you or as implied by the width and height.
	Note that the widths of the page columns set by
	your theme's CSS limit the width of the player window.

*	Spam_BLIP allows you to set the display aspect ratio
	for the video. Some video is 'anamorphic' in that
	the pixel width and height do not match the intended
	proportion of display width and height. You might
	film your child's school play as 16:9 'widescreen'
	but use a space saving feature of your recorder that
	saves the video at 480x360 (which is not 16:9). You can
	set Spam_BLIP to display the video at the intended 16:9
	aspect ratio. You may set any aspect ratio (make it
	distorted if you wish).

*	The core features of the flash video player program
	included with Spam_BLIP have been verified to work with
	the Gnash free-software browser plugin, which is good
	because non-free binary-only software is bad. (At the
	time of this writing, Gnash does not handle the MP4
	video container format, so it is preferable that you
	prepare video in the FLV container, even using the
	h.264 and AAC codecs. Of course, you may use MP4 if
	you must.)

*	The flash video player program included with Spam_BLIP
	is written and compiled with the *Ming* PHP extension,
	and the code is included, so you may modify the player.

*	Spam_BLIP does not add any JavaScript to the pages
	generated for your visitors, which might be helpful if
	you try to keep your pages useful to those who disable
	JavaScript in their browsers. (Such visitors might need to
	explicitly enable the flash web browser plugin, but that is
	another, unavoidable, issue.) JavaScript is only used in the
	administrative interface for the forms and manipulation of
	shortcodes in the editor.

== Installation ==

Spam_BLIP is installed through the WordPress administrative interface.

1. Fetch the Spam_BLIP zip file; save it on your computer

1. Log in at your WordPress site

1. Select 'Plugins -> Add New'

1. Select 'Upload'

1. Select 'Browse'

1. In your system's file selector, select the Spam_BLIP zip file;
  select 'OK' or equivalent

1. Select 'Install Now'

1. Select 'Activate Plugin'

At this point "SWFlash Put" should be an entry on the plugins page.
The Settings menu should have an item "Spam_BLIP Plugin".

If the above is not so, there is probably a problem at your site's
host; for example if the host is Unix system there is very likely
a problem with incorrect permissions metadata (mode) on a directory
such as wp-content/uploads, or an unsuitable user or group ownership
of (probably several) files and directories. This can be a frequent
problem if the host has PHP configured in "safe mode".

== Frequently Asked Questions ==

= Is this really a FAQ? =

No.

== Screenshots ==

1. The Spam_BLIP widget setup form (bottom).

2. The Spam_BLIP posts/page setup form ('meta box') with the first
	section hidden.

3. The appearance of video placed by Spam_BLIP (Twentyeleven theme
	with dark custom colors, sidebar on left), not yet playing.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
* Initial release.
	
