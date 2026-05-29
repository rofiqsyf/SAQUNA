<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;

Auth::logout();
header("Location: login.php");
exit;
