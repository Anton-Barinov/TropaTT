<?php
declare(strict_types=1);

$dict = require __DIR__ . '/../gateway.php';
$flattened = [];

foreach ($dict as $section => $entries) {
    if (is_array($entries)) {
        foreach ($entries as $k => $v) {
            $flattened[$section . '.' . $k] = $v;
        }
    }
}

return $flattened;
