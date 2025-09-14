<?php

declare(strict_types=1);

namespace App\Extractor;

use GuzzleHttp\HandlerStack;
use Http\Client\Common\Plugin\AddHostPlugin;
use Http\Client\Common\Plugin\AddPathPlugin;
use Http\Client\Common\PluginClient;
use Http\Discovery\Psr17FactoryDiscovery;
use Illuminate\Support\Facades\Storage;
use Jikan\JikanPHP\Client;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\FlysystemStorage;
use Kevinrob\GuzzleCache\Strategy\GreedyCacheStrategy;

class JikanFactory
{
    public static function create(): Client
    {
        $plugins = [];
        $uri = Psr17FactoryDiscovery::findUriFactory()->createUri('https://api.jikan.moe/v4');
        $plugins[] = new AddHostPlugin($uri);
        $plugins[] = new AddPathPlugin($uri);

        $stack = HandlerStack::create();
        $driver = Storage::drive('jikan');
        $adapter = $driver->getAdapter();
        $stack->push(new CacheMiddleware(new GreedyCacheStrategy(
            new FlysystemStorage($adapter),
            60 * 60 * 24 * 7
        )), 'cache');

        $guzzleClient = new \GuzzleHttp\Client(['handler' => $stack]);

        return Client::create(new PluginClient($guzzleClient, $plugins));
    }
}
