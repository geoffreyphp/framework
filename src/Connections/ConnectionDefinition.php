<?php

declare(strict_types=1);

namespace Geoffrey\Connections;

use Geoffrey\Connections\Attributes\ConnectionName;
use Geoffrey\Connections\Attributes\ConnectionUrl;
use Geoffrey\Connections\Attributes\Shared;
use Geoffrey\Connections\Attributes\WithOauth;
use Geoffrey\Connections\Attributes\WithToken;
use InvalidArgumentException;
use ReflectionClass;

final readonly class ConnectionDefinition
{
    public function __construct(
        public string $name,
        public string $url,
        public bool $oauth,
        public ?string $tokenEnv,
        public bool $shared,
    ) {
        //
    }

    /** @param  class-string  $class */
    public static function fromClass(string $class): self
    {
        $reflection = new ReflectionClass($class);

        $name = self::requiredAttributeValue($reflection, ConnectionName::class, 'value');
        $url = self::requiredAttributeValue($reflection, ConnectionUrl::class, 'value');
        $oauth = $reflection->getAttributes(WithOauth::class) !== [];
        $shared = $reflection->getAttributes(Shared::class) !== [];
        $tokenEnv = self::optionalAttributeValue($reflection, WithToken::class, 'env');

        if ($oauth && $tokenEnv !== null) {
            throw new InvalidArgumentException(
                "Connection [{$reflection->getShortName()}] cannot declare both #[WithOauth] and #[WithToken].",
            );
        }

        return new self(
            name: $name,
            url: $url,
            oauth: $oauth,
            tokenEnv: $tokenEnv,
            shared: $shared,
        );
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     * @param  class-string  $attribute
     */
    private static function requiredAttributeValue(ReflectionClass $reflection, string $attribute, string $property): string
    {
        $value = self::optionalAttributeValue($reflection, $attribute, $property);

        if ($value === null) {
            $shortName = new ReflectionClass($attribute)->getShortName();

            throw new InvalidArgumentException(
                "Connection [{$reflection->getShortName()}] is missing the #[{$shortName}] attribute.",
            );
        }

        return $value;
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     * @param  class-string  $attribute
     */
    private static function optionalAttributeValue(ReflectionClass $reflection, string $attribute, string $property): ?string
    {
        $attributes = $reflection->getAttributes($attribute);

        if ($attributes === []) {
            return null;
        }

        $instance = $attributes[0]->newInstance();

        /** @var string $value */
        $value = $instance->{$property};

        return $value;
    }

    public function isOauth(): bool
    {
        return $this->oauth;
    }

    public function isShared(): bool
    {
        return $this->shared;
    }

    public function tokenFromEnv(): ?string
    {
        return $this->tokenEnv;
    }
}
