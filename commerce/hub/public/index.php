<?php

declare(strict_types=1);

use Commerce\Core\Support\SystemClock;
use Commerce\Hub\App;
use Commerce\Hub\Http\NativeSession;
use Commerce\Hub\Http\Request;

require __DIR__ . '/../bootstrap.php';

$env = hub_env();
$req = Request::fromGlobals();
$secure = $req->secure || ($env['FORCE_SECURE_COOKIES'] ?? '') === '1';
// API calls are stateless; only the dashboard gets a session cookie.
$session = str_starts_with($req->path, '/api/') ? new Commerce\Hub\Http\ArraySession() : new NativeSession($secure);
(new App(hub_db($env), new SystemClock(), $session, $env))->handle($req)->send();
