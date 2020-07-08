@extends('profiles.admin')

@section('content2')


<div class="container">
    <div class="row justify-content-center">

                   <p> UN PRODUCTO</p>

                  <div class="caption">
                      <h3> {{$product->nombre}} </h3>


                      <img :src="'images/'+ product.img + '.jpg'" class="img-responsive" 
                           height="500" width="500">

                   </div>
    </div>
</div>
@endsection



  
