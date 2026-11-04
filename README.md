# README

[![Release](https://img.shields.io/github/v/release/jcdenis/pollsFactory?color=lightblue)](https://github.com/JcDenis/pollsFactory/releases)
![Date](https://img.shields.io/github/release-date/jcdenis/pollsFactory?color=red)
[![Dotclear](https://img.shields.io/badge/dotclear-v2.39-137bbb.svg)](https://fr.dotclear.org/download)
[![License](https://img.shields.io/github/license/jcdenis/pollsFactory?color=white)](https://github.com/JcDenis/pollsFactory/blob/master/LICENSE)

## ABOUT

_pollsFactory_ is a plugin for the open-source web publishing software called [Dotclear](https://www.dotclear.org).

> Create polls with multiple queries (choices lists, options boxes,
text fields) and add them to entries, pages, widgets or templates.

## REQUIREMENTS

* Dotclear 2.39
* PHP 8.1+
* Dotclear admin permissions to configure plugin
* Dotclear usage,contentadmin permissions to manage polls

## NOTICE

This plugin uses the shared table `post_option` (also used by other plugins
like _postWidgetText_). This table is not removed when the plugin is uninstalled.

Polls created with old versions of _pollsFactory_ (1.x) are kept and
their settings are migrated on update.

## USAGE

First install _pollsFactory_, manualy from a zip package or from
Dotclear plugins repository. (See Dotclear's documentation to know how do this)

Enable plugin and set main options from plugin configuration page.

When enabled you have a menu in main sidebar menu called _Polls manager_
under "Plugins" group, follow it to manage polls:

* Create a poll with a title and a description,
* Add queries from tab _Queries_ of the poll,
* Add choices to queries of type choices list or options box,
* Open or close votes, see results and select text responses
  to display on public side.

Each poll has its own public page (URL _survey/..._).

When you edit an entry or a page you have a new sidebar option
called _Polls_ to link polls to this entry.

Polls can also be displayed with widgets _Polls: list_,
_Polls: polls of an entry_ and _Polls: selected polls_,
or with template tags:

* `{{tpl:PollForm}}` : display current poll in poll page template (attributes _title_ and _description_ can be set to 1 to show them)
* `{{tpl:PollsOfEntry}}` : display polls of current entry

Voters are identified by a cookie, their IP address or both.
IP addresses are not stored, only a hash of them.

## LINKS

* [License](https://github.com/JcDenis/pollsFactory/blob/master/LICENSE)
* [Packages & details](https://github.com/JcDenis/pollsFactory/releases)
* [Sources & contributions](https://github.com/JcDenis/pollsFactory)
* [Issues & security](https://github.com/JcDenis/pollsFactory/issues)

## CONTRIBUTORS

* Jean-Christian Denis (author)

You are welcome to contribute to this code.
