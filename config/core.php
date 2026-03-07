<?php

declare(strict_types=1);

$ignoreMalIdsCsv = env('IGNORE_MAL_IDS_CSV', '');

return [
    'ignore_mal_ids' => $ignoreMalIdsCsv ? explode(',', $ignoreMalIdsCsv) : [],
];
