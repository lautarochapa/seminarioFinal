<template>
  <li class="node-tree">
    <!--<p>{{ categoria }}</p>-->
    <span class="label caret">{{ categoria.nombre }}</span>      
  
    <div v-for="supply in categoria.supplies">
        <p style="color: #FF0000;">{{ supply.nombre }}</p>
      </div>

    <ul v-if="categoria.allchildren && categoria.allchildren.length" class="nested">
      <categoria v-for="child in categoria.allchildren" :categoria="child"></categoria>
    </ul>
  </li>
</template>

<script>
export default {
  name: "categoria",
  props: {
    categoria: [Object, Array]
  }
};
</script>