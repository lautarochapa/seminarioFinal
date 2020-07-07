<template>
    <ul id="myUL">
      <node-tree2 :categoria="treeData" @bus="bus" ></node-tree2>

    </ul>
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
