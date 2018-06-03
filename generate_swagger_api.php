<?php

require("../vendor/autoload.php");
$swagger = \Swagger\scan('../api');
header('Content-Type: application/json');
echo $swagger;


?>