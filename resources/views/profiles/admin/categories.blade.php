@extends('profiles.admin')

@section('content2')

<!--<script src="{{ asset('js/categories.js') }}" defer></script>
-->
<div class="container">
    <div class="row justify-content-center">

                   <p> Categorias</p>
                   <category-component></category-component>

                   <!--
                   <div id="appCategorias">


                   <table class="table table-striped" >
                        <tbody v-for="cat in categorias">

                            <tr><td class="dinnLig">@{{ cat }}</td>
                            </tr>
                        </tbody>
                    </table> -->


                   </div>
    </div>
</div>
@endsection
