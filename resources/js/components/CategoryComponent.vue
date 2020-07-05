<template>
  <div>
    <tree :tree-data="tree"></tree>
  </div>
</template>

<script>
import Tree from "./Tree";

export default {
  data: () => ({
    tree: {
      nombre: "A cool folder",
      allchildren: [
        {
          nombre: "A cool sub-folder 1",
          allchildren: [
            { nombre: "A cool sub-sub-folder 1" },
            { nombre: "A cool sub-sub-folder 2" }
          ]
        },
        { nombre: "This one is not that cool" }
      ]
    }
  }),
  components: {
    Tree
  }
};
</script>