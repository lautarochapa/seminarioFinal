<template>
  <li class="node-tree">
    <!--<p>{{ categoria }}</p>-->
    <span class="label">{{ categoria.nombre }}</span>

    <ul v-if="categoria.allchildren && categoria.allchildren.length">
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