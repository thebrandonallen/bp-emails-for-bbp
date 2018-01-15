# BP Emails for BBP #
**Contributors:** [thebrandonallen](https://profiles.wordpress.org/thebrandonallen)  
**Donate link:** https://brandonallen.me/donate/  
**Tags:** bbpress, buddypress, email  
**Requires at least:** 4.5.0  
**Tested up to:** 4.9.1  
**Requires PHP:** 5.3  
**Stable tag:** 0.2.3  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/old-licenses/gpl-2.0.html  

Send bbPress forum and topic subscription emails using Buddypress' email API.

## Description ##

>This plugin requires both bbPress 2.5+ and BuddyPress 2.5+.

Send bbPress forum and topic subscription emails using Buddypress' email API. Now you have pretty, customizable, HTML emails for your forums.

Note that BP Emails for BBP sends emails differently than bbPress. bbPress places every subscriber email in the BCC field, and sends one email. BP Emails for BBP, asynchronously, sends one email per subscriber. This may help improve email deliverability if you have forums or topics with a large number of subscribers. Email providers don't always look favorably on emails with large BCC fields. At the very least, this should improve page load time when posting a new topic or reply.

## Installation ##

Through the Dashboard.

1. In your Dashboard go to Plugins > Add New.
1. Search for `bp-emails-for-bbp`.
1. Click "Install."
1. Activate the plugin.

Manually.

1. Download the plugin and unzip.
1. Upload the `bp-emails-for-bbp` directory to the `/wp-content/plugins/` directory.
1. Activate the BP Emails for BBP through the 'Plugins' menu in WordPress

## Frequently Asked Questions ##

### Where can I find the settings? ###

Settings? We don't need no stinking settings.

## Changelog ##

### 0.2.3 ###
* Release date: 2018-01-15
* Update compatibility to 4.9.1

### 0.2.2 ###
* Release date: 2017-06-15
* Update compatibility to WordPress 4.8

### 0.2.1 ###
* Release date: 2017-04-30
* Fix incorrect file path, causing sites to crash. Sorry :(

### 0.2.0 ###
* Release date: 2017-04-29
* Refactored loading of plugin code to be faster and leaner.
* Refactored email installation.

### 0.1.1 ###
* Release date: 2016-05-26
* Fixes an issue where a topic or reply author might receive a notification of their own topic or reply if they're the first in the list of subscribers.

### 0.1.0 ###
* Release date: 2016-05-12
* Initial release.
