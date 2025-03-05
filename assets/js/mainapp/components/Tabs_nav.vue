<template>
  <div class="originality-ai-navs">
    <div class="inline-flex max-w-full flex-nowrap">
      <div class="" v-for="tab in mainVar.tabs_data" :key="tab.title">
        <a href="#" @click.prevent="handleTabClick(tab)"
          :class="['!shadow-none rounded overflow-hidden hover:text-black block no-underline text-base text-black px-3 py-2', { 'bg-primary-30': tab.active }]">{{ tab.title }}</a>
      </div>
    </div>
  </div>
</template>

<script>

export default {
  data: function () {
    return {
    }
  },
  methods: {
    handleTabClick: function (tab) {
      this.$emit('change_tab', tab);
    }
  },
  props: {
    mainVar: { type: Object }
  }
}
</script>

<style lang="scss">
.originality-ai-navs {
  margin-top: 24px;
  max-width: calc(100% - 40px);
  margin-left: auto;
  margin-right: auto;

}
</style>