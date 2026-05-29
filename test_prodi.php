<?php
require 'autoload.php';
$repo = new \Src\OperatorRepository();
echo json_encode($repo->getAllProdi());
