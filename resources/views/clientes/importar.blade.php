@extends('default.layout')
@section('content')

<div class="content d-flex flex-column flex-column-fluid">
	<div class="container">
		<div class="card card-custom gutter-b">
			<div class="card-header">
				<div class="card-title">
					<h3 class="card-label">Importar Clientes</h3>
				</div>
			</div>

			<div class="card-body">

				@if(session('mensagem_sucesso'))
				<div class="alert alert-success" role="alert">
					{{ session('mensagem_sucesso') }}
				</div>
				@endif

				@if(session('mensagem_erro'))
				<div class="alert alert-danger" role="alert">
					{{ session('mensagem_erro') }}
				</div>
				@endif

				@if(session('erros_importacao'))
				<div class="alert alert-warning" role="alert">
					<strong>Linhas com problema:</strong>
					<ul class="mb-0">
						@foreach(session('erros_importacao') as $erro)
						<li>{{ $erro }}</li>
						@endforeach
					</ul>
				</div>
				@endif

				<div class="row">
					<div class="col-lg-8 col-md-12">
						<form method="post" action="/clientes/importar" enctype="multipart/form-data">
							@csrf
							<div class="form-group">
								<label class="col-form-label">Arquivo (CSV ou XLSX)</label>
								<input type="file" name="file" class="form-control" accept=".csv,.txt,.xlsx,.xlsm" required>
								<small class="form-text text-muted">CSV separado por ponto e v&iacute;rgula (;) ou Excel .xlsx. A primeira linha deve ser o cabe&ccedil;alho.</small>
							</div>

							<div class="mt-4">
								<button type="submit" class="btn btn-success">
									<i class="fa fa-file-upload"></i> Importar
								</button>
								<a href="/clientes/importar/modelo" class="btn btn-info">
									<i class="fa fa-download"></i> Baixar modelo CSV
								</a>
								<a href="/clientes" class="btn btn-secondary">Voltar</a>
							</div>
						</form>
					</div>
				</div>

				<hr>

				<h5>Colunas aceitas no cabe&ccedil;alho</h5>
				<p class="text-muted">A ordem n&atilde;o importa; o sistema identifica pelo nome da coluna. Informe <strong>razao_social</strong> e uma cidade v&aacute;lida por c&oacute;digo IBGE, nome + UF ou <strong>cidade_id</strong>.</p>
				<p class="text-muted">Planilhas XLSX exportadas com <code>cidade_id</code> s&atilde;o aceitas quando os IDs correspondem ao cadastro de cidades deste sistema. O <code>id</code> do cliente e as datas da exporta&ccedil;&atilde;o n&atilde;o s&atilde;o importados.</p>
				<div class="table-responsive">
					<table class="table table-bordered table-sm">
						<thead>
							<tr><th>Coluna</th><th>Descri&ccedil;&atilde;o</th></tr>
						</thead>
						<tbody>
							<tr><td><code>razao_social</code></td><td>Nome/raz&atilde;o social (obrigat&oacute;rio)</td></tr>
							<tr><td><code>nome_fantasia</code></td><td>Nome fantasia (se vazio, usa a raz&atilde;o social)</td></tr>
							<tr><td><code>cpf_cnpj</code></td><td>CPF ou CNPJ (s&oacute; n&uacute;meros ou com m&aacute;scara). Usado para n&atilde;o duplicar.</td></tr>
							<tr><td><code>ie_rg</code></td><td>Inscri&ccedil;&atilde;o estadual / RG (vazio = ISENTO)</td></tr>
							<tr><td><code>telefone</code>, <code>celular</code>, <code>email</code></td><td>Contatos</td></tr>
							<tr><td><code>cep</code>, <code>rua</code>, <code>numero</code>, <code>bairro</code></td><td>Endere&ccedil;o</td></tr>
							<tr><td><code>cidade_codigo</code></td><td>C&oacute;digo IBGE do munic&iacute;pio (prefer&iacute;vel)</td></tr>
							<tr><td><code>cidade_id</code></td><td>ID existente no cadastro de cidades deste sistema</td></tr>
							<tr><td><code>cidade</code> + <code>uf</code></td><td>Alternativa ao c&oacute;digo: nome da cidade + estado</td></tr>
							<tr><td><code>contribuinte</code></td><td>1/0 (ou S/N) &mdash; contribuinte de ICMS</td></tr>
							<tr><td><code>consumidor_final</code></td><td>1/0 (ou S/N)</td></tr>
							<tr><td><code>limite_venda</code></td><td>Limite de cr&eacute;dito (n&uacute;mero)</td></tr>
						</tbody>
					</table>
				</div>

				<p class="text-muted">
					Clientes com CPF/CNPJ j&aacute; cadastrado s&atilde;o <strong>pulados</strong> (n&atilde;o duplica).
				</p>

			</div>
		</div>
	</div>
</div>

@endsection
