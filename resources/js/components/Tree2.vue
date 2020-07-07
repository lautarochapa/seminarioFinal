<template>
<div>

<p>{{treeData}}</p>

      <ul id="myUL">

      <node-tree2 :categoria="treeData" @bus="bus" ></node-tree2>

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
  },
        mounted() {console.log(this.treeData)
        },
  
  };
</script>
