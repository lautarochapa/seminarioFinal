<template>
  <li>

    <span class="caret">
        {{ categoria.nombre }} ({{ categoria.products_count }})  
    </span>  
    <ul class="nested" v-if="categoria.allchildren && categoria.allchildren.length">
      <categoria v-for="child in categoria.allchildren" :categoria="child"></categoria>


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
        mounted() {
                console.log("treviewingggggggggggg111")
                var toggler = document.getElementsByClassName("caret");
                var i;

                for (i = 0; i < toggler.length; i++) {
                toggler[i].addEventListener("click", function() {
                    this.parentElement.querySelector(".nested").classList.toggle("active");
                    this.classList.toggle("caret-down");
                });
                }
        },


};
</script>