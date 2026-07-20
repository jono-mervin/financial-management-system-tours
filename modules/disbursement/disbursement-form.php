<?php
$qs = $_SERVER['QUERY_STRING'] ?? '';
header('Location: disbursements.php' . ($qs !== '' ? '?' . $qs : '?new=1'));
exit;
