<template>
  <div>
    <categories :categories-data="categories"></categories>
  </div>
</template>

<script>
import CategoryTree from "./CategoryTree";


    export default {

        data() {
            return {
              categorias: {},
            }
        },
        methods: {
            getCategorias(){
                axios.get('/categoriasConInsumos')
                     .then((response)=>{
                       this.categorias = response.data.categories
                     })
            }
        },
        created() {
            this.getCategorias()
        }
    }

</script>