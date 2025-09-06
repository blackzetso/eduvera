@foreach ($forms as $row)
    <tr>
        <td class="text-center">{{ $row->id }}</td>
        <td class="cell-ta"> {{ $row->name }} </td>
        <td class="text-center"> <span class="text-success" >active</span> </td>
        <td class="text-center">
            <a href="{{ route('admin.forms.destroy',$row->id) }}" data-confirm-delete="true" class="gray-s flaticon-trash"  ></a>
            <a href="{{ route('admin.forms.edit',$row->id) }}" title="edit" class="gray-s flaticon-edit-2"></a>
            <a href="{{ route('admin.forms.show',$row->id) }}" title="edit" class="gray-s flaticon-plus"></a>
            <a href="{{ route('admin.forms.show',$row->id) }}" title="edit" class="gray-s flaticon-link"></a> 
        </td>
    </tr>
@endforeach
@include('sweetalert::alert')
