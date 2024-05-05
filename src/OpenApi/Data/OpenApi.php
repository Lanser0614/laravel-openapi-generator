<?php

namespace Lanser\LaravelApiGenerator\OpenApi\Data;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Support\Transformation\TransformationContextFactory;
use Spatie\LaravelData\Support\Wrapping\WrapExecutionType;
use stdClass;
use Throwable;

class OpenApi extends Data
{
    /** @var array<string,class-string<Data>> */
    protected static array $schemas = [];

    /** @var array<string,class-string<Data>> */
    protected static array $temp_schemas = [];

    public function __construct(
        public string $openapi,
        public Info $info,
        /** @var array<string,array<string,Operation>> */
        protected array $paths,
    ) {
    }

    /**
     * @param string $name
     * @param string $schema
     */
    public static function addClassSchema(string $name, string $schema): void
    {
        static::$temp_schemas[$name] = $schema;
    }

    /** @return array<string,class-string<Data>> */
    public static function getSchemas(): array
    {
        return static::$schemas;
    }

 /** @return array<string,class-string<Data>> */
 public static function getTempSchemas(): array
 {
     return static::$temp_schemas;
 }

    /**
     * @param array<string,array<string,Route>> $routes
     */
    public static function fromRoutes(array $routes, Command $command = null): self
    {
        /** @var array<string,array<string,Operation>> $paths */
        $paths = [];

        foreach ($routes as $uri => $uri_routes) {
            foreach ($uri_routes as $method => $route) {
                try {
                    self::$temp_schemas = [];

                    $paths[$uri][$method] = Operation::fromRoute($route);

                    if ($route->getName()) {
                        $paths[$uri][$method]->tags = [$uri => $route->getName()];
                    }


                    self::addTempSchemas();

                } catch (Throwable $th) {
                    $command->error("Failed to generate Operation from route $method {$route->getName()} $uri: {$th->getMessage()}");

                    Log::error($th);
                }
            }
        }

        return new self(
            openapi: config('openapi-generator.openapi'),
            info: Info::create(),
            paths: $paths,
        );
    }


    protected static function addTempSchemas(): void
    {
        static::$schemas = array_merge(
            static::$schemas,
            static::$temp_schemas,
        );
    }

    /**
     * @return array<string,mixed>
     */
    protected function resolveSchemas(): array
    {
        $schemas = array_map(
            fn (string $schema) => Schema::fromDataClass($schema)->toArray(),
            static::$schemas
        );

        $this->addTempSchemas();

        return $schemas;
    }

    /**
     * @param bool|TransformationContextFactory|TransformationContext|null $transformationContext
     * @param WrapExecutionType $wrapExecutionType
     * @param bool $mapPropertyNames
     * @return array<string,mixed>
     * @throws Exception
     */
    public function transform(
        bool|TransformationContextFactory|TransformationContext|null $transformationContext = null,
        WrapExecutionType                                            $wrapExecutionType = WrapExecutionType::Disabled,
        bool                                                         $mapPropertyNames = true,
    ): array {
        // Double call to make sure all schemas are resolved
        $this->resolveSchemas();

        $paths = [
            'paths' => count($this->paths) > 0 ?
                array_map(
                    fn (array $path) => array_map(
                        fn (Operation $operation) => $operation->toArray(),
                        $path
                    ),
                    $this->paths
                ) :
                new stdClass(),
        ];

        foreach ($this->paths as $path) {
            $object = $path;
            $first = array_shift($object);
            if ($first->tags != null) {
                $uri = array_key_first($first->tags);
                $value = array_values($first->tags);
                $key = array_key_first($paths['paths'][$uri]);
                $paths['paths'][$uri][$key]['tags'] = $value;
            }

            if ($first->errorMessages) {
                $uri = array_key_first($first->errorMessages);
                $value = array_values(array_shift($first->errorMessages));
                $uri = '/'.$uri;
                $key = array_key_first($paths['paths'][$uri]);

                foreach ($value as $res) {
                    $errors[] = [
                        "type" => "object",
                        "properties" => [
                            "message" => [
                                "type" => "string",
                            ],
                        ],
                        "example" => [
                            "message" => $res
                        ]
                    ];
                }
                $paths['paths'][$uri][$key]['responses'] += [
                    500 => [
                        "description" => 'Server errors',
                        "content" => [
                            "application/json" => [
                                "schema" => [
                                    "oneOf" => $errors
                                ]
                            ]
                        ]
                    ]];
                unset($paths['paths'][$uri][$key]['errorMessages']);
            }

        }

        return array_merge(
            parent::transform($transformationContext),
            $paths,
            [
                'components' => [
                    'schemas'         => $this->resolveSchemas(),
                    'securitySchemes' => [
                        SecurityScheme::BEARER_SECURITY_SCHEME => [
                            'type'   => 'http',
                            'scheme' => 'bearer',
                        ],
                    ],
                ],
            ]
        );
    }
}
