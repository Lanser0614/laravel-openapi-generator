<?php

namespace Lanser\LaravelApiGenerator\OpenApi\Spatie;

use Illuminate\Support\Collection;
use ReflectionAttribute;
use ReflectionProperty;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\GetsCast;
use Spatie\LaravelData\Attributes\Hidden;
use Spatie\LaravelData\Attributes\WithoutValidation;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Mappers\NameMapper;
use Spatie\LaravelData\Resolvers\NameMappersResolver;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\DataPropertyType;
use Spatie\LaravelData\Support\DataType;
use Spatie\LaravelData\Transformers\Transformer;

class OpenApiDataProperty extends DataProperty
{
    public function __construct(string $name, string $className, DataPropertyType $type, bool $validate, bool $computed, bool $hidden, bool $isPromoted, bool $isReadonly, bool $hasDefaultValue, mixed $defaultValue, ?Cast $cast, ?Transformer $transformer, ?string $inputMappedName, ?string $outputMappedName, Collection $attributes)
    {
        parent::__construct($name, $className, $type, $validate, $computed, $hidden, $isPromoted, $isReadonly, $hasDefaultValue, $defaultValue, $cast, $transformer, $inputMappedName, $outputMappedName, $attributes);
    }

    public static function create(
        ReflectionProperty $property,
        bool $hasDefaultValue = false,
        mixed $defaultValue = null,
        ?NameMapper $classInputNameMapper = null,
        ?NameMapper $classOutputNameMapper = null,
    ): self {
        $attributes = collect($property->getAttributes())
            ->filter(fn (ReflectionAttribute $reflectionAttribute) => class_exists($reflectionAttribute->getName()))
            ->map(fn (ReflectionAttribute $reflectionAttribute) => $reflectionAttribute->newInstance());

        $mappers = NameMappersResolver::create()->execute($attributes);

        $inputMappedName = match (true) {
            $mappers['inputNameMapper'] !== null => $mappers['inputNameMapper']->map($property->name),
            $classInputNameMapper !== null => $classInputNameMapper->map($property->name),
            default => null,
        };

        $outputMappedName = match (true) {
            $mappers['outputNameMapper'] !== null => $mappers['outputNameMapper']->map($property->name),
            $classOutputNameMapper !== null => $classOutputNameMapper->map($property->name),
            default => null,
        };

        $computed = $attributes->contains(
            fn (object $attribute) => $attribute instanceof Computed
        );

        $hidden = $attributes->contains(
            fn (object $attribute) => $attribute instanceof Hidden
        );

        return new self(
            name: $property->name,
            className: $property->class,
            type: DataType::create($property),
            validate: ! $attributes->contains(
                fn (object $attribute) => $attribute instanceof WithoutValidation
            ) && ! $computed,
            computed: $computed,
            hidden: $hidden,
            isPromoted: $property->isPromoted(),
            isReadonly: $property->isReadOnly(),
            hasDefaultValue: $property->isPromoted() ? $hasDefaultValue : $property->hasDefaultValue(),
            defaultValue: $property->isPromoted() ? $defaultValue : $property->getDefaultValue(),
            cast: $attributes->first(fn (object $attribute) => $attribute instanceof GetsCast)?->get(),
            transformer: $attributes->first(fn (object $attribute) => $attribute instanceof WithTransformer)?->get(),
            inputMappedName: $inputMappedName,
            outputMappedName: $outputMappedName,
            attributes: $attributes,
        );
    }

}
