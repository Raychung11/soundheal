<?php
/**
 * Legacy path — the reservation flow now lives at /public/reserve.php
 * (open to guests as well as members). Forward every query param so
 * bookmarks, Aria links, admin debug links, and share URLs from
 * before the rename all keep working.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$qs = $_SERVER['QUERY_STRING'] ?? '';
redirect('/public/reserve.php' . ($qs !== '' ? '?' . $qs : ''), 301);
