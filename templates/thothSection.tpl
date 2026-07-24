{**
 * plugins/generic/thoth/templates/thothSection.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Copyright (c) 2024-2025 Lepidus Tecnologia
 * Copyright (c) 2024-2025 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Thoth section
 *
 *}

<span
    v-if="submission.status === getConstant('STATUS_PUBLISHED') || submission.thothWorkId" class="pkpPublication__thoth"
>
    <strong>
        {translate key="plugins.generic.thoth.thothBook"}
    </strong>
    <span v-if="submission.thothWorkId">
        <spinner v-if="!$.pkp.plugins.generic.thothplugin.workflow.workStatusLoaded" />
        <template v-else>
            <span class="thothWorkStatus">
                <strong>{translate key="plugins.generic.thoth.workStatus"}:</strong>
                <span
                    class="thothWorkStatus__indicator"
                    :class="$.pkp.plugins.generic.thothplugin.workflow.getWorkStatusClass()"
                    aria-hidden="true"
                ></span>
                <span>{{ldelim}} $.pkp.plugins.generic.thothplugin.workflow.getWorkStatusLabel() {{rdelim}}</span>
            </span>
            <pkp-button
                v-if="$.pkp.plugins.generic.thothplugin.workflow.workNotFound"
                @click="$.pkp.plugins.generic.thothplugin.workflow.unlinkWork()"
            >
                {translate key="plugins.generic.thoth.unlink"}
            </pkp-button>
            <template v-else-if="$.pkp.plugins.generic.thothplugin.workflow.canShowLinkedWorkActions()">
                <a
                    class="pkpButton"
                    target="_blank"
                    rel="noopener noreferrer"
                    :href="'https://thoth.pub/books/' + submission.thothWorkId"
                >
                    {translate key="common.view"}
                </a>
                <pkp-button
                    v-if="submission.status !== getConstant('STATUS_PUBLISHED')"
                    @click="$.pkp.plugins.generic.thothplugin.workflow.updateMetadata(workingPublication.id)"
                >
                    {translate key="plugins.generic.thoth.update"}
                </pkp-button>
            </template>
            <spinner v-if="$.pkp.plugins.generic.thothplugin.workflow.loading" />
        </template>
    </span>
    <span v-else>
        <pkp-button @click="$.pkp.plugins.generic.thothplugin.workflow.openRegister(workingPublication.id)">
            {translate key="plugins.generic.thoth.register"}
        </pkp-button>
    </span>
</span>
