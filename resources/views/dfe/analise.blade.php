@extends('default.layout')
@section('content')
<style>
	.dfe-bi-wrap{background:linear-gradient(145deg,#f8fcff 0%,#eef7f7 100%);border:1px solid #d9ece9;border-radius:16px;padding:20px;margin-bottom:22px}
	.dfe-bi-title{font-size:21px;font-weight:700;color:#0f172a;margin-bottom:4px}
	.dfe-bi-sub{color:#475569;font-size:13px;margin-bottom:16px}
	.dfe-kpi{background:#fff;border:1px solid #dbeafe;border-radius:14px;padding:16px;box-shadow:0 8px 24px rgba(2,6,23,.04);height:100%}
	.dfe-kpi-label{font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#64748b;font-weight:700}
	.dfe-kpi-value{font-size:28px;line-height:1.1;font-weight:800;color:#0f172a;margin-top:8px}
	.dfe-kpi-help{font-size:12px;color:#334155}
	.dfe-bi-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:16px;height:100%}
	.dfe-bi-card-title{font-size:15px;font-weight:700;color:#0f172a;margin-bottom:10px}
	.dfe-bi-line{margin-bottom:12px}
	.dfe-bi-line:last-child{margin-bottom:0}
	.dfe-bi-line-head{display:flex;justify-content:space-between;font-size:12px;color:#334155;margin-bottom:5px}
	.dfe-bi-bar{height:8px;background:#e2e8f0;border-radius:10px;overflow:hidden}
	.dfe-bi-fill{height:100%;border-radius:10px}
	.dfe-bi-top-item{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px dashed #e2e8f0}
	.dfe-bi-top-item:last-child{border-bottom:none}
	.dfe-bi-top-name{font-size:13px;color:#0f172a;font-weight:600;padding-right:10px}
	.dfe-bi-top-meta{font-size:12px;color:#475569;text-align:right}
	.entrada-produtos-scroll{max-height:420px;overflow:auto;border:1px solid #e2e8f0;border-radius:8px}
	.entrada-produtos-scroll thead th{position:sticky;top:0;background:#f8fafc;z-index:2}
</style>

<div class="card card-custom gutter-b">
	<div class="card-body">
		<div style="margin-left: 10px; margin-right: 10px;">
			<form method="get" action="/dfe/analise/filtro">
				<div class="row align-items-center">
					<div class="form-group col-lg-3 col-md-4 col-sm-6">
						<label class="col-form-label">Data Inicial</label>
						<div class="input-group date">
							<input type="text" name="data_inicial" class="form-control" readonly value="{{{isset($data_inicial) ? $data_inicial : ''}}}" id="kt_datepicker_3" />
							<div class="input-group-append"><span class="input-group-text"><i class="la la-calendar"></i></span></div>
						</div>
					</div>

					<div class="form-group col-lg-3 col-md-4 col-sm-6">
						<label class="col-form-label">Data Final</label>
						<div class="input-group date">
							<input type="text" name="data_final" class="form-control" readonly value="{{{isset($data_final) ? $data_final : ''}}}" id="kt_datepicker_3" />
							<div class="input-group-append"><span class="input-group-text"><i class="la la-calendar"></i></span></div>
						</div>
					</div>

					<div class="form-group validated col-lg-2 col-md-2 col-sm-6">
						<label class="col-form-label text-left col-lg-12 col-sm-12">Tipo</label>
						<select class="custom-select form-control" id="tipo" name="tipo">
							<option value="--" @if(isset($tipo_selecionado) && $tipo_selecionado == '--') selected @endif>TODOS</option>
							<option value="1" @if(isset($tipo_selecionado) && $tipo_selecionado == '1') selected @endif>CIENCIA</option>
							<option value="2" @if(isset($tipo_selecionado) && $tipo_selecionado == '2') selected @endif>CONFIRMADA</option>
							<option value="3" @if(isset($tipo_selecionado) && $tipo_selecionado == '3') selected @endif>DESCONHECIDA</option>
							<option value="4" @if(isset($tipo_selecionado) && $tipo_selecionado == '4') selected @endif>NAO REALIZADA</option>
							<option value="0" @if(isset($tipo_selecionado) && $tipo_selecionado == '0') selected @endif>SEM ACAO</option>
						</select>
					</div>

					<div class="col-lg-2 col-xl-2 mt-2 mt-lg-0">
						<button style="margin-top: 15px;" class="btn btn-light-primary px-6 font-weight-bold">Filtrar</button>
					</div>
					<div class="col-lg-2 col-xl-2 mt-2 mt-lg-0">
						<a href="/dfe" style="margin-top: 15px;" class="btn btn-light-info px-6 font-weight-bold">Tela Principal</a>
					</div>
				</div>
			</form>

			<div class="dfe-bi-wrap">
				<div class="dfe-bi-title">Analise de Entrada DF-e</div>
				<div class="dfe-bi-sub">Periodo padrao igual a tela principal (ultimos 90 dias).</div>

				<div class="row">
					<div class="col-sm-6 col-lg-2 mb-4"><div class="dfe-kpi"><div class="dfe-kpi-label">Valor Entrada</div><div class="dfe-kpi-value">R$ {{number_format($bi['kpis']['valor_entrada_total'], 2, ',', '.')}}</div><div class="dfe-kpi-help">Total de entrada no periodo</div></div></div>
					<div class="col-sm-6 col-lg-2 mb-4"><div class="dfe-kpi"><div class="dfe-kpi-label">Qtd Saida</div><div class="dfe-kpi-value">{{number_format($bi['kpis']['quantidade_saida_total'], 2, ',', '.')}}</div><div class="dfe-kpi-help">Total de itens vendidos</div></div></div>
					<div class="col-sm-6 col-lg-2 mb-4"><div class="dfe-kpi"><div class="dfe-kpi-label">Valor Saida</div><div class="dfe-kpi-value">R$ {{number_format($bi['kpis']['valor_saida_total'], 2, ',', '.')}}</div><div class="dfe-kpi-help">Faturamento dos itens</div></div></div>
					<div class="col-sm-6 col-lg-2 mb-4"><div class="dfe-kpi"><div class="dfe-kpi-label">Lucro Total</div><div class="dfe-kpi-value">R$ {{number_format($bi['kpis']['lucro_total_saida'], 2, ',', '.')}}</div><div class="dfe-kpi-help">Lucro estimado na saida</div></div></div>
					<div class="col-sm-6 col-lg-2 mb-4"><div class="dfe-kpi"><div class="dfe-kpi-label">Margem Media</div><div class="dfe-kpi-value">{{number_format($bi['kpis']['margem_media_saida'], 2, ',', '.')}}%</div><div class="dfe-kpi-help">Lucro sobre valor vendido</div></div></div>
					<div class="col-sm-6 col-lg-2 mb-4"><div class="dfe-kpi"><div class="dfe-kpi-label">Ticket Medio Saida</div><div class="dfe-kpi-value">R$ {{number_format($bi['kpis']['ticket_medio_saida'], 2, ',', '.')}}</div><div class="dfe-kpi-help">Valor por item vendido</div></div></div>
					<div class="col-sm-6 col-lg-2 mb-4"><div class="dfe-kpi"><div class="dfe-kpi-label">Produtos com Saida</div><div class="dfe-kpi-value">{{$bi['kpis']['produtos_com_saida']}}</div><div class="dfe-kpi-help">Itens com venda no periodo</div></div></div>
				</div>

				<div class="row">
					<div class="col-lg-7 mb-4">
						<div class="dfe-bi-card">
							<div class="dfe-bi-card-title">Top Fornecedores (volume)</div>
							@if(count($bi['top_fornecedores']) > 0)
								@foreach($bi['top_fornecedores'] as $forn)
								<div class="dfe-bi-top-item">
									<div class="dfe-bi-top-name">{{$forn['nome']}}</div>
									<div class="dfe-bi-top-meta">{{$forn['quantidade']}} docs<br>R$ {{number_format($forn['valor'], 2, ',', '.')}}</div>
								</div>
								@endforeach
							@else
								<div class="text-muted">Sem registros no periodo.</div>
							@endif
						</div>
					</div>
					<div class="col-lg-5 mb-4">
						<div class="dfe-bi-card">
							<div class="dfe-bi-card-title">Top Clientes (valor)</div>
							@if(isset($bi['top_clientes']) && count($bi['top_clientes']) > 0)
								@foreach($bi['top_clientes'] as $cliente)
								<div class="dfe-bi-top-item">
									<div class="dfe-bi-top-name">{{$cliente['nome']}}</div>
									<div class="dfe-bi-top-meta">{{$cliente['quantidade']}} vendas<br>R$ {{number_format($cliente['valor'], 2, ',', '.')}}</div>
								</div>
								@endforeach
							@else
								<div class="text-muted">Sem registros no periodo.</div>
							@endif
						</div>
					</div>
				</div>

				<div class="row">
					<div class="col-lg-12 mb-2">
						<div class="dfe-bi-card">
							<div class="d-flex justify-content-between align-items-center mb-3">
								<div class="dfe-bi-card-title mb-0">Quantidade de entrada por produto</div>
								<div class="d-flex align-items-center">
									<small class="text-muted mr-3">Itens: {{isset($bi['entrada_por_produto']) ? count($bi['entrada_por_produto']) : 0}}</small>
									<small class="text-muted mr-2">Total Valor Entrada: <strong>R$ {{number_format($bi['kpis']['valor_entrada_total'], 2, ',', '.')}}</strong></small>
									<select id="ordenacao_produtos_entrada" class="form-control form-control-sm" style="min-width: 250px;">
										<option value="quantidade_saida_desc">Qtd saida (maior para menor)</option>
										<option value="quantidade_saida_asc">Qtd saida (menor para maior)</option>
										<option value="quantidade_entrada_desc">Qtd entrada (maior para menor)</option>
										<option value="quantidade_entrada_asc">Qtd entrada (menor para maior)</option>
										<option value="valor_venda_desc">Valor de venda (maior para menor)</option>
										<option value="valor_venda_asc">Valor de venda (menor para maior)</option>
										<option value="margem_desc">Margem lucro % (maior para menor)</option>
										<option value="margem_asc">Margem lucro % (menor para maior)</option>
										<option value="nome_asc">Produto (A-Z)</option>
										<option value="nome_desc">Produto (Z-A)</option>
									</select>
								</div>
							</div>
							<div class="entrada-produtos-scroll">
								<table class="table table-sm table-hover mb-0" id="tabela_produtos_entrada">
									<thead>
										<tr>
											<th>Produto</th>
											<th class="text-right">Qtd Entrada</th>
											<th class="text-right">Qtd Saída</th>
											<th class="text-right">Valor Entrada</th>
											<th class="text-right">Valor Venda</th>
											<th class="text-right">Lucro</th>
											<th class="text-right">Margem %</th>
										</tr>
									</thead>
									<tbody>
										@if(isset($bi['entrada_por_produto']) && count($bi['entrada_por_produto']) > 0)
											@foreach($bi['entrada_por_produto'] as $item)
											<tr
												data-nome="{{strtolower($item['nome'])}}"
												data-quantidade-entrada="{{$item['quantidade_entrada']}}"
												data-quantidade-saida="{{$item['quantidade_saida']}}"
												data-valor-entrada="{{$item['valor_entrada_total']}}"
												data-valor-venda="{{$item['valor_venda_total']}}"
												data-lucro="{{$item['lucro_total']}}"
												data-margem="{{$item['margem_lucro_percentual']}}">
												<td>{{$item['nome']}}</td>
												<td class="text-right">{{number_format($item['quantidade_entrada'], 2, ',', '.')}}</td>
												<td class="text-right">{{number_format($item['quantidade_saida'], 2, ',', '.')}}</td>
												<td class="text-right">R$ {{number_format($item['valor_entrada_total'], 2, ',', '.')}}</td>
												<td class="text-right">R$ {{number_format($item['valor_venda_total'], 2, ',', '.')}}</td>
												<td class="text-right">R$ {{number_format($item['lucro_total'], 2, ',', '.')}}</td>
												<td class="text-right">{{number_format($item['margem_lucro_percentual'], 2, ',', '.')}}%</td>
											</tr>
											@endforeach
										@else
											<tr>
												<td colspan="7" class="text-center text-muted">Sem entradas por produto no período selecionado.</td>
											</tr>
										@endif
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
	const select = document.getElementById('ordenacao_produtos_entrada');
	const tbody = document.querySelector('#tabela_produtos_entrada tbody');
	if (!select || !tbody) return;

	const sortRows = function () {
		const rows = Array.from(tbody.querySelectorAll('tr[data-nome]'));
		const mode = select.value;
		const compare = function (a, b) {
			const aNome = (a.dataset.nome || '').toLowerCase();
			const bNome = (b.dataset.nome || '').toLowerCase();
			const aQtdEntrada = parseFloat(a.dataset.quantidadeEntrada || '0');
			const bQtdEntrada = parseFloat(b.dataset.quantidadeEntrada || '0');
			const aQtdSaida = parseFloat(a.dataset.quantidadeSaida || '0');
			const bQtdSaida = parseFloat(b.dataset.quantidadeSaida || '0');
			const aValorVenda = parseFloat(a.dataset.valorVenda || '0');
			const bValorVenda = parseFloat(b.dataset.valorVenda || '0');
			const aMargem = parseFloat(a.dataset.margem || '0');
			const bMargem = parseFloat(b.dataset.margem || '0');

			switch (mode) {
				case 'quantidade_entrada_asc': return aQtdEntrada - bQtdEntrada;
				case 'quantidade_entrada_desc': return bQtdEntrada - aQtdEntrada;
				case 'quantidade_saida_asc': return aQtdSaida - bQtdSaida;
				case 'quantidade_saida_desc': return bQtdSaida - aQtdSaida;
				case 'valor_venda_asc': return aValorVenda - bValorVenda;
				case 'valor_venda_desc': return bValorVenda - aValorVenda;
				case 'margem_asc': return aMargem - bMargem;
				case 'margem_desc': return bMargem - aMargem;
				case 'nome_desc': return bNome.localeCompare(aNome, 'pt-BR');
				case 'nome_asc':
				default: return aNome.localeCompare(bNome, 'pt-BR');
			}
		};
		rows.sort(compare);
		rows.forEach(function (row) { tbody.appendChild(row); });
	};

	select.addEventListener('change', sortRows);
	sortRows();
});
</script>
@endsection

