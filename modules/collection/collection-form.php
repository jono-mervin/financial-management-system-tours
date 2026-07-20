<?php
$qs = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ../accounts-receivable/collection-form.php' . ($qs !== '' ? '?' . $qs : ''), true, 301);
exit;
