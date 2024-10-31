=== Imotiv Conversations ===
Contributors: imotiv nuffnangx, hocklai8
Tags: imotiv, nuffnang, comments, conversation
Requires at least: 3.0
Tested up to: 3.5
Stable tag: 1.0.7
License: GPLv2 or later

Imotiv Conversations replaces your WordPress comment system with your comments and conversations on Imotiv.

== Description ==

Imotiv Conversations WordPress plugin provides an easy integration of Imotiv Conversations and syncing 
with WordPress comments.

= What Is Imotiv? =

* Imotiv is a mobile blog reader with a social layer that allows you to find, follow and communicate with blogs.
* Users can follow blogs in a news feed format just like how they would follow people on Twitter.
* Users can also favourite blog posts they like and communicate directly with bloggers.

= Imotiv Conversations =

* Imotiv Conversations allows direct communication between blogger and readers.
* Whenever someone comments on a blog, the blogger will receive it on their mobile phone via push notifications.
* When the blogger responds, the reader will also receive it on their mobile phone.

= Imotiv WordPress Plugin =

* Installs Imotiv Conversations without editing your WordPress template.
* Automatically synchronize (imports) Imotiv Conversations to your WordPress comments.
* Exports your existing WordPress comments to Imotiv Conversations.

PS: You'll need a Imotiv account and [Imotiv API key](http://www.imotiv.ly/) to use it. API Keys are free!

== Installation ==

1. From your WordPress blog admin, go to Plugins > Add New > Upload and upload the zipped Imotiv plugin file 
2. Activate the plugin
3. Click on Comments > Imotiv and enter your Imotiv API key.
4. Click on Export Comments to sync your WordPress comments to Imotiv Conversation

* Your API Key can be obtained from [Imotiv](http://www.imotiv.ly), clicking on your username at the top-right hand corner of the screen, and select 'My blogs'.
* Click on 'View Code' of your blog and expand the Wordpress Plugin method. The API Key is in step 3.

== Changelog ==

= 1.0.7 =
* Moved domain to www.imotiv.ly

= 1.0.6 =
* Export WP comments with post title
* Add upgrade plugin function and version

= 1.0.5 =
* Fix multiple conversation being loaded on home/index page
* Fix zero WP comments to export

= 1.0.4 =
* Overwrite WP auto close comment option for blogpost
* Fix conversation not loading on pages
* Exclude deleted post's comments from being exported

= 1.0.3 =
* Change blog URL parameters to use home_url() instead of site_url()
* Update instruction guide to obtain API Key
* Comment count failover to WP comment count

= 1.0.2 =
* Add instruction guide to obtain API Key

= 1.0.1 =
* Check for duplicate comments already in WP comments during import
* Increase sync timeout duration

= 1.0.0 =
* First release