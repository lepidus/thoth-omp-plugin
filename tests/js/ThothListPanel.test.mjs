import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';

const componentSource = fs.readFileSync(
	new URL('../../resources/js/Components/ThothListPanel.vue', import.meta.url),
	'utf8',
);
const enLocaleSource = fs.readFileSync(
	new URL('../../locale/en/locale.po', import.meta.url),
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

test('reports full success only after every registration succeeds', () => {
	assert.match(componentSource, /actions\.register\.success/);
	assert.match(componentSource, /failed[^\n]*=== 0/);
});

test('cancelling the confirmation only closes the dialog', () => {
	assert.match(
		componentSource,
		/label: t\('common\.cancel'\),\s+callback: \(close\) => close\(\)/,
	);
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
