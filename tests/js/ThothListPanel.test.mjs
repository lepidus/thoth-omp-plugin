import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = fs.readFileSync(
	new URL('../../js/ui/components/ListPanel/ThothListPanel.js', import.meta.url),
	'utf8',
);
const enLocaleSource = fs.readFileSync(
	new URL('../../locale/en/locale.po', import.meta.url),
	'utf8',
);

function loadListPanel() {
	const ajaxCalls = [];
	const dialogs = [];
	const notifications = [];
	let component;
	const listPanel = {components: {Notification: {}, PkpFilter: {}, PkpHeader: {}}};
	const submissions = {
		mixins: [{}],
		components: {ListPanel: listPanel, Pagination: {}, Search: {}},
	};
	const modalHost = {mixins: [{}], components: {Modal: {}}};
	const pkp = {
		Vue: {
			compile: () => ({render() {}}),
			component: (name, definition) => {
				component = definition;
			},
		},
		controllers: {
			Container: {components: {SubmissionsListPanel: submissions}},
			ManageEmailsPage: modalHost,
		},
		currentUser: {csrfToken: 'csrf-token'},
		eventBus: {$emit: (...args) => notifications.push(args)},
	};
	const $ = {
		ajax: (options) => ajaxCalls.push(options),
		pkp: {classes: {Helper: {uuid: () => 'uuid'}}},
	};

	vm.runInNewContext(source, {pkp, $});
	const instance = {
		...component.data(),
		apiUrl: '/api/submissions',
		items: [{id: 11}, {id: 12}],
		selected: [11, 12],
		selectedImprint: 'imprint-id',
		imprintValue: 'imprint-id',
		$emit() {},
		$modal: {hide() {}},
		__: (key, params = {}) => `${key}:${params.count ?? ''}`,
		openDialog: (options) => dialogs.push(options),
	};
	Object.entries(component.methods).forEach(([name, method]) => {
		instance[name] = method.bind(instance);
	});

	return {ajaxCalls, dialogs, instance, notifications};
}

test('waits for confirmation and reports only a fully successful batch', () => {
	const {ajaxCalls, dialogs, instance, notifications} = loadListPanel();

	instance.openRegister();
	assert.equal(ajaxCalls.length, 0);
	dialogs[0].actions[0].callback();
	ajaxCalls[0].success({id: 11});
	ajaxCalls[0].complete();
	assert.equal(notifications.length, 0);
	ajaxCalls[1].success({id: 12});
	ajaxCalls[1].complete();

	assert.deepEqual(notifications, [[
		'notify',
		'plugins.generic.thoth.actions.register.success:2',
		'success',
	]]);
});

test('cancelling the confirmation does not send metadata', () => {
	const {ajaxCalls, dialogs, instance} = loadListPanel();

	instance.openRegister();
	dialogs[0].actions[1].callback();

	assert.equal(ajaxCalls.length, 0);
});

test('does not report full success when one registration fails', () => {
	const {ajaxCalls, dialogs, instance, notifications} = loadListPanel();

	instance.openRegister();
	dialogs[0].actions[0].callback();
	ajaxCalls[0].error({status: 400, responseJSON: {id: 11, errors: ['invalid']}});
	ajaxCalls[0].complete();
	ajaxCalls[1].success({id: 12});
	ajaxCalls[1].complete();

	assert.equal(notifications.length, 0);
});

test('uses the complete Forthcoming explanation before registration', () => {
	function localeMessage(key) {
		const start = enLocaleSource.indexOf(`msgid "${key}"`);
		const end = enLocaleSource.indexOf('\n\n', start);
		return enLocaleSource
			.slice(start, end)
			.split('\n')
			.filter((line) => line.startsWith('msgstr ') || line.startsWith('"'))
			.map((line) => JSON.parse(line.replace(/^msgstr\s+/, '')))
			.join('');
	}

	assert.equal(
		localeMessage('plugins.generic.thoth.register.confirmation'),
		'Do you want to register this submission\'s metadata in Thoth? Please note that these titles are being marked as "Forthcoming" in Thoth to enable the publisher to review the current metadata set in the Thoth backend. Titles still need to be set to "Active" in Thoth to be picked up by related workflows.',
	);
	assert.equal(
		localeMessage('plugins.generic.thoth.actions.register.prompt'),
		'You are about to send submission metadata for \'{$count}\' item(s) to Thoth. Please note that these titles are being marked as "Forthcoming" in Thoth to enable the publisher to review the current metadata set in the Thoth backend. Titles still need to be set to "Active" in Thoth to be picked up by related workflows. Are you sure you want to register these records?',
	);
});
