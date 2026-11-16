<?php
declare(strict_types=1);

namespace Quran\Http;

interface HttpClientInterface
{
    /**
     * Perform a GET request.
     *
     * @return array{status:int,body:string,error:string}
     */
    public function get(string $url): array;
}
