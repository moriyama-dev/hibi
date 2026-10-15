<?php

declare(strict_types=1);

namespace Hibi\Vendor\SpomkyLabs\Pki\ASN1\Type\Primitive;

use Hibi\Vendor\SpomkyLabs\Pki\ASN1\Component\Identifier;
use Hibi\Vendor\SpomkyLabs\Pki\ASN1\Component\Length;
use Hibi\Vendor\SpomkyLabs\Pki\ASN1\Element;
use Hibi\Vendor\SpomkyLabs\Pki\ASN1\Exception\DecodeException;
use Hibi\Vendor\SpomkyLabs\Pki\ASN1\Feature\ElementBase;
use Hibi\Vendor\SpomkyLabs\Pki\ASN1\Type\PrimitiveType;
use Hibi\Vendor\SpomkyLabs\Pki\ASN1\Type\UniversalClass;

/**
 * Implements *End-of-contents* type.
 */
final class EOC extends Element
{
    use UniversalClass;
    use PrimitiveType;

    private function __construct()
    {
        parent::__construct(self::TYPE_EOC);
    }

    public static function create(): self
    {
        return new self();
    }

    protected function encodedAsDER(): string
    {
        return '';
    }

    protected static function decodeFromDER(Identifier $identifier, string $data, int &$offset): ElementBase
    {
        $idx = $offset;
        if (! $identifier->isPrimitive()) {
            throw new DecodeException('EOC value must be primitive.');
        }
        // EOC type has always zero length
        Length::expectFromDER($data, $idx, 0);
        $offset = $idx;
        return self::create();
    }
}
