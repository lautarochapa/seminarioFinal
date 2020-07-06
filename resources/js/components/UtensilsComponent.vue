<template>
  <div>
    <div v-for="ut in utensils">
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
                axios.get('/api/utensils')
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


