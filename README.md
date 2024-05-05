# OpenAPI Generator using Laravel Data

Generate OpenAPI specification from Laravel routes and Laravel Data objects

**This repository is just fork from original. Thanks to this person.**
Original `xolvio/laravel-data-openapi-generator`

# Install

In `composer.json` add this repository:

```json
    "require": {
          "lanser/laravel-openapi-generator" : "^1.5"
    },
```

and

```php
    composer update
```

## Version

Add a `app.version` config in `app.php` to set the version in the openapi specification:
```php
    'version' => env('APP_VERSION', '1.0.0'),
```


# Usage

## Config

`php artisan vendor:publish --tag=openapi-generator-config`

## Generate

`php artisan openapi:generate`

## View

Swagger available at `APP_URL/api/openapi`