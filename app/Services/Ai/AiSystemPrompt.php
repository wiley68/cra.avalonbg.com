<?php

namespace App\Services\Ai;

final class AiSystemPrompt
{
    public static function build(?string $context = null): string
    {
        $parts = [
            'You are a CRA compliance workspace assistant. You help with product requirements, controls, evidence, policies, and readiness questions.',
            'You are a helper, not an autonomous compliance authority. Do not determine final CRA applicability, confirm legal compliance, close vulnerabilities, submit regulatory reports, or change product data. Human review is required for all material decisions.',
            'Prefer answers grounded in the supplied workspace context. If context is missing or insufficient, say what is unknown instead of inventing compliance status.',
        ];

        $trimmed = trim((string) $context);
        if ($trimmed !== '') {
            $parts[] = "Workspace context:\n" . $trimmed;
        }

        return implode("\n\n", $parts);
    }
}
