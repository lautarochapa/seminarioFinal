<template>
    <div class="container" :class="{'loading': loading}">
        <div class="row">
            <div class="col-lg-3 mb-4">
                <h1 class="mt-4">Filtros</h1>


                <h3 class="mt-2">Insumos</h3>
                <div class="form-check" v-for="(supply, index) in supplies">
                    <input class="form-check-input" type="checkbox" :value="supply.id" :id="'supply'+index" v-model="selected.supplies">
                    <label class="form-check-label" :for="'supply' + index">
                        {{ supply.nombre }} ({{ supply.products_count }})
                    </label>
                </div>

                <h3 class="mt-2">Marcas</h3>
                <div class="form-check" v-for="(br, index) in brands">
                    <input class="form-check-input" type="checkbox" :value="br.id" :id="'br'+index" v-model="selected.brands">
                    <label class="form-check-label" :for="'br' + index">
                        {{ br.nombre }} ({{ br.products_count }})
                    </label>
                </div>
            </div>
            <div class="col-lg-9">
                <div class="row mt-4">
                    <div class="col-lg-4 col-md-6 mb-4" v-for="product in products">
                        <div class="card h-100">
                            <a href="#">
                               <!-- <img class="card-img-top" src="http://placehold.it/700x400" alt="">
                                 <img src="{{URL::asset('image/propic.png')}}" alt="profile Pic" height="200" width="200">
                            --><img :src="'images'+ product.img + '.jpg'" class="img-responsive" height="70" width="90">
                            
                            
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
    export default {
        data: function () {
            return {
                supplies: [],
                brands: [],
                products: [],
                loading: true,
                selected: {
                    supplies: [],
                    brands: []
                }
            }
        },
        mounted() {
            this.loadSupplies();
            this.loadBrands();
            this.loadProducts();
        },
        watch: {
            selected: {
                handler: function () {
                    this.loadSupplies();
                    this.loadBrands();
                    this.loadProducts();
                },
                deep: true
            }
        },
        methods: {
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
        }
    }
</script>