<?php

namespace App\Enums;

use App\Support\Translations;

enum AuditEventType: string
{
    case LoginSuccess = 'login_success';
    case LoginFailed = 'login_failed';
    case TwoFactorChallengeSuccess = 'two_factor_challenge_success';
    case TwoFactorChallengeFailed = 'two_factor_challenge_failed';
    case TwoFactorReset = 'two_factor_reset';
    case TaskApproved = 'task_approved';
    case TaskRejected = 'task_rejected';
    case ProductCreated = 'product_created';
    case ProductUpdated = 'product_updated';
    case ProductDeleted = 'product_deleted';
    case CustomerCreated = 'customer_created';
    case CustomerUpdated = 'customer_updated';
    case CustomerDeleted = 'customer_deleted';
    case DeploymentCreated = 'deployment_created';
    case DeploymentUpdated = 'deployment_updated';
    case DeploymentDeleted = 'deployment_deleted';
    case PatchCampaignCreated = 'patch_campaign_created';
    case PatchCampaignUpdated = 'patch_campaign_updated';
    case PatchCampaignActivated = 'patch_campaign_activated';
    case PatchCampaignCompleted = 'patch_campaign_completed';
    case PatchCampaignDeleted = 'patch_campaign_deleted';
    case CampaignTargetUpdated = 'campaign_target_updated';
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
    case CompliancePassportViewed = 'compliance_passport_viewed';
    case ReportingDraftUpdated = 'reporting_draft_updated';
    case ReportingSubmittedForApproval = 'reporting_submitted_for_approval';
    case ReportingApproved = 'reporting_approved';
    case ReportingRejected = 'reporting_rejected';
    case ReportingMarkedSubmitted = 'reporting_marked_submitted';
    case ReportingExported = 'reporting_exported';
    case ReportingEscalationCreated = 'reporting_escalation_created';
    case VcsConnectionCreated = 'vcs_connection_created';
    case VcsConnectionUpdated = 'vcs_connection_updated';
    case VcsConnectionDeleted = 'vcs_connection_deleted';
    case VcsRepositoryLinked = 'vcs_repository_linked';
    case VcsRepositoryUnlinked = 'vcs_repository_unlinked';
    case VcsSyncSucceeded = 'vcs_sync_succeeded';
    case VcsSyncFailed = 'vcs_sync_failed';
    case VcsSuggestionAccepted = 'vcs_suggestion_accepted';
    case VcsSuggestionDismissed = 'vcs_suggestion_dismissed';

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
