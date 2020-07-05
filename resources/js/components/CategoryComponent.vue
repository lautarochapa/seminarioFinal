<template>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">Lista de Categorias</div>
                
                <div class="card-body">

                <table>

                    <tr v-for="cat in categorias" :key="cat.id">
                        <td>{{ cat.nombre }}</td>
                    </tr>
                </table>

                </div>
            </div>
        </div>
    </div>
</div>
</template>

<script>
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
