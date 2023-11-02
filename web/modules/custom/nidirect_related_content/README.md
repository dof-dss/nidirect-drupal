# Related Content Manager service

## How to use

Inject the service into your class or create an instance statically using
```php
$content_service = \Drupal::service('nidirect_related_content.manager');
````
To return content you call the methods on the service in the sequence:
Get -> For -> As

Examples:

```php
$content = $content_service
  ->getSubThemesAndNodes()
  ->forNode()
  ->asRenderArray();
```

```php
$content = $content_service
  ->getSubThemes()
  ->forTheme()
  ->excludingCurrentTheme()
  ->asRenderArray();
```


## Service methods

### getSubThemesAndNodes()
Return sub-terms and nodes for a theme term.
### getSubThemes()
Return sub-terms for theme term.
### getNodes()
Return nodes for theme term.
### forTheme()
Either pass in a term id or leave null and the method will try to extract
theme term from the current request.
### forNode()
Either pass in a node id or leave null and the method will try to extract a
node from the current request.
### excludingCurrentTheme()
Remove the term id from the current request from the results.
### asArray()
Return the results as an array.
### asRenderArray()
Return the results as a Drupal item list render array.

## Term and node selection

The service uses 2 Views, Related Content Manager - Content and Related Content
Manager - Terms to generate results.

The service returns all published sub-terms for a term ID that do not have a
dedicated landing page node. Should a sub-term have a published landing page
(node) it the term will be replaced with this node data.

Nodes of type
- Application (1,2)
- Article (1,2)
- External link (1,2)
- Health condition (2)
- Publication (2)
- Webform (1,2)

(1) returned if the node field_subtheme (Theme/subtheme) matches the service
term ID.

(2) returned if the node field_site_themes (Supplementary subthemes) matches
the service term ID.
