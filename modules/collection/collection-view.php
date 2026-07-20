<?php
$qs = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ../accounts-receivable/collection-view.php' . ($qs !== '' ? '?' . $qs : ''), true, 301);
exit;
