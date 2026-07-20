<?php
$qs = $_SERVER['QUERY_STRING'] ?? '';
header('Location: collections.php' . ($qs !== '' ? '?' . $qs : '?new=1'));
exit;
