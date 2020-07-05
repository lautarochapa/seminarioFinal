<template>
<div>
<div v-for="prod in products">

    <p>{{prod }}</p>
</div>
   </div>
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
            }
        },
        created() {
            this.getProducts()
        }
    }

</script>


