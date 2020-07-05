<template>
  <li class="node-tree">
    <span class="label">{{ node.label }}</span>
  </li>
</template>

<script>
export default {
  props: {
    node: Object
  }
};
</script>