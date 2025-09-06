@extends('admin.theme1.layout.app')
@section('style')
    <style>
        .flaticon-trash:before {
            content: "\f1b2";
        }

        .page-link{
            color: #db2828;
        }
        .active>.page-link, .page-link.active{
            background-color: #db2828;
            border-color: #db2828;
        }

        .card {
            background-color: #fff;
            border-color: #ebebeb;
        }
        .card-header {
            background-color: #fff !important;
            border-color: #ebebeb !important;
        }
        .card-body {
            background-color: #fff !important;
            border-color: #ebebeb !important;
        }
        .card-footer {
            background-color: #fff !important;
            border-color: #ebebeb !important;
        }
    </style>
@endsection
@section('content')
<!-- Body Start -->
<div class="wrapper">
    <div class="sa4d25">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12">
                    <h2 class="st_title"><i class="uil uil-award"></i>  Forms  </h2>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <div class="mt-10">
                        <div class="card">
                            <div class="card-header">
                                <h1> Edit Form</h1>
                            </div>
                            <div class="card-body bg-light mb-3">
                                <form method="POST" id="editForm" action="{{ route('admin.forms.update',$form->id) }}" >
                                    @csrf
                                     @method('PUT')
                                    <div class="form-group" >
                                        <input type="text" class="form-control" name="name" value="{{ $form->name }}" >
                                    </div>
                                </form>
                            </div>
                            <div class="card-footer" >
                                <button form="editForm" class="create_btn_dash" type="submit" > Save </button>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
     @include('admin.theme1.layout.footer')
</div>

@endsection
@section('script')
<script>
    /** insert Category **/
   $(document).on('submit','#add-new-form',function(event){
       event.preventDefault();
       $.ajax({
           headers: {
               'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
           },
           type:'POST',
           url:"{{ route('admin.forms.store') }}",
           data:new FormData(document.getElementById("add-new-form")),
           contentType:false,
           processData:false,
           success:function(data){
               $("#ajax-response").html(data);
               ///$('#offcanvasRight').modal('hide')
           },
       })
   });

   $(document).ready(function() {
        $("#search").on("input", function() {
            var searchQuery = $(this).val();
            $.ajax({
                url: '/admin/search?forms=' + encodeURIComponent(searchQuery),
                type: 'GET',
                data: { search: searchQuery },
                success: function(response) {
                        $("#ajax-response").html(response);
                },
                error: function(error) {
                    console.log("Error: ", error);
                }
            });
        });
    });
 </script>
@endsection
