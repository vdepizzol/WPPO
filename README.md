WPPO
====

WPPO enables WordPress-based websites to go multi-lingual by using the same infrastructure that already exists in most of open-source projects.

How WPPO works
--------------

WPPO adds a translation layer to WordPress content. It uses [ITS Tool] [1], made with rules from the [W3C Internationalization Tag Set] [2], together with GNU's `gettext` library to allow localizing the website content using PO files.

[1]: http://itstool.org/
[2]: http://www.w3.org/TR/its/
    
WPPO is not a platform to translate strings and create PO files online. Instead, WPPO generates POT files of all the website content and has the ability to read the filled PO files and convert them to translated pages on the web.

Creating a new language for a WordPress-based website requires translations of three parts (in parenthesis the place where PO files for each part is located):

- The WordPress itself (`wp-content/languages`)
- The current theme (`wp-content/themes/{$currentTheme}/languages`)
- The content (`wppo/static/` and `wppo/dynamic`)

Since WordPress can manage translations of its core, themes and plugins, WPPO comes to fill the gap and allow translation of the content of the website (and also allow to use different languages accordingly).

WordPress is already translated into a lot of languages. WPPO tries to download dynamically the WordPress translation strings when a new language is added.

Permalinks
----------

_TODO_

Widget
------

_TODO_


Requirements
------------

- PHP 5.3 or later
- MySQL
- WordPress 3.3 or later
- itstool
- gettext
- activated support for custom permalinks in WordPress (`mod_rewrite`)

Install
-------

- Extract WPPO files in `wp-content/plugins/wppo` as usual;
- Create a folder called `wppo`in `ABSPATH` with writing permissions;
- Give writing permissions to `wp-content/languages`;
- Comment the line `define('WPLANG', '');` in `wp-config.php`;
- Activate the plugin.

Adding new languages to my website
----------------------------------

- Generate the POT files in the _Translations_ admin panel
- Download the POT files from `wppo` folder, translate the strings and save as `ll.po` or `ll_CC.po`* in the `po` folder inside the directories `static` and `dynamic`
- Add the language in the _Translations_ panel following the same language code
- Make sure to provide translations for the theme and WordPress instance
- Press the _Check for language updates_ button and voilà.

_* Language format should follow the same pattern from GetText:_  
 - `ll`: ISO 639 two-letter language code (lowercase)  
 - `CC`: ISO 3166 two-letter country code (uppercase)

Keeping track of translation updates
------------------------------------

_TODO_
