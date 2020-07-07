@extends('profiles.admin')

@section('content2')


<div class="container">
    <div class="row justify-content-center">

                   <p> Productos</p>

                   





    <link href="./bower_components/bootstrap/dist/css/bootstrap.css" rel="stylesheet">
  <style type="text/css" id="treeview1-style"> .treeview .list-group-item{cursor:pointer}.treeview span.indent{margin-left:10px;margin-right:10px}.treeview span.icon{width:12px;margin-right:5px}.treeview .node-disabled{color:silver;cursor:not-allowed}.node-treeview1{}.node-treeview1:not(.node-disabled):hover{background-color:#F5F5F5;} </style><style type="text/css" id="treeview2-style"> .treeview .list-group-item{cursor:pointer}.treeview span.indent{margin-left:10px;margin-right:10px}.treeview span.icon{width:12px;margin-right:5px}.treeview .node-disabled{color:silver;cursor:not-allowed}.node-treeview2{}.node-treeview2:not(.node-disabled):hover{background-color:#F5F5F5;} </style><style type="text/css" id="treeview3-style"> .treeview .list-group-item{cursor:pointer}.treeview span.indent{margin-left:10px;margin-right:10px}.treeview span.icon{width:12px;margin-right:5px}.treeview .node-disabled{color:silver;cursor:not-allowed}.node-treeview3{}.node-treeview3:not(.node-disabled):hover{background-color:#F5F5F5;} </style><style type="text/css" id="treeview4-style"> .treeview .list-group-item{cursor:pointer}.treeview span.indent{margin-left:10px;margin-right:10px}.treeview span.icon{width:12px;margin-right:5px}.treeview .node-disabled{color:silver;cursor:not-allowed}.node-treeview4{color:#428bca;}.node-treeview4:not(.node-disabled):hover{background-color:#F5F5F5;} </style><style type="text/css" id="treeview5-style"> .treeview .list-group-item{cursor:pointer}.treeview span.indent{margin-left:10px;margin-right:10px}.treeview span.icon{width:12px;margin-right:5px}.treeview .node-disabled{color:silver;cursor:not-allowed}.node-treeview5{color:#428bca;}.node-treeview5:not(.node-disabled):hover{background-color:#F5F5F5;} </style><style type="text/css" id="treeview6-style"> .treeview .list-group-item{cursor:pointer}.treeview span.indent{margin-left:10px;margin-right:10px}.treeview span.icon{width:12px;margin-right:5px}.treeview .node-disabled{color:silver;cursor:not-allowed}.node-treeview6{color:#428bca;}.node-treeview6:not(.node-disabled):hover{background-color:#F5F5F5;} </style><style type="text/css" id="treeview7-style"> .treeview .list-group-item{cursor:pointer}.treeview span.indent{margin-left:10px;margin-right:10px}.treeview span.icon{width:12px;margin-right:5px}.treeview .node-disabled{color:silver;cursor:not-allowed}.node-treeview7{color:#428bca;border:none;}.node-treeview7:not(.node-disabled):hover{background-color:#F5F5F5;} </style><style type="text/css" id="treeview8-style"> .treeview .list-group-item{cursor:pointer}.treeview span.indent{margin-left:10px;margin-right:10px}.treeview span.icon{width:12px;margin-right:5px}.treeview .node-disabled{color:silver;cursor:not-allowed}.node-treeview8{color:yellow;background-color:purple;border:none;}.node-treeview8:not(.node-disabled):hover{background-color:orange;} </style><style type="text/css" id="treeview9-style"> .treeview .list-group-item{cursor:pointer}.treeview span.indent{margin-left:10px;margin-right:10px}.treeview span.icon{width:12px;margin-right:5px}.treeview .node-disabled{color:silver;cursor:not-allowed}.node-treeview9{color:yellow;background-color:purple;border:none;}.node-treeview9:not(.node-disabled):hover{background-color:orange;} </style><style type="text/css" id="treeview10-style"> .treeview .list-group-item{cursor:pointer}.treeview span.indent{margin-left:10px;margin-right:10px}.treeview span.icon{width:12px;margin-right:5px}.treeview .node-disabled{color:silver;cursor:not-allowed}.node-treeview10{color:#428bca;}.node-treeview10:not(.node-disabled):hover{background-color:#F5F5F5;} </style><style type="text/css" id="treeview-searchable-style"> .treeview .list-group-item{cursor:pointer}.treeview span.indent{margin-left:10px;margin-right:10px}.treeview span.icon{width:12px;margin-right:5px}.treeview .node-disabled{color:silver;cursor:not-allowed}.node-treeview-searchable{}.node-treeview-searchable:not(.node-disabled):hover{background-color:#F5F5F5;} </style><style type="text/css" id="treeview-selectable-style"> .treeview .list-group-item{cursor:pointer}.treeview span.indent{margin-left:10px;margin-right:10px}.treeview span.icon{width:12px;margin-right:5px}.treeview .node-disabled{color:silver;cursor:not-allowed}.node-treeview-selectable{}.node-treeview-selectable:not(.node-disabled):hover{background-color:#F5F5F5;} </style><style type="text/css" id="treeview-expandible-style"> .treeview .list-group-item{cursor:pointer}.treeview span.indent{margin-left:10px;margin-right:10px}.treeview span.icon{width:12px;margin-right:5px}.treeview .node-disabled{color:silver;cursor:not-allowed}.node-treeview-expandible{}.node-treeview-expandible:not(.node-disabled):hover{background-color:#F5F5F5;} </style><style type="text/css" id="treeview-checkable-style"> .treeview .list-group-item{cursor:pointer}.treeview span.indent{margin-left:10px;margin-right:10px}.treeview span.icon{width:12px;margin-right:5px}.treeview .node-disabled{color:silver;cursor:not-allowed}.node-treeview-checkable{}.node-treeview-checkable:not(.node-disabled):hover{background-color:#F5F5F5;} </style><style type="text/css" id="treeview-disabled-style"> .treeview .list-group-item{cursor:pointer}.treeview span.indent{margin-left:10px;margin-right:10px}.treeview span.icon{width:12px;margin-right:5px}.treeview .node-disabled{color:silver;cursor:not-allowed}.node-treeview-disabled{}.node-treeview-disabled:not(.node-disabled):hover{background-color:#F5F5F5;} </style><style type="text/css" id="treeview12-style"> .treeview .list-group-item{cursor:pointer}.treeview span.indent{margin-left:10px;margin-right:10px}.treeview span.icon{width:12px;margin-right:5px}.treeview .node-disabled{color:silver;cursor:not-allowed}.node-treeview12{}.node-treeview12:not(.node-disabled):hover{background-color:#F5F5F5;} </style>






                  <!-- <product-component></product-component>-->
                    <front-component></front-component>

                   </div>
    </div>

    <script src="./bower_components/jquery/dist/jquery.js"></script>
    <script src="./js/bootstrap-treeview.js"></script>
</div>
@endsection
