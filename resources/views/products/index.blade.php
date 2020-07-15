<table>
    @foreach($products as $prod)
        <tr>
            <td>{{ $product->nombre}}</td>
            <td>{{ $product->supply_id}}</td>
        </tr>
    @endforeach
</table>