<?php

declare(strict_types=1);

namespace JsonMapper\Middleware;

use JsonMapper\Builders\PropertyBuilder;
use JsonMapper\Enums\Visibility;
use JsonMapper\JsonMapperInterface;
use JsonMapper\ValueObjects\AnnotationMap;
use JsonMapper\ValueObjects\PropertyMap;
use JsonMapper\ValueObjects\PropertyType;
use JsonMapper\Wrapper\ObjectWrapper;
use Psr\SimpleCache\CacheInterface;

class DocBlockAnnotations extends AbstractMiddleware
{
    private const DOC_BLOCK_REGEX = '/@(?P<name>[A-Za-z_-]+)[ \t]+(?P<value>[\w\[\]\\\\|]*).*$/m';

    /** @var CacheInterface */
    private $cache;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    public function handle(
        \stdClass $json,
        ObjectWrapper $object,
        PropertyMap $propertyMap,
        JsonMapperInterface $mapper
    ): void {
        $propertyMap->merge($this->fetchPropertyMapForObject($object));
    }

    private function fetchPropertyMapForObject(ObjectWrapper $object): PropertyMap
    {
        if ($this->cache->has($object->getName())) {
            return $this->cache->get($object->getName());
        }

        $properties = $object->getReflectedObject()->getProperties();
        $intermediatePropertyMap = new PropertyMap();

        foreach ($properties as $property) {
            $name = $property->getName();
            $docBlock = $property->getDocComment();
            if ($docBlock === false) {
                continue;
            }

            $annotations = self::parseDocBlockToAnnotationMap($docBlock);

            if (! $annotations->hasVar()) {
                continue;
            }

            $type = $annotations->getVar();
            $nullable = stripos('|' . $type . '|', '|null|') !== false;
            $cleanedType = str_replace(['null|', '|null'], '', $type);

            $isArray = substr($cleanedType, -2) === '[]';
            if ($isArray) {
                $cleanedType = substr($cleanedType, 0, -2);
            }

            $property = PropertyBuilder::new()
                ->setName($name)
                ->setType($cleanedType)
                ->setIsNullable($nullable)
                ->setVisibility(Visibility::fromReflectionProperty($property))
                ->setIsArray($isArray)
                ->build();
            $intermediatePropertyMap->addProperty($property);
        }

        $this->cache->set($object->getName(), $intermediatePropertyMap);

        return $intermediatePropertyMap;
    }

    public static function parseDocBlockToAnnotationMap(string $docBlock): AnnotationMap
    {
        // Strip away the start "/**' and ending "*/"
        if (strpos($docBlock, '/**') === 0) {
            $docBlock = substr($docBlock, 3);
        }
        if (substr($docBlock, -2) === '*/') {
            $docBlock = substr($docBlock, 0, -2);
        }
        $docBlock = trim($docBlock);

        $var = null;
        if (preg_match_all(self::DOC_BLOCK_REGEX, $docBlock, $matches)) {
            for ($x = 0, $max = count($matches[0]); $x < $max; $x++) {
                if ($matches['name'][$x] === 'var') {
                    $var = $matches['value'][$x];
                }
            }
        }

        return new AnnotationMap($var ?: null, [], null);
    }
}
