<template>
  <div>
    <tree :tree-data="categorias"></tree>
  </div>
</template>

<script>
import Tree from "./Tree";

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
    };
</script>