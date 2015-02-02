parse HTML
===================

Similar with jquery but created with PHP Language.

## Version 
0.0.1


## Requirements
 - PHP 5.4
 
## Demo

<?php
$contents = file_get_contents('/home/ijortengab/my.html');
$html = new parseHTML($contents);
// Get text of title.
echo $html->find('title')->text();
// Get link of some anchor.
echo $html->find('.links')->attr('href');
?>
