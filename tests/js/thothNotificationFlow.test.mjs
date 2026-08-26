import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

import {synchronizeMetadata} from '../../resources/js/thothWorkflow.mjs';

const notificationSource = readFileSync(
	new URL('../../js/Notification.js', import.meta.url),
	'utf8',
);

function createHarness({withNotificationIntegration = true} = {}) {
	const requests = [];
	const events = [];
	const workflow = {
		connectionError: 'plugins.generic.thoth.connectionError',
		loading: false,
		refreshSection: () => {},
		synchronizeUrl: '/synchronize/__publicationId__',
	};
	const context = {
		$: {
			ajax: (request) => requests.push(request),
			pkp: {plugins: {generic: {}}},
		},
		pkp: {
			eventBus: {
				$emit: (...event) => events.push(event),
				$on: () => {},
			},
		},
	};

	vm.createContext(context);
	if (withNotificationIntegration) {
		context.$.pkp.plugins.generic.thothplugin = {
			notification: {notificationUrl: '/notification/fetchNotification'},
		};
		vm.runInContext(notificationSource, context, {filename: 'Notification.js'});
	}

	return {
		events,
		requests,
		isLoading: () => workflow.loading,
		updateMetadata: () =>
			synchronizeMetadata({
				ajax: context.$.ajax,
				eventBus: context.pkp.eventBus,
				workflow,
				csrfToken: 'csrf-token',
				publicationId: 42,
				notification:
					context.$.pkp.plugins.generic.thothplugin?.notification,
			}),
	};
}

test('reports fetched success notifications and ends loading', () => {
	const harness = createHarness();

	harness.updateMetadata();

	assert.equal(harness.isLoading(), true);
	assert.equal(harness.requests.length, 1);
	assert.equal(harness.requests[0].method, 'PUT');
	assert.equal(harness.requests[0].url, '/synchronize/42');
	assert.equal(harness.requests[0].headers['X-Csrf-Token'], 'csrf-token');
	assert.equal(harness.requests[0].headers['X-Http-Method-Override'], 'PUT');

	harness.requests[0].complete();
	assert.equal(harness.requests.length, 2);
	assert.equal(harness.requests[1].type, 'POST');
	assert.equal(harness.requests[1].url, '/notification/fetchNotification');

	harness.requests[1].success({
		content: {
			general: {normal: {1: {addclass: 'notifySuccess', text: 'Synced'}}},
		},
	});
	harness.requests[1].complete();

	assert.deepEqual(harness.events, [['notify', 'Synced', 'success']]);
	assert.equal(harness.isLoading(), false);
});

test('uses a safe message when synchronization fails without one', () => {
	const harness = createHarness();

	harness.updateMetadata();
	harness.requests[0].error({});
	harness.requests[0].complete();
	harness.requests[1].success({content: {}});
	harness.requests[1].complete();

	assert.deepEqual(harness.events, [
		['notify', 'plugins.generic.thoth.connectionError', 'warning'],
	]);
	assert.equal(harness.isLoading(), false);
});

test('reports notification fetch failure and ends loading', () => {
	const harness = createHarness();

	harness.updateMetadata();
	harness.requests[0].complete();
	assert.equal(typeof harness.requests[1].error, 'function');
	harness.requests[1].error();
	harness.requests[1].complete();

	assert.deepEqual(harness.events, [
		['notify', 'plugins.generic.thoth.connectionError', 'warning'],
	]);
	assert.equal(harness.isLoading(), false);
});

test('reports unavailable notification integration and ends loading', () => {
	const harness = createHarness({withNotificationIntegration: false});

	harness.updateMetadata();
	harness.requests[0].complete();

	assert.deepEqual(harness.events, [
		['notify', 'plugins.generic.thoth.connectionError', 'warning'],
	]);
	assert.equal(harness.isLoading(), false);
});
