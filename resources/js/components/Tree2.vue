<template>
<div>
      <ul id="myUL" v-for="cat in treeData">
      <node-tree2 :categoria="treeData[cat]" @bus="bus" ></node-tree2>

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
