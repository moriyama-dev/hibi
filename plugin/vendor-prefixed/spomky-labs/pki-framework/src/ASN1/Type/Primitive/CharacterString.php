<?php

declare(strict_types=1);

namespace Hibi\Vendor\SpomkyLabs\Pki\ASN1\Type\Primitive;

use Hibi\Vendor\SpomkyLabs\Pki\ASN1\Type\PrimitiveString;
use Hibi\Vendor\SpomkyLabs\Pki\ASN1\Type\UniversalClass;

/**
 * Implements *CHARACTER STRING* type.
 */
final class CharacterString extends PrimitiveString
{
    use UniversalClass;

    private function __construct(string $string)
    {
        parent::__construct(self::TYPE_CHARACTER_STRING, $string);
    }

    public static function create(string $string): self
    {
        return new self($string);
    }
}
