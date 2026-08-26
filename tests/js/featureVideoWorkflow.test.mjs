import assert from 'node:assert/strict';
import test from 'node:test';

import {registerVue2Components} from '../../resources/js/vue2Components.mjs';

test('loads the feature video form through the OMP 3.4 API contract', () => {
	const components = {};
	const requests = [];
	const jquery = () => ({});
	jquery.ajax = (request) => requests.push(request);
	jquery.pkp = {
		plugins: {
			generic: {
				thothplugin: {
					workflow: {
						featureVideoUrl: '/submissions/__submissionId__/featureVideo',
					},
				},
			},
		},
	};
	const pkpApi = {
		Vue: {
			compile: () => ({render: () => {}, staticRenderFns: []}),
			component: (name, definition) => {
				components[name] = definition;
			},
		},
		currentUser: {csrfToken: 'csrf-token'},
		eventBus: {$emit: () => {}},
	};
	registerVue2Components({jquery, pkpApi});
	const instance = {submissionId: 12, form: null};

	components['feature-video-form'].mounted.call(instance);
	assert.equal(requests.length, 1);
	assert.equal(requests[0].url, '/submissions/12/featureVideo');
	assert.equal(requests[0].headers['X-Csrf-Token'], 'csrf-token');

	requests[0].success({id: 'featureVideo'});
	assert.deepEqual(instance.form, {id: 'featureVideo'});
});
