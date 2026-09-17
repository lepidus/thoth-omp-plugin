const helperPath = 'plugins/generic/thoth/cypress/support/ThothTestData.php';
const quote = (value) => `'${String(value).replace(/'/g, `'"'"'`)}'`;

const runHelper = (operation, key) => {
	const args = ['php', helperPath, operation];
	if (key) {
		args.push(key);
	}
	return cy.exec(args.map(quote).join(' '), {log: false})
		.then(({stdout}) => JSON.parse(stdout.trim()));
};

export const seedPublishedBook = () => runHelper('create');
export const readRegisteredWork = (key) => runHelper('verify', key);
