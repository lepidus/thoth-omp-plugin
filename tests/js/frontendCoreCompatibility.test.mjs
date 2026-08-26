import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const bundleSource = readFileSync(
	new URL('../../public/build/build.iife.js', import.meta.url),
	'utf8',
);

test('loads against the public Vue 2 frontend contract of OMP 3.4', () => {
	const registeredComponents = [];
	const jquery = () => ({pkpHandler: () => {}});
	jquery.ajax = () => {};
	jquery.pkp = {classes: {Helper: {uuid: () => 'fixture-id'}}, plugins: {generic: {}}};

	const context = {
		$: jquery,
		document: {activeElement: {focus: () => {}}},
		pkp: {
			Vue: {
				compile: () => ({render: () => {}, staticRenderFns: []}),
				component: (name) => registeredComponents.push(name),
			},
			const: {STATUS_PUBLISHED: 3},
			controllers: {},
			currentUser: {csrfToken: 'csrf-token'},
			eventBus: {$emit: () => {}, $on: () => {}},
			registry: {_instances: {}},
		},
		window: {open: () => {}},
	};

	vm.runInNewContext(bundleSource, context, {filename: 'build.iife.js'});

	assert.deepEqual(registeredComponents.sort(), [
		'feature-video-form',
		'thoth-list-item',
		'thoth-list-panel',
	]);
});
