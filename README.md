# parseHTML

parseHTML is PHP library working like jQuery, give you an easy way to get any information from text html. 

### Requirement
PHP 5.4

### Usage

If you familiar with jQuery, you'll be smile.

 jQuery                                       | parseHTML
----------------------------------------------|---------------------------------------------
document                                      | $contents = file_get_contents('~/my.html');
$html = $(document);                          | $html = new parseHTML($contents);
var title = $html.find('title').text();       | $title = $html->find('title')->text();
var url = $html.find('a.links').attr('href'); | $url = $html->find('a.links')->attr('href');

### Method Support

 Method                    | Description
-------------------------- | --------------------------------------------
::find()                   | http://api.jquery.com/find/
::html()                   | http://api.jquery.com/html/
::text()                   | http://api.jquery.com/text/
::attr()                   | http://api.jquery.com/attr/
::eq()                     | http://api.jquery.com/eq/
::getElementById()         | https://developer.mozilla.org/en-US/docs/Web/API/Document/getElementById
::getElementByClass()      | https://developer.mozilla.org/en-US/docs/Web/API/Document/getElementsByClassName
::getElementByTag()        | https://developer.mozilla.org/en-US/docs/Web/API/Element/getElementsByTagName
::getElementByAttribute()  | https://developer.mozilla.org/en-US/docs/Mozilla/Tech/XUL/Method/getElementsByAttribute

### Selector Support

You can find more description by visit this link
http://api.jquery.com/category/selectors/


 Selector                            | Example
------------------------------------ | -----------------------
ID selector                          | #my-table-1
Class Selector                       | .links'
Element Selector                     | element
Descendant Selector                  | ancestor descendant
Child Selector                       | parent > child
Attribute                            | [name]
Attribute Contains Prefix Selector   | [name|='value']
Attribute Contains Selector          | [name*='value']
Attribute Contains Word Selector     | [name~='value']
Attribute Ends With Selector         | [name$='value']
Attribute Equals Selector            | [name='value']
Attribute Not Equal Selector         | [name!='value']
Attribute Starts With Selector       | [name^='value']

Of course, you can mix all selector above, example:
 - #form-register.front input
 - a.links[ref='nofollow']
 - div.office > span.address
