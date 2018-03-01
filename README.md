# FurAffinity-API-PHP
Class for work with [FurAffinity.Net](https://furaffinity.net/)<br />
Example: [example.php](https://github.com/d1KdaT/FurAffinity-API-PHP/blob/master/example.php)
# How to use
#### Include:
```php
require_once("furaffinity.class.php");
```
#### Initialization:
```php
$settings = array(
  "username" => "PUT_YOUR_USERNAME",
  "a" => "PUT_YOUR_A_COOKIE",
  "b" => "PUT_YOUR_B_COOKIE"
);

$fa = new FurAffinityAPI($settings);
```
#### Use:
```php
$submission = $fa->getById(22872063);
echo $submission["author"]; // Falvie
```
