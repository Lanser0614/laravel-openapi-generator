<?php

namespace Lanser\LaravelApiGenerator\OpenApi\Data;

use Closure;
use Exception;
use Illuminate\Routing\Route;
use Lanser\LaravelApiGenerator\OpenApi\Enum\HttpResponseEnum;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Support\Transformation\TransformationContextFactory;
use Spatie\LaravelData\Support\Wrapping\WrapExecutionType;

class Operation extends Data
{
    public function __construct(
        public ?string $description,
        /** @var null|DataCollection<int,Parameter> */
        #[DataCollectionOf(Parameter::class)]
        public ?DataCollection $parameters,
        public ?RequestBody    $requestBody,
        /** @var DataCollection<string,Response> */
        #[DataCollectionOf(Response::class)]
        public DataCollection  $responses,
        /** @var null|DataCollection<int,SecurityScheme> */
        #[DataCollectionOf(SecurityScheme::class)]
        public ?DataCollection $security,
    ) {
    }

    /**
     * @param Route $route
     * @return Operation
     * @throws ReflectionException
     */
    public static function fromRoute(Route $route): self
    {
        $uses = $route->action['uses'];

        if (is_string($uses)) {
            $controller_function = (new ReflectionClass($route->getController()))
                ->getMethod($route->getActionMethod());
        } elseif ($uses instanceof Closure) {
            $controller_function = new ReflectionFunction($uses);
        } else {
            throw new Exception('Unknown route uses');
        }

        $responses = [
            HttpResponseEnum::OK->value => Response::fromRoute($controller_function),
        ];

        $security = SecurityScheme::fromRoute($route);

        if ($security) {
            $responses[HttpResponseEnum::HTTP_UNAUTHORIZED->value] = Response::unauthorized($controller_function);
        }

        $permissions = SecurityScheme::getPermissions($route);

        $description = null;

        if (count($permissions) > 0) {
            $permissions_string = implode(', ', $permissions);

            $description = "Permissions needed: {$permissions_string}";

            $responses[HttpResponseEnum::HTTP_FORBIDDEN->value] = Response::forbidden($controller_function);
        }


        return new self(
            description: $description,
            parameters: Parameter::fromRoute($route, $controller_function),
            requestBody: RequestBody::fromRoute($controller_function),
            responses: Response::collect($responses, DataCollection::class),
            security: $security,
        );
    }


    /**
     * @param bool|TransformationContextFactory|TransformationContext|null $transformationContext
     * @param WrapExecutionType $wrapExecutionType
     * @param bool $mapPropertyNames
     * @return array
     * @throws Exception
     */
    public function transform(
        bool|TransformationContextFactory|TransformationContext|null $transformationContext = null,
        WrapExecutionType                                            $wrapExecutionType = WrapExecutionType::Disabled,
        bool                                                         $mapPropertyNames = true,
    ): array {
        return array_filter(
            parent::transform($transformationContext, $wrapExecutionType, $mapPropertyNames),
            fn (mixed $value) => null !== $value,
        );
    }

}
