<?php

namespace App\Services\Ai;

use App\Contracts\AiProvider;
use App\Enums\AiProviderDriver;

class StubAiProvider implements AiProvider
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array{context?: string|null}  $options
     * @return array{content: string, provider: string, model: string|null}
     */
    public function complete(array $messages, array $options = []): array
    {
        $lastUser = '';
        foreach (array_reverse($messages) as $message) {
            if (($message['role'] ?? '') === 'user') {
                $lastUser = trim((string) ($message['content'] ?? ''));
                break;
            }
        }

        if ($lastUser === '') {
            $lastUser = '(empty prompt)';
        }

        if (($options['mode'] ?? null) === 'document_analyse') {
            $filename = (string) ($options['filename'] ?? 'document.txt');
            $payload = [
                'document_summary' => 'Stub analysis of uploaded document for CRA workspace review.',
                'document_kind_guess' => 'other',
                'requirement_mappings' => [
                    [
                        'requirement_code' => null,
                        'confidence' => 0.4,
                        'rationale' => 'Stub provider cannot map requirements; human review required.',
                        'excerpt' => mb_substr($lastUser, 0, 120),
                    ],
                ],
                'evidence_mappings' => [
                    [
                        'suggested_evidence_type' => 'document',
                        'title_suggestion' => pathinfo($filename, PATHINFO_FILENAME) ?: 'Uploaded document',
                        'confidence' => 0.5,
                        'rationale' => 'Consider attaching this file as product evidence after human review.',
                    ],
                ],
                'gaps' => [
                    [
                        'kind' => 'coverage_gap',
                        'severity' => 'info',
                        'description' => 'Stub analysis does not verify completeness against CRA requirements.',
                        'suggested_action' => 'Have a compliance reviewer validate mappings before applying them.',
                    ],
                ],
                'human_review_required' => true,
                'disclaimer' => 'Suggestions only; no automated compliance decisions.',
            ];

            return [
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}',
                'provider' => AiProviderDriver::Stub->value,
                'model' => 'stub-local-template',
            ];
        }

        if (($options['mode'] ?? null) === 'draft_generate') {
            $draftType = (string) ($options['draft_type'] ?? 'customer_notification');
            $campaignId = (int) ($options['campaign_id'] ?? 0);
            $isAdvisory = $draftType === 'security_advisory';
            $subject = $isAdvisory
                ? 'Security advisory — product update (draft)'
                : 'Security update available — please review (draft)';
            $bodyPlain = $isAdvisory
                ? "This is a stub security advisory draft for campaign #{$campaignId}.\n\nSummary: A security-related product update is available. Review the campaign details and target version before publishing.\n\nRecommended action: Have a human compliance owner review and approve before any external distribution."
                : "Hello,\n\nThis is a stub customer notification draft for campaign #{$campaignId}.\n\nA security/product update is available for your deployment. Please review the target version in the campaign and confirm once applied.\n\nThis message was not sent automatically.";

            $payload = [
                'draft_type' => $draftType,
                'subject' => $subject,
                'body_markdown' => $bodyPlain,
                'body_plain' => $bodyPlain,
                'highlights' => [
                    'Stub draft grounded in campaign context only',
                    'Human review required before send',
                ],
                'affected_summary' => $campaignId > 0
                    ? "Campaign #{$campaignId} targets (see workspace context)."
                    : 'Campaign targets from workspace context.',
                'recommended_actions' => [
                    'Review subject and body with a compliance owner',
                    'Do not auto-send or auto-close from this draft',
                ],
                'human_review_required' => true,
                'disclaimer' => 'Draft only; no auto-send / no auto-close.',
            ];

            return [
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}',
                'provider' => AiProviderDriver::Stub->value,
                'model' => 'stub-local-template',
            ];
        }

        if (($options['mode'] ?? null) === 'usi_section_draft') {
            $sectionKey = (string) ($options['section_key'] ?? 'secure_installation');
            $locale = (string) ($options['locale'] ?? 'en');
            $title = (string) ($options['section_title'] ?? $sectionKey);
            $body = $locale === 'bg'
                ? "## {$title}\n\nТова е stub чернова за секция `{$sectionKey}`.\n\n- Прегледайте съдържанието преди запис\n- Не публикувайте без human review\n- Допълнете с реални стъпки за продукта"
                : "## {$title}\n\nThis is a stub draft for section `{$sectionKey}`.\n\n- Review the content before saving\n- Do not publish without human review\n- Replace with product-specific steps";

            $payload = [
                'section_key' => $sectionKey,
                'body_markdown' => $body,
                'human_review_required' => true,
                'disclaimer' => 'Draft only; human review required before save/publish.',
            ];

            return [
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}',
                'provider' => AiProviderDriver::Stub->value,
                'model' => 'stub-local-template',
            ];
        }

        if (($options['mode'] ?? null) === 'tech_doc_section_draft') {
            $sectionKey = (string) ($options['section_key'] ?? 'architecture');
            $locale = (string) ($options['locale'] ?? 'en');
            $title = (string) ($options['section_title'] ?? $sectionKey);
            $body = $locale === 'bg'
                ? "## {$title}\n\nТова е stub чернова за техническа документация, секция `{$sectionKey}`.\n\n- Прегледайте съдържанието преди запис\n- Не публикувайте без human review\n- Допълнете с реални факти за продукта"
                : "## {$title}\n\nThis is a stub draft for technical documentation section `{$sectionKey}`.\n\n- Review the content before saving\n- Do not publish without human review\n- Replace with product-specific facts";

            $payload = [
                'section_key' => $sectionKey,
                'body_markdown' => $body,
                'human_review_required' => true,
                'disclaimer' => 'Draft only; human review required before save/publish.',
            ];

            return [
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}',
                'provider' => AiProviderDriver::Stub->value,
                'model' => 'stub-local-template',
            ];
        }

        if (($options['mode'] ?? null) === 'incident_summary') {
            $locale = (string) ($options['locale'] ?? 'en');
            $title = (string) ($options['incident_title'] ?? 'Incident');
            $summary = $locale === 'bg'
                ? "## {$title}\n\nТова е stub чернова за summary на security инцидент.\n\n- Прегледайте съдържанието преди запис\n- Не затваряйте и не докладвайте без human review\n- Допълнете с реални факти от timeline"
                : "## {$title}\n\nThis is a stub draft for a security incident summary.\n\n- Review the content before saving\n- Do not close or report without human review\n- Replace with facts from the incident timeline";

            $payload = [
                'summary_markdown' => $summary,
                'human_review_required' => true,
                'disclaimer' => 'Draft only; human review required before save.',
            ];

            return [
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}',
                'provider' => AiProviderDriver::Stub->value,
                'model' => 'stub-local-template',
            ];
        }

        if (($options['mode'] ?? null) === 'sdl_stage_notes') {
            $locale = (string) ($options['locale'] ?? 'en');
            $title = (string) ($options['sdl_run_title'] ?? 'SDL run');
            $stage = (string) ($options['stage'] ?? 'stage');
            $notes = $locale === 'bg'
                ? "## {$title} — {$stage}\n\nТова е stub чернова за бележки / чеклист на SDL етап.\n\n- Прегледайте съдържанието преди запис на етапа\n- Не одобрявайте release security без human review\n- Заменете с реални threat / checklist резултати"
                : "## {$title} — {$stage}\n\nThis is a stub draft for SDL stage notes / checklist outcomes.\n\n- Review the content before saving the stage\n- Do not grant release security approval without human review\n- Replace with real threat / checklist outcomes";

            $payload = [
                'notes_markdown' => $notes,
                'human_review_required' => true,
                'disclaimer' => 'Draft only; human review required before save.',
            ];

            return [
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}',
                'provider' => AiProviderDriver::Stub->value,
                'model' => 'stub-local-template',
            ];
        }

        if (($options['mode'] ?? null) === 'vulnerability_triage') {
            $vulnerabilityId = (int) ($options['vulnerability_id'] ?? 0);
            $componentIds = [];
            $affectedIds = [];
            $fixedIds = [];

            if (preg_match_all('/Available components[\s\S]*?(?=## |\z)/', $options['context'] ?? '', $m) === 1) {
                if (preg_match('/- #(\d+)/', $m[0][0] ?? '', $cm)) {
                    $componentIds[] = (int) $cm[1];
                }
            }
            if (preg_match_all('/Available versions[\s\S]*?(?=## |\z)/', $options['context'] ?? '', $m) === 1) {
                if (preg_match_all('/- #(\d+)/', $m[0][0] ?? '', $vm)) {
                    $ids = array_map('intval', $vm[1]);
                    $affectedIds = array_slice($ids, 0, 1);
                    $fixedIds = array_slice($ids, 1, 1);
                }
            }

            $payload = [
                'suggested_component_ids' => $componentIds,
                'suggested_affected_version_ids' => $affectedIds,
                'suggested_fixed_version_ids' => $fixedIds,
                'suggested_business_severity' => 'high',
                'suggested_exploitation_status' => 'unknown',
                'suggested_status' => 'triage',
                'suggested_workaround' => 'Restrict exposure until a fixed version is confirmed by a human reviewer.',
                'suggested_corrective_action' => 'Plan a patch release after human confirmation of severity and affected versions.',
                'rationale' => $vulnerabilityId > 0
                    ? "Stub triage for vulnerability #{$vulnerabilityId}. Suggestions are illustrative only."
                    : 'Stub triage suggestions. Human review required before applying.',
                'cross_product_hints' => [
                    'Review sibling products in the same organization for similar components (hint only).',
                ],
                'human_review_required' => true,
                'disclaimer' => 'Suggestions only; no auto-close / no auto-apply.',
            ];

            return [
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}',
                'provider' => AiProviderDriver::Stub->value,
                'model' => 'stub-local-template',
            ];
        }

        $context = trim((string) ($options['context'] ?? ''));
        $contextBlock = '';
        if ($context !== '') {
            $excerptLimit = max(80, (int) config('ai.context_excerpt_chars', 400));
            $excerpt = mb_strlen($context) <= $excerptLimit
                ? $context
                : rtrim(mb_substr($context, 0, $excerptLimit - 1)) . '…';

            $contextBlock = <<<CTX
Workspace context was supplied (stub does not call an external model).

Grounded workspace context (excerpt):
{$excerpt}

CTX;
        }

        $content = <<<TEXT
[CRA AI stub — local template, not a live model]

{$contextBlock}You asked:
{$lastUser}

This response is generated by the local stub provider for development and Must-slice demos.
Human review is required for all compliance decisions. The assistant must not close vulns, submit reports, or change product data.
TEXT;

        return [
            'content' => $content,
            'provider' => AiProviderDriver::Stub->value,
            'model' => 'stub-local-template',
        ];
    }
}
