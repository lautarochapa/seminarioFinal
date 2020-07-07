<template>
    <div class="container" :class="{'loading': loading}">






        <div class="row">
            <div class="col-lg-3 mb-4">
                <h1 class="mt-4">Filtros</h1>


                <h3 class="mt-2">Categorias</h3>

                 <tree2 :tree-data="categories" @bus="bus"></tree2>

   <!--  <form>
        <tree2
            :tree-data="categories"
            :value="address"
            @input="(newAddress) => {address = newAddress}"
        />
    </form>
-->




                <h3 class="mt-2">Marcas</h3>
                <div class="form-check" v-for="(br, index) in brands">
                    <input class="form-check-input" type="checkbox" :value="br.id" :id="'br'+index" v-model="selected.brands">
                    <label class="form-check-label" :for="'br' + index">
                        {{ br.nombre }} ({{ br.products_count }})
                    </label>
                </div>
            </div>
            <div class="col-lg-9">


                <p> Mostrando {{products.from}} a {{products.to}} de {{products.total}}

                <nav>
                    <ul class="pagination">
                        <li class="page-item" v-show="products['first_page_url']">
                            <a href="#" class="page-link" @click.prevent="getFirstPage">
                                <span>
                                  <span aria-hidden="true">«</span>
                                </span>
                            </a>
                        </li>
                        <li class="page-item" v-show="products['prev_page_url']">
                            <a href="#" class="page-link" @click.prevent="getPreviousPage">
                                <span>
                                  <span aria-hidden="true">〈</span>
                                </span>
                            </a>
                        </li>
                        <li class="page-item" :class="{ 'active': (products['current_page']=== n) }" v-for="n in products['last_page']">


                            <div v-if="products['current_page'] < 5">
                                <div v-if="n < 10">
                                    <a href="#" class="page-link" @click.prevent="getPage(n)">
                                        <span >
                                            {{ n }}
                                        </span>
                                    </a>
                                </div>
                            </div>

                            <div v-if="products['current_page'] >= 5 && products['last_page'] - products['current_page'] >= 5">
                                <div v-if="n >= products['current_page']-4 && n < products['current_page']+5">
                                    <a href="#" class="page-link" @click.prevent="getPage(n)">
                                        <span >
                                            {{ n }}
                                        </span>
                                    </a>
                                </div>
                            </div>

                            <div v-if="products['last_page'] - products['current_page'] <= 5">
                                <div v-if="n > products['last_page'] -9">
                                    <a href="#" class="page-link" @click.prevent="getPage(n)">
                                        <span >
                                            {{ n }}
                                        </span>
                                    </a>
                                </div>
                            </div>


                        </li>
                        <li class="page-item" v-show="products['next_page_url']">
                            <a href="#" class="page-link" @click.prevent="getNextPage">
                                <span>
                                  <span aria-hidden="true">〉</span>
                                </span>
                            </a>
                        </li>
                        <li class="page-item" v-show="products['last_page_url']">
                            <a href="#" class="page-link" @click.prevent="getLastPage">
                                <span>
                                  <span aria-hidden="true">»</span>
                                </span>
                            </a>
                        </li>
                    </ul>
                </nav>



                <div class="row mt-4">

                    <div class="col-lg-4 col-md-6 mb-4" v-for="product in products.data">
                        <div class="card h-100">
                            <a href="#">
                                <img class="card-img-top" src="http://placehold.it/700x400" alt="">
                                 <!--
                            <img :src="'images/'+ product.img + '.jpg'" class="img-responsive" height="200" width="200">
                            -->
                            
                            </a>
                            <div class="card-body">
                                <h4 class="card-title">
                                    <a href="#">{{ product.nombre }}</a>
                                </h4>
                               <!-- <h5>$ {{ product.price }}</h5>
                                <p class="card-text">{{ product.description }}</p> -->
                            </div>
                        </div>
                    </div>
                </div>



            </div>
        </div>
    </div>
</template>





<script>
import Tree2 from "./Tree2";


    export default {
        data: function () {
            return {
                categories: [],
                brands: [],
                products: [],
                loading: true,
                selected: {
                    supplies: [],
                    brands: [],
                    categories: []
                },
               /* address: {
                    street: '',
                    state: '',
                }*/
            }
        },
        mounted() {
            this.loadCategories();
            this.loadBrands();
            this.loadProducts();
            this.loadSupplies();
        },
        watch: {
            selected: {
                handler: function () {
                    this.loadSupplies();
                    this.loadBrands();
                    this.loadProducts();
                    this.loadCategories();
                    console.log(this.selected.supplies)
                   // console.log(this.selected.brands)
                },
                deep: true
            }
        },
        methods: {
            bus: function (data) {
                
                //console.log(data)
                if(data.substring(0,4) == 'true'){
                    //console.log(data.substring(10,20) + " true")
                    if( this.selected.supplies.includes(data.substring(10,20)) == false){
                        this.selected.supplies.push(data.substring(10,20))
                        //console.log(this.selected.supplies)
                    }
                }else{
                    if(data.substring(0,5) == 'false'){
                        //console.log(data.substring(11,20) + " false")
                        if( this.selected.supplies.includes(data.substring(11,20))){

                        
this.selected.supplies.splice(this.selected.supplies.findIndex(sup => sup === data.substring(11,20)), 1)

                            //console.log(data.substring(11,20) + " eliminar")
                        }
                    }    
                }
            },
            loadSupplies: function () {
                axios.get('/api/supplies', {
                        params: _.omit(this.selected, 'supplies')
                    })
                    .then((response) => {
                        this.supplies = response.data.supplies;
                    })
                    .catch(function (error) {
                        console.log(error);
                    });
            },
            loadProducts: function () {
                axios.get('/api/products', {
                        params: this.selected
                    })
                    .then((response) => {
                        this.products = response.data.products;
                        this.loading = false;
                    })
                    .catch(function (error) {
                        console.log(error);
                    });
            },           
            getPage(page){
                axios.get('/api/products?page='+page, {
                        params: this.selected
                    }).then((response)=>{
                       this.products = response.data.products;
                });
            },
            getPreviousPage(){
                axios.get(this.products['prev_page_url'], {
                        params: this.selected
                    }).then((response)=>{
                       this.products = response.data.products;
                });
            },
            getNextPage(){
                axios.get(this.products['next_page_url'], {
                        params: this.selected
                    }).then((response)=>{
                       this.products = response.data.products;
                });
            },
            getLastPage(){
                axios.get(this.products['last_page_url'], {
                        params: this.selected
                    }).then((response)=>{
                       this.products = response.data.products;
                });
            },
            getFirstPage(){
                axios.get(this.products['first_page_url'], {
                        params: this.selected
                    }).then((response)=>{
                       this.products = response.data.products;
                });
            },
            loadBrands: function () {
                axios.get('/api/brands', {
                        params: _.omit(this.selected, 'brands')
                    })
                    .then((response) => {
                        this.brands = response.data.brands;
                        this.loading = false;
                    })
                    .catch(function (error) {
                        console.log(error);
                    });
            },
            loadCategories: function () {
                axios.get('/a', {
                        params: _.omit(this.selected, 'categories')
                    })
                    .then((response) => {
                        this.categories = response.data.categories[0];
                        this.loading = false;
                    })
                    .catch(function (error) {
                        console.log(error);
                    });
            },
        },
        components: {
            Tree2
        }
    }
</script>