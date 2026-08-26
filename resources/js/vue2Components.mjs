const workStatusLocaleMap = {
	ACTIVE: 'plugins.generic.thoth.workStatus.active',
	FORTHCOMING: 'plugins.generic.thoth.workStatus.forthcoming',
	WITHDRAWN: 'plugins.generic.thoth.workStatus.withdrawn',
	SUPERSEDED: 'plugins.generic.thoth.workStatus.superseded',
	POSTPONED_INDEFINITELY:
		'plugins.generic.thoth.workStatus.postponedIndefinitely',
	CANCELLED: 'plugins.generic.thoth.workStatus.cancelled',
};

const workStatusColorMap = {
	ACTIVE: '#00B24E',
	FORTHCOMING: '#DED15D',
	WITHDRAWN: '#D00A0A',
	CANCELLED: '#D00A0A',
	SUPERSEDED: '#777777',
	POSTPONED_INDEFINITELY: '#E08914',
};

const listItemTemplate = `
	<div :id="'list-item-submission-' + item.id" class="listPanel__item--thoth">
		<div class="listPanel__itemSummary">
			<label class="listPanel__selectWrapper">
				<div class="listPanel__selector">
					<input
						type="checkbox"
						name="submissions[]"
						:value="item.id"
						:disabled="!!item.thothWorkId"
						:checked="isSelected"
						@change="$emit('select-item', item.id)"
					/>
				</div>
				<div class="listPanel__itemIdentity">
					<div class="listPanel__itemTitle">
						<span v-if="currentPublication && currentPublication.authorsStringShort">
							{{ currentPublication.authorsStringShort }}
						</span>
					</div>
					<div class="listPanel__itemSubtitle">
						<a :href="item.urlWorkflow" target="_blank" rel="noopener noreferrer">
							{{ localize(currentPublication ? currentPublication.fullTitle : '') }}
						</a>
					</div>
					<pkp-spinner v-if="isLoading"></pkp-spinner>
				</div>
			</label>
			<div class="listPanel__itemActions">
				<div class="listPanel__itemMetadata">
					<pkp-badge class="listPanel__itemMetadata--badge" :style="badgeStyle" :has-dot="true">
						{{ statusLabel }}
					</pkp-badge>
				</div>
				<button v-if="hasErrors" class="expander" @click="expanded = !expanded">
					<icon :icon="expanded ? 'chevron-up' : 'chevron-down'" :inline="true"></icon>
					<span class="-screenReader">
						{{ expanded ? __('list.viewLess', {name: String(item.id)}) : __('list.viewMore', {name: String(item.id)}) }}
					</span>
				</button>
			</div>
		</div>
		<div v-if="expanded && hasErrors" class="listPanel__itemExpanded listPanel__itemExpanded--thoth">
			<pkp-notification type="warning">
				<div v-for="(error, index) in errors" :key="index" class="thothListItem__error">
					<icon icon="exclamation-triangle" :inline="true"></icon>
					<span>{{ error }}</span>
				</div>
			</pkp-notification>
		</div>
	</div>
`;

const listPanelTemplate = `
	<pkp-list-panel class="listPanel--thoth" :items="currentItems" :is-sidebar-visible="true">
		<template slot="header">
			<pkp-header>
				<h2>{{ title }}</h2>
				<pkp-spinner v-if="isLoading"></pkp-spinner>
				<template slot="actions">
					<pkp-search :search-phrase="searchPhrase" @search-phrase-changed="setSearchPhrase"></pkp-search>
					<pkp-button @click="toggleSelectAll">
						{{ isAllSelected ? __('common.selectNone') : __('common.selectAll') }}
					</pkp-button>
					<pkp-button :is-disabled="selected.length === 0" @click="confirmRegister">
						{{ __('plugins.generic.thoth.register') }}
					</pkp-button>
				</template>
			</pkp-header>
		</template>
		<template slot="sidebar">
			<div class="listPanel__block">
				<pkp-header :is-one-line="false">
					<h3>{{ __('plugins.generic.thoth.imprint') }}</h3>
				</pkp-header>
				<label v-for="option in imprintOptions" :key="'imprint' + option.value" class="pkpFormField--options__option">
					<input v-model="imprintValue" class="pkpFormField--options__input" type="radio" :value="option.value" />
					<span class="pkpFormField--options__optionLabel">{{ option.label }}</span>
				</label>
			</div>
			<pkp-header :is-one-line="false"><h3>{{ __('common.filter') }}</h3></pkp-header>
			<div v-for="(filterSet, index) in filters" :key="index" class="listPanel__block">
				<pkp-header v-if="filterSet.heading"><h4>{{ filterSet.heading }}</h4></pkp-header>
				<pkp-filter
					v-for="filter in filterSet.filters"
					:key="filter.param + filter.value"
					v-bind="filter"
					:is-filter-active="isFilterActive(filter.param, filter.value)"
					@add-filter="addFilter"
					@remove-filter="removeFilter"
				></pkp-filter>
			</div>
		</template>
		<template v-if="isLoading && !currentItems.length" slot="itemsEmpty">
			<pkp-spinner></pkp-spinner> {{ __('common.loading') }}
		</template>
		<template slot="item" slot-scope="slotProps">
			<thoth-list-item
				:key="slotProps.item.id"
				:api-url="apiUrl"
				:item="slotProps.item"
				:errors="errors[slotProps.item.id] || []"
				:is-loading="startedItems.indexOf(slotProps.item.id) !== -1"
				:is-selected="selected.indexOf(slotProps.item.id) !== -1"
				@select-item="selectItem"
			></thoth-list-item>
		</template>
		<pkp-pagination
			v-if="lastPage > 1"
			slot="footer"
			:current-page="currentPage"
			:is-loading="isLoading"
			:last-page="lastPage"
			@set-page="setPage"
		></pkp-pagination>
	</pkp-list-panel>
`;

const featureVideoFormTemplate = `
	<pkp-form v-if="form" v-bind="form" @set="set"></pkp-form>
	<pkp-spinner v-else></pkp-spinner>
`;

function renderFrom(vueApi, template) {
	const compiled = vueApi.compile(template);
	return {
		render: compiled.render,
		staticRenderFns: compiled.staticRenderFns,
	};
}

function registerListItem(vueApi, jquery, pkpApi) {
	vueApi.component('thoth-list-item', {
		name: 'ThothListItem',
		...renderFrom(vueApi, listItemTemplate),
		props: {
			apiUrl: {type: String, required: true},
			errors: {type: Array, default: () => []},
			item: {type: Object, required: true},
			isLoading: {type: Boolean, default: false},
			isSelected: {type: Boolean, default: false},
		},
		data: () => ({expanded: false, workStatus: null, fetchError: false}),
		computed: {
			hasErrors() {
				return this.errors.length > 0;
			},
			currentPublication() {
				return (this.item.publications || []).find(
					(publication) => publication.id === this.item.currentPublicationId,
				);
			},
			statusLabel() {
				if (!this.item.thothWorkId) {
					return this.__(
						this.hasErrors
							? 'common.error'
							: 'plugins.generic.thoth.status.unregistered',
					);
				}
				if (this.fetchError) {
					return this.__('common.error');
				}
				if (!this.workStatus) {
					return '...';
				}
				return this.__(workStatusLocaleMap[this.workStatus] || this.workStatus);
			},
			badgeStyle() {
				let color = '#777777';
				if (!this.item.thothWorkId && this.hasErrors) {
					color = '#D00A0A';
				} else if (this.fetchError) {
					color = '#D00A0A';
				} else if (this.item.thothWorkId) {
					color = workStatusColorMap[this.workStatus] || '#777777';
				}
				return {borderColor: color, color};
			},
		},
		methods: {
			fetchWorkStatus() {
				if (!this.item.thothWorkId) {
					return;
				}
				this.fetchError = false;
				jquery.ajax({
					method: 'GET',
					url: `${this.apiUrl}/${this.item.id}/thothWorkStatus`,
					headers: {'X-Csrf-Token': pkpApi.currentUser.csrfToken},
					success: (response) => {
						this.workStatus = response.workStatus;
					},
					error: () => {
						this.fetchError = true;
					},
				});
			},
		},
		watch: {
			'item.thothWorkId': {
				immediate: true,
				handler(workId) {
					if (!workId) {
						return;
					}
					if (this.item.thothWorkStatus) {
						this.workStatus = this.item.thothWorkStatus;
					} else {
						this.fetchWorkStatus();
					}
				},
			},
		},
	});
}

function registerListPanel(vueApi, jquery, pkpApi) {
	vueApi.component('thoth-list-panel', {
		name: 'ThothListPanel',
		...renderFrom(vueApi, listPanelTemplate),
		props: {
			apiUrl: {type: String, required: true},
			count: {type: Number, default: 30},
			csrfToken: {type: String, default: ''},
			filters: {type: Array, default: () => []},
			getParams: {type: Object, default: () => ({})},
			id: {type: String, required: true},
			imprintOptions: {type: Array, default: () => []},
			items: {type: Array, default: () => []},
			itemsMax: {type: Number, default: 0},
			selectedImprint: {type: String, default: ''},
			title: {type: String, default: ''},
		},
		data() {
			return {
				activeFilters: {},
				currentItems: [...this.items],
				currentPage: 1,
				errors: {},
				imprintValue: this.selectedImprint || '',
				isLoading: false,
				registrationBatch: null,
				searchPhrase: '',
				selected: [],
				startedItems: [],
				totalItems: this.itemsMax,
			};
		},
		computed: {
			lastPage() {
				return Math.ceil(this.totalItems / this.count);
			},
			isAllSelected() {
				const unregistered = this.currentItems.filter(
					(item) => !item.thothWorkId,
				);
				return (
					this.selected.length > 0 &&
					this.selected.length === unregistered.length
				);
			},
		},
		methods: {
			fetchItems() {
				this.isLoading = true;
				jquery.ajax({
					method: 'GET',
					url: this.apiUrl,
					data: {
						...this.getParams,
						searchPhrase: this.searchPhrase || undefined,
						count: this.count,
						offset: (this.currentPage - 1) * this.count,
						...this.activeFilters,
					},
					success: (response) => {
						this.currentItems = response.items || [];
						this.totalItems = response.itemsMax ?? this.totalItems;
						this.$emit('set', this.id, {
							items: this.currentItems,
							itemsMax: this.totalItems,
						});
					},
					complete: () => {
						this.isLoading = false;
					},
				});
			},
			setSearchPhrase(searchPhrase) {
				this.searchPhrase = searchPhrase;
				this.currentPage = 1;
				this.fetchItems();
			},
			setPage(page) {
				this.currentPage = page;
				this.fetchItems();
			},
			addFilter(param, value) {
				this.activeFilters = {[param]: value};
				this.currentPage = 1;
				this.fetchItems();
			},
			removeFilter() {
				this.activeFilters = {};
				this.currentPage = 1;
				this.fetchItems();
			},
			isFilterActive(param, value) {
				const active = this.activeFilters[param];
				return Array.isArray(active) ? active.includes(value) : active === value;
			},
			selectItem(itemId) {
				this.selected = this.selected.includes(itemId)
					? this.selected.filter((id) => id !== itemId)
					: [...this.selected, itemId];
			},
			toggleSelectAll() {
				this.selected = this.isAllSelected
					? []
					: this.currentItems
							.filter((item) => !item.thothWorkId)
							.map((item) => item.id);
			},
			confirmRegister() {
				if (!this.imprintValue) {
					pkpApi.eventBus.$emit(
						'notify',
						this.__('plugins.generic.thoth.imprint.required'),
						'warning',
					);
					return;
				}
				const focusElement = globalThis.document?.activeElement;
				const title = this.__(
					'plugins.generic.thoth.actions.register.label',
				);
				const options = {
					title,
					okButton: title,
					cancelButton: this.__('common.cancel'),
					dialogText: this.__(
						'plugins.generic.thoth.actions.register.prompt',
						{count: this.selected.length},
					),
					callback: this.registerAll,
					closeCallback: () => focusElement?.focus(),
					titleIcon: 'modal_confirm',
					width: 'auto',
				};
				jquery(
					`<div id="${jquery.pkp.classes.Helper.uuid()}" class="pkp_modal pkpModalWrapper" tabindex="-1"></div>`,
				).pkpHandler(
					'$.pkp.controllers.modal.ConfirmationModalHandler',
					options,
				);
			},
			registerAll() {
				this.startedItems = [...this.selected];
				this.registrationBatch = {
					total: this.selected.length,
					completed: 0,
					failed: 0,
				};
				this.selected.forEach((id) => {
					const item = this.currentItems.find((candidate) => candidate.id === id);
					if (item) {
						this.submitItem(item);
					}
				});
			},
			submitItem(item) {
				let succeeded = false;
				jquery.ajax({
					method: 'PUT',
					url: `${this.apiUrl}/${item.id}/register`,
					headers: {
						'X-Csrf-Token': pkpApi.currentUser.csrfToken,
						'X-Http-Method-Override': 'PUT',
					},
					data: {
						thothImprintId: this.imprintValue,
						disableNotification: true,
					},
					success: (response) => {
						succeeded = true;
						this.updateItem(response);
					},
					error: (response) => this.setErrors(item.id, response),
					complete: () => this.completeItemRegistration(item.id, succeeded),
				});
			},
			updateItem(updatedItem) {
				this.currentItems = this.currentItems.map((item) =>
					item.id === updatedItem.id ? updatedItem : item,
				);
				this.$emit('set', this.id, {items: this.currentItems});
			},
			setErrors(itemId, response) {
				const responseData = response.responseJSON || response;
				if (responseData?.errors) {
					this.$set(this.errors, itemId, responseData.errors);
				}
			},
			completeItemRegistration(itemId, succeeded) {
				this.selected = this.selected.filter((id) => id !== itemId);
				this.startedItems = this.startedItems.filter((id) => id !== itemId);
				this.registrationBatch.completed += 1;
				if (!succeeded) {
					this.registrationBatch.failed += 1;
				}
				if (
					this.registrationBatch.completed === this.registrationBatch.total &&
					this.registrationBatch.failed === 0
				) {
					pkpApi.eventBus.$emit(
						'notify',
						this.__(
							'plugins.generic.thoth.actions.register.success',
							{count: this.registrationBatch.total},
						),
						'success',
					);
				}
			},
		},
	});
}

function registerFeatureVideoForm(vueApi, jquery, pkpApi) {
	vueApi.component('feature-video-form', {
		name: 'FeatureVideoForm',
		...renderFrom(vueApi, featureVideoFormTemplate),
		props: {submissionId: {type: Number, required: true}},
		data: () => ({form: null}),
		methods: {
			set(key, data) {
				if (this.form?.id !== key) {
					return;
				}
				this.form = {...this.form, ...data};
			},
		},
		mounted() {
			const workflow =
				jquery.pkp?.plugins?.generic?.thothplugin?.workflow;
			const url = (workflow?.featureVideoUrl || '').replace(
				'__submissionId__',
				this.submissionId,
			);
			if (!url) {
				return;
			}
			jquery.ajax({
				method: 'GET',
				url,
				headers: {'X-Csrf-Token': pkpApi.currentUser.csrfToken},
				success: (response) => {
					this.form = response;
				},
				error: (response) => {
					pkpApi.eventBus.$emit(
						'notify',
						response.responseJSON?.error || 'common.error',
						'warning',
					);
				},
			});
		},
	});
}

export function registerVue2Components({
	jquery = globalThis.$,
	pkpApi = globalThis.pkp,
} = {}) {
	const vueApi = pkpApi?.Vue;
	if (
		typeof vueApi?.compile !== 'function' ||
		typeof vueApi?.component !== 'function'
	) {
		return false;
	}

	registerListItem(vueApi, jquery, pkpApi);
	registerListPanel(vueApi, jquery, pkpApi);
	registerFeatureVideoForm(vueApi, jquery, pkpApi);
	return true;
}
