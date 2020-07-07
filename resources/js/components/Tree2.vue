<template>
  <div class="tree">
    
    <ul class="tree-list">
      <node-tree2 :categoria="treeData" v-model="local.supplies"></node-tree2>
    </ul>
  </div>
</template>

<script>
import NodeTree2 from "./NodeTree2";

export default {
  props: {
    treeData: [Object, Array],
            supplies: {
                type: Object,
                required: true
            }
  },
  components: {
    NodeTree2
  },        
        computed: {
            local: {
                get() { return this.supplies },
                set(local) {this.$emit('input', local)}
            }
        }
};
</script>

<style>
.tree-list ul {
  padding-left: 16px;
  margin: 6px 0;
}
</style>

