import assert from 'node:assert/strict';
import test from 'node:test';

import {initializeThothWorkflow} from '../../resources/js/thothWorkflow.mjs';

test('only unlinks the Work after confirmation', () => {
	let modalOptions;
	const requests = [];
	const jquery = () => ({
		pkpHandler: (handler, options) => {
			assert.equal(
				handler,
				'$.pkp.controllers.modal.ConfirmationModalHandler',
			);
			modalOptions = options;
		},
	});
	jquery.ajax = (request) => requests.push(request);
	jquery.pkp = {
		classes: {Helper: {uuid: () => 'fixture-id'}},
		plugins: {
			generic: {
				thothplugin: {
					workflow: {
						cancelLabel: 'Cancel',
						connectionError: 'Connection error',
						hasLinkedWork: false,
						unlinkConfirm: 'Confirm unlink',
						unlinkTitle: 'Unlink',
						unlinkUrl: '/unlink',
						workStatusLabels: {},
					},
				},
			},
		},
	};
	const pkpApi = {
		const: {STATUS_PUBLISHED: 3},
		currentUser: {csrfToken: 'csrf-token'},
		eventBus: {$emit: () => {}, $on: () => {}},
		registry: {_instances: {app: {refreshSubmission: () => {}}}},
	};
	const workflow = initializeThothWorkflow({jquery, pkpApi, windowApi: {}});

	workflow.confirmUnlink();
	assert.equal(requests.length, 0);
	assert.equal(modalOptions.dialogText, 'Confirm unlink');

	modalOptions.callback();
	assert.equal(requests.length, 1);
	assert.equal(requests[0].url, '/unlink');
});
