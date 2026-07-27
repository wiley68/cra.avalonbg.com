import { edit as editProduct } from '@/routes/products';
import { index as campaignsIndex } from '@/routes/products/campaigns';
import { index as productComponentsIndex } from '@/routes/products/components';
import { index as productControlsIndex } from '@/routes/products/controls';
import {
    index as deploymentsIndex,
    unsupported as deploymentsUnsupported,
} from '@/routes/products/deployments';
import { index as productEvidenceIndex } from '@/routes/products/evidence';
import { index as requirementsIndex } from '@/routes/products/requirements';
import { index as productRisksIndex } from '@/routes/products/risks';
import {
    edit as editSdlRun,
    index as productSdlIndex,
} from '@/routes/products/sdl';
import {
    edit as editSecurityInstruction,
    index as securityInstructionsIndex,
} from '@/routes/products/security-instructions';
import {
    edit as editTask,
    index as productTasksIndex,
} from '@/routes/products/tasks';
import {
    edit as editTechnicalDocumentation,
    index as technicalDocumentationIndex,
} from '@/routes/products/technical-documentation';
import { index as supportPeriodsIndex } from '@/routes/products/support-periods';
import { index as versionsIndex } from '@/routes/products/versions';
import {
    edit as editVulnerability,
    index as productVulnerabilitiesIndex,
} from '@/routes/products/vulnerabilities';
import { edit as editPolicy, index as policiesIndex } from '@/routes/policies';

const SECTION_DEFAULT_LINKS: Record<string, string> = {
    identification: 'edit',
    classification: 'edit',
    scope: 'edit',
    versions: 'versions',
    support: 'support-periods',
    policies: 'policies',
    security_instructions: 'security-instructions',
    requirements: 'requirements',
    controls: 'controls',
    risks: 'risks',
    sbom: 'components',
    vulnerabilities: 'vulnerabilities',
    deployments: 'deployments',
    evidence: 'evidence',
    technical_documentation: 'technical-documentation',
    repository: 'edit',
    integrations: 'edit',
    tasks: 'tasks',
    responsible_persons: 'edit',
    release: 'versions',
    sdl: 'sdl',
    reporting: 'vulnerabilities',
};

export function useReadinessLinks(productId: number) {
    const resolveLink = (link: string | null | undefined): string | null => {
        if (!link) {
            return null;
        }

        const entityMatch = link.match(
            /^(policy|vulnerability|task|sdl|security-instruction|technical-documentation):(\d+)$/,
        );

        if (entityMatch) {
            const [, type, idRaw] = entityMatch;
            const id = Number(idRaw);

            switch (type) {
                case 'policy':
                    return editPolicy(id).url;
                case 'vulnerability':
                    return editVulnerability({
                        product: productId,
                        vulnerability: id,
                    }).url;
                case 'task':
                    return editTask({ product: productId, task: id }).url;
                case 'sdl':
                    return editSdlRun({
                        product: productId,
                        sdlRun: id,
                    }).url;
                case 'security-instruction':
                    return editSecurityInstruction({
                        product: productId,
                        instruction: id,
                    }).url;
                case 'technical-documentation':
                    return editTechnicalDocumentation({
                        product: productId,
                        package: id,
                    }).url;
                default:
                    return null;
            }
        }

        switch (link) {
            case 'edit':
                return editProduct(productId).url;
            case 'versions':
                return versionsIndex(productId).url;
            case 'support-periods':
                return supportPeriodsIndex(productId).url;
            case 'requirements':
                return requirementsIndex(productId).url;
            case 'controls':
                return productControlsIndex(productId).url;
            case 'risks':
                return productRisksIndex(productId).url;
            case 'components':
                return productComponentsIndex(productId).url;
            case 'vulnerabilities':
                return productVulnerabilitiesIndex(productId).url;
            case 'campaigns':
                return campaignsIndex(productId).url;
            case 'deployments':
                return deploymentsIndex(productId).url;
            case 'deployments-unsupported':
                return deploymentsUnsupported(productId).url;
            case 'evidence':
                return productEvidenceIndex(productId).url;
            case 'tasks':
                return productTasksIndex(productId).url;
            case 'policies':
                return policiesIndex().url;
            case 'security-instructions':
                return securityInstructionsIndex(productId).url;
            case 'sdl':
                return productSdlIndex(productId).url;
            case 'technical-documentation':
                return technicalDocumentationIndex(productId).url;
            default:
                return null;
        }
    };

    const sectionLink = (
        sectionKey: string,
        explicitLink?: string | null,
    ): string | null => {
        return resolveLink(
            explicitLink ?? SECTION_DEFAULT_LINKS[sectionKey] ?? null,
        );
    };

    return { resolveLink, sectionLink };
}
