<?php

declare(strict_types=1);

namespace Hibi\Vendor\Jose\Component\KeyManagement\Analyzer;

use Hibi\Vendor\Jose\Component\Core\JWK;

final class KeyIdentifierAnalyzer implements KeyAnalyzer
{
    public function analyze(JWK $jwk, MessageBag $bag): void
    {
        if (! $jwk->has('kid')) {
            $bag->add(Message::medium('The parameter "kid" should be added.'));
        }
    }
}
