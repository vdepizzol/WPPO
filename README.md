WPPO
====

WPPO is a gettext layer for WordPress content. It uses xml2po lib from gnome-doc-utils and gettext libraries to allow any WordPress website to be full translatable using PO files.

Install
-------

WPPO requires gnome-doc-utils & gettext libraries in Linux server.

- Extract WPPO files in `wp-content/plugins/wppo` as usual.
- Give WordPress `ABSPATH` folder writing permissions
- Give writing permissions to `wp-content/languages`.
- Comment the line `define('WPLANG', '');` in wp-config.php
- Activate the plugin and voilà.
