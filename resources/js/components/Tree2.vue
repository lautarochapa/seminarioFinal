<template>
  <div id="treeview7" class="treeview">
    
    <ul class="list-group">
      <node-tree2 :categoria="treeData" @bus="bus" ></node-tree2>
<!--                        <node-tree2 v-model="localAddress.state" /> -->

    </ul>

<!--
<div id="treeview4" class="treeview">
  <ul class="list-group">
    <li class="list-group-item node-treeview4" data-nodeid="0" style="color:undefined;background-color:undefined;">
      <span class="icon expand-icon glyphicon glyphicon-minus"></span>
      <span class="icon node-icon"></span>Parent 1
    </li>
    <li class="list-group-item node-treeview4" data-nodeid="1" style="color:undefined;background-color:undefined;">
      <span class="indent"></span>
      <span class="icon expand-icon glyphicon glyphicon-plus"></span>
      <span class="icon node-icon"></span>Child 1
    </li>
    <li class="list-group-item node-treeview4" data-nodeid="4" style="color:undefined;background-color:undefined;">
      <span class="indent"></span>
      <span class="icon glyphicon"></span>
      <span class="icon node-icon"></span>Child 2
    </li>
    <li class="list-group-item node-treeview4" data-nodeid="5" style="color:undefined;background-color:undefined;">
                        <span class="icon glyphicon">
                          </span>
                          <span class="icon node-icon">
                            </span>Parent 2</li>
                            <li class="list-group-item node-treeview4" data-nodeid="6" style="color:undefined;background-color:undefined;">
                              <span class="icon glyphicon">
                                </span>
                                <span class="icon node-icon">
                                  </span>Parent 3</li>
                                  <li class="list-group-item node-treeview4" data-nodeid="7" style="color:undefined;background-color:undefined;">
                                    <span class="icon glyphicon">
                                      </span>
                                      <span class="icon node-icon">
                                        </span>Parent 4</li>
                                        <li class="list-group-item node-treeview4" data-nodeid="8" style="color:undefined;background-color:undefined;">
                                          <span class="icon glyphicon"></span>
                                          <span class="icon node-icon"></span>Parent 5</li>
                                          </ul>
                                          </div>

-->
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