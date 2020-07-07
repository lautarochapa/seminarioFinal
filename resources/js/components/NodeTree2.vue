<template>
  <li class="node-tree">
    <!--<p>{{ categoria }}</p>-->
    <span class="label">{{ categoria.nombre }} ({{ categoria.products_count }})</span>      
  
    <div v-for="supply in categoria.supplies">
        <p style="color: #FF0000;">{{ supply.nombre }}</p>
      </div>



                    <div class="form-check" v-for="(supply, index) in categoria.supplies">
                        <input class="form-check-input" type="checkbox" :value="supply.id" :id="'supply'+index" v-model="selected.supplies">
                        <label class="form-check-label" :for="'supply' + index">
                        --   {{ supply.nombre }} ({{ supply.products_count }})
                        </label>
                    </div>



    <ul v-if="categoria.allchildren && categoria.allchildren.length">
      <categoria v-for="child in categoria.allchildren" :categoria="child"></categoria>


    </ul>
  </li>
</template>

<script>
export default {
  name: "categoria",
  props: {
    categoria: [Object, Array],
                selected: {
                    supplies: [],
                }
  }
};
</script>

