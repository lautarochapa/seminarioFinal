<template>
  <li class="list-group-item node-treeview7" data-nodeid="0" style="color:undefined;background-color:undefined;">
    <span class="icon expand-icon glyphicon glyphicon-minus"></span>
    <span class="icon node-icon"></span>{{ categoria.nombre }} ({{ categoria.products_count }})     
  
        <ul class="list-group">

                
                    <div class="form-check" v-for="(supply, index) in categoria.supplies">
<li class="list-group-item node-treeview7" data-nodeid="0" style="color:undefined;background-color:undefined;">
      <span class="icon expand-icon glyphicon glyphicon-minus"></span>
                        <input class="form-check-input" type="checkbox" @change="handleChange($event)" :value="supply.id"  
                        :id="'supply'+supply.id" v-model="supplies2">
                        <!--                                            :value="abbreviation"                   v-model="localState"

                        -->
                        <label class="form-check-label" :for="'supply' + index">
                        <span class="indent"></span> {{ supply.nombre }} ({{ supply.products_count }})
                        </label>
    </li>
                    </div>


    </ul>

    <ul class="list-group" v-if="categoria.allchildren && categoria.allchildren.length">
     <span class="indent"></span> <categoria v-for="child in categoria.allchildren" :categoria="child" @bus="bus"></categoria>


    </ul>
  </li>




</template>

<script>
export default {
  name: "categoria",
  props: {
    categoria: [Object, Array],  
    value: [Object, Array],  
  },       
  methods: {
            bus: function (data) {
                this.$emit('bus', data)
            },
                handleChange: function(e) {


     // const name = e.target.name;
     // console.log(e.srcElement)
     // console.log(e.srcElement.checked)
     // console.log(e.target)

     { this.$emit('bus', e.srcElement.checked + e.srcElement.id )}
    }
  },
      /*  computed: {
            supplies2: {
                get() {return this.value},
                set(supplies2) { this.$emit('bus', supplies2)}
            }
        },*/

       data: function () {
            return {
                    supplies2: [],
                }
            },
       /* watch: {
            supplies2: {
                handler: function () {
                    { this.$emit('bus', this.supplies2)}
                },
                deep: true
            }
        },*/



};
</script>
<!--
<template>
    <select v-model="localState">
        <option v-for="(state, abbreviation) in states"
                :value="abbreviation"
                v-html="state"
        ></option>
    </select>
</template>
<script type="text/babel">
    export default {
        props: {
            value: {
                type: String,
                required: true
            }
        },
        data() {
            return {
                states: {
                    NY: 'New York',
                    WI: 'Wisconsin'
                    // + rest of the states
                }
            }
        },
        computed: {
            localState: {
                get() {return this.value},
                set(localState) { this.$emit('input', localState)}
            }
        }
    }
</script>

-->