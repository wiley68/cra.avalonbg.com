<?php

namespace App\Contracts;

interface AiProvider
{
    /**
     * Generate an assistant completion from chat messages.
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array{context?: string|null}  $options
     * @return array{
     *     content: string,
     *     provider: string,
     *     model: string|null
     * }
     */
    public function complete(array $messages, array $options = []): array;
}
