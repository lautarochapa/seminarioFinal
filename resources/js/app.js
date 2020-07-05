
require('./bootstrap');

window.Vue = require('vue');

//categorias
Vue.component('node-tree', require('./components/NodeTree.vue').default);
Vue.component('tree', require('./components/Tree.vue').default);
Vue.component('category-component', require('./components/CategoryComponent.vue').default);

//users

Vue.component('user-component', require('./components/UserComponent.vue').default);

const app = new Vue({
    el: '#app',
});
