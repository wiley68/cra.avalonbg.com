<?php

namespace App\Enums;

enum EvidenceType: string
{
    case Document = 'document';
    case Screenshot = 'screenshot';
    case TestReport = 'test_report';
    case Sbom = 'sbom';
    case VulnerabilityScan = 'vulnerability_scan';
    case ArchitectureDiagram = 'architecture_diagram';
    case Policy = 'policy';
    case Approval = 'approval';
    case ReleaseArtifact = 'release_artifact';
    case IntegrationSnapshot = 'integration_snapshot';
    case Other = 'other';
}
