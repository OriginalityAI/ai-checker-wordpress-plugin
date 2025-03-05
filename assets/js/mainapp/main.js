import { createApp } from 'vue';

import App from './components/main-component.vue'

import Cookies from 'js-cookie';

let show_vue = Cookies.get('show_vue') === 'true';

if (show_vue) {
    let app = createApp(App);
    app.mount('#app');
}
