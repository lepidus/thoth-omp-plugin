/**
 * Copyright (c) 2024-2026 Lepidus Tecnologia
 * Copyright (c) 2024-2026 Thoth
 * Distributed under the GNU GPL v3. See docs/COPYING.
 */
import {seedPublishedBook, readRegisteredWork} from '../../support/thoth';

describe('Thoth book registration', function () {
	beforeEach(function () {
		seedPublishedBook().as('book');
	});

	it('registers a published monograph using the Register button', function () {
		// Open the publication of an unregistered book.
		cy.login('admin', 'admin', 'publicknowledge');
		cy.visit(`/index.php/publicknowledge/en/workflow/access/${this.book.submissionId}`);
		cy.openWorkflowMenu('Title & Abstract');
		cy.contains('span', 'Thoth Status:').parent().contains('Unregistered').scrollIntoView();
		cy.contains('span', 'Thoth Status:').parent().contains('Unregistered').should('be.visible');

		// Confirm registration with the disposable publisher's imprint.
		// PKP submits forms as POST with a method override for the PUT endpoint.
		cy.intercept('POST', `**/api/v1/_submissions/${this.book.submissionId}/register`).as('register');
		cy.contains('span', 'Thoth Status:').parent().contains('button', /^\s*Register\s*$/).click();
		cy.get('.pkpWorkflow__thothRegisterModal form').should('be.visible').within(() => {
			cy.get('select[name="thothImprintId"]').select('Cypress Imprint');
			cy.contains('button', /^\s*Register\s*$/).click();
		});
		cy.wait('@register').then(({response}) => {
			expect(response.statusCode).to.eq(200);
			expect(response.body.thothWorkId).to.match(/^[a-f0-9-]{36}$/);
		});
		cy.get('.pkpWorkflow__thothRegisterModal form').should('not.exist');
		cy.contains('span', 'Thoth Status:').parent().contains('span', /^\s*Active\s*$/).scrollIntoView();
		cy.contains('span', 'Thoth Status:').parent().contains('span', /^\s*Active\s*$/).should('be.visible');

		// Check persisted OMP linkage and metadata in the real, isolated Thoth API.
		readRegisteredWork(this.book.key).then((work) => {
			expect(work.workStatus).to.eq('ACTIVE');
			expect(work.workType).to.eq('MONOGRAPH');
			expect(work.title).to.eq(this.book.title);
			expect(work.imprintId).to.eq(this.book.imprintId);
		});
		cy.reload();
		cy.contains('span', 'Thoth Status:').parent().contains('span', /^\s*Active\s*$/).scrollIntoView();
		cy.contains('span', 'Thoth Status:').parent().contains('span', /^\s*Active\s*$/).should('be.visible');
		cy.contains('span', 'Thoth Status:').parent().contains('button', /^\s*Register\s*$/).should('not.exist');
	});
});
