<template>
  <div class="tree">
    <ul class="tree-list">
      <node-tree :node="treeData"></node-tree>
    </ul>
  </div>
</template>

<script>
import CategoryNodeTree from "./CategoryNodeTree";

export default {
  props: {
    treeData: Object
  },
  components: {
    CategoryNodeTree
  }
};
</script>

<style>
.tree-list ul {
  padding-left: 16px;
  margin: 6px 0;
}
</style>