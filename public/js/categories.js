/*___________________________________________________________
Inicializacion de variable Data
___________________________________________________________*/

const data = {
    categorias:[]
}

function updateCategorias(){
    axios.get("/categoriasConInsumos")
        .then((resp)=> { data.categorias = resp.data })
        .catch((err)=> { console.error(err.response.data)})
}




/*___________________________________________________________
Inicializacion de la pagina
___________________________________________________________*/
        
    window.addEventListener("load",()=> {
        updateCategorias()
        console.log(data.categorias)

new Vue({
    el: '#appCategorias',
    data: data 
})  


})  