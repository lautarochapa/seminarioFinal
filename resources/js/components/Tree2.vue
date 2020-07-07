<template>
  <div class="tree">
    
    <ul class="tree-list">
      <node-tree2 :categoria="treeData"></node-tree2>
    </ul>
  </div>
</template>

<script>
import NodeTree2 from "./NodeTree2";

export default {
  props: {
    treeData: [Object, Array]
  },
  components: {
    NodeTree2
  }
};
</script>

<style>
.tree-list ul {
  padding-left: 16px;
  margin: 6px 0;
}
</style>

