<?php
/*
 * TawasulChat is gone: the chat module was merged into TawasulMessenger, and
 * every chat page now lives at messenger_chat*.php under that module.
 *
 * This file exists only so bookmarks, links in older notification emails and any
 * cached menu entry keep working. It forwards to the new address and nothing
 * else: no page renders here, and nothing is served out of this folder, so the
 * module is retired in every sense that the platform looks at.
 *
 * The query string is carried across, so ?chat=123 still opens that
 * conversation rather than dropping the reader on the default one.
 */

// The base path is derived from the request rather than read from the session.
// These stubs are reachable straight off disk, before any session exists, so
// $_SESSION['absoluteURL'] is not populated yet. When the router includes this
// file the script is index.php and the base is its directory; when it is
// requested directly the base is three levels up from modules/TawasulChat.
$script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$base = basename($script) === 'index.php'
    ? dirname($script)
    : dirname(dirname(dirname($script)));

$target = rtrim($base, '/').'/index.php?q=/modules/TawasulMessenger/messenger_chat_settings.php';

$query = $_SERVER['QUERY_STRING'] ?? '';
if ($query !== '' && !str_contains($query, 'q=')) {
    $target .= '&'.$query;
}

header('Location: '.$target, true, $status);
exit;
