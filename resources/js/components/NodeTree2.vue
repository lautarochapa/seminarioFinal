<template>
  <li class="node-tree">
    <span class="label">{{ categoria.nombre }} ({{ categoria.products_count }})</span>      
  
                    <div class="form-check" v-for="(supply, index) in categoria.supplies">
                        <input class="form-check-input" type="checkbox" :value="supply.id" :id="'supply'+index" v-model="supplies2">
                        <!--                                            :value="abbreviation"                   v-model="localState"

                        -->
                        <label class="form-check-label" :for="'supply' + index">
                        --   {{ supply.nombre }} ({{ supply.products_count }})
                        </label>
                    </div>



    <ul v-if="categoria.allchildren && categoria.allchildren.length">
      <categoria v-for="child in categoria.allchildren" :categoria="child" @bus="bus"></categoria>


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
        watch: {
            supplies2: {
                handler: function () {
                    { this.$emit('bus', this.supplies2)}
                },
                deep: true
            }
        },



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