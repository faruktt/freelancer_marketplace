<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$me = require_login();
$target = panel_url($me);

$qs = $_GET;
unset($qs['view']);
if ($me['role'] !== 'seller') {
    unset($qs['new_gig'], $qs['edit_gig']);
}
$query = http_build_query($qs);
header('Location: ' . $target . ($query !== '' ? '?' . $query : ''));
exit;
