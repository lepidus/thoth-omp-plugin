import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const componentSource = readFileSync(
	new URL('../../resources/js/Components/ThothSection.vue', import.meta.url),
	'utf8',
);
const notificationSource = readFileSync(
	new URL('../../js/Notification.js', import.meta.url),
	'utf8',
);

function createHarness({withNotificationIntegration = true} = {}) {
	const requests = [];
	const events = [];
	const context = {
		$: {
			ajax: (request) => requests.push(request),
			pkp: {plugins: {generic: {}}},
		},
		pkp: {
			const: {STATUS_PUBLISHED: 3},
			currentUser: {csrfToken: 'csrf-token'},
			eventBus: {
				$emit: (...event) => events.push(event),
				$on: () => {},
			},
			modules: {
				useDataChanged: {useDataChanged: () => ({triggerDataChange: () => {}})},
				useLocalize: {useLocalize: () => ({t: (key) => key})},
				useModal: {useModal: () => ({})},
			},
		},
		window: {open: () => {}},
		ref: (value) => ({value}),
		computed: (callback) => ({get value() { return callback(); }}),
		onMounted: (callback) => callback(),
		getThothActionVisibility: () => ({}),
		openUnlinkWorkConfirmation: () => {},
		defineProps: () => ({
			registerTitle: 'Register',
			registerUrl: '/register/__publicationId__',
			selectedPublicationId: 42,
			submission: {status: 1, thothWorkId: null},
			synchronizeUrl: '/synchronize/__publicationId__',
			unlinkUrl: '/unlink',
			workStatusUrl: '/status',
		}),
	};

	vm.createContext(context);
	if (withNotificationIntegration) {
		context.$.pkp.plugins.generic.thothplugin = {
			notification: {notificationUrl: '/notification/fetchNotification'},
		};
		vm.runInContext(notificationSource, context, {filename: 'Notification.js'});
	}

	const script = componentSource.match(/<script setup>([\s\S]*?)<\/script>/)[1]
		.replace(/import\s+[\s\S]*?\s+from\s+['"][^'"]+['"];\s*/g, '');
	vm.runInContext(
		`${script}\n;globalThis.__thoth = {updateMetadata, isLoading};`,
		context,
		{filename: 'ThothSection.vue'},
	);

	return {
		events,
		requests,
		isLoading: () => context.__thoth.isLoading.value,
		updateMetadata: context.__thoth.updateMetadata,
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
