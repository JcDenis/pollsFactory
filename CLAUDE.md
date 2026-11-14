# pollsFactory

Notes for AI assistants working on the Dotclear plugin pollsFactory.

**Read first** the shared conventions of the author's Dotclear modules:
https://raw.githubusercontent.com/JcDenis/gh2da/master/CONVENTIONS.md
(if a web tool refuses the address, use `curl -s` on it from the shell).
The rules below take precedence for this module.

## Module

Create polls with multiple queries (choices lists, options boxes, text fields) and add them to entries, pages, widgets or templates. A user guide with screenshots is available in French: [guide d'utilisation](https://github.com/JcDenis/pollsFactory/blob/master/docs/fr/utilisation.md).

Admin page and configuration page. Public side: widgets, URL handlers.

Required Dotclear and PHP versions: see `_define.php`.

## Structure

* `src/ActionsPolls.php`: polls list actions.
* `src/ActivityReportAction.php`: add polls actions to the plugin activity report.
* `src/Backend.php`: admin side: menu entry, favorites, behaviors registration.
* `src/BackendBehaviors.php`: admin behaviors.
* `src/Config.php`: configuration page of the module (plugins manager).
* `src/FilterPolls.php`: polls list filters.
* `src/Frontend.php`: public side: behaviors, template tags and URL handlers registration.
* `src/FrontendBehaviors.php`: public behaviors.
* `src/FrontendTemplate.php`: template tags.
* `src/FrontendUrl.php`: public URL handler.
* `src/Install.php`: install and update, database structure (a change needs a migration here).
* `src/ListingPolls.php`: polls list.
* `src/Manage.php`: admin page of the module.
* `src/ManagePoll.php`: admin page (poll).
* `src/ManageResults.php`: admin page (results).
* `src/My.php`: module helpers (`My::id()`, settings, permissions).
* `src/Polls.php`: polls are entries of type "pollsfactory".
* `src/Prepend.php`: loaded on every request: autoload, URL handlers, permission types.
* `src/Render.php`: public rendering.
* `src/Uninstall.php`: cleaners for the plugin Uninstaller.
* `src/Widgets.php`: widgets definition and rendering.
* `default-templates/`: public templates.
* Database table `post_option`.
* URL handlers: `pollsfactory_page`, `pollsfactory_preview`.
* Widgets: `pollsFactoryList`, `pollsFactoryEntry`, `pollsFactorySelected`.
* Behaviors used: `initWidgets`, `adminDashboardFavoritesV2`, `adminColumnsListsV2`, `adminFiltersListsV2`, `adminBeforePostDelete`, `adminBeforePageDelete`, `adminPostFormItems`, `adminPageFormItems`, `adminAfterPostCreate`, `adminAfterPostUpdate`, `adminAfterPageCreate`, `adminAfterPageUpdate`, `publicHeadContent`, `publicBeforeDocumentV2`, `publicEntryBeforeContent`, `publicEntryAfterContent`.

## Pitfalls

* Table structure changes need a migration in `src/Install.php` (version compare), and must work on MySQL, PostgreSQL and SQLite.

## Testing

* Check the admin page, the configuration page, the widgets (admin and public), the public URLs, the admin help, in English and French.
* CI browses the common admin and public pages; extra pages can be listed in `.github/dotclear-test.txt`.
* The user guide `docs/fr/utilisation.md` describes the features: update it, and its screenshots, when the interface changes.
