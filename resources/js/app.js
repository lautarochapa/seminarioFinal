
require('./bootstrap');

window.Vue = require('vue');


//NAVBAR
Vue.component('navbar', require('./components/NavBar.vue').default);


//categorias
Vue.component('node-tree', require('./components/NodeTree.vue').default);
Vue.component('tree', require('./components/Tree.vue').default);
Vue.component('category-component', require('./components/CategoryComponent.vue').default);

//users

Vue.component('user-component', require('./components/UserComponent.vue').default);

//product
Vue.component('product-component', require('./components/ProductComponent.vue').default);
//utensils
Vue.component('utensils-component', require('./components/UtensilsComponent.vue').default);


Vue.component('front-component', require('./components/Front.vue').default);




Vue.component('node-tree2', require('./components/NodeTree2.vue').default);
Vue.component('tree2', require('./components/Tree2.vue').default);


Vue.component('autocomplete-component', require('./components/AutocompleteComponent.vue').default);






Vue.component('productCard', require('./components/ProductCard.vue').default);




const app = new Vue({
    el: '#app',
});
