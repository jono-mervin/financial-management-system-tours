<?php
/** Legacy form URL — open create modal on the invoices list */
$qs = isset($_GET['id']) ? '?edit=' . (int)$_GET['id'] : '?new=1';
header('Location: journal-entries.php' . $qs);
exit;
