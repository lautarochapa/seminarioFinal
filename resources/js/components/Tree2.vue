<template>
  <div class="tree">
    
    <ul class="tree-list">
      <node-tree2 :categoria="treeData" v-model="local.supplies"></node-tree2>
<!--                        <node-tree2 v-model="localAddress.state" /> -->

    </ul>
  </div>
</template>

<script>
import NodeTree2 from "./NodeTree2";

export default {
  props: {
    treeData: [Object, Array],
            value: {
                type: Object,
                required: true
            }
  },
  components: {
    NodeTree2
  },   
        computed: {
            local: {
            //localAddress: {
                get() { return this.value },
                set(local) {this.$emit('input', local)}
                //set(localAddress) {this.$emit('input', localAddress)}
            }
}};
</script>

<style>
.tree-list ul {
  padding-left: 16px;
  margin: 6px 0;
}
</style>

<!--

<template>
    <div>
        <input name="street" v-model="localAddress.street">
        <node-tree2 v-model="localAddress.state" />
    </div>
</template>
<script>
    import NodeTree2 from "./NodeTree2";
    export default {
        props: {
            value: {
                type: Object,
                required: true
            }
        },
        components: { NodeTree2 },
        computed: {
            localAddress: {
                get() { return this.value },
                set(localAddress) {this.$emit('input', localAddress)}
            }
        }
    }
</script>

-->