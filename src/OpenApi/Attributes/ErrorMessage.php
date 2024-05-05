<?php

namespace Lanser\LaravelApiGenerator\OpenApi\Attributes;

use Attribute;

#[Attribute]
class ErrorMessage
{
    protected array $errors;

    public function __construct(
        public array|string $message,
        public int $code = 500
    ) {
        if (is_string($this->message)) {
            $this->errors = [$this->message => $this->code];
        } else {
            foreach ($this->message as $message) {
                $this->errors[$message] = $this->code;
            }
        }
    }
}
