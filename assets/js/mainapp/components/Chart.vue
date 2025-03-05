<template>
  <apexchart :options="chartOptions" :series="series"></apexchart>
</template>

<script>

import VueApexCharts from "vue3-apexcharts";

export default {
  data: function () {
    return {

    }
  },
  props: {
    series: { type: Array },
    mainVar: {
      type: Object,
      required: true
    }
  },
  computed: {
    chartOptions: function () {
      let _self = this;
      return {
        chart: {
          height: 180,
          width: '100%',
          type: 'line',
          toolbar: {
            show: false
          },
          zoom: {
            enabled: false
          }
        },
        stroke: {
          width: 5,
          curve: 'smooth',
        },
        grid: {
          show: false
        },
        yaxis: {
          show: false
        },
        xaxis: {
          type: 'category',
          categories: _self.mainVar.lastDays.categories,
          // tickAmount: 7,
          overwriteCategories: _self.mainVar.lastDays.overwriteCategories,
          labels: {
            rotate: 0,
            hideOverlappingLabels: false,
            showDuplicates: false,
            trim: false,
            style: {
              colors: [],
              fontSize: '12px',
              fontFamily: 'satoshi',
              fontWeight: 400,
              cssClass: 'apexcharts-xaxis-label',
            },

            formatter: function (value) {
              const date = new Date(value);
              const options = {
                day: 'numeric',
                month: 'short',
                year: 'numeric'
              };

              if (value) {
                return date.toLocaleDateString('en-GB', options).replace(',', '');
              } else {
                return '';
              }

            },
          },
          tooltip: {
            enabled: false,
          },
          axisTicks: {
            show: true,
            borderType: 'solid',
            color: 'rgb(224, 224, 224)',
            height: 4,
            offsetX: 0,
            offsetY: 0
          },
          axisBorder: {
            show: true,
            // color: '#78909C',
            height: 1,
            width: '100%',
            offsetX: 0,
            offsetY: 3
          },

        },
        tooltip: {
          marker: {
            fillColors: ['rgba(120,89,255,0.9)']
          },
          style: {
            fontSize: '12px',
            fontFamily: 'satoshi'
          },
        },
        markers: {
          colors: ['rgba(120, 89, 255, 1)'],
        },
        fill: {
          type: 'gradient',
          gradient: {
            shade: 'light',
            shadeIntensity: 0.5,
            inverseColors: false,
            opacityFrom: 0.5,
            opacityTo: 0.9,
            stops: [0, 100],
            colorStops: [{
              offset: 0,
              color: 'rgba(188, 172, 255, 0.5)',
              opacity: 1
            }, {
              offset: 50,
              color: 'rgba(120, 89, 255, 0.84)',
              opacity: 1
            }]
          }
        }
      }
    },
  },
  components: {
    apexchart: VueApexCharts,
  }
}
</script>

<style lang="scss"></style>