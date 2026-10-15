<?php

declare(strict_types=1);

namespace Hibi\Vendor\SpomkyLabs\Pki\CryptoTypes\AlgorithmIdentifier\Signature;

use Hibi\Vendor\SpomkyLabs\Pki\CryptoTypes\AlgorithmIdentifier\AlgorithmIdentifier;
use Hibi\Vendor\SpomkyLabs\Pki\CryptoTypes\AlgorithmIdentifier\Feature\SignatureAlgorithmIdentifier;
use Hibi\Vendor\SpomkyLabs\Pki\CryptoTypes\AlgorithmIdentifier\SpecificAlgorithmIdentifier;

/**
 * Base class for signature algorithms employing RSASSA.
 */
abstract class RSASignatureAlgorithmIdentifier extends SpecificAlgorithmIdentifier implements SignatureAlgorithmIdentifier
{
    public function supportsKeyAlgorithm(AlgorithmIdentifier $algo): bool
    {
        return $algo->oid() === self::OID_RSA_ENCRYPTION;
    }
}
