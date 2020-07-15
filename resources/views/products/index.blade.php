<table>
    @foreach($products as $prod)
        <tr>
            <td>{{ $prod->nombre}}</td>
            <td>{{ $prod->supply_id}}</td>
        </tr>
    @endforeach
</table>