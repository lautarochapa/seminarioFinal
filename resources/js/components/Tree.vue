<template>
  <div class="tree">
    

 <ul v-for="cat in treeData.categoria">

      <node-tree :categoria="cat"></node-tree>
 </ul>


    <ul class="tree-list">
      <node-tree :categoria="treeData"></node-tree>
    </ul>

    <p>{{treeData}}</p>
  </div>
</template>

<script>
import NodeTree from "./NodeTree";

export default {
  props: {
    treeData: [Object, Array]
  },
  components: {
    NodeTree
  }
};
</script>

<style>
.tree-list ul {
  padding-left: 16px;
  margin: 6px 0;
}
</style>

