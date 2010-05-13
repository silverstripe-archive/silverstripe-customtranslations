Introduction
============

The `customtranslations` module lets you override the default translations for strings provided by SilverStripe. It
provides an admin interface titled "Translations", and lets you add and manage language overrides.

The translation overrides are stored in the database. The core translations are in PHP and are not changed.

Installation
============

Dependencies
------------
This depends on a minimum of Sapphire 2.4.1.

Installation Instructions
=========================
* Install the module directory into the top-level directory of your SilverStripe project.
* Perform a dev/build?flush=all


How to Use
==========
The module installs a new Admin interface called Translations. This lets you search the translations, and will search
through all common locales unless you provide an explicit locale.

The admin interface lets you override individual translations strings. When you override a translation, it will show
you the values overridden.

If you delete a custom translation, the system will revert back to the built-in translation.

When you create or edit a custom translation, it must have the same % tokens that the original has, in the same order.
e.g. if the original translation is "%s owes you %d pieces of gold", any custom translation of this string must
contain %s and %d, in that order. Validation rules enforce this.

Known Issues
============

* Doesn't load translations outside the 'common' ones defined in i18n.

* If _t is called before customtranslations/_config.php, the translations won't be loaded for the locales used. Generally
  calling _t before Director is called is a bad idea anyway.

* After deleting an override, the list doesn't refresh properly.

* Validation of % tokens in custom translations only understands %s and %d, and doesn't handle cases like %5d. It does
  handle %%, which is treated as a literal, and doesn't have to match.
