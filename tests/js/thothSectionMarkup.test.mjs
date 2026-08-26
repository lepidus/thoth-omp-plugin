import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';

const templateSource = readFileSync(
	new URL('../../templates/workflow/thothSection.tpl', import.meta.url),
	'utf8',
);
const englishLocale = readFileSync(
	new URL('../../locale/en_US/locale.po', import.meta.url),
	'utf8',
);
const portugueseLocale = readFileSync(
	new URL('../../locale/pt_BR/locale.po', import.meta.url),
	'utf8',
);
test('uses the short missing Work labels in English and Portuguese', () => {
	assert.match(
		englishLocale,
		/msgid "plugins\.generic\.thoth\.status\.notFound"\nmsgstr "Not found"/,
	);
	assert.match(
		portugueseLocale,
		/msgid "plugins\.generic\.thoth\.status\.notFound"\nmsgstr "Não encontrado"/,
	);
});

test('uses the standard OMP button markup for every Thoth action', () => {
	const actionTags = templateSource.match(/<pkp-button\b[\s\S]*?>/g) || [];

	assert.equal(actionTags.length, 4);
	actionTags.forEach((actionTag) =>
		assert.match(actionTag, /:is-link="true"/),
	);
	assert.doesNotMatch(templateSource, /<a\b/);
	assert.match(
		templateSource,
		/<pkp-button\s+v-if="[^\n]+actionVisibility\(\)\.view"/,
	);
});
