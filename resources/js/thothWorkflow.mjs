import {getThothActionVisibility} from './thothActionVisibility.mjs';

function notify(eventBus, message, type = 'warning') {
	eventBus.$emit('notify', message, type);
}

export function synchronizeMetadata({
	ajax,
	eventBus,
	workflow,
	csrfToken,
	publicationId,
	notification,
}) {
	workflow.loading = true;
	workflow.refreshSection();

	const url = workflow.synchronizeUrl.replace(
		'__publicationId__',
		publicationId,
	);
	ajax({
		method: 'PUT',
		url,
		headers: {
			'X-Csrf-Token': csrfToken,
			'X-Http-Method-Override': 'PUT',
		},
		error(response) {
			const responseMessage = response.responseJSON?.errorMessage;
			notify(
				eventBus,
				typeof responseMessage === 'string' && responseMessage.trim()
					? responseMessage
					: workflow.connectionError,
			);
		},
		complete() {
			if (
				typeof notification?.notificationUrl !== 'string' ||
				typeof notification?.showNotification !== 'function'
			) {
				notify(eventBus, workflow.connectionError);
				workflow.loading = false;
				workflow.refreshSection();
				return;
			}

			ajax({
				type: 'POST',
				url: notification.notificationUrl,
				success: notification.showNotification,
				error() {
					notify(eventBus, workflow.connectionError);
				},
				complete() {
					workflow.loading = false;
					workflow.refreshSection();
				},
				dataType: 'json',
				async: false,
			});
		},
	});
}

export function initializeThothWorkflow({
	jquery = globalThis.$,
	pkpApi = globalThis.pkp,
	windowApi = globalThis.window,
} = {}) {
	const workflow = jquery?.pkp?.plugins?.generic?.thothplugin?.workflow;
	if (!workflow || !pkpApi?.eventBus) {
		return null;
	}

	workflow.loading = false;
	workflow.fetchError = false;
	workflow.workStatus = null;
	workflow.statusRequestCompleted = !workflow.hasLinkedWork;
	workflow.workNotFound = false;
	workflow.refreshSection = () => {
		const app = pkpApi.registry?._instances?.app;
		if (typeof app?.$forceUpdate === 'function') {
			app.$forceUpdate();
		}
	};
	workflow.actionVisibility = () =>
		getThothActionVisibility({
			hasWorkLink: workflow.hasLinkedWork,
			workStatus: workflow.workStatus,
			statusRequestCompleted: workflow.statusRequestCompleted,
			workNotFound: workflow.workNotFound,
			fetchError: workflow.fetchError,
			isPublished:
				workflow.submissionStatus === pkpApi.const.STATUS_PUBLISHED,
		});
	workflow.getWorkStatusLabel = () => {
		if (!workflow.hasLinkedWork) {
			return workflow.statusUnregistered;
		}
		if (workflow.workNotFound) {
			return workflow.statusNotFound;
		}
		if (workflow.fetchError) {
			return workflow.statusError;
		}
		return workflow.workStatus
			? workflow.workStatusLabels[workflow.workStatus] || workflow.workStatus
			: '...';
	};
	workflow.getWorkStatusClass = () => {
		if (workflow.workNotFound || workflow.fetchError) {
			return 'thothWorkStatus__indicator--declined';
		}
		return (
			{
				ACTIVE: 'thothWorkStatus__indicator--active',
				FORTHCOMING: 'thothWorkStatus__indicator--forthcoming',
				WITHDRAWN: 'thothWorkStatus__indicator--declined',
				SUPERSEDED: 'thothWorkStatus__indicator--superseded',
				POSTPONED_INDEFINITELY:
					'thothWorkStatus__indicator--postponed',
				CANCELLED: 'thothWorkStatus__indicator--declined',
			}[workflow.workStatus] || 'thothWorkStatus__indicator--superseded'
		);
	};
	workflow.fetchWorkStatus = () => {
		if (!workflow.hasLinkedWork) {
			return;
		}
		workflow.fetchError = false;
		workflow.workNotFound = false;
		workflow.workStatus = null;
		workflow.statusRequestCompleted = false;
		workflow.refreshSection();
		jquery.ajax({
			method: 'GET',
			url: workflow.workStatusUrl,
			headers: {'X-Csrf-Token': pkpApi.currentUser.csrfToken},
			success(response) {
				workflow.workStatus = response.workStatus;
			},
			error(response) {
				workflow.workNotFound =
					response.status === 404 &&
					response.responseJSON?.workNotFound === true;
				workflow.fetchError = !workflow.workNotFound;
			},
			complete() {
				workflow.statusRequestCompleted = true;
				workflow.refreshSection();
			},
		});
	};
	workflow.viewWork = () => {
		windowApi.open(
			`https://thoth.pub/books/${workflow.thothWorkId}`,
			'_blank',
			'noopener,noreferrer',
		);
	};
	workflow.performUnlink = () => {
		workflow.loading = true;
		workflow.refreshSection();
		jquery.ajax({
			method: 'POST',
			url: workflow.unlinkUrl,
			headers: {
				'X-Csrf-Token': pkpApi.currentUser.csrfToken,
				'X-Http-Method-Override': 'DELETE',
			},
			success() {
				pkpApi.registry._instances.app.refreshSubmission();
			},
			error(response) {
				notify(
					pkpApi.eventBus,
					response.responseJSON?.error ||
						response.responseJSON?.errorMessage ||
						workflow.connectionError,
				);
			},
			complete() {
				workflow.loading = false;
				workflow.refreshSection();
			},
		});
	};
	workflow.confirmUnlink = () => {
		const focusElement = globalThis.document?.activeElement;
		const options = {
			title: workflow.unlinkTitle,
			okButton: workflow.unlinkTitle,
			cancelButton: workflow.cancelLabel,
			dialogText: workflow.unlinkConfirm,
			callback: workflow.performUnlink,
			closeCallback: () => focusElement?.focus(),
			titleIcon: 'modal_confirm',
			width: 'auto',
		};
		jquery(
			`<div id="${jquery.pkp.classes.Helper.uuid()}" class="pkp_modal pkpModalWrapper" tabindex="-1"></div>`,
		).pkpHandler('$.pkp.controllers.modal.ConfirmationModalHandler', options);
	};
	workflow.openRegister = (publicationId) => {
		const focusElement = globalThis.document?.activeElement;
		const options = {
			title: workflow.registerTitle,
			url: workflow.registerUrl.replace(
				'__publicationId__',
				publicationId,
			),
			closeCallback: () => focusElement?.focus(),
			closeOnFormSuccessId: 'register',
		};
		jquery(
			`<div id="${jquery.pkp.classes.Helper.uuid()}" class="pkp_modal pkpModalWrapper" tabindex="-1"></div>`,
		).pkpHandler('$.pkp.controllers.modal.AjaxModalHandler', options);
	};
	workflow.updateMetadata = (publicationId) =>
		synchronizeMetadata({
			ajax: jquery.ajax,
			eventBus: pkpApi.eventBus,
			workflow,
			csrfToken: pkpApi.currentUser.csrfToken,
			publicationId,
			notification:
				jquery.pkp?.plugins?.generic?.thothplugin?.notification,
		});

	pkpApi.eventBus.$on('form-success', (formId) => {
		if (formId === 'register') {
			pkpApi.registry._instances.app.refreshSubmission();
			workflow.fetchWorkStatus();
		}
	});

	if (workflow.hasLinkedWork) {
		workflow.fetchWorkStatus();
	}

	return workflow;
}
