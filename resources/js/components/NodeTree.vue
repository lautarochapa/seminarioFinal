<template>
  <li class="node-tree">
    <span class="label">{{ node.label }}</span>

    <ul v-if="node.allchildren && node.allchildren.length">
      <node v-for="child in node.allchildren" :node="child"></node>
    </ul>
  </li>
</template>

<script>
export default {
  name: "node",
  props: {
    node: Object
  }
};
</script>