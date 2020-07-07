@extends('profiles.admin')

@section('content2')


<div class="container">
    <div class="row justify-content-center">

                   <p> Productos</p>

                   <ul id="myUL">
  <li><span class="caret">Beverages</span>
    <ul class="nested">
      <li><span class="caret">Water</span></li>
      <li><span class="caret">Coffee</span></li>
      <li><span class="caret">Tea</span>
        <ul class="nested">
          <li><span class="caret">Black Tea</span></li>
          <li><span class="caret">White Tea</span></li>
          <li><span class="caret">Green Tea</span>
            <ul class="nested">
              <li><span class="caret">Sencha</span></li>
              <li><span class="caret">Gyokuro</span></li>
              <li><span class="caret">Matcha</span></li>
              <li><span class="caret">Pi Lo Chun</li>
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



  
