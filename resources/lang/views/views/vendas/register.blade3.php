@extends('default.layout')
@section('content')

<div class="row">
      <div class="form-group validated col-sm-7 col-lg-7 col-12">
        <label class="col-form-label" id="">Cliente</label>
        <div class="input-group">

          <select class="form-control select2" id="cliente" name="cliente" autofocus>
            <option value="null">Selecione Fornecedor</option>
          </select>

          <div class="input-group-prepend">
            <span class="input-group-text btn-info btn" onclick="novoCliente()">
              <i class="la la-plus"></i>
            </span>
          </div>
        </div>
      </div>


    </div>


@endsection

