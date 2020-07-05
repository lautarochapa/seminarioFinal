/*___________________________________________________________
Inicializacion de variable Data
___________________________________________________________*/

data = {
    categorias:[]
}
new Vue({
    el: '#appCategorias',
    data: data 
})  


function updateCategorias(){
    axios.get("/categoriasConInsumos")
        .then((resp)=> { data.categorias = resp.data; 
            console.log(data.categorias) })
        .catch((err)=> { console.error(err.response.data)})
}




/*___________________________________________________________
Inicializacion de la pagina
___________________________________________________________*/
        
    window.addEventListener("load",()=> {
        updateCategorias()



})  