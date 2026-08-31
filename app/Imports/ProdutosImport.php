<?php

namespace App\Imports;

use App\Models\Produto;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProdutosImport implements ToModel,WithHeadingRow
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        return new Produto([
            'nome'          =>  $row['nome'],
            'cor'           =>  $row['cor'],
            'categoria_id'  =>  $row['categoria'],
            'valor_venda'   =>  $row['venda'],
            'valor_compra'  =>  $row['compra'],
            'NCM'           =>  $row['ncm'],
            'codBarras'     =>  $row['codbarras'],
            'CEST'          =>  $row['cest'],
            'CST_CSOSN'     =>  $row['cst_csosn'],
            'CST_PIS'       =>  $row['cst_pis'],
            'CST_COFINS'    =>  $row['cst_cofins'],
            'CST_IPI'        =>  $row['cst_ipi'],
            'unidade_compra'       =>  $row['unidade_compra'],
            'conversao_unitaria'    =>  $row['conversao_unitaria'],
            'unidade_venda'  =>  $row['unidade_venda'],
            'composto'     =>  $row['composto'],
            'valor_livre'     =>  $row['valorlivre'],
            'perc_icms'     =>  $row['percicms'],
            'perc_pis'     =>  $row['percpis'],
            'perc_cofins'     =>  $row['perccofins'],
            'perc_ipi'        =>  $row['percipi'],
            'perc_iss'        =>  $row['perciss'],
            'pRedBC'          =>  $row['pred'],
            'cBenef'             =>  $row['cbnef'],
            'cListServ'          =>  $row['clistserv'],
            'CFOP_saida_estadual'          =>  $row['cfopsaidaest'],
            'CFOP_saida_inter_estadual'          =>  $row['cfopsaidafora'],
            'codigo_anp'          =>  $row['codigoanp'],
            'descricao_anp'          =>  $row['descrcaoanp'],
            'imagem'          =>  $row['imagem'],
            'alerta_vencimento'          =>  $row['alerta'],
            'gerenciar_estoque'          =>  $row['estoque'],
            'estoque_minimo'          =>  $row['minimo'],
            'referencia'               =>  $row['refer'],
            'tela_id'                  =>  $row['tela'],
            'largura'                  =>  $row['largura'],
            'comprimento'              =>  $row['comprimento'],
            'altura'                   =>  $row['altura'],
            'peso_liquido'             =>  $row['pesol'],
            'peso_bruto'               =>  $row['pesob'],
           



            //
        ]);
    }
}
