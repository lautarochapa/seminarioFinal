@extends('profiles.admin')

@section('content2')


<div class="container">
    <div class="row justify-content-center">

                   <p> UN PRODUCTO</p>

                  <div class="caption">
                      <h3> {{$product->nombre}} </h3>
                    <!--  <p class="discription"> {{$product->discription}} </p>
                      <div class="clearfix">
                          <div class="pull-left price"/>$ {{$product->price}}</div>
                          <a href= {{ route('product.addToCart', ['id' => $product->id ]) }} class="btn btn-danger pull-right" role="button">Add to cart</a>
                  </div>-->

                   </div>
    </div>
</div>
@endsection



  
