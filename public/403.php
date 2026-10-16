<?php
/** GiftAura - "access denied" page used by Apache (ErrorDocument 403) for blocked folders. */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

abort(403);
