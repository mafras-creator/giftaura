<?php
/** GiftAura - "page not found" page used by Apache (ErrorDocument 404). */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

abort(404);
