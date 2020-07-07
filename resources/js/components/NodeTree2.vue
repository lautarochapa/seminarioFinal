<template>
  <li>
    <span class="caret">{{ categoria.nombre }} ({{ categoria.products_count }})  </span>   
        <ul class="nested">

                
                   <!-- <div class="form-check" v-for="(supply, index) in categoria.supplies">-->
<li v-for="(supply, index) in categoria.supplies">
                        <input class="form-check-input" type="checkbox" @change="handleChange($event)" :value="supply.id"  
                        :id="'supply'+supply.id" v-model="supplies2">
                        <label class="form-check-label" :for="'supply' + index">
                       <!-- <span class="indent"></span>--> {{ supply.nombre }} ({{ supply.products_count }})
                        </label>
    </li>
                    <!--</div>-->



    <!--<ul class="nested" v-if="categoria.allchildren && categoria.allchildren.length">-->
    <!-- <span class="indent"></span> <categoria v-for="child in categoria.allchildren" :categoria="child" @bus="bus"></categoria> -->


   <!-- </ul>-->

    </ul>
  </li>
</template>




<script>
export default {
  name: "categoria",
  props: {
    categoria: [Object, Array],  
    value: [Object, Array],  
  },       
  methods: {
            bus: function (data) {
                this.$emit('bus', data)
            },
                handleChange: function(e) {

     { this.$emit('bus', e.srcElement.checked + e.srcElement.id )}
    }
  },

       data: function () {
            return {
                    supplies2: [],
                }
            },


};
</script>