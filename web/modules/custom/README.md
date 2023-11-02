[![CircleCI](https://circleci.com/gh/dof-dss/nidirect-site-modules.svg?style=svg)](https://circleci.com/gh/dof-dss/nidirect-site-modules)

# NI Direct custom modules

This repository houses the custom modules required for the NI Direct website.

## Breadcrumbs
- Custom navigation breadcrumbs.

## Cold Weather Payments
- Weather station entity.
- Cold Weather Period field, widget and formatter.
- Search form.
- Search block and token.
- Cold Weather Payments REST API.

## Common
- Cron job for audit date updates.
- Save top_level_theme to node on save.
- Invalidate taxonomy cache tags on node sav or deletion.
- Add additional guidance on LinkIt to WYSIWYG editor.
- Display singular/plural header on Recipes search View.
- Display singular/plural header on Publications search View.
- Provide banner field for application, article, publication and
  health_condition entities.
- Description added to theme/subtheme field for landing pages.
- Switch between entity embed view modes for edit and view conditions.
- Site search block.
- D8NID-347 : Various tweaks to publication search page.
- D8NID-373 : Add entries for site footer links.
- D8NID-426 : Redirect theme taxonomy term urls to landing pages.
- D8NID-460 : Warn user if landing page for subtheme already exists.
- D8NID-471 : Hide top level theme field from node form.
- D8NID-478 : Entity browser - hide authored by, authored on, URL alias, and
  revision log fields.
- D8NID-480 : Adjusted the View and CSS to tweak the Entity browser display
  to prevent overflows.
- D8NID-479 : Entity browser - disable select button until entity is selected.
- D8NID-490 : CKEditor: Improve UX for adding a map (location).
- D8NID-571 : Add headless JS tests to CircleCI to guard against known config
  import issues.
- D8NID-617 : Flags, checkboxes, help text changes.
- D8NID-601 : Linkit: remove option to open link in a new window.
- D8NID-220 : Featured content blocks for homepage.
- D8NID-556 : Allow editors to select from a list of predefined title values
  for telephone plus entries.
- D8NID-635 : JS to rewrite links as spans if current url matches link href.

## Contacts
- AtoZ block.
- Listing controller.
- Tweaks to exposed View form.
- Update View Query to alter query conditions.
- Process View display to alter sort links.

## Custom Blocks
- Article teasers by Topic block.

## Driving Instructor
- Removes sort/order by options from exposed form.
- Display singular/plural header on search View.
- Disable Driving Instructor title field and generate title on node save.
- Prevent selection of parent category terms on node edit form.
- Remove second portion of the postcode if present on search query.
- Hide empty text, result count and sort options on initial display of search
  View.
- Provide custom sort links based on url parameters.

## Error Pages
- Provides template suggestions for error pages.

## GP
- GP entity.
- GP entity forms.
- GP entity and access controllers.
- Lookup lead and member GPs for practices.
- GPs field for practices.
- Disable Practice title field and generate title on node save.
- Validation on Practice form for practice pr surgery name.
- Provides default and hides title field for appointment and prescription
  links on GP Practice form.
- Adds result count and form tweaks to View display.

## Health Conditions
- Blocks for AtoZ and Related Conditions.
- Health Conditions listing controller.
- Update exposed View form with reset link.
- Add summary and related conditions to node view.
- Update node title if viewing alternative health condition.

## Hospital Waiting Times
- Fetch waiting times from API.
- Setting form for configuring API calls.
- Ultimate Cron job for calling API.
- Token for displaying waiting times.
- Theme hook and twig template for displaying waiting times.

## Money Advice Articles
- Migration API configuration for importing Money Advice Service articles.
- Ultimate Cron job calling Migration API.
- Migration process function to alter article fields.

## News
- News listings controller.
- Change date published label.

## Related Content
- Blocks for Recipes by Course, ingredient, season, special diet.
- Display related content on View.
- Set View title to parent theme title.

## Search
- Query adjustments for local dev:
  - Lando works with LANGUAGE_UNDEFINED spellcheck but not with EN. Could be
    env specific so restricted to that environment.

> NB: Review of this recommended once hosting environment is available.

## School Closures
- School closures service and interface.
- C2KSchools closure service.
- School closures settings form.
- Theme hook and twig template.
- School closures Token.
- JS for filtering closure results.

## Webforms
- Webform handler for Quiz Results.
- Theme hooks and twig templates for Quiz Results.
