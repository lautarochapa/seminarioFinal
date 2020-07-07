<template>
<div>
<div v-for="a in treeData">
     
      <ul :id="'myUL' + a.id">

      <node-tree2 :categoria="a" @bus="bus" ></node-tree2>

    </ul>

<!--<p>{{a.nombre}}</p>-->
</div>


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
  
  };
</script>
