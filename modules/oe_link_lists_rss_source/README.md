# OpenEuropa RSS Link Lists

This module provides support for dynamic link lists extracted from an RSS source. To this end, it uses the core Aggregator module to import the RSS feeds that will be displayed inside the link lists.

Beware that with the default configuration, Drupal discards entries older than 3 months. You should change that in case you are importing feed items that are older than that.

## Access

In order to allow users of the website to see the RSS feed items, you have to grant them the correct permission.
The Aggregator module contains only one permission for accessing feeds and items, namely `View news feeds`.
Granting this permission to users gives access to view the feed items but also exposes a set of routes related to the feed entity itself (which you may not want).

### Access permission-based solution

To solve this issue, we ship with an optional sub-module called "OpenEuropa Aggregator item access" (`oe_link_lists_aggregator_item_access`).\
It provides the permission `view feed items` which grants users the capability to view feed item entities.

## Installation

Before enabling this module, make sure the following dependencies are present in your codebase by adding them to your
`composer.json` and by running `composer update`:

```json
"require": {
    "drupal/multivalue_form_element": "^1",
}
```
