<?php
require_once __DIR__ . '/../vendor/autoload.php';
$classes = get_declared_classes();
$spreadsheet = array_filter($classes, fn($c) => strpos($c, 'PhpOffice') !== false);
echo count($spreadsheet) > 0 ? 'PhpSpreadsheet OK' : 'PhpSpreadsheet NO encontrado';
?>