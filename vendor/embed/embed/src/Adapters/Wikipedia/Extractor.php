<?php
declare(strict_types = 1);

namespace Embed\Adapters\Wikipedia;

use Embed\Extractor as Base;
use Embed\Http\Crawler;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

class Extractor extends Base
{
    private Api $api;

    public function __construct(UriInterface $uri, RequestInterface $request, ResponseInterface $response, Crawler $crawler)
    {
        parent::__construct($uri, $request, $response, $crawler);

        $this->api = new Api($this);

        $this->title = new Detectors\Title($this);
        $this->description = new Detectors\Description($this);
    }

    public function getApi(): Api
    {
        return $this->api;
    }
}
