<!--<template>
  <div class="tree">
    
    <ul class="tree-list">
      <node-tree2 :categoria="treeData" @bus="bus" ></node-tree2>
                     <node-tree2 v-model="localAddress.state" /> 

    </ul>
  </div>
</template>

<script>
import NodeTree2 from "./NodeTree2";

export default {
  props: {
    treeData: [Object, Array],

  },
  components: {
    NodeTree2
  },       
  methods: {
            bus: function (data) {
                this.$emit('bus', data)
            },
  }
  
  };
</script>

<style>
.tree-list ul {
  padding-left: 16px;
  margin: 6px 0;
}
</style>


-->

<template>
    <div class="tree">
          <ul class="tree-list">
        <input name="street" v-model="localAddress.street">
      <node-tree2 :categoria="treeData" v-model="localAddress.state"></node-tree2>
                     <node-tree2 v-model="localAddress.state" /> 

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
        components: { NodeTree2 },
        computed: {
            localAddress: {
                get() { return this.value },
                set(localAddress) {this.$emit('input', localAddress)}
            }
        }
    }
</script>

<style>
.tree-list ul {
  padding-left: 16px;
  margin: 6px 0;
}
</style>
