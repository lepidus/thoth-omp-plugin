{**
 * templates/workflow/thothSection.tpl
 *
 * Thoth controls for the OMP 3.4 workflow.
 *}

<span
	v-if="submission.status === getConstant('STATUS_PUBLISHED') || submission.thothWorkId"
	class="pkpPublication__thoth"
>
	<strong>{translate key="plugins.generic.thoth.workStatus"}:</strong>
	<span
		class="thothWorkStatus__indicator"
		:class="$.pkp.plugins.generic.thothplugin.workflow.getWorkStatusClass()"
		aria-hidden="true"
	></span>
	<span>{{ $.pkp.plugins.generic.thothplugin.workflow.getWorkStatusLabel() }}</span>
	<pkp-button
		v-if="$.pkp.plugins.generic.thothplugin.workflow.actionVisibility().view"
		:is-link="true"
		@click="$.pkp.plugins.generic.thothplugin.workflow.viewWork()"
	>
		{translate key="common.view"}
	</pkp-button>
	<pkp-button
		v-if="$.pkp.plugins.generic.thothplugin.workflow.actionVisibility().unlink"
		:is-link="true"
		:is-disabled="$.pkp.plugins.generic.thothplugin.workflow.loading"
		@click="$.pkp.plugins.generic.thothplugin.workflow.confirmUnlink()"
	>
		{translate key="plugins.generic.thoth.unlink"}
	</pkp-button>
	<pkp-button
		v-if="$.pkp.plugins.generic.thothplugin.workflow.actionVisibility().update"
		:is-link="true"
		:is-disabled="$.pkp.plugins.generic.thothplugin.workflow.loading"
		@click="$.pkp.plugins.generic.thothplugin.workflow.updateMetadata(workingPublication.id)"
	>
		{translate key="plugins.generic.thoth.update"}
	</pkp-button>
	<pkp-button
		v-if="$.pkp.plugins.generic.thothplugin.workflow.actionVisibility().register"
		:is-link="true"
		@click="$.pkp.plugins.generic.thothplugin.workflow.openRegister(workingPublication.id)"
	>
		{translate key="plugins.generic.thoth.register"}
	</pkp-button>
	<pkp-spinner v-if="$.pkp.plugins.generic.thothplugin.workflow.loading"></pkp-spinner>
</span>
