<?php

namespace Lanser\LaravelApiGenerator\OpenApi\Data;

use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Data as LaravelData;
use Spatie\LaravelData\DataCollection;

class Property extends Data
{
    public function __construct(
        protected string $name,
        public Schema $type,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $class
     * @return DataCollection<int,self>
     * @throws ReflectionException
     */
    public static function fromDataClass(string $class): DataCollection
    {
        if (! is_a($class, LaravelData::class, true)) {
            throw new RuntimeException('Class does not extend LaravelData');
        }

        $reflection = new ReflectionClass($class);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        /** @var DataCollection<int,self> */
        return self::collect(
            array_map(
                fn (ReflectionProperty $property) => self::fromProperty($property),
                $properties
            ),
            DataCollection::class
        );
    }

    public static function fromProperty(ReflectionProperty $reflection): self
    {
        return new self(
            name: $reflection->getName(),
            type: Schema::fromReflectionProperty($reflection),
        );
    }
}
