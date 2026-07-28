<?php

namespace App\Services;

/**
 * Keeps Stripe SDK type surface out of StripeBillingService (Intelephense limits).
 */
class StripeCheckoutGateway
{
    /**
     * @param  array<string, mixed>  $params
     * @return array{id: string, url: string}
     */
    public function createSession(array $params): array
    {
        $session = $this->createRawSession($params);

        return [
            'id' => (string) ($session['id'] ?? ''),
            'url' => (string) ($session['url'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function createRawSession(array $params): array
    {
        // String class name avoids pulling StripeClient into the caller's type graph.
        $class = 'Stripe\\StripeClient';
        $client = new $class((string) config('billing.stripe.secret'));
        $created = call_user_func([$client->checkout->sessions, 'create'], $params);

        if (is_array($created)) {
            return $created;
        }

        if (is_object($created) && method_exists($created, 'toArray')) {
            /** @var array<string, mixed> $array */
            $array = $created->toArray();

            return $array;
        }

        return [
            'id' => is_object($created) && isset($created->id) ? (string) $created->id : '',
            'url' => is_object($created) && isset($created->url) ? (string) $created->url : '',
        ];
    }
}
