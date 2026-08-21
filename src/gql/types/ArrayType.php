<?php
namespace verbb\videopicker\gql\types;

use craft\gql\GqlEntityRegistry;

use GraphQL\Type\Definition\ScalarType;
use GraphQL\Language\AST\Node;

class ArrayType extends ScalarType
{
    // Static Methods
    // =========================================================================

    public static function getType()
    {
        return GqlEntityRegistry::getEntity(self::getName()) ?: GqlEntityRegistry::createEntity(self::getName(), new self());
    }

    public static function getName(): string
    {
        return 'ArrayType';
    }


    // Public Methods
    // =========================================================================

    public function serialize($value)
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            return $value->toArray();
        }

        return null;
    }

    public function parseValue($value)
    {
        return $value;
    }

    public function parseLiteral($valueNode, array $variables = null)
    {
        return $valueNode;
    }
}
