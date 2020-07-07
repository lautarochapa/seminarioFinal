@extends('profiles.admin')

@section('content2')


<div class="container">
    <div class="row justify-content-center">

                   <p> Productos</p>

                   <ul id="myUL">
  <li><span class="caret">Beverages</span>
    <ul class="nested">
      <li>Water</li>
      <li>Coffee</li>
      <li><span class="caret">Tea</span>
        <ul class="nested">
          <li>Black Tea</li>
          <li>White Tea</li>
          <li><span class="caret">Green Tea</span>
            <ul class="nested">
              <li>Sencha</li>
              <li>Gyokuro</li>
              <li>Matcha</li>
              <li>Pi Lo Chun</li>
            </ul>
          </li>
        </ul>
      </li>
    </ul>
  </li>
</ul> 


                  <!-- <product-component></product-component>-->
                    <front-component></front-component>



                   </div>
    </div>
</div>
@endsection



  
