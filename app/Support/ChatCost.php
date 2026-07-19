<?php

namespace App\Support;

/**
 * Resolves USD cost from token counts using the price table in
 * config/chat_pricing.php. Falls back to the 'default' price when a
 * message's model isn't listed (unknown/legacy model).
 */
class ChatCost
{
    /**
     * @return list<string>
     */
    public static function models(): array
    {
        return array_keys(config('chat_pricing.models', []));
    }

    public static function usd(?string $model, ?int $promptTokens, ?int $completionTokens): float
    {
        $price = config("chat_pricing.models.{$model}") ?? config('chat_pricing.default');

        return (($promptTokens ?? 0) * $price['input'] + ($completionTokens ?? 0) * $price['output']) / 1_000_000;
    }
}
