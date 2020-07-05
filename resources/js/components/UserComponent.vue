<template>
<table class="table">
                        <thead>
                            <tr>
                                <th>
                                    <span>Nombre</span>
                                </th>
                                <th>
                                    <span>Apellido</span>
                                </th>
                                <th>
                                    <span>Mail</span>
                                </th>
                                <th>
                                    <span>Puesto</span>
                                </th>
                                <th>
                                    <span>Estado</span>
                                </th>
                                <th>
                                    <span>Editar</span>
                                </th>
                                <th>
                                    <span></span>
                                </th>
                            </tr>
                        </thead>
                        <tbody v-for="usr in users">
                        
                        <tr class="success">                                
                                <td class="dinnLig">@{{ usr.name }}</td>
                                <td class="dinnLig">@{{ usr.lastname }}</td>
                                <td class="dinnLig">@{{ usr.username }}</td>
                                <td class="dinnLig">@{{ usr.email }}</td>
                                <td class="dinnLig">
                                    <button type="button" class="btn btn-primary dinn" data-toggle="modal" 
                                    data-target="#ModalEditarUsuario" >
                                        Editar
                                    </button>
                                </td>
                                <td class="dinnLig" v-if="usr.suspendido == 0 || usr.suspendido==null">
                                    <button type="button" class="btn btn-primary dinn" data-toggle="modal" 
                                    data-target="#ModalSuspenderUsuario" style="background-color: red; border: 2px solid red;">
                                    Suspender
                                    </button>
                                
                                </td>
                                <td class="dinnLig" v-if="usr.suspendido == 1">
                                    <button type="button" class="btn btn-primary dinn" data-toggle="modal" 
                                    data-target="#ModalActivarUsuario" style="background-color: #9aca3c; border: 2px solid #9aca3c;">
                                        Activar
                                    </button>
                                
                                </td>
                            </tr>

                       <!-- <tr v-if="usr.suspendido == 0 || usr.suspendido==null" class="success">                                <td class="dinnLig">@{{ usr.name }}</td>
                                <td class="dinnLig">@{{ usr.lastname }}</td>
                                <td class="dinnLig">@{{ usr.email }}</td>
                                <td class="dinnLig" v-if="usr.nivel_acceso == '0'">Operadora</td>
                                <td class="dinnLig" v-if="usr.nivel_acceso == '1'">Cordinadora</td>
                                <td class="dinnLig" v-if="usr.nivel_acceso == '2'">Relevadora</td>
                                <td class="dinnLig" v-if="usr.suspendido == 0 || usr.suspendido==null">Activo</td>
                                <td class="dinnLig" v-if="usr.suspendido == 1">Suspendido</td>
                                <td class="dinnLig">
                                    <button type="button" class="btn btn-primary dinn" data-toggle="modal" 
                                    data-target="#ModalEditarUsuario" >
                                        Editar
                                    </button>
                                </td>
                                <td class="dinnLig" v-if="usr.suspendido == 0 || usr.suspendido==null">
                                    <button type="button" class="btn btn-primary dinn" data-toggle="modal" 
                                    data-target="#ModalSuspenderUsuario" style="background-color: red; border: 2px solid red;">
                                    Suspender
                                    </button>
                                
                                </td>
                                <td class="dinnLig" v-if="usr.suspendido == 1">
                                    <button type="button" class="btn btn-primary dinn" data-toggle="modal" 
                                    data-target="#ModalActivarUsuario" style="background-color: #9aca3c; border: 2px solid #9aca3c;">
                                        Activar
                                    </button>
                                
                                </td>
                            </tr>




                        <tr v-if="usr.suspendido == 1" class="danger">
                                <td class="dinnLig">@{{ usr.name }}</td>
                                <td class="dinnLig">@{{ usr.lastname }}</td>
                                <td class="dinnLig">@{{ usr.email }}</td>
                                <td class="dinnLig" v-if="usr.nivel_acceso == '0'">Operadora</td>
                                <td class="dinnLig" v-if="usr.nivel_acceso == '1'">Cordinadora</td>
                                <td class="dinnLig" v-if="usr.nivel_acceso == '2'">Relevadora</td>
                                <td class="dinnLig" v-if="usr.suspendido == 0 || usr.suspendido==null">Activo</td>
                                <td class="dinnLig" v-if="usr.suspendido == 1">Suspendido</td>
                                <td class="dinnLig">
                                    <button type="button" class="btn btn-primary dinn" data-toggle="modal" 
                                    data-target="#ModalEditarUsuario" >
                                        Editar
                                    </button>
                                </td>
                                <td class="dinnLig" v-if="usr.suspendido == 0 || usr.suspendido==null">
                                    <button type="button" class="btn btn-primary dinn" data-toggle="modal" 
                                    data-target="#ModalSuspenderUsuario" style="background-color: red; border: 2px solid red;">
                                    Suspender
                                    </button>
                                
                                </td>
                                <td class="dinnLig" v-if="usr.suspendido == 1">
                                    <button type="button" class="btn btn-primary dinn" data-toggle="modal" 
                                    data-target="#ModalActivarUsuario" style="background-color: #9aca3c; border: 2px solid #9aca3c;">
                                        Activar
                                    </button>
                                
                                </td>
                            </tr>
                            -->
                        </tbody>
                    </table> 
</template>

<script>
    export default {

        data() {
            return {
              users: [],
            }
        },
        methods: {
            getUsers(){
                axios.get('/api/users')
                     .then((response)=>{
                       this.users = response.data.users[0]
                     })
            }
        },
        created() {
            this.getUsers()
        }
    }

</script>


