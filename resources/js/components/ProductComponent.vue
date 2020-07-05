<template>
    <table class="table table-striped">
        <thead>
        <tr>
            <td>ID</td>
            <td>Nombre</td>
        </tr>
        </thead>
        <tbody>
        <tr v-for="prod in products.data">
            <td>{{ prod.id }}</td>
            <td>{{ prod.nombre }}</td>
        </tr>

        <tr>
            <td colspan="5">
                <nav>
                    <ul class="pagination">
                        <li class="page-item" v-show="products['prev_page_url']">
                            <a href="#" class="page-link" @click.prevent="getPreviousPage">
                                <span>
                                  <span aria-hidden="true">«</span>
                                </span>
                            </a>
                        </li>
                        <li class="page-item" :class="{ 'active': (products['current_page']=== n) }" v-for="n in products['last_page']">
                            <a href="#" class="page-link" @click.prevent="getPage(n)">
                                <span >
                                    {{ n }}
                                </span>
                            </a>
                        </li>
                        <li class="page-item" v-show="products['next_page_url']">
                            <a href="#" class="page-link" @click.prevent="getNextPage">
                                <span>
                                  <span aria-hidden="true">»</span>
                                </span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </td>
        </tr>

        </tbody>
    </table>



</template>

<script>
    export default {

        data() {
            return {
              products: [],
            }
        },
        methods: {
            getProducts(){
                axios.get('/api/products')
                     .then((response)=>{
                       this.products = response.data.products;
                       console.log(this.products)
                     })
            },            
            getPage(page){
                this.$http.get('/api/products?page='+page).then((response)=>{
                    this.$set(this.$data, 'products', response.data);
                },(response)=>{
                });
            },
            getPreviousPage(){
                this.$http.get(this.products['prev_page_url']).then((response)=>{
                    this.$set(this.$data, 'products', response.data);
                },(response)=>{
                });
            },
            getNextPage(){
                this.$http.get(this.products['next_page_url']).then((response)=>{
                    this.$set(this.$data, 'products', response.data);
                },(response)=>{
                });
            },
        },
        created() {
            this.getProducts()
        }
    }

</script>
