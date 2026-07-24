import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = fs.readFileSync(
	new URL('../../js/ThothSection.js', import.meta.url),
	'utf8',
);

function loadWorkflow(confirmResult) {
	const ajaxCalls = [];
	const confirmMessages = [];
	const workflow = {
		unlinkConfirm: 'Confirm unlink',
		unlinkError: 'Unable to unlink',
		unlinkUrl: 'api/_submissions/13/thothWork',
		workStatusUrl: 'api/_submissions/13/thothWorkStatus',
	};
	const $ = function () {};
	$.ajax = (options) => ajaxCalls.push(options);
	$.pkp = {
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
		confirm(message) {
			confirmMessages.push(message);
			return confirmResult;
		},
		document: {activeElement: {focus() {}}},
		pkp,
	});
	ajaxCalls.length = 0;

	return {ajaxCalls, confirmMessages, workflow};
}

test('does not unlink the Work when confirmation is cancelled', () => {
	const {ajaxCalls, confirmMessages, workflow} = loadWorkflow(false);

	workflow.unlinkWork();

	assert.deepEqual(confirmMessages, ['Confirm unlink']);
	assert.equal(ajaxCalls.length, 0);
	assert.equal(workflow.loading, false);
});

test('unlinks the Work after confirmation', () => {
	const {ajaxCalls, workflow} = loadWorkflow(true);

	workflow.unlinkWork();

	assert.equal(ajaxCalls.length, 1);
	assert.equal(ajaxCalls[0].url, 'api/_submissions/13/thothWork');
	assert.equal(ajaxCalls[0].headers['X-Http-Method-Override'], 'DELETE');
});
