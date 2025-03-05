<template>
	<div class="main-content" v-if="mainVar.is_loaded">
		<div v-for="tab in mainVar.tabs_data" :key="tab.id" v-show="tab.active && tab.id !== 'settings-originality-ai'"
			class="main-content-items">

			<template v-if="tab.id === 'dashboard'">
				<div class="main-content-item">

					<template v-if="mainVar.response_data_exist">
						<div v-if="!isManualSelectedDate" class="px-6 text-2xl font-satoshi font-bold main-content-item__title">
							<span>{{ period }}</span>
						</div>

						<div class="px-6 flex flex-nowrap items-center gap-2 py-4">
							<div class="text-sm font-satoshi font-bold "><span>Select Dates</span></div>
							<input type="text" ref="date" class="!p-2 !inline-block !text-xs !grow-0 !h-auto !basis-auto !min-h-0">
						</div>
					</template>

					<div class="px-6 pt-7 flex flex-wrap -mx-3 font-satoshi dc-chart-wrap">

						<div v-for="chart in charts_data" :key="chart.title" class="px-3 w-full md:w-1/2 xl:w-1/4">
							<div class="flex flex-nowrap -mx-3">
								<div class="w-1/3 px-3">
									<div class="font-base font-medium">{{ chart.title }}</div>
									<div :class="[
										{
											'text-3xl': chart.id === 'scans',
											'text-2xl': chart.id === 'credits',
											'text-xl': chart.id === 'words'
										},
										'font-medium',
										'pt-1',
										'whitespace-nowrap',
										'text-primary-70'
									]">
										{{ chart.val }}
									</div>
								</div>
								<div class="w-2/3">
									<div>
										<dc_chart :mainVar="mainVar" :series="chart.series"></dc_chart>
									</div>
								</div>
							</div>
						</div>


						<div v-for="chart in charts_comparison_data" :key="chart.title" class="px-3 w-full md:w-1/2 xl:w-1/4">

							<div class="flex flex-nowrap -mx-3">
								<div class="w-1/3 px-3">
									<div class="font-base font-medium">{{ chart.title }}</div>
									<div :class="[
										{
											'text-3xl': chart.id === 'scans',
											'text-2xl': chart.id === 'credits',
											'text-xl': chart.id === 'words',
											'text-lg': chart.id === 'ai_vs_original'
										},
										'font-medium',
										'pt-1',
										'whitespace-nowrap',
										'text-primary-70'
									]">
										{{ chart.val }}
									</div>
								</div>
								<div class="w-2/3 xl:w-4/5 xl:shrink-0">
									<div>
										<compare_chart :series="chart.series" :mainVar="mainVar"></compare_chart>
									</div>
								</div>
							</div>

						</div>

					</div>

				</div>

				<div class="main-content-item">
					<div class="px-6 text-2xl font-satoshi font-bold main-content-item__title">
						<span>{{ mainVar.scans_title }}</span>
					</div>
					<div :class="[
						{
							'overflow-x-auto': mainVar.response_data_exist,
						},
						'px-6',
						'pt-7',
						'font-satoshi',
					]">

						<dc_table :sorted_data_table="sorted_data_table" :mainVar="mainVar"></dc_table>

					</div>
				</div>

			</template>

		</div>
	</div>
	<div v-else>
		<div class="main-content">
			<div class="main-content-items">
				<div class="main-content-item text-center p-5">

					<div role="status">
						<svg aria-hidden="true" class="w-8 h-8 text-gray-200 animate-spin fill-primary-100" viewBox="0 0 100 101"
							fill="none" xmlns="http://www.w3.org/2000/svg">
							<path
								d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z"
								fill="currentColor" />
							<path
								d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z"
								fill="currentFill" />
						</svg>
						<span class="sr-only">Loading...</span>
					</div>

				</div>
			</div>
		</div>
	</div>
</template>

<script>

import axios from 'axios';

import AirDatepicker from 'air-datepicker'
import localeEn from 'air-datepicker/locale/en';
import 'air-datepicker/air-datepicker.css'

import Chart from './Chart.vue';
import Table from './Table_scans.vue';
import Compare_chart from './Compare_chart.vue';


export default {
	data: function () {
		return {
			table_data: [],
			sorted_data_table: [],
			readyDataObject: {},

			readyDataObjectComparison: {},

			period: 'This Week',
			isManualSelectedDate: false,

			charts_comparison_data: [],
			charts_data: [],
		}
	},
	mounted: function () {
		let _self = this;
		console.log('mounted');

		_self.ai_get_table_data(function (response) {
			// console.log(response, 'response response response');
			// console.log(response.hasOwnProperty('success'), 'hhh');
			if (!response.hasOwnProperty('success') || (response.hasOwnProperty('success') && response.success)) {
				_self.table_data = null;
				_self.table_data = response;

				response = response.map(function (item) {
					item.share_title = {
						text: "Share Scan",
						copied: false
					}
					return item;
				});

				_self.sorted_data_table = response.slice().sort((a, b) => {
					return new Date(a.request_timestamp) - new Date(b.request_timestamp);
				});

				_self.$emit('set_is_loaded', { is_loaded: true, response_data_exist: true });
			} else {
				_self.table_data = null;

				_self.sorted_data_table = [];

				_self.$emit('set_is_loaded', { is_loaded: true, response_data_exist: false });

				// if (response.success === false) {
				// console.log(response.success, 'response.success');
				// }
			}
			// console.log(response, 'response ai_get_table_data');

			if (_self.mainVar.is_loaded) {

				_self.readyDataObject = _self.ai_create_charts_data(response);
				_self.charts_data = _self.ai_create_series({ credits: true, scans: true, words: true });

				// chart comparison

				let tempSortedArr;
				if (_self.mainVar.response_data_exist) {
					tempSortedArr = response.slice().sort((a, b) => {
						return new Date(b.request_timestamp) - new Date(a.request_timestamp);
					});
				}

				let score_ai = 0;
				let score_original = 0;
				let iteration_val = 0;
				if (typeof tempSortedArr !== 'undefined') {
					tempSortedArr.forEach(function (item, index, arr) {
						if (item.score_ai !== 'undefined' && item.score_original !== 'undefined') {
							score_ai += parseFloat(item.score_ai);
							score_original += parseFloat(item.score_original);
							iteration_val++;
						}
					});
				}

				score_ai = score_ai / iteration_val;
				score_ai = score_ai * 100;
				score_ai = Math.floor(score_ai);
				score_original = 100 - score_ai;

				_self.charts_comparison_data = [{
					id: 'ai_vs_original',
					title: 'AI vs Original',
					val: '',
					series: [{
						name: 'AI',
						data: [score_ai],
					}, {
						name: 'ORIGINAL',
						data: [score_original],
					}]
				}];

				// chart comparison

				_self.$nextTick(function () {
					let element = _self.$refs.date;
					// console.log(element, 'element!'); 
					if (element) {
						new AirDatepicker(element[0], {
							range: true,
							locale: localeEn,
							multipleDatesSeparator: ' - ',
							maxDate: new Date(),
							dateFormat: 'dd.MM.yyyy',

							onSelect({
								date,
								formattedDate,
								datepicker
							}) {
								// console.log(date, 'date');
								if (formattedDate.length === 2) {
									let offsetArrDays = [];

									let dateEnd = new Date();
									dateEnd.setDate(dateEnd.getDate() - 1);
									// dateEnd.setDate(dateEnd.getDate());

									let month = String(dateEnd.getMonth() + 1).padStart(2, '0');
									let day = String(dateEnd.getDate()).padStart(2, '0');
									let year = dateEnd.getFullYear();
									let formatDate = `${day}.${month}.${year}`;

									offsetArrDays.push(formattedDate[1], formatDate);

									let daysCountBetweenSelectedDays = _self.countDaysBetween(formattedDate);
									let daysCountOffset = _self.countDaysBetween(offsetArrDays);

									_self.$emit('updateLastDaysObj', daysCountBetweenSelectedDays, -daysCountOffset);
									_self.readyDataObject = _self.ai_create_charts_data(_self.table_data);
									_self.charts_data = _self.ai_create_series({
										credits: true,
										scans: true,
										words: true
									});

									// let dateFromAirPicker = new Date(formattedDate);
									let options = {
										day: 'numeric',
										month: 'short',
										year: 'numeric'
									};

									let start = date[0].toLocaleDateString('en-GB', options).replace(',', '');
									let end = date[1].toLocaleDateString('en-GB', options).replace(',', '');

									_self.period = start + ' - ' + end;
									// _self.isManualSelectedDate = true;

								}

							}
						});
					} // if element exist

				})
				// console.log(_self.charts_data);
			}

		});
	},
	components: {
		'dc_chart': Chart,
		'dc_table': Table,
		'compare_chart': Compare_chart,
	},
	props: {
		mainVar: {
			type: Object,
			required: true
		},
	},
	methods: {
		countDaysBetween: function (dates) {
			const [start, end] = dates;

			const startDateParts = start.split('.');
			const endDateParts = end.split('.');

			const startDate = new Date(`${startDateParts[2]}-${startDateParts[1]}-${startDateParts[0]}`);
			const endDate = new Date(`${endDateParts[2]}-${endDateParts[1]}-${endDateParts[0]}`);

			const differenceInTime = endDate - startDate;

			const differenceInDays = parseInt((differenceInTime / (1000 * 3600 * 24)) + 1);
			// const differenceInDays = (differenceInTime / (1000 * 3600 * 24));

			return differenceInDays;
		},
		ai_get_table_data: function (callback) {
			let _self = this;

			axios.get(_self.mainVar.ajax_url, {
				params: {
					action: 'ai_get_table_data'
				}
			})
				.then(function (response) {
					callback(response.data);
				})
				.catch(function (error) {
					console.error('Axios Error:', error);
					if (error.response) {
						console.log('Response data:', error.response.data);
						console.log('Response status:', error.response.status);
						console.log('Response headers:', error.response.headers);
					} else if (error.request) {
						console.log('Request data:', error.request);
					} else {
						console.log('Error message:', error.message);
					}
				});

		},
		create_chart_data_empty_object: function (id, title, name) {
			return {
				id: id,
				title: title,
				val: '',
				series: [{
					name: name,
					data: [],
				}]
			}
		},
		ai_create_series: function (types = { scans: false, credits: false, words: false }) {
			let _self = this;
			let result = [];

			let credits_obj = null;
			let scans_obj = null;
			let words_obj = null;


			if (types.scans) {
				scans_obj = _self.create_chart_data_empty_object('scans', 'Content Scans', 'Scans');
			}
			if (types.credits) {
				credits_obj = _self.create_chart_data_empty_object('credits', 'Credits Used', 'Credits');
			}
			if (types.words) {
				words_obj = _self.create_chart_data_empty_object('words', 'Words Scanned', 'Words');
			}

			let scans_val = 0;
			let credits_val = 0;
			let words_val = 0;

			// console.log(_self.readyDataObject, '_self.readyDataObject');

			for (let key in _self.readyDataObject) {

				let dayCredit = 0;
				let dayScans = 0;
				let dayWords = 0;

				if (_self.mainVar.isArrayNotEmpty(_self.readyDataObject[key])) {

					dayScans += _self.readyDataObject[key].length;

					for (let i = 0; i < _self.readyDataObject[key].length; i++) {

						if (_self.readyDataObject[key][i].hasOwnProperty('credits_used')) {
							if (_self.readyDataObject[key][i].credits_used) {
								dayCredit += parseFloat(_self.readyDataObject[key][i].credits_used);
							}
						}

						if (_self.readyDataObject[key][i].hasOwnProperty('words_count')) {
							if (_self.readyDataObject[key][i].words_count) {
								dayWords += parseFloat(_self.readyDataObject[key][i].words_count);
							}
						}

					}
				}

				scans_val += parseFloat(dayScans);
				credits_val += parseFloat(dayCredit);
				words_val += parseFloat(dayWords);

				if (types.scans) {
					scans_obj.series[0].data.push(dayScans);
				}
				if (types.credits) {
					credits_obj.series[0].data.push(dayCredit);
				}
				if (types.words) {
					words_obj.series[0].data.push(dayWords);
				}
			}

			if (types.scans) {
				scans_obj.val = scans_val;
				result.push(scans_obj);
			}
			if (types.credits) {
				credits_obj.val = credits_val;
				result.push(credits_obj);
			}
			if (types.words) {
				words_obj.val = words_val;
				result.push(words_obj);
			}

			return result;
		},
		ai_create_charts_data: function (data, last_or_all = 'lastDays') {
			let _self = this;
			let result;
			if (_self.mainVar.isArrayNotEmpty(data)) {
				// console.log(data, 'not empty create charts data');
				// console.log(_self.mainVar.lastDays.daysArray, '_self.mainVar.lastDays.daysArray');


				// let filteredData = testData.filter(function(item) {
				let filteredData = data.filter(function (item) {
					let itemDate = item.request_timestamp.split(' ')[0];
					return _self.mainVar[last_or_all].daysArray.includes(itemDate);
				});
				// console.log(filteredData, 'filteredData');

				let groupedData = {};
				filteredData.forEach(function (item) {
					let date = item.request_timestamp.split(' ')[0];

					if (!groupedData[date]) {
						groupedData[date] = [];
					}

					groupedData[date].push(item);
				});
				// console.log(groupedData, 'groupedData');


				let readyDataObject = {};
				_self.mainVar[last_or_all].daysArray.forEach(function (item) {
					let arr_items_oneday = [];
					for (let k in groupedData) {
						if (item === k) {
							// arr_items_oneday.push(groupedData[k]);
							arr_items_oneday = null;
							arr_items_oneday = groupedData[k];
						}
					}
					readyDataObject[item] = arr_items_oneday;
				});
				result = readyDataObject;
				// console.log(result, 'result readyDataObject');
			}
			return result;
		},

	}
}
</script>

<style lang="css">
.air-datepicker {
	--adp-cell-background-color-selected: #7859ff;
	--adp-cell-background-color-selected-hover: #7859ff;
	--adp-cell-background-color-in-range: #f6f3ff;
	--adp-background-color-in-range: #f6f3ff;
	--adp-background-color-selected-other-month: #7859ff;
	--adp-cell-background-color-in-range-hover: #7859ff;
	--adp-day-name-color: #7859ff;
	--adp-color-current-date: #7859ff;
}

.air-datepicker-cell.-in-range-:hover,
.air-datepicker-cell.-in-range-.-focus- {
	color: #fff;
}
</style>
<style lang="scss">
.originality-ai--admin-container {
	box-sizing: border-box;

	*,
	*::before,
	*::after {
		box-sizing: border-box;
	}
}

#wpcontent {
	position: relative;

	&:before {
		content: '';
		display: block;
		background: linear-gradient(90deg, #fff, #fffc);
		z-index: 0;
		position: absolute;
		width: 100%;
		height: 100%;
		top: 0;
		left: 0;

	}
}

.main-content {

	// min-height: 100vh;
	&+.v-card {
		margin-top: 0;
	}
}

.v-card {
	margin-top: 24px;
}

.main-content-items {
	padding-top: 24px;

	.main-content-item {
		&:not(:last-child) {
			margin-bottom: 24px;
		}
	}
}

.main-content-item {
	border-radius: 4px;
	padding: 24px;
	background: rgb(252, 252, 255);
	box-shadow: 0 2px 1px -1px rgba(0, 0, 0, .2), 0 1px 1px 0 rgba(0, 0, 0, .14), 0 1px 3px 0 rgba(0, 0, 0, .12);
	max-width: calc(100% - 40px);
	margin-left: auto;
	margin-right: auto;

	&__title {
		text-decoration: underline;
		text-decoration-color: #feba59;
	}
}

.apexcharts-xaxis-texts-g text {
	&:first-child {
		text-anchor: middle;
	}

	&:last-child {
		text-anchor: end;
	}
}

.dc-chart-wrap {
	svg {
		overflow: visible;
	}
}

.v-card {
	background: rgb(252, 252, 255);
}
</style>