<template>
	<app-tabs-nav @change_tab="change_tab" :mainVar="mainVar"></app-tabs-nav>
	<app-content @updateAllDaysObj="updateAllDaysObj" @updateLastDaysObj="updateLastDaysObj" @set_is_loaded="set_is_loaded"
		:mainVar="mainVar"></app-content>
</template>

<script>
import Tabs_nav from './Tabs_nav.vue';
import Content from './Content.vue';

export default {
	data: function () {
		return {
			mainVar: {
				ajax_url: window.dc_ajax.url,
				adminPostListUrl: window.dc_ajax.adminPostListUrl,
				is_loaded: false,
				response_data_exist: null,
				scans_title: window.dc_ajax.scans_title,
				tabs_data: [{
					active: true,
					title: 'Dashboard',
					id: 'dashboard',
				}, {
					active: false,
					title: 'Settings',
					id: 'settings-originality-ai',
				}],
				lastDays: {},
				allDays: {},

				isArrayNotEmpty: function (value) {
					return Array.isArray(value) && value.length > 0;
				}
			}
		}
	},
	mounted: function () {
		let _self = this;
		_self.mainVar.lastDays = _self.getLastDays(7);

		// console.log(_self.mainVar.lastDays, '_self.mainVar.lastDaysArr');
	},
	methods: {
		updateLastDaysObj: function (days, offset) {
			const result = this.getLastDays(days, offset);
			this.mainVar.lastDays = result;
			// console.log(this.mainVar.lastDays, 'this.mainVar.lastDays');
		},

		updateAllDaysObj: function (days, offset) {
			const result = this.getLastDays(days, offset);
			// console.log(result, 'result updateAllDaysObj');
			// console.log(result.length, 'result updateAllDaysObj length');
			this.mainVar.allDays = result;
			// console.log(this.mainVar.allDays, 'this.mainVar.allDays');
		},

		set_is_loaded: function (val) {
			let _self = this;
			_self.mainVar.is_loaded = val.is_loaded;
			_self.mainVar.response_data_exist = val.response_data_exist;
		},
		change_tab: function (tab) {
			let _self = this;
			_self.mainVar.tabs_data.map(function (item, index, arr) {

				if (tab.title === item.title) {
					item.active = true;

					let settingsTabNavId = 'settings-originality-ai';
					if (item.id === settingsTabNavId) {
						const element = document.getElementById(settingsTabNavId);
						if (element) {
							element.style.display = 'block';
						}
					} else {
						const element = document.getElementById(settingsTabNavId);
						if (element) {
							element.style.display = 'none';
						}
					}

				} else {
					item.active = false;
				}
			});
		},

		formatDate: function (date, format = '/') {
			const month = String(date.getMonth() + 1).padStart(2, '0');
			const day = String(date.getDate()).padStart(2, '0');
			const year = date.getFullYear();
			if (format === '-') {
				return `${year}-${month}-${day}`;
			} else {
				return `${month}/${day}/${year}`;
			}
		},

		getLastDays: function (daysCount, offset = 0) {
			let _self = this;
			let days = daysCount - 1;
			let endDate = new Date();

			endDate.setDate(endDate.getDate() + offset);


			const datesObj = {
				categories: [],
				overwriteCategories: [],
				daysArray: [],
			};

			for (let i = days; i >= 0; i--) {
				let date = new Date(endDate);
				date.setDate(endDate.getDate() - i);
				datesObj.categories.push(_self.formatDate(date));

				datesObj.daysArray.push(_self.formatDate(date, '-'));

				let overwriteCategories_val = '';
				if (i === days || i === 0) {
					overwriteCategories_val = _self.formatDate(date);
				}
				datesObj.overwriteCategories.push(overwriteCategories_val);
			}

			// console.log(datesObj, 'datesObj');
			return datesObj;
		},
	},
	components: {
		'app-content': Content,
		'app-tabs-nav': Tabs_nav
	}
}
</script>

<style lang="scss">
// @import 'tailwindcss/base';
@use 'tailwindcss/components';
@use 'tailwindcss/utilities';
</style>