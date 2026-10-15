<?php

declare(strict_types=1);

namespace Hibi\Vendor\Jose\Component\Signature;

use Hibi\Vendor\Jose\Component\Core\AlgorithmManagerFactory;

class JWSBuilderFactory
{
    public function __construct(
        private readonly AlgorithmManagerFactory $signatureAlgorithmManagerFactory
    ) {
    }

    /**
     * This method creates a JWSBuilder using the given algorithm aliases.
     *
     * @param string[] $algorithms
     */
    public function create(array $algorithms): JWSBuilder
    {
        $algorithmManager = $this->signatureAlgorithmManagerFactory->create($algorithms);

        return new JWSBuilder($algorithmManager);
    }
}
