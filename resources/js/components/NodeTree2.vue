<!--<template>
  <li class="node-tree">
    <span class="label">{{ categoria.nombre }} ({{ categoria.products_count }})</span>      
  
                    <div class="form-check" v-for="(supply, index) in categoria.supplies">
                        <input class="form-check-input" type="checkbox" :value="supply.id" :id="'supply'+index" v-model="supplies2">

                        <label class="form-check-label" :for="'supply' + index">
                        --   {{ supply.nombre }} ({{ supply.products_count }})
                        </label>
                    </div>



    <ul v-if="categoria.allchildren && categoria.allchildren.length">
      <categoria v-for="child in categoria.allchildren" :categoria="child"></categoria>


    </ul>
  </li>
</template>

<script>
export default {
  name: "categoria",
  props: {
    categoria: [Object, Array],  

  },

          mounted() {
            this.$emit('bus', {data1: 'somedata', data2: 'somedata'})
        },

};
</script>

-->
<template>
     <li class="node-tree" v-model="localState">
    <select v-model="localStateBus">
        <option v-for="(state, abbreviation) in states"
                :value="abbreviation"
                v-html="state"
        ></option>
    </select>
    <span class="label">{{ categoria.nombre }} ({{ categoria.products_count }})</span>      
  
                    <div class="form-check" v-for="(supply, index) in categoria.supplies">
                        <input class="form-check-input" type="checkbox" :value="supply.id" :id="'supply'+index" v-model="supplies2">

                        <label class="form-check-label" :for="'supply' + index">
                        --   {{ supply.nombre }} ({{ supply.products_count }})
                        </label>
                    </div>



    <ul v-if="categoria.allchildren && categoria.allchildren.length">
      <categoria v-for="child in categoria.allchildren" :categoria="child" @bus="bus"></categoria>


    </ul>
  </li>
</template>
<script type="text/babel">
    export default {
  name: "categoria",
        props: {
            categoria: [Object, Array],  
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
            localStateBus: {
                get() {return this.value},
                set(localStateBus) { this.$emit('bus', localStateBus)}
            },
            localState: {
                get() {return this.value},
                set(localState) { this.$emit('input', localState)}
            }
        },/*
        methods: {
          bus: function (data) {
              this.$emit('bus', data)
          }
      }*/
    }
</script>
