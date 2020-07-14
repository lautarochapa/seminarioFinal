<template>
    <div class="card">
        <div class="head">
            <div class="likes">
             <!--  <ion class="ion-md-heart"></ion>--->
                <p>MARCA</p>
            </div>
            <div class="join"></div>

        </div>
        <div class="product">
            <img :src="'images/'+ prod.img + '.jpg'" @error="$event.target.src='http://placehold.it/700x400'" height="190">   
        </div>
        <div class="text">
            <!---->
            <div class="title"  >
                <h2 class="tituloProd" style="font-size: 1.3rem;">{{prod.nombre}}</h2>
            </div>
        </div>
        <div class="footer">
            <div class="action">
                <a href="'products/'+ prod.id " class="cta">Ver</a>
            </div>
        </div>
    </div>    


</template>

<style scoped>

body{
    font-family: Arial, Helvetica, sans-serif;
}


.tituloProd{
    font-weight: 400;
    letter-spacing: 1px;
    color: #24252a;

}

button {

    border: none;
    outline: none;
    padding: 10px 15px;
    color: #fff;
    border-radius: 5px;

}

.container{
    height: 100vh;
    width: 100vw;
    background: #ccc;
    display: flex;
    justify-content: center;
    align-items: center;

}

.container .card{
    background: #fff;
    display: inline-block;
    padding: 8%;    
    height: 100%;
    /*width: 300px;*/
    border-radius: 15px;
    box-shadow: 2px 2px 10px rgba(0,0,0,0.3);
    padding-bottom: 28%;
    overflow: hidden;
}

.container .card:hover {
/*    background: rgba(4,172,133, 0.1);
*/
    box-shadow: 2px 2px 10px rgba(0,0,0,0.6);
}



.container .card:before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(4,172,133, 0.1);
    clip-path: circle(150px at 80% -40%);
    transition: 0.8s ease-in-out;
}


.container .card:hover:before{
    clip-path: circle(400px at 30% 45%);

   /* clip-path: circle(300px at 80% -20%);*/

}



.container .card .head .likes,
.container .card .head{
    display: flex;
    widows: 100%;
    justify-content: space-between;
    /*-webkit-flex-direction: rox;
    flex-direction: row;*/

}
.container .card .head .likes{
    justify-content: flex-start;
    color: #ccc;

}
.container .card:hover .head .likes{
    color: #24252a;

}

.container .card .product{
    display:flex;
    justify-content: center;
    align-items: center;
    /*margin: 15% 0px;*/

}
.container .card .product img{
    mix-blend-mode: multiply;

}

.container .card .text{
    text-align: center;
}

.container .card .text h2{
    margin: 15px 0px;
}

.container .card .text p{
    color: rgba(0,0,0,0.7)
}

.container .card .footer .action button{
    background-color: #04ac85;

}
.container .card .footer{
    display: flex;
    bottom: 5%;
    position: absolute;
    justify-content: space-between;
    margin-top: 30px;



}


.container .card .footer .action{

   /* padding: 5%; */
   transform: translateX(-150%);
    transition: 0.5s;
}

.container .card:hover .footer .action{

   /* padding: 5%; */
   transform: translateX(0%);
}



    

</style>


<script>
export default {
  name: "productCard",
  props: {
    prod: [Object, Array]
  }
};
</script>