(function(){"use strict";function h({hasWorkLink:s,workStatus:i,statusRequestCompleted:r,workNotFound:t,fetchError:e,isPublished:o}){const n=s&&r&&!!i&&!t&&!e;return{view:n,unlink:s&&r&&t&&!e,update:n&&!o,register:!s}}function a(s,i,r="warning"){s.$emit("notify",i,r)}function d({ajax:s,eventBus:i,workflow:r,csrfToken:t,publicationId:e,notification:o}){r.loading=!0,r.refreshSection();const n=r.synchronizeUrl.replace("__publicationId__",e);s({method:"PUT",url:n,headers:{"X-Csrf-Token":t,"X-Http-Method-Override":"PUT"},error(I){const c=I.responseJSON?.errorMessage;a(i,typeof c=="string"&&c.trim()?c:r.connectionError)},complete(){if(typeof o?.notificationUrl!="string"||typeof o?.showNotification!="function"){a(i,r.connectionError),r.loading=!1,r.refreshSection();return}s({type:"POST",url:o.notificationUrl,success:o.showNotification,error(){a(i,r.connectionError)},complete(){r.loading=!1,r.refreshSection()},dataType:"json",async:!1})}})}function u({jquery:s=globalThis.$,pkpApi:i=globalThis.pkp,windowApi:r=globalThis.window}={}){const t=s?.pkp?.plugins?.generic?.thothplugin?.workflow;return!t||!i?.eventBus?null:(t.loading=!1,t.fetchError=!1,t.workStatus=null,t.statusRequestCompleted=!t.hasLinkedWork,t.workNotFound=!1,t.refreshSection=()=>{const e=i.registry?._instances?.app;typeof e?.$forceUpdate=="function"&&e.$forceUpdate()},t.actionVisibility=()=>h({hasWorkLink:t.hasLinkedWork,workStatus:t.workStatus,statusRequestCompleted:t.statusRequestCompleted,workNotFound:t.workNotFound,fetchError:t.fetchError,isPublished:t.submissionStatus===i.const.STATUS_PUBLISHED}),t.getWorkStatusLabel=()=>t.hasLinkedWork?t.workNotFound?t.statusNotFound:t.fetchError?t.statusError:t.workStatus?t.workStatusLabels[t.workStatus]||t.workStatus:"...":t.statusUnregistered,t.getWorkStatusClass=()=>t.workNotFound||t.fetchError?"thothWorkStatus__indicator--declined":{ACTIVE:"thothWorkStatus__indicator--active",FORTHCOMING:"thothWorkStatus__indicator--forthcoming",WITHDRAWN:"thothWorkStatus__indicator--declined",SUPERSEDED:"thothWorkStatus__indicator--superseded",POSTPONED_INDEFINITELY:"thothWorkStatus__indicator--postponed",CANCELLED:"thothWorkStatus__indicator--declined"}[t.workStatus]||"thothWorkStatus__indicator--superseded",t.fetchWorkStatus=()=>{t.hasLinkedWork&&(t.fetchError=!1,t.workNotFound=!1,t.workStatus=null,t.statusRequestCompleted=!1,t.refreshSection(),s.ajax({method:"GET",url:t.workStatusUrl,headers:{"X-Csrf-Token":i.currentUser.csrfToken},success(e){t.workStatus=e.workStatus},error(e){t.workNotFound=e.status===404&&e.responseJSON?.workNotFound===!0,t.fetchError=!t.workNotFound},complete(){t.statusRequestCompleted=!0,t.refreshSection()}}))},t.viewWork=()=>{r.open(`https://thoth.pub/books/${t.thothWorkId}`,"_blank","noopener,noreferrer")},t.performUnlink=()=>{t.loading=!0,t.refreshSection(),s.ajax({method:"POST",url:t.unlinkUrl,headers:{"X-Csrf-Token":i.currentUser.csrfToken,"X-Http-Method-Override":"DELETE"},success(){i.registry._instances.app.refreshSubmission()},error(e){a(i.eventBus,e.responseJSON?.error||e.responseJSON?.errorMessage||t.connectionError)},complete(){t.loading=!1,t.refreshSection()}})},t.confirmUnlink=()=>{const e=globalThis.document?.activeElement,o={title:t.unlinkTitle,okButton:t.unlinkTitle,cancelButton:t.cancelLabel,dialogText:t.unlinkConfirm,callback:t.performUnlink,closeCallback:()=>e?.focus(),titleIcon:"modal_confirm",width:"auto"};s(`<div id="${s.pkp.classes.Helper.uuid()}" class="pkp_modal pkpModalWrapper" tabindex="-1"></div>`).pkpHandler("$.pkp.controllers.modal.ConfirmationModalHandler",o)},t.openRegister=e=>{const o=globalThis.document?.activeElement,n={title:t.registerTitle,url:t.registerUrl.replace("__publicationId__",e),closeCallback:()=>o?.focus(),closeOnFormSuccessId:"register"};s(`<div id="${s.pkp.classes.Helper.uuid()}" class="pkp_modal pkpModalWrapper" tabindex="-1"></div>`).pkpHandler("$.pkp.controllers.modal.AjaxModalHandler",n)},t.updateMetadata=e=>d({ajax:s.ajax,eventBus:i.eventBus,workflow:t,csrfToken:i.currentUser.csrfToken,publicationId:e,notification:s.pkp?.plugins?.generic?.thothplugin?.notification}),i.eventBus.$on("form-success",e=>{e==="register"&&(i.registry._instances.app.refreshSubmission(),t.fetchWorkStatus())}),t.hasLinkedWork&&t.fetchWorkStatus(),t)}const p={ACTIVE:"plugins.generic.thoth.workStatus.active",FORTHCOMING:"plugins.generic.thoth.workStatus.forthcoming",WITHDRAWN:"plugins.generic.thoth.workStatus.withdrawn",SUPERSEDED:"plugins.generic.thoth.workStatus.superseded",POSTPONED_INDEFINITELY:"plugins.generic.thoth.workStatus.postponedIndefinitely",CANCELLED:"plugins.generic.thoth.workStatus.cancelled"},m={ACTIVE:"#00B24E",FORTHCOMING:"#DED15D",WITHDRAWN:"#D00A0A",CANCELLED:"#D00A0A",SUPERSEDED:"#777777",POSTPONED_INDEFINITELY:"#E08914"},f=`
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
`,g=`
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
`,k=`
	<pkp-form v-if="form" v-bind="form" @set="set"></pkp-form>
	<pkp-spinner v-else></pkp-spinner>
`;function l(s,i){const r=s.compile(i);return{render:r.render,staticRenderFns:r.staticRenderFns}}function _(s,i,r){s.component("thoth-list-item",{name:"ThothListItem",...l(s,f),props:{apiUrl:{type:String,required:!0},errors:{type:Array,default:()=>[]},item:{type:Object,required:!0},isLoading:{type:Boolean,default:!1},isSelected:{type:Boolean,default:!1}},data:()=>({expanded:!1,workStatus:null,fetchError:!1}),computed:{hasErrors(){return this.errors.length>0},currentPublication(){return(this.item.publications||[]).find(t=>t.id===this.item.currentPublicationId)},statusLabel(){return this.item.thothWorkId?this.fetchError?this.__("common.error"):this.workStatus?this.__(p[this.workStatus]||this.workStatus):"...":this.__(this.hasErrors?"common.error":"plugins.generic.thoth.status.unregistered")},badgeStyle(){let t="#777777";return!this.item.thothWorkId&&this.hasErrors||this.fetchError?t="#D00A0A":this.item.thothWorkId&&(t=m[this.workStatus]||"#777777"),{borderColor:t,color:t}}},methods:{fetchWorkStatus(){this.item.thothWorkId&&(this.fetchError=!1,i.ajax({method:"GET",url:`${this.apiUrl}/${this.item.id}/thothWorkStatus`,headers:{"X-Csrf-Token":r.currentUser.csrfToken},success:t=>{this.workStatus=t.workStatus},error:()=>{this.fetchError=!0}}))}},watch:{"item.thothWorkId":{immediate:!0,handler(t){t&&(this.item.thothWorkStatus?this.workStatus=this.item.thothWorkStatus:this.fetchWorkStatus())}}}})}function S(s,i,r){s.component("thoth-list-panel",{name:"ThothListPanel",...l(s,g),props:{apiUrl:{type:String,required:!0},count:{type:Number,default:30},csrfToken:{type:String,default:""},filters:{type:Array,default:()=>[]},getParams:{type:Object,default:()=>({})},id:{type:String,required:!0},imprintOptions:{type:Array,default:()=>[]},items:{type:Array,default:()=>[]},itemsMax:{type:Number,default:0},selectedImprint:{type:String,default:""},title:{type:String,default:""}},data(){return{activeFilters:{},currentItems:[...this.items],currentPage:1,errors:{},imprintValue:this.selectedImprint||"",isLoading:!1,registrationBatch:null,searchPhrase:"",selected:[],startedItems:[],totalItems:this.itemsMax}},computed:{lastPage(){return Math.ceil(this.totalItems/this.count)},isAllSelected(){const t=this.currentItems.filter(e=>!e.thothWorkId);return this.selected.length>0&&this.selected.length===t.length}},methods:{fetchItems(){this.isLoading=!0,i.ajax({method:"GET",url:this.apiUrl,data:{...this.getParams,searchPhrase:this.searchPhrase||void 0,count:this.count,offset:(this.currentPage-1)*this.count,...this.activeFilters},success:t=>{this.currentItems=t.items||[],this.totalItems=t.itemsMax??this.totalItems,this.$emit("set",this.id,{items:this.currentItems,itemsMax:this.totalItems})},complete:()=>{this.isLoading=!1}})},setSearchPhrase(t){this.searchPhrase=t,this.currentPage=1,this.fetchItems()},setPage(t){this.currentPage=t,this.fetchItems()},addFilter(t,e){this.activeFilters={[t]:e},this.currentPage=1,this.fetchItems()},removeFilter(){this.activeFilters={},this.currentPage=1,this.fetchItems()},isFilterActive(t,e){const o=this.activeFilters[t];return Array.isArray(o)?o.includes(e):o===e},selectItem(t){this.selected=this.selected.includes(t)?this.selected.filter(e=>e!==t):[...this.selected,t]},toggleSelectAll(){this.selected=this.isAllSelected?[]:this.currentItems.filter(t=>!t.thothWorkId).map(t=>t.id)},confirmRegister(){if(!this.imprintValue){r.eventBus.$emit("notify",this.__("plugins.generic.thoth.imprint.required"),"warning");return}const t=globalThis.document?.activeElement,e=this.__("plugins.generic.thoth.actions.register.label"),o={title:e,okButton:e,cancelButton:this.__("common.cancel"),dialogText:this.__("plugins.generic.thoth.actions.register.prompt",{count:this.selected.length}),callback:this.registerAll,closeCallback:()=>t?.focus(),titleIcon:"modal_confirm",width:"auto"};i(`<div id="${i.pkp.classes.Helper.uuid()}" class="pkp_modal pkpModalWrapper" tabindex="-1"></div>`).pkpHandler("$.pkp.controllers.modal.ConfirmationModalHandler",o)},registerAll(){this.startedItems=[...this.selected],this.registrationBatch={total:this.selected.length,completed:0,failed:0},this.selected.forEach(t=>{const e=this.currentItems.find(o=>o.id===t);e&&this.submitItem(e)})},submitItem(t){let e=!1;i.ajax({method:"PUT",url:`${this.apiUrl}/${t.id}/register`,headers:{"X-Csrf-Token":r.currentUser.csrfToken,"X-Http-Method-Override":"PUT"},data:{thothImprintId:this.imprintValue,disableNotification:!0},success:o=>{e=!0,this.updateItem(o)},error:o=>this.setErrors(t.id,o),complete:()=>this.completeItemRegistration(t.id,e)})},updateItem(t){this.currentItems=this.currentItems.map(e=>e.id===t.id?t:e),this.$emit("set",this.id,{items:this.currentItems})},setErrors(t,e){const o=e.responseJSON||e;o?.errors&&this.$set(this.errors,t,o.errors)},completeItemRegistration(t,e){this.selected=this.selected.filter(o=>o!==t),this.startedItems=this.startedItems.filter(o=>o!==t),this.registrationBatch.completed+=1,e||(this.registrationBatch.failed+=1),this.registrationBatch.completed===this.registrationBatch.total&&this.registrationBatch.failed===0&&r.eventBus.$emit("notify",this.__("plugins.generic.thoth.actions.register.success",{count:this.registrationBatch.total}),"success")}}})}function b(s,i,r){s.component("feature-video-form",{name:"FeatureVideoForm",...l(s,k),props:{submissionId:{type:Number,required:!0}},data:()=>({form:null}),methods:{set(t,e){this.form?.id===t&&(this.form={...this.form,...e})}},mounted(){const e=(i.pkp?.plugins?.generic?.thothplugin?.workflow?.featureVideoUrl||"").replace("__submissionId__",this.submissionId);e&&i.ajax({method:"GET",url:e,headers:{"X-Csrf-Token":r.currentUser.csrfToken},success:o=>{this.form=o},error:o=>{r.eventBus.$emit("notify",o.responseJSON?.error||"common.error","warning")}})}})}function v({jquery:s=globalThis.$,pkpApi:i=globalThis.pkp}={}){const r=i?.Vue;return typeof r?.compile!="function"||typeof r?.component!="function"?!1:(_(r,s,i),S(r,s,i),b(r,s,i),!0)}v(),u()})();
