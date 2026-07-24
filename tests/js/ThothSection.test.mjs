import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = fs.readFileSync(
	new URL('../../js/ThothSection.js', import.meta.url),
	'utf8',
);

function loadWorkflow() {
	const ajaxCalls = [];
	const modalCalls = [];
	const workflow = {
		unlinkCancel: 'Cancel',
		unlinkConfirm: 'Confirm unlink',
		unlinkError: 'Unable to unlink',
		unlinkTitle: 'Unlink',
		unlinkUrl: 'api/_submissions/13/thothWork',
		workStatusUrl: 'api/_submissions/13/thothWorkStatus',
	};
	const $ = function () {
		return {
			pkpHandler(handler, options) {
				modalCalls.push({handler, options});
			},
		};
	};
	$.ajax = (options) => ajaxCalls.push(options);
	$.pkp = {
		classes: {
			Helper: {
				uuid: () => 'modal-id',
			},
		},
		plugins: {
			generic: {
				thothplugin: {workflow},
			},
		},
	};
	const pkp = {
		currentUser: {csrfToken: 'csrf-token'},
		eventBus: {
			$emit() {},
			$on() {},
		},
		registry: {
			_instances: {
				app: {
					$forceUpdate() {},
					refreshSubmission() {},
				},
			},
		},
	};

	vm.runInNewContext(source, {
		$,
		document: {activeElement: {focus() {}}},
		pkp,
	});
	ajaxCalls.length = 0;

	return {ajaxCalls, modalCalls, workflow};
}

test('opens an OMP confirmation modal before unlinking the Work', () => {
	const {ajaxCalls, modalCalls, workflow} = loadWorkflow();

	workflow.unlinkWork();

	assert.equal(ajaxCalls.length, 0);
	assert.equal(modalCalls.length, 1);
	assert.equal(
		modalCalls[0].handler,
		'$.pkp.controllers.modal.ConfirmationModalHandler',
	);
	assert.equal(modalCalls[0].options.dialogText, 'Confirm unlink');
	assert.equal(modalCalls[0].options.cancelButton, 'Cancel');
});

test('unlinks the Work only after modal confirmation', () => {
	const {ajaxCalls, modalCalls, workflow} = loadWorkflow();

	workflow.unlinkWork();
	modalCalls[0].options.callback();

	assert.equal(ajaxCalls.length, 1);
	assert.equal(ajaxCalls[0].url, 'api/_submissions/13/thothWork');
	assert.equal(ajaxCalls[0].headers['X-Http-Method-Override'], 'DELETE');
});
