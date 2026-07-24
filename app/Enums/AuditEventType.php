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
    case PatchCampaignExported = 'patch_campaign_exported';
    case PatchCampaignNotificationsQueued = 'patch_campaign_notifications_queued';
    case PatchCampaignDeleted = 'patch_campaign_deleted';
    case CampaignTargetUpdated = 'campaign_target_updated';
    case OrgPolicyCreated = 'org_policy_created';
    case OrgPolicyUpdated = 'org_policy_updated';
    case OrgPolicyDeleted = 'org_policy_deleted';
    case UserSecurityInstructionCreated = 'user_security_instruction_created';
    case UserSecurityInstructionUpdated = 'user_security_instruction_updated';
    case UserSecurityInstructionDeleted = 'user_security_instruction_deleted';
    case UserSecurityInstructionSubmitted = 'user_security_instruction_submitted';
    case UserSecurityInstructionPublished = 'user_security_instruction_published';
    case UserSecurityInstructionRetired = 'user_security_instruction_retired';
    case UserSecurityInstructionExported = 'user_security_instruction_exported';
    case UserSecurityInstructionPublishedEvidence = 'user_security_instruction_published_evidence';
    case IncidentCreated = 'incident_created';
    case IncidentUpdated = 'incident_updated';
    case IncidentDeleted = 'incident_deleted';
    case IncidentStatusUpdated = 'incident_status_updated';
    case IncidentClosed = 'incident_closed';
    case IncidentTimelineEventAdded = 'incident_timeline_event_added';
    case IncidentExported = 'incident_exported';
    case IncidentReportAdded = 'incident_report_added';
    case IncidentCustomerCommunicationAdded = 'incident_customer_communication_added';
    case SdlRunCreated = 'sdl_run_created';
    case SdlRunUpdated = 'sdl_run_updated';
    case SdlRunDeleted = 'sdl_run_deleted';
    case SdlStageUpdated = 'sdl_stage_updated';
    case SdlRunApproved = 'sdl_run_approved';
    case SdlRunApprovalRevoked = 'sdl_run_approval_revoked';
    case SdlRunExported = 'sdl_run_exported';
    case SdlExceptionRecorded = 'sdl_exception_recorded';
    case SdlExceptionCleared = 'sdl_exception_cleared';
    case TechnicalDocumentationCreated = 'technical_documentation_created';
    case TechnicalDocumentationUpdated = 'technical_documentation_updated';
    case TechnicalDocumentationDeleted = 'technical_documentation_deleted';
    case TechnicalDocumentationGeneratedRefreshed = 'technical_documentation_generated_refreshed';
    case TechnicalDocumentationSubmitted = 'technical_documentation_submitted';
    case TechnicalDocumentationPublished = 'technical_documentation_published';
    case TechnicalDocumentationRetired = 'technical_documentation_retired';
    case TechnicalDocumentationExported = 'technical_documentation_exported';
    case OrgPolicySubmitted = 'org_policy_submitted';
    case OrgPolicyApproved = 'org_policy_approved';
    case OrgPolicyRetired = 'org_policy_retired';
    case OrgPolicyPublishedEvidence = 'org_policy_published_evidence';
    case OrgPolicyExported = 'org_policy_exported';
    case AuditorPackageCreated = 'auditor_package_created';
    case AuditorPackageUpdated = 'auditor_package_updated';
    case AuditorPackageDeleted = 'auditor_package_deleted';
    case AuditorPackageShared = 'auditor_package_shared';
    case AuditorPackageNotificationsQueued = 'auditor_package_notifications_queued';
    case AuditorPackageGuestLinkGenerated = 'auditor_package_guest_link_generated';
    case AuditorPackageGuestLinkRevoked = 'auditor_package_guest_link_revoked';
    case AuditorPackageClosed = 'auditor_package_closed';
    case AuditorPackageExported = 'auditor_package_exported';
    case AuditorFindingCreated = 'auditor_finding_created';
    case AuditorFindingUpdated = 'auditor_finding_updated';
    case AuditorFindingDeleted = 'auditor_finding_deleted';
    case AuditorFindingStatusUpdated = 'auditor_finding_status_updated';
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
    case AiRequestCompleted = 'ai_request_completed';
    case AiDocumentAnalysed = 'ai_document_analysed';
    case AiDraftGenerated = 'ai_draft_generated';
    case AiUsiSectionDraftSuggested = 'ai_usi_section_draft_suggested';
    case AiIncidentSummaryDraftSuggested = 'ai_incident_summary_draft_suggested';
    case AiSdlStageNotesDraftSuggested = 'ai_sdl_stage_notes_draft_suggested';
    case AiVulnerabilityTriageSuggested = 'ai_vulnerability_triage_suggested';

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
