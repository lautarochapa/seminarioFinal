<template>
<div class="containerProd">
    <div class="card">
        <div class="imgBx">
            <img :src="'images/'+ prod.img + '.jpg'" @error="$event.target.src='http://placehold.it/700x400'" height="190">   
        </div>
        <div class="contentBx">
                <h2 style="font-size: 1.3rem;">{{prod.nombre}}</h2>

               <!-- <div class="size">
                    <h3>MARCA</h3>
                </div> -->
                <a href="'products/'+ prod.id " class="cta">Ver</a>




        </div>


    </div>    
</div>    


</template>

<style scoped>


.containerProd{
    position: relative;
    height: 45vh;
}

.containerProd .card{
    position: relative;
    /*width: 320px;*/
    height: 100%;
    background: white;
    border-radius: 20px;
    overflow: hidden;
}

.containerProd .card:before{
    content: '';
    position: absolute;
    top:0;
    left:0;
    width: 100%;
    height: 100%;
    background: rgba(4,172,133, 0.1); ;
    clip-path: circle(150px at 80% 20%);
    transition: 0.5s ease-in-out;
    
}

.containerProd .card:hover:before{
    clip-path: circle(300px at 30% 45%);

   /* clip-path: circle(300px at 80% -20%);*/

}

/* marca de agua
.containerProd .card:after{
    content: 'Nike';
    position: absolute;
    top: 30%;
    left: -20%;
    font-size:12em;
    font-weight: 800;
    font-style: italic;
    color: rgba(255,255,255,0.04)

}
*/

.containerProd .card .imgBox{
    position: absolute;
    top: 50%;
    transform: translateY(-10%);
    z-index: 99;
    width:100%;
    height: 220px;
    transition: 0.5s;


}

.containerProd .card:hover .imgBx img{
    top: 25%;
  /*  transform: translateY(0%); */
}

.containerProd .card .imgBx img{
    position: absolute;
    top:35%;
    left:50%;
    transform: translate(-50%,-50%);
    mix-blend-mode: multiply;
}

.containerProd .card .contentBx{
    position: absolute;
    bottom: 0;
    width: 100%;
    height: 35%;
    text-align: center;
    transition: 1s;
    z-index: 10;
}


.containerProd .card:hover .contentBx{

    height: 210px;
}

.containerProd .card .contentBx h2{
    position: relative;
    font-weight: 600;
    letter-spacing: 1px;
    color:#24252a;
}

.containerProd .card .contentBx .size{
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 8px 20px;
    transition: 0.5s;
    opacity: 0;
    visibility: hidden;
}
.containerProd .card:hover .contentBx .size{
    transition-delay: 0.3s;
    opacity: 1;
    visibility: visible;
}


.containerProd .card .contetnBx .size h3{
    color: #24252a;
    font-weight: 300;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-right:10px;

}

.containerProd .card .contentBx a{
    display: inline-block;
    padding: 10px 20px;
    margin-top: 10px;
    font-weight: 600;
    opacity: 0;
    transform: translateY(50px);
    transition: 0.5s
}
.containerProd .card:hover .contentBx a{
    opacity: 1;
    transform: translateY(0px);
    transition-delay: 0.3s
}




</style>


<script>
export default {
  name: "productCard2",
  props: {
    prod: [Object, Array]
  }
};
</script>