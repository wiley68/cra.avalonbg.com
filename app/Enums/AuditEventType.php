<?php

namespace App\Enums;

use App\Support\Translations;

enum AuditEventType: string
{
    case LoginSuccess = 'login_success';
    case LoginFailed = 'login_failed';
    case TwoFactorChallengeSuccess = 'two_factor_challenge_success';
    case TwoFactorChallengeFailed = 'two_factor_challenge_failed';
    case TaskApproved = 'task_approved';
    case TaskRejected = 'task_rejected';
    case ProductCreated = 'product_created';
    case ProductUpdated = 'product_updated';
    case ProductDeleted = 'product_deleted';
    case RiskCreated = 'risk_created';
    case RiskUpdated = 'risk_updated';
    case RiskDeleted = 'risk_deleted';
    case EvidenceCreated = 'evidence_created';
    case EvidenceUpdated = 'evidence_updated';
    case EvidenceDeleted = 'evidence_deleted';
    case TaskCreated = 'task_created';
    case TaskUpdated = 'task_updated';
    case TaskDeleted = 'task_deleted';
    case ReadinessReportViewed = 'readiness_report_viewed';
    case ReadinessReportExported = 'readiness_report_exported';

    public function label(): string
    {
        return Translations::get('audit_logs.event_types.' . $this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(
            array_map(fn(self $case) => $case->value, self::cases()),
            array_map(fn(self $case) => $case->label(), self::cases()),
        );
    }
}
