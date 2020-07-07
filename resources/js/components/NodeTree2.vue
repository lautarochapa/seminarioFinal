<template>
  <li>
    <span class="caret">{{ categoria.nombre }} ({{ categoria.products_count }})  </span>   

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