<?php

/**
 * @file plugins/generic/thoth/classes/api/ThothWriteAuthorization.php
 *
 * Copyright (c) 2024-2026 Lepidus Tecnologia
 * Copyright (c) 2024-2026 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothWriteAuthorization
 *
 * @ingroup plugins_generic_thoth
 *
 * @brief Authorizes the Thoth API routes that change data with the permission the core
 *  requires to edit the publication metadata of a submission.
 */

namespace APP\plugins\generic\thoth\classes\api;

use PKP\core\PKPRequest;
use PKP\plugins\interfaces\HasAuthorizationPolicy;
use PKP\security\authorization\internal\PublicationCanBeEditedPolicy;
use PKP\security\authorization\PublicationWritePolicy;
use PKP\security\authorization\StageRolePolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;

class ThothWriteAuthorization implements HasAuthorizationPolicy
{
    /**
     * @param bool $publicationScoped Whether the route identifies a publication with {publicationId}
     */
    public function __construct(private bool $publicationScoped)
    {
    }

    public function getPolicies(PKPRequest $request, array &$args, array $roleAssignments): array
    {
        // Same policy the core applies to edit a publication (PKPSubmissionController::editPublication).
        if ($this->publicationScoped) {
            return [new PublicationWritePolicy($request, $args, $roleAssignments)];
        }

        // Routes without {publicationId} get the rest of PublicationWritePolicy: the submission access,
        // a role assigned to the current stage (managers and site admins pass by their context role) and
        // the publication edit permission (site admins, managers or assignments that can change metadata).
        return [
            new SubmissionAccessPolicy($request, $args, $roleAssignments),
            new StageRolePolicy([Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR]),
            new PublicationCanBeEditedPolicy($request, 'api.submissions.403.userCantEdit'),
        ];
    }
}
