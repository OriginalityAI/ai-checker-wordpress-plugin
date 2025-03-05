<template>
  <apexchart :options="chartOptions" :series="series"></apexchart>
</template>

<script>

import VueApexCharts from 'vue3-apexcharts'

export default {
  data: function () {
    return {}
  },
  props: {
    mainVar: { type: Object },
    series: { type: Array },
  },
  mounted: function () { },
  components: {
    apexchart: VueApexCharts,
  },
  computed: {
    chartOptions: function () {
      let _self = this;
      return {
        chart: {
          type: 'bar',
          height: '400px',
          stacked: true,
          stackType: '100%',
          stackOnlyBar: true,

          toolbar: {
            show: false
          },
          zoom: {
            enabled: false
          }
        },
        dataLabels: {
          enabled: true,
          style: {
            fontSize: '8px',
            fontFamily: 'satoshi'
          },
        },
        tooltip: {
          enabled: true,
          y: {
            formatter: function (val) {
              console.log(val, 'val');
              return val + '%';
            },
          },
          style: {
            fontSize: '12px',
            fontFamily: 'satoshi'
          },
        },
        fill: {
          opacity: 1,
          colors: ['#eb4735', '#539d17']
        },
        grid: {
          show: false
        },
        yaxis: {
          show: false
        },
        xaxis: {
          type: 'category',
          categories: ['All time'],
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
            height: 1,
            width: '100%',
            offsetX: 0,
            offsetY: 3
          },
        },
        legend: {
          show: false,
          position: 'right',
          offsetX: 0,
          offsetY: 50,
          markers: {
            fillColors: ['#eb4735', '#539d17'],
          },
          labels: {
            colors: ['#eb4735', '#539d17'],
            useSeriesColors: false,
          },
        },
      }
    },
  }
}
</script>

<style lang="scss"></style>