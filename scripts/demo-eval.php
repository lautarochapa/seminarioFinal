<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$expression = $argv[1] ?? '';
if ($expression === '') {
    fwrite(STDERR, "Missing expression.\n");
    exit(1);
}

$value = eval('return '.$expression.';');

if (is_bool($value)) {
    echo $value ? 'yes' : 'no';
    exit(0);
}

if ($value === null) {
    echo '';
    exit(0);
}

if (is_array($value) || is_object($value)) {
    echo json_encode($value);
    exit(0);
}

echo (string) $value;
