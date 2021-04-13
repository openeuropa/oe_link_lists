# OpenEuropa Manual Link Lists

This module allows creating manual link lists.
Upon enabling the module, the link lists of type "Manual" can be created.\
This list type can reference "link list link" (`link_list_link`) entities.
Two bundles for this entity are provided: external links and internal links.

External links are meant to point to external URLs. A title and a teaser must
be provided when creating the link.\
Internal links (labelled "Internal content") are meant to reference nodes that
exist on the website. Title and teaser can be provided as "override" (see below).

## Installation

Before enabling this module, make sure the following module is present in your codebase by adding them to your
`composer.json` and by running `composer update`:

```json
"require": {
    "drupal/composite_reference": "~1.0@alpha2",
    "drupal/entity_reference_revisions": "^1.7",
    "drupal/inline_entity_form": "~1.0-rc8"
}
```

Moreover, the following Inline Entity Form issue patch is required:

* https://www.drupal.org/project/inline_entity_form/issues/2875716

## Marking a link list link bundle as overridable
Bundles of this entity type can be marked as “overridable” to mean that the
title and teaser value are optional and they would be used to override values
that are resolved elsewhere.\
In effect, this option hides the form elements and shows a checkbox that would
make them visible if checked.

No UI is provided at the moment for adding the third party setting. This can be
achieved manually by adding the following to the bundle YAML file:
```yaml
[rest of bundle config]
third_party_settings:
  oe_link_lists_manual_source:
    override: true
```

