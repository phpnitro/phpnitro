<?php

require __DIR__ . '/vendor/autoload.php';

// Single place that pins the SQLite path — every caller (Kernel, or a page
// that instantiates a Repository directly, bypassing Kernel entirely) loads
// the backend through this file instead of vendor/autoload.php directly, so
// packages/database never falls back to guessing a path from getcwd().
\Engine\Database\Database::useSqlitePath(__DIR__ . '/var/data.sqlite');
