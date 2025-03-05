<template>
  <div v-if="localSortedDataTable && localSortedDataTable.length && mainVar.response_data_exist" class="-mx-4">

    <div class="dc-table">

      <table>
        <thead>
          <tr>
            <th>Editor</th>
            <th>Title</th>
            <th>AI</th>
            <th>Original</th>
            <th>Plagiarism Clear</th>
            <th>
              <div @click="sort_down = !sort_down" class="cursor-pointer inline-flex flex-nowrap items-center gap-x-0.5">
                <span>Date</span>
                <i :class="[{ 'mdi-arrow-down': sort_down, 'mdi-arrow-up': !sort_down }, 'mdi-arrow-down mdi']"></i>
              </div>
            </th>
            <th>Results</th>
            <th>Share Scan</th>
            <th>Remove Scan</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, index) in items_show_in_table" :key="row.id">
            <td class="text-base italic">Owner</td>
            <td class="text-base italic">
              <template v-if="row.title">{{ row.title }}</template>
              <template v-else>Lorem ipsum</template>
            </td>
            <td class="!text-red-300 text-xl font-medium" v-html="convertToPercent(row.score_ai)"></td>
            <td class="text-xl !text-green-100 font-medium" v-html="convertToPercent(row.score_ai, true)"></td>
            <td class="text-sm">N/A</td>
            <td class="text-sm whitespace-nowrap" v-html="formatTableTimestamp(row.request_timestamp)"></td>
            <td>
              <a :href="row.results_url" target="_blank"
                class="btn-main px-3 py-1.5 no-underline cursor-pointer bg-transparent appearance-none border border-solid border-primary-70 rounded-lg hover:text-primary-70 text-primary-70 text-xs font-medium">
                Results
              </a>
            </td>
            <td>
              <button @click="share({ item_id: row.id, url: row.share_url, action: 'share' })"
                class="dc-share-link border-none bg-transparent appearance-none cursor-pointer relative tooltip-trigger"
                type="button">
                <i class="share-icon mdi mdi-share-variant"></i>
                <div class="tooltip bg-gray-700 text-white text-xs rounded py-2 px-4">
                  {{ row.share_title.text }}
                </div>
              </button>

            </td>
            <td>
              <button @click="remove_scan({ item_id: row.id, action: 'remove' })"
                class="dc-remove-link border-none rounded-lg bg-transparent px-4 appearance-none cursor-pointer"
                type="button">
                <i class="remove-icon mdi mdi-trash-can-outline"></i>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="px-4 flex justify-end items-center flex-wrap gap-x-6">
      <div class="items-per-page">
        <div class="items-per-page__select">
          <select @change="current_page = 1" v-model="items_per_page"
            class="!p-2 !inline-block !text-xs !grow-0 !h-auto !basis-auto !min-h-0 !max-w-[80px]">
            <option value="5">5</option>
            <option value="10">10</option>
            <option value="15">15</option>
            <option value="20">20</option>
          </select>
        </div>
      </div>
      <div class="items-info">
        <span class="text-sm">{{ current_items_range }} of {{ ready_data.length }}</span>
      </div>
      <div class="items-pagination">
        <ul>
          <li>
            <button @click="current_page = 1" :disabled="this.current_page === 1" class="appearance-none bg-transparent">
              <i class="mdi-page-first mdi"></i>
            </button>
          </li>
          <li>
            <button @click="prevItems" :disabled="this.current_page === 1" class="appearance-none bg-transparent">
              <i class="mdi-chevron-left mdi"></i>
            </button>
          </li>
          <li>
            <button :disabled="this.total_pages === this.current_page" @click="nextItems"
              class="appearance-none bg-transparent">
              <i class="mdi-chevron-right mdi"></i>
            </button>
          </li>
          <li>
            <button @click="current_page = total_pages" :disabled="this.total_pages === this.current_page"
              class="appearance-none bg-transparent">
              <i class="mdi-page-last mdi"></i>
            </button>
          </li>
        </ul>
      </div>
    </div>

  </div>
  <div v-else class="text-center">
    <a :href="mainVar.adminPostListUrl"
      class="!shadow-none rounded overflow-hidden hover:bg-primary-70 hover:text-white no-underline text-xl font-medium text-white px-3 py-2 bg-primary-50">Start
      your first scan</a>
  </div>
</template>

<script>

import axios from 'axios';


export default {
  data: function () {
    return {
      data: [],
      localSortedDataTable: JSON.parse(JSON.stringify(this.sorted_data_table)),
      items_per_page: 5,
      current_page: 1,
      sort_down: true,
    }
  },
  methods: {
    remove_scan: function (obj) {
      let _self = this;
      _self.ready_data = obj;

      axios.post(_self.mainVar.ajax_url, {
        action: 'ai_scan_result_remove',
        id: obj.item_id
      })
        .then(function (response) {
          console.log(response.data);
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
    share: function (obj) {
      let _self = this;
      _self.ready_data = obj;

      let url = typeof obj.url !== 'undefined' ? obj.url : 'EMPTY';
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url)
          .then(() => {
            console.log('Copied');
          })
          .catch(err => {
            console.error('Failed to copy', err);
          });
      } else {
        console.warn('Clipboard API is not supported');
        _self.fallbackCopyToClipboard(url); // for non-HTTPS
      }

    },
    fallbackCopyToClipboard: function (text) {
      const textArea = document.createElement("textarea");
      textArea.value = text;

      // Avoid showing the textarea element
      textArea.style.position = "fixed";
      textArea.style.left = "-99999px";

      document.body.appendChild(textArea);
      textArea.select();

      try {
        document.execCommand('copy');
        console.log('Text copied to clipboard!');
      } catch (err) {
        console.error('Unable to copy to clipboard:', err);
      }

      document.body.removeChild(textArea);
    },
    nextItems: function () {
      let _self = this;
      _self.current_page += 1;
    },
    prevItems: function () {
      let _self = this;
      if (_self.current_page > 1) {
        _self.current_page -= 1;
      }
    },
    convertToPercent: function (val, calculate) {
      let percent_val = Math.floor(val * 100);
      if (calculate) {
        return (100 - percent_val) + '%';
      } else {
        return percent_val + '%';
      }
    },
    formatTableTimestamp: function (timestamp) {
      let formatTimestampArr = timestamp.split(' ');
      let time = formatTimestampArr[1].split(':').slice(0, -1).join(':');
      return formatTimestampArr[0] + ', ' + time;
    }
  },
  props: {
    mainVar: { type: Object },
    sorted_data_table: { type: Array, required: true }
  },
  mounted: function () {},
  computed: {
    ready_data: {
      get: function () {
        let _self = this;
        let res;
        if (_self.sort_down) {
          res = _self.localSortedDataTable;
        } else {
          res = _self.localSortedDataTable.slice().sort((a, b) => {
            return new Date(b.request_timestamp) - new Date(a.request_timestamp);
          });
        }
        return res;
      },
      set: function (obj) {
        let _self = this;

        if (obj.action === 'share') {

          _self.localSortedDataTable = _self.localSortedDataTable.map(function (item, index, arr) {
            if (item.id === obj.item_id) {
              item.share_title.copied = true;
              let old_txt = item.share_title.text;
              item.share_title.text = "Copied!";
              setTimeout(function () {
                item.share_title.copied = false;
                item.share_title.text = old_txt;
              }, 3000);
            }
            return item;
          })

        }
        if (obj.action === 'remove') {

          let remove_index;
          _self.localSortedDataTable.forEach(function (item, index, arr) {
            if (item.id === obj.item_id) {
              remove_index = index;
            }
          });
          _self.localSortedDataTable.splice(remove_index, 1);

        }

      }
    },
    items_show_in_table: function () {
      let _self = this;
      let items = [];

      let start = _self.items_per_page * _self.current_page;
      let end = _self.items_per_page * (_self.current_page - 1);
      if (end) {
        items = _self.ready_data.slice(-start, -end);
      } else {
        items = _self.ready_data.slice(-_self.items_per_page);
      }

      if (_self.sort_down) {
        items.sort((a, b) => {
          return new Date(b.request_timestamp) - new Date(a.request_timestamp);
        });
      } else {
        items.sort((a, b) => {
          return new Date(a.request_timestamp) - new Date(b.request_timestamp);
        });
      }

      return items;
    },
    total_pages: function () {
      let _self = this;
      return Math.ceil(_self.ready_data.length / _self.items_per_page)
    },
    current_items_range: function () {
      let _self = this;
      let start;
      let end;

      if (_self.current_page === _self.total_pages) {
        end = _self.ready_data.length;
        start = end - _self.items_show_in_table.length + 1;
      } else {
        end = _self.current_page * _self.items_per_page;

        // update end val on last page
        if (end > _self.ready_data.length && _self.current_page > 1) {
          _self.current_page -= 1;
          end = _self.ready_data.length;
        }
        // update end val on last page

        start = end - _self.items_per_page + 1;
      }
      return start + ' - ' + end;
    }

  }
}
</script>

<style lang="scss" scoped>
table {
  width: 100%;
  border-collapse: collapse;

  th,
  td {
    text-align: center;
    @apply px-4 py-0;
    color: rgba(#000, .87);
    vertical-align: middle;
    border-bottom: thin solid rgba(#000, .12);

    &:nth-child(1),
    &:nth-child(2),
    &:nth-child(6) {
      text-align: left;
    }
  }

  th {
    @apply text-sm font-medium leading-normal;
    height: 56px;
  }

  td {
    height: 52px;
    @apply py-1.5;
  }
}

.originality-ai--admin-container .main-content-item select {
  min-width: 0 !important;
}

.btn-main {
  letter-spacing: .0892857143em;

  &:hover {
    background: rgba(33, 33, 33, .04) !important;
  }
}

.dc-share-link {
  width: 28px;
  height: 28px;
  display: inline-flex;
  flex-direction: row;
  flex-wrap: nowrap;
  align-items: center;
  justify-content: center;

}

.dc-remove-link {
  height: 36px;

  display: inline-flex;
  flex-direction: row;
  flex-wrap: nowrap;
  align-items: center;
  justify-content: center;

  &:hover {
    background: rgba(33, 33, 33, .04) !important;
  }

}

.mdi:before {
  display: inline-block;
  font: normal normal normal 24px/1 "Material Design Icons";
  font-size: inherit;
  text-rendering: auto;
  line-height: inherit;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

.mdi-page-first::before {
  content: "\F0600";
}

.mdi-page-last::before {
  content: "\F0601";
}

.mdi-chevron-right::before {
  content: "\F0142";
}

.mdi-chevron-left::before {
  content: "\F0141";
}

.mdi-share-variant::before {
  content: "\F0497";
}

.mdi-trash-can-outline::before {
  content: "\F0A7A";
}

.items-info {
  color: rgba(#000, .87);
}

.items-pagination {
  ul {
    display: flex;
    flex-direction: row;
    flex-wrap: nowrap;
    align-items: center;
    justify-content: flex-start;

    li {
      margin: 0;

      &:first-child {
        button {
          margin-left: 0 !important;
        }
      }

      button {
        width: 36px;
        height: 36px;
        margin: 4.8px;
        display: flex;
        flex-direction: row;
        flex-wrap: nowrap;
        align-items: center;
        justify-content: center;
        background: transparent;
        cursor: pointer;
        border: 0;

        i {
          font-size: 24px;
          color: rgba(#000, .87);
        }

        &[disabled] {
          pointer-events: none;

          i {
            opacity: .26;
          }
        }

      }
    }
  }

}

.tooltip {
  position: absolute;
  z-index: 100;
  top: 100%;
  left: 50%;
  transform: translateX(-50%);
  margin-top: 0.5rem;
  opacity: 0;
  transition: opacity 0.3s ease;
  pointer-events: none;
  min-width: 98px;
}

.tooltip-trigger:hover .tooltip {
  opacity: 1;
}

.mdi-arrow-down {
  font-size: 21px;

  &:before {
    content: "\F0045";
  }
}

.mdi-arrow-up {
  font-size: 21px;

  &:before {
    content: "\F005D";
  }
}
</style>