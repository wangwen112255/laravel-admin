import Vue from 'vue'

import App from '@/views/app.vue';
import router from '@/router';
import store from '@/store';
import VueCookie from 'vue-cookie' ;

// import 'normalize.css/normalize.css' // a modern alternative to CSS resets
import ElementUI from 'element-ui';
import 'element-ui/lib/theme-chalk/index.css';

window.Vue = require('vue');

Vue.prototype.$eventBus = new Vue();

Vue.use(ElementUI);
Vue.use(VueCookie);

require('./bootstrap');

new Vue({
    el: '#app',
    router,
    store,
    render: h => h(App),
});
