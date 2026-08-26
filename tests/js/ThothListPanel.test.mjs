import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';

import {registerVue2Components} from '../../resources/js/vue2Components.mjs';

const enLocaleSource = readFileSync(
	new URL('../../locale/en_US/locale.po', import.meta.url),
	'utf8',
);

function localeEntry(key) {
	const start = enLocaleSource.indexOf(`msgid "${key}"`);
	const end = enLocaleSource.indexOf('\n\n', start);
	return enLocaleSource.slice(start, end);
}

function localeMessage(key) {
	return localeEntry(key)
		.split('\n')
		.filter((line) => line.startsWith('msgstr ') || line.startsWith('"'))
		.map((line) => JSON.parse(line.replace(/^msgstr\s+/, '')))
		.join('');
}

function componentHarness() {
	const components = {};
	const events = [];
	let modalOptions;
	const jquery = () => ({
		pkpHandler: (handler, options) => {
			assert.equal(
				handler,
				'$.pkp.controllers.modal.ConfirmationModalHandler',
			);
			modalOptions = options;
		},
	});
	jquery.ajax = () => {};
	jquery.pkp = {classes: {Helper: {uuid: () => 'fixture-id'}}};
	const pkpApi = {
		Vue: {
			compile: () => ({render: () => {}, staticRenderFns: []}),
			component: (name, definition) => {
				components[name] = definition;
			},
		},
		currentUser: {csrfToken: 'csrf-token'},
		eventBus: {$emit: (...event) => events.push(event)},
	};
	registerVue2Components({jquery, pkpApi});
	return {components, events, jquery, modalOptions: () => modalOptions};
}

function panelInstance(definition, overrides = {}) {
	const instance = {
		__: (key, params) => (params ? `${key}:${params.count}` : key),
		$emit: () => {},
		$set: (target, key, value) => {
			target[key] = value;
		},
		...overrides,
	};
	Object.entries(definition.methods).forEach(([name, method]) => {
		instance[name] = method.bind(instance);
	});
	return instance;
}

test('reports full success only after every registration succeeds', () => {
	const harness = componentHarness();
	const panel = panelInstance(harness.components['thoth-list-panel'], {
		registrationBatch: {total: 2, completed: 0, failed: 0},
		selected: [1, 2],
		startedItems: [1, 2],
	});

	panel.completeItemRegistration(1, true);
	assert.deepEqual(harness.events, []);
	panel.completeItemRegistration(2, true);
	assert.deepEqual(harness.events, [
		['notify', 'plugins.generic.thoth.actions.register.success:2', 'success'],
	]);
});

test('does not report success when any registration fails', () => {
	const harness = componentHarness();
	const panel = panelInstance(harness.components['thoth-list-panel'], {
		registrationBatch: {total: 2, completed: 0, failed: 0},
		selected: [1, 2],
		startedItems: [1, 2],
	});

	panel.completeItemRegistration(1, false);
	panel.completeItemRegistration(2, true);
	assert.deepEqual(harness.events, []);
});

test('registers only after the native confirmation callback', () => {
	const harness = componentHarness();
	let registrations = 0;
	const panel = panelInstance(harness.components['thoth-list-panel'], {
		imprintValue: 'imprint-id',
		selected: [1],
	});
	panel.registerAll = () => registrations++;

	panel.confirmRegister();
	assert.equal(registrations, 0);
	harness.modalOptions().callback();
	assert.equal(registrations, 1);
});

test('uses the complete Forthcoming explanation before registration', () => {
	assert.equal(
		localeMessage('plugins.generic.thoth.register.confirmation'),
		'Do you want to register this submission\'s metadata in Thoth? Please note that these titles are being marked as "Forthcoming" in Thoth to enable the publisher to review the current metadata set in the Thoth backend. Titles still need to be set to "Active" in Thoth to be picked up by related workflows.',
	);
	assert.equal(
		localeMessage('plugins.generic.thoth.actions.register.prompt'),
		'You are about to send submission metadata for \'{$count}\' item(s) to Thoth. Please note that these titles are being marked as "Forthcoming" in Thoth to enable the publisher to review the current metadata set in the Thoth backend. Titles still need to be set to "Active" in Thoth to be picked up by related workflows. Are you sure you want to register these records?',
	);
});
