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
                        <div class="card_dash1">
                            <div class="card_dash_left1">
                                <i class="uil uil-award"></i>
                                <h1>Jump Into New Form</h1>
                            </div>
                            <div class="card_dash_right1 mb-3">
                                <button class="create_btn_dash" data-bs-toggle="offcanvas" data-bs-target="#offcanvasRight"  >New Form</button>
                            </div>
                            <input type="search" name="search" class="form-control" id="search" placeholder="search ...">
                        </div>
                        <div class="table-cerificate">
                            <div class="table-responsive">
                                <table class="table ucp-table" id="content-table">
                                    <thead class="thead-s">
                                        <tr>
                                            <th class="text-center" scope="col">#id</th>
                                            <th scope="col">name</th>
                                            <th class="text-center" scope="col">status</th>
                                            <th class="text-center" scope="col">Controls</th>
                                        </tr>
                                    </thead>
                                    <tbody id="ajax-response">
                                        @include('admin.theme1.form-bulider.partials.list')
                                    </tbody>
                                </table>
                                {{ $forms->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
     @include('admin.theme1.layout.footer')
</div>
<!-- Body End -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasRight" aria-labelledby="offcanvasRightLabel">
    <div class="offcanvas-header">
      <h5 id="offcanvasRightLabel"> Add new Form </h5>
      <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <form id="add-new-form" method="POST" >
            @csrf
            <div class="form-group" >
                <label>Form name</label>
                <input type="text" class="form-control mt-3" name="name" >
            </div>
            <div class="form-group mt-3" >
                <div class="d-grid gap-2">
                    <button type="submit" class="create_btn_dash" >Save</button>
                </div>
            </div>
        </form>
    </div>
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
