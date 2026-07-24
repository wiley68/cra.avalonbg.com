<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Validation\ValidationException;
use Throwable;

class AiUserFacingError
{
    /**
     * Resolve a stable, translated message for AI UI / queued job error_message.
     * Never returns Laravel's generic "The given data was invalid."
     */
    public static function messageFromThrowable(Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            $messages = $e->errors()['assistant'] ?? [];
            $first = $messages[0] ?? null;

            if (is_string($first) && trim($first) !== '') {
                return $first;
            }
        }

        if (self::isTimeout($e)) {
            return Translations::get('assistant.provider_timeout');
        }

        return Translations::get('assistant.provider_failed');
    }

    /**
     * Map transport/provider failures to ValidationException with assistant bag.
     */
    public static function throwFromTransport(Throwable $e): never
    {
        report($e);

        throw ValidationException::withMessages([
            'assistant' => self::isTimeout($e)
                ? Translations::get('assistant.provider_timeout')
                : Translations::get('assistant.provider_failed'),
        ]);
    }

    public static function isTimeout(Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        $message = strtolower($e->getMessage());

        return str_contains($message, 'timed out')
            || str_contains($message, 'cURL error 28')
            || str_contains($message, 'operation timed out')
            || (str_contains($message, 'timeout') && str_contains($message, 'curl'));
    }
}
