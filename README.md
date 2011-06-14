WPPO
====

WPPO creates WordPress-based websites multi-lingual by using the same infrastructure that already exists for open source projects.

How WPPO works
--------------

WPPO is a `gettext` layer for WordPress content. It uses `xml2po` lib from `gnome-doc-utils` and `gettext` libraries to allow localizing the website content.

WPPO is not a platform to translate strings and create PO files. Instead, WPPO generates POT files of all the website content and has the ability to read the filled PO files and convert them to translated pages on the web.

Creating a new language for a WordPress-based website requires translations of three parts (in parenthesis the place where PO files for each part are located):

- The WordPress itself (`wp-content/languages`)
- The current theme (`wp-content/themes/{$currentTheme}/languages`)
- The content (`wppo/static/` and `wppo/dynamic`)

Since WordPress can manage translations of its core, themes and plugins, WPPO comes to fill the gap and allow translation of the content of the website (and also allow to use different languages accordingly).

WordPress is already translated in a lot of languages. WPPO tries to download dynamically the WordPress translation strings when a new language is added.


Requirements
------------

- PHP 5.3 or later
- MySQL
- WordPress 3.0 or later
- gnome-doc-utils
- gettext

Install
-------

- Extract WPPO files in `wp-content/plugins/wppo` as usual.
- Give WordPress `ABSPATH` folder writing permissions
- Give writing permissions to `wp-content/languages`.
- Comment the line `define('WPLANG', '');` in wp-config.php
- Activate the plugin
- Generate the POT files in the _Translations_ admin panel
- Download the POT files from `wppo` folder, translate the strings and save as `ll.po` or `ll_CC.po`* in the `po` folder inside the directories `static` and `dynamic`
- Add the language in the _Translations_ panel following the same language code
- Press the button _Check for language updates_ and voilà.

_Language format should follow the same pattern from GetText:_  
 - `ll`: ISO 639 two-letter language code (lowercase)  
 - `CC`: ISO 3166 two-letter country code (uppercase)
