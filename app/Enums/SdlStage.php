<?php

namespace App\Enums;

enum SdlStage: string
{
    case Requirement = 'requirement';
    case ThreatReview = 'threat_review';
    case Design = 'design';
    case Development = 'development';
    case CodeReview = 'code_review';
    case DependencyScan = 'dependency_scan';
    case SecurityTest = 'security_test';
    case ReleaseApproval = 'release_approval';
    case Publication = 'publication';
    case Monitoring = 'monitoring';

    /**
     * Fixed §5.14 workflow order.
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [
            self::Requirement,
            self::ThreatReview,
            self::Design,
            self::Development,
            self::CodeReview,
            self::DependencyScan,
            self::SecurityTest,
            self::ReleaseApproval,
            self::Publication,
            self::Monitoring,
        ];
    }

    public static function first(): self
    {
        return self::Requirement;
    }

    public function next(): ?self
    {
        $ordered = self::ordered();
        $index = array_search($this, $ordered, true);

        if ($index === false) {
            return null;
        }

        return $ordered[$index + 1] ?? null;
    }

    public function isReleaseGate(): bool
    {
        return $this === self::ReleaseApproval;
    }

    /**
     * Stages that remain editable after release security approval (post-release ops).
     */
    public function isPostRelease(): bool
    {
        return in_array($this, [self::Publication, self::Monitoring], true);
    }

    /**
     * @return list<self>
     */
    public static function postRelease(): array
    {
        return [self::Publication, self::Monitoring];
    }
}
