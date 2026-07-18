<?php

namespace App\Enums;

enum PermissionSlug: string
{
    case PlatformAdmin = 'platform.admin';
    case UsersView = 'users.view';
    case UsersCreate = 'users.create';
    case UsersUpdate = 'users.update';
    case UsersDelete = 'users.delete';
    case UsersAssignRoles = 'users.assign_roles';
    case OrganizationsView = 'organizations.view';
    case OrganizationsManage = 'organizations.manage';
    case ProductsView = 'products.view';
    case ProductsManage = 'products.manage';
    case RequirementsView = 'requirements.view';
    case RequirementsManage = 'requirements.manage';
    case ControlsView = 'controls.view';
    case ControlsManage = 'controls.manage';
    case ReleasesView = 'releases.view';
    case ReleasesApprove = 'releases.approve';
    case VulnerabilitiesView = 'vulnerabilities.view';
    case VulnerabilitiesManage = 'vulnerabilities.manage';
    case EvidenceView = 'evidence.view';
    case EvidenceManage = 'evidence.manage';
    case AuditView = 'audit.view';
}
