<?php

declare(strict_types=1);

namespace App\Http\Cache;

use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;

class HttpCacher
{
    private $client;

    public function __construct()
    {
        $this->client = new Client;
    }

    public function getLocalPath(
        string $url,
        string $localPath,
        Filesystem $storage,
        CarbonImmutable $expiry
    ): string {
        if ($storage->exists($localPath) && $expiry->getTimestamp() < $storage->lastModified($localPath)) {
            return $storage->path($localPath);
        }

        $responseBody = Http::setClient($this->client)
            ->throw()
            ->get($url)
            ->body();

        $storage->put($localPath, $responseBody);

        return $storage->path($localPath);
    }
}
