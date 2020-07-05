require('../../resources/js/bootstrap');

window.Vue = require('vue');

Vue.component('category-component', require('../../resources/js/components/CategoryComponent.vue').default);

const app = new Vue({
    el: '#app',
});
