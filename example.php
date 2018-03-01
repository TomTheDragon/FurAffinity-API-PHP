<?php
require_once("furaffinity.class.php");

$settings = array(
	"username" => "PUT_YOUR_USERNAME",
	"a" => "PUT_YOUR_A_COOKIE",
	"b" => "PUT_YOUR_B_COOKIE"
);

try
{
	$fa = new FurAffinityAPI($settings);
}
catch(Exception $e)
{
	echo get_class($e).": ".$e->getMessage();
}

var_dump($fa->getById(22872063));

/*
stdout:
array(4) {
	["author"]=>
	string(6) "Falvie"
	["username"]=>
	string(6) "falvie"
	["file"]=>
	string(74) "http://d.facdn.net/art/falvie/1489335347/1489335347.falvie_tayruufinsm.png"
	["title"]=>
	string(10) "Fox Shrine"
}
*/
