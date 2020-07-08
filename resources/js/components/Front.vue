<template>
    <div class="container" :class="{'loading': loading}">






        <div class="row">
            <div class="col-lg-3 mb-4">
                <h1 class="mt-4">Filtros</h1>


                <h3 class="mt-2">Categorias</h3>

                 <tree2 :tree-data="categories" @bus="bus"></tree2>

<!--
                <h3 class="mt-2">Marcas</h3>
                <div class="form-check" v-for="(br, index) in brands">
                    <input class="form-check-input" type="checkbox" :value="br.id" :id="'br'+index" v-model="selected.brands">
                    <label class="form-check-label" :for="'br' + index">
                        {{ br.nombre }} ({{ br.products_count }})
                    </label>
                </div>
-->

                <h3 class="mt-2">Marcas</h3>
<!-- v-model="selected.brands" -->
                <input type="text" list="marcas" multiple="multiple" @change="handleDatalist2Change($event)" />

            <datalist id="marcas"> 
                <option v-for="(br, index) in brands"  :key="br.id" :value="br.id">{{ br.nombre }} ({{ br.products_count }})</option>
                
</datalist>
                <!--<select  name="select" multiple>

                 <option v-for="(br, index) in brands" v-model="selected.brands" :value="br.id">{{ br.nombre }} ({{ br.products_count }})</option>
                
</select>-->













            </div>
            <div class="col-lg-9">


                <p> Mostrando {{products.from}} a {{products.to}} de {{products.total}} </p>
                <br>

                <p> Filtrado:</p>
            
            <div v-for="sup in this.sups">
                <span>{{sup.category.grandparent.grandparent.nombre}}/{{sup.category.grandparent.nombre}}/{{sup.category.nombre}}/{{sup.nombre}}</span> <span @click="eliminarFiltro(sup.id)">x</span>
            </div>



                
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

                            <div v-if="products['last_page'] - products['current_page'] < 5 && products['current_page'] >  5">
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
                               <!-- <img class="card-img-top" src="http://placehold.it/700x400" alt=""> -->
                                 
                            <img :src="'images/'+ product.img + '.jpg'" class="img-responsive" 
                           @error="$event.target.src='http://placehold.it/700x400'" height="200" width="200">
                            

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
<!--
<script>



    document.addEventListener("DOMContentLoaded", function () {
    const separator = ',';
    for (const input of document.getElementsByTagName("input")) {
        if (!input.multiple) {
            continue;
        }
        if (input.list instanceof HTMLDataListElement) {
            const optionsValues = Array.from(input.list.options).map(opt => opt.value);
            let valueCount = input.value.split(separator).length;
            input.addEventListener("input", () => {
                const currentValueCount = input.value.split(separator).length;
                // Do not update list if the user doesn't add/remove a separator
                // Current value: "a, b, c"; New value: "a, b, cd" => Do not change the list
                // Current value: "a, b, c"; New value: "a, b, c," => Update the list
                // Current value: "a, b, c"; New value: "a, b" => Update the list
                if (valueCount !== currentValueCount) {
                    const lsIndex = input.value.lastIndexOf(separator);
                    const str = lsIndex !== -1 ? input.value.substr(0, lsIndex) + separator : "";
                    filldatalist(input, optionsValues, str);
                    valueCount = currentValueCount;
                }
            });
        }
    }
    function filldatalist(input, optionValues, optionPrefix) {
        const list = input.list;
        if (list && optionValues.length > 0) {
            list.innerHTML = "";
            const usedOptions = optionPrefix.split(separator).map(value => value.trim());
            for (const optionsValue of optionValues) {
                if (usedOptions.indexOf(optionsValue) < 0) {
                    const option = document.createElement("option");
                    option.value = optionPrefix + optionsValue;
                    list.append(option);
                }
            }
        }
    }
});

    </script>-->



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
                sups: [],
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
        },
        watch: {
            selected: {
                handler: function () {
                    this.loadBrands();
                    this.loadProducts();
                    this.loadCategories();
                    this.loadSups();
                   // console.log(this.selected.supplies)
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
            loadSups: function () {
                axios.get('/api/s', {
  params: {
    sups: this.selected.supplies
  },})
                    .then((response) => {
                        this.sups = response.data.supplies;
                        this.loading = false;
                    })
                    .catch(function (error) {
                        console.log(error);
                    });
            },
            eliminarFiltro: function(id){

                var checkbox = document.getElementById("supply"+id).checked = false;
                this.selected.supplies.splice(this.selected.supplies.findIndex(sup => sup === id), 1)

            },
            handleDatalist2Change : function (e){
                console.log(e.srcElement)
                console.log(e.srcElement.value)
                console.log(e.srcElement.selected)

            },




            loadCategories: function () {
                axios.get('/api/categories', {
                        params: _.omit(this.selected, 'categories')
                    })
                    .then((response) => {
                        this.categories = response.data.categories;
                       
                        this.loading = false;
                    })
                    .catch(function (error) {
                        console.log(error);
                    });
            }


        },
        components: {
            Tree2
        }, ready: function()
  {
     this.treeViewLoad()

  }
    }
</script>