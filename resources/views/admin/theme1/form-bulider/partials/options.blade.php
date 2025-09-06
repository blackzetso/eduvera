@foreach ($categories as $row)
    <option  value="{{ $row->id }}">{{ $prefix }}{{ $row->name }}</option>
    @if ($row->children->isNotEmpty())
        @include('admin-panel.theme-2.categories.partials.options',['categories' => $row->children,'prefix' => $prefix .' - '])
    @endif
@endforeach
