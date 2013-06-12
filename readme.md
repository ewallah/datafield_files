Place this folder into your moodle installation under the [site_root]/mod/data/field folder.

Moodle doesn't completely handle language strings for 3rd party database fields well. So you should add these stings to the bottom of [site_root]/mod/data/lang/en/data.php
$string['files'] = 'Files';
$string['namefiles'] = 'Files';