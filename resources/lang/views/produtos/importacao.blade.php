@extends('default.layout')
@section('content')

<style type="text/css">
	.btn-file {
		position: relative;
		overflow: hidden;
	}

	.btn-file input[type=file] {
		position: absolute;
		top: 0;
		right: 0;
		min-width: 100%;
		min-height: 100%;
		font-size: 100px;
		text-align: right;
		filter: alpha(opacity=0);
		opacity: 0;
		outline: none;
		background: white;
		cursor: inherit;
		display: block;
	}
</style>


<div class="container">
    <div class="card bg-light mt-3">
        <div class="card-header">
            Importar Produtos
        </div>
        <div class="card-body">
            <form action="/produtos/importacao" method="POST" enctype="multipart/form-data">
                @csrf
              
								
                <input type="file" name="file" class="form-control"  [(ngModel)]="model.fileupload">
                <br>
                <button class="btn btn-success">Importar Produtos</button>
            </form>
        </div>
    </div>
</div>

@endsection
