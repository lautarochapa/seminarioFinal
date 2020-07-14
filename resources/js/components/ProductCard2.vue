<template>
<div class="container">
    <div class="card">
        <div class="imgBx">
            <img :src="'images/'+ prod.img + '.jpg'" @error="$event.target.src='http://placehold.it/700x400'" height="190">   
        </div>
        <div class="contentBx">
                <h2 style="font-size: 1.3rem;">{{prod.nombre}}</h2>

                <div class="size">
                    <h3>MARCA</h3>
                </div>
                <a href="'products/'+ prod.id " class="cta">Ver</a>




        </div>


    </div>    
</div>    


</template>

<style scoped>

body{
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    background: green;
}

.container{
    position: relative;
}

.container .card{
    position: relative;
    width: 320px;
    height: 450px;
    background: blue;
    border-radius: 20px;
    overflow: hidden;
}

.container .card:before{
    content: '';
    position: absolute;
    top:0;
    left:0;
    width: 100%;
    height: 100%;
    background: yellow;
    clip-path: circle(150px at 80% 20%);
    transition: 0.5s ease-in-out;
    
}

.container .card:hover:before{

    clip-path: circle(300px at 80% -20%);

}

/* marca de agua
.container .card:after{
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

.container .card .imgBox{
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 99;
    width:100%;
    height: 220px;
    transition: 0.5s;


}

.container .card:hover .imgBx{
    top: 0%;
    transform: translateY(0%);
}

.container .card .imgBx img{
    position: absolute;
    top:50%;
    left:50;
    transform: translate(-50%,-50%) rotate(-25deg);
    width:270px;
}

.container .card .contentBx{
    position: absolute;
    bottom: 0;
    width: 100%;
    height: 100px;
    text-align: center;
    transition: 1s;
    z-index: 10;
}


.container .card:hover .contentBx{

    height: 210px;
}

.container .card .contentBx h2{
    position: relative;
    font-weight: 600;
    letter-spacing: 1px;
    color:hotpink;
}

.container .card .contentBx .size{
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 8px 20px;
    transition: 0.5s;
    opacity: 0;
    visibility: hidden;
}
.container .card:hover .contentBx .size{
    transition-delay: 0.3s;
    opacity: 1;
    visibility: visible;
}


.container .card .contetnBx .size h3{
    color: blue;
    font-weight: 300;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-right:10px;

}

.container .card .contentBx a{
    display: inline-block;
    padding: 10px 20px;
    background: #fff;
    border-radius: 4px;
    margin-top: 10px;
    text-decoration: none;
    font-weight: 600;
    color: #111;
    opacity: 0;
    transform: translateY(50px);
    transition: 0.5s
}
.container .card:hover .contentBx a{
    opacity: 1;
    transform: translateY(0px);
    transition-delay: 0.5s
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