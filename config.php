<?php

//declare(strict_types=1);

/**
 * @env SHELL_BROWSER="C:\Program Files (x86)\Google\Chrome\Application\chrome.exe"
 * @env SHELL_BROWSERPROFILE="%USERPROFILE%\temp\cryodriftbrowser"
 * @env SHELL_BROWSERAGENT="cryodrift/shell"
 */

use cryodrift\fw\Core;

if (!isset($ctx)) {
    $ctx = Core::newContext(new \cryodrift\fw\Config());
}

$cfg = $ctx->config();
$cfg[\cryodrift\shell\Cli::class] = [
  'browser' => Core::env('SHELL_BROWSER'),
  'agent' => Core::env('SHELL_BROWSERAGENT'),
  'browserprofile' => Core::env('SHELL_BROWSERPROFILE'),
];

\cryodrift\fw\Router::addConfigs($ctx, [
  'shell' => \cryodrift\shell\Cli::class,
], \cryodrift\fw\Router::TYP_CLI);
