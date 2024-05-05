<?php

namespace Lanser\LaravelApiGenerator\OpenApi\Data;

use Lanser\LaravelApiGenerator\OpenApi\Spatie\MyDataProperty;
use DateTimeInterface;
use Exception;
use Illuminate\Database\Eloquent\Model;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\Types\AbstractList;
use PhpParser\Node\Stmt\Return_;
use ReflectionEnum;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use RuntimeException;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Data as LaravelData;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Support\Transformation\TransformationContextFactory;
use Spatie\LaravelData\Support\Wrapping\WrapExecutionType;
use UnitEnum;

class Schema extends Data
{
    protected const CASTS = [
        'int'   => 'integer',
        'bool'  => 'boolean',
        'float' => 'number',
    ];

    public function __construct(
        public ?string $type = null,
        public ?bool $nullable = null,
        public ?string $format = null,
        public ?Schema $items = null,
        public ?string $ref = null,
        /** @var DataCollection<int,Property> */
        #[DataCollectionOf(Property::class)]
        protected ?DataCollection $properties = null,
    ) {
        $this->type     = self::CASTS[$this->type] ?? $this->type;
        $this->nullable = $this->nullable ? $this->nullable : null;
    }

    public static function fromReflectionProperty(ReflectionProperty $reflection): self
    {
        $property = MyDataProperty::create($reflection);

        $type = $property->type;

        /** @var null|string $data_class */
        $data_class = $type->dataClass;

        if ($type->isDataObject && $data_class) {
            return self::fromData($data_class, $type->isNullable || $type->isOptional);
        }
        if ($type->isDataCollectable && $data_class) {
            return self::fromDataCollection($data_class, $type->isNullable || $type->isOptional);
        }

        /** @var null|string */
        $other_type = array_keys($type->acceptedTypes)[0] ?? null;

        if (! $other_type) {
            throw new RuntimeException("Parameter {$property->name} has no type defined");
        }

        return self::fromDataReflection(type_name: $other_type, reflection: $reflection, nullable: $type->isNullable);
    }

    public static function fromDataReflection(
        string|ReflectionNamedType $type_name,
        ReflectionMethod|ReflectionFunction|ReflectionProperty|null $reflection = null,
        bool $nullable = false,
    ): self {
        if ($type_name instanceof ReflectionNamedType) {
            $nullable  = $type_name->allowsNull();
            $type_name = $type_name->getName();
        }

        $is_class = class_exists($type_name);

        if (is_a($type_name, DateTimeInterface::class, true)) {
            return self::fromDateTime($nullable);
        }

        if (! $is_class && 'array' !== $type_name) {
            return self::fromBuiltin($type_name, $nullable);
        }

        if (null !== $reflection && (is_a($type_name, DataCollection::class, true) || 'array' === $type_name)) {
            return self::fromListDocblock($reflection, $nullable);
        }

        if (is_a($type_name, UnitEnum::class, true)) {
            return self::fromEnum($type_name, $nullable);
        }

        return self::fromData($type_name, $nullable);
    }

    public static function fromParameterReflection(ReflectionParameter $parameter): self
    {
        $type = $parameter->getType();

        if (! $type instanceof ReflectionNamedType) {
            throw new RuntimeException("Parameter {$parameter->getName()} has no type defined");
        }

        $type_name = $type->getName();

        if (is_a($type_name, Model::class, true)) {
            /** @var Model */
            $instance  = (new $type_name());
            $type_name = $instance->getKeyType();
        }

        return new self(type: $type_name, nullable: $type->allowsNull());
    }

    public static function fromDataClass(string $class): self
    {
        return new self(
            type: 'object',
            properties: Property::fromDataClass($class),
        );
    }

    /**
     * @param bool|TransformationContextFactory|TransformationContext|null $transformationContext
     * @param WrapExecutionType $wrapExecutionType
     * @param bool $mapPropertyNames
     * @return array<int|string,mixed>
     * @throws Exception
     */
    public function transform(
        bool|TransformationContextFactory|TransformationContext|null $transformationContext = null,
        WrapExecutionType                                            $wrapExecutionType = WrapExecutionType::Disabled,
        bool                                                         $mapPropertyNames = true,
    ): array {
        $array = array_filter(
            parent::transform($transformationContext, $wrapExecutionType),
            fn (mixed $value) => null !== $value,
        );

        if ($array['ref'] ?? false) {
            $array['$ref'] = $array['ref'];
            unset($array['ref']);

            if ($array['nullable'] ?? false) {
                $array['allOf'][] = ['$ref' => $array['$ref']];
                unset($array['$ref']);
            }
        }

        if (null !== $this->properties) {
            $array['properties'] = collect($this->properties->all())
                ->mapWithKeys(fn (Property $property) => [$property->getName() => $property->type->transform($transformationContext, $wrapExecutionType, $mapPropertyNames)])
                ->toArray();
        }

        return $array;
    }

    protected static function fromBuiltin(string $type_name, bool $nullable): self
    {
        return new self(type: $type_name, nullable: $nullable);
    }

    protected static function fromDateTime(bool $nullable): self
    {
        return new self(type: 'string', format: 'date-time', nullable: $nullable);
    }

    protected static function fromEnum(string $type, bool $nullable): self
    {
        $enum = (new ReflectionEnum($type));

        $type_name = 'string';

        if ($enum->isBacked() && $type = $enum->getBackingType()) {
            $type_name = (string) $type;
        }

        return new self(type: $type_name, nullable: $nullable);
    }

    protected static function fromData(string $type_name, bool $nullable): self
    {
        $type_name = ltrim($type_name, '\\');

        if (! is_a($type_name, LaravelData::class, true)) {
            throw new RuntimeException("Type {$type_name} is not a Data class");
        }

        $scheme_name = last(explode('\\', $type_name));

        if (! $scheme_name || ! is_string($scheme_name)) {
            throw new RuntimeException("Cannot read basename from {$type_name}");
        }

        /** @var class-string<LaravelData> $type_name */
        OpenApi::addClassSchema($scheme_name, $type_name);

        return new self(
            ref: '#/components/schemas/' . $scheme_name,
            nullable: $nullable,
        );
    }

    protected static function fromDataCollection(string $type_name, bool $nullable): self
    {
        $type_name = ltrim($type_name, '\\');

        if (! is_a($type_name, LaravelData::class, true)) {
            throw new RuntimeException("Type {$type_name} is not a Data class");
        }

        return new self(
            type: 'array',
            nullable: $nullable,
            items: self::fromData($type_name, false),
        );
    }

    protected static function fromListDocblock(ReflectionMethod|ReflectionFunction|ReflectionProperty $reflection, bool $nullable): self
    {
        $docs = $reflection->getDocComment();
        if (! $docs) {
            throw new RuntimeException('Could not find required docblock of method/property ' . $reflection->getName());
        }

        $docblock = DocBlockFactory::createInstance()->create($docs);

        if ($reflection instanceof ReflectionMethod || $reflection instanceof ReflectionFunction) {
            $tag = $docblock->getTagsByName('return')[0] ?? null;
        } else {
            $tag = $docblock->getTagsByName('var')[0] ?? null;
        }

        /** @var null|Return_ $tag */
        if (! $tag) {
            throw new RuntimeException('Could not find required tag in docblock of method/property ' . $reflection->getName());
        }

        $tag_type = $tag->getType();

        if (! $tag_type instanceof AbstractList) {
            throw new RuntimeException('Return tag of method ' . $reflection->getName() . ' is not a list');
        }

        $class = $tag_type->getValueType()->__toString();

        if (! class_exists($class)) {
            throw new RuntimeException('Cannot resolve "' . $class . '". Make sure to use the full path in the phpdoc including the first "\".');
        }

        return new self(
            type: 'array',
            nullable: $nullable,
            items: self::fromDataReflection($class),
        );
    }
}
