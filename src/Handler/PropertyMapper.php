<?php

declare(strict_types=1);

namespace JsonMapper\Handler;

use JsonMapper\Enums\Visibility;
use JsonMapper\Helpers\TypeHelper;
use JsonMapper\JsonMapperInterface;
use JsonMapper\ValueObjects\PropertyMap;
use JsonMapper\Wrapper\ObjectWrapper;

class PropertyMapper
{
    public function __invoke(
        \stdClass $json,
        ObjectWrapper $object,
        PropertyMap $propertyMap,
        JsonMapperInterface $mapper
    ): void {
        $values = (array) $json;
        foreach ($values as $key => $value) {
            if (! $propertyMap->hasProperty($key)) {
                continue;
            }

            $propertyInfo = $propertyMap->getProperty($key);
            $type = $propertyInfo->getType();

            if (TypeHelper::isArray($type, $innerType)) {
                $value = array_map(function ($value) use ($mapper, $innerType) {
                    return self::mapPropertyValue($mapper, $innerType, $value);
                }, $value);
            } else {
                $value = self::mapPropertyValue($mapper, $type, $value);
            }

            if ($propertyInfo->getVisibility()->equals(Visibility::PUBLIC())) {
                $object->getObject()->$key = $value;
                continue;
            }

            $setterMethod = 'set' . ucfirst($key);
            if (method_exists($object->getObject(), $setterMethod)) {
                $object->getObject()->$setterMethod($value);
            }
        }
    }
    
    private static function mapPropertyValue(JsonMapperInterface $mapper, string $type, $value)
    {
        if (TypeHelper::isBuiltinClass($type)) {
            return new $type($value);
        }
        if (TypeHelper::isScalarType($type)) {
            return TypeHelper::cast($value, $type);
        }
        if (TypeHelper::isCustomClass($type)) {
            $instance = new $type();
            $mapper->mapObject($value, $instance);
            return $instance;
        }

        // @codeCoverageIgnoreStart
        return null;
        // @codeCoverageIgnoreEnd
    }
}
