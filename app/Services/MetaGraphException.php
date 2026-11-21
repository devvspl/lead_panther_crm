<?php

namespace App\Services;

class MetaGraphException extends \RuntimeException
{
    public function __construct(public readonly bool $retryable, public readonly bool $expired, public readonly int $retryAfter = 300,
        public readonly ?int $httpStatus = null, public readonly ?int $metaCode = null)
    {
        parent::__construct($expired ? 'Token expired or revoked. Reconnect this Ad Account.' : ($retryable
            ? 'Meta is temporarily unavailable or rate limited. Synchronization will retry.'
            : ($metaCode === 100 ? 'Meta rejected a field, object or parameter. Check the failed request stage.' : 'Meta denied this request. Check access for the failed stage.')));
    }
}
