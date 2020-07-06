<template>
  <div>
    <div v-for="ut in utenils">
      <p>{{ut}}</p>
    </div> 
   </div>
</template>

<script>

    export default {

        data() {
            return {
              utensils: [],
            }
        },
        methods: {
            getUtensils(){
                axios.get('/api/categories')
                     .then((response)=>{
                       this.utensils = response.data.categoutensilsries[0]
                     })
            }
        },
        created() {
            this.getUtensils()
        },
    }

</script>


