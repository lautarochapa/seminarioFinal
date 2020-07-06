<template>
<div>
<p>agregar filtros</p>

  <select class="form-control" :required="true" @change="changeLocation">
   <option :selected="true">Elegir Insumo</option>
    <option v-for="sup in supplies" v-bind:value="sup.id" >{{ sup.nombre }}</option>
  </select>



    <table class="table table-striped">
        <thead>
        <tr>
            <td>ID</td>
            <td>Nombre</td>
            <td>Insumo</td>
            <td>Marca</td>
        </tr>
        </thead>
        <tbody>
        <tr v-for="prod in products.data">
            <td>{{ prod.id }}</td>
            <td>{{ prod.nombre }}</td>
            <td>{{ prod.supply.nombre }}</td>
            <td>{{ prod.brand.nombre }}</td>
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

</div>

</template>

<script>
    export default {

        data() {
            return {
              products: {},
              supplies: {},
              selected: "Elegir Insumo",
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
            getSupplies(){
                axios.get('/api/supplies')
                     .then((response)=>{
                       this.supplies = response.data.supplies;
                       console.log(this.supplies)
                     })
            },            
            getPage(page){
                axios.get('/api/products?page='+page).then((response)=>{
                       this.products = response.data.products;
                });
            },
            getPreviousPage(){
                axios.get(this.products['prev_page_url']).then((response)=>{
                       this.products = response.data.products;
                });
            },
            getNextPage(){
                axios.get(this.products['next_page_url']).then((response)=>{
                       this.products = response.data.products;
                });
            },
        },
        mounted() {
            this.getProducts()
            this.getSupplies()
        }
    }

</script>


