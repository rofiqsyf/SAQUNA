<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\OperatorRepository;

Auth::requireLogin();
Auth::requireOperator();

header('Content-Type: application/json');

$mk_id = isset($_GET['mk_id']) ? (int)$_GET['mk_id'] : 0;

$repo = new OperatorRepository();
$prasyarat = $repo->getPrasyaratMk($mk_id);

echo json_encode($prasyarat);
