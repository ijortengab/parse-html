# parse HTML

parse HTML is similar with jQuery, an easy way for you to get any information from html page. Build with jQuery's style of syntax and API.

### Version
0.0.1

### Requirement
PHP 5.4

### Usage

If you familiar with jQuery, you'll be smile.

```php
<?php
// Grab html string.
$contents = file_get_contents('/home/ijortengab/my.html');
// Build object.
$html = new parseHTML($contents);
// Get text of title.
echo $html->find('title')->text();
// Get link of some anchor.
echo $html->find('.links')->attr('href');
// Get url action in form element.
echo $html->find('form#id.class1.class2')->attr('action');
// Build a child object.
$ul = $html->find('ul.links');
// Print
echo $ul->html();
?>
```
