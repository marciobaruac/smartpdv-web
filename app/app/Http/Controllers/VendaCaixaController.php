<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VendaCaixa;
use App\Helpers\StockMove;
use App\Models\ItemVendaCaixa;
use App\Models\CreditoVenda;
use App\Models\ConfigNota;
use App\Models\PedidoDelivery;
use App\Models\Produto;
use App\Models\ProdutoPizza;
use App\Models\Pedido;
use App\Models\AberturaCaixa;
use App\Models\Usuario;
use App\Models\Agendamento;
use App\Models\ComissaoVenda;
use App\Models\Orcamento;
use App\Models\ConfigCartao;
use App\Models\ContaReceber;
use Illuminate\Support\Facades\DB;

class VendaCaixaController extends Controller
{

  public function __construct(){
    $this->middleware(function ($request, $next) {
      $value = session('user_logged');
      if(!$value){
        return redirect("/login");
      }
      return $next($request);
    });
  }
  
  public function save(Request $request){

    DB::beginTransaction();
	
    $venda = $request->venda;
    $agendamento_id = $venda['agendamento_id'];

    $config = ConfigNota::first();
    
    $vtxcartao = 0;
    $vtxcartao1 = 0;
    $vtxcartao2 = 0;
    $vtxcartao3 = 0;
    $datavenda = date('Y/m/d H:i:s'); 
    if($venda['orcamento_id'] > 0){
      $orcamento = Orcamento::find($venda['orcamento_id']);
      $orcamento->estado = 'APROVADO';
      $orcamento->save();
    }

   

 


    $totalVenda = str_replace(",", ".", $venda['valor_total']) + str_replace(",", ".", $venda['acrescimo']) - str_replace(",", ".", $venda['desconto']);

    $valorParcela = $totalVenda;
    // $valorrecebido = str_replace(",", ".", $f['valor']);
           
    
    if ($venda['tipo_pagamento']=='02'){
      
      $infcartao =  ConfigCartao::where('cartao', 'Debito')
      ->where('bandeira',$venda['bandeira_cartao'])
      ->first();

     
      if ($infcartao){
        $taxa = $infcartao->taxa;
      }
      else{
        $taxa = 0;
      }

      $vtxcartao = ($totalVenda * $taxa)/100;

      
      $valorrecebido = ($totalVenda-  $vtxcartao);
    
      


      $datafaturamento =  date('Y/m/d');
      $vencimento   = date('Y/m/d', strtotime('+ 1 days',strtotime($datafaturamento)));
           
            
              
      $vencimento  = $this->verificadiautil($this->parseDate($vencimento));
      $recebimento = $this->verificadiautil($this->parseDate($vencimento));

    }
    
    if ($venda['tipo_pagamento']=='01' || $venda['tipo_pagamento']=='04'){
      
      $vencimento   =  date('Y/m/d');
      $vencimento   =   $this->parseDate($vencimento);
      $recebimento  =   $vencimento ;
     
      $valorrecebido = $totalVenda;
    }

  

    
    if ($venda['tipo_pagamento']=='05' || $venda['tipo_pagamento']=='06'){
      
      
      $vencimento   =  date('Y/m/d');
    
      $valorrecebido = 0;     
            
              
      $vencimento  = $this->verificadiautil($this->parseDate($vencimento));
      $recebimento = $vencimento;
    }




    if ($venda['tipo_pagamento']=='03'){
      $infcartao =  ConfigCartao::where('cartao', 'Credito')
      ->where('bandeira',$venda['bandeira_cartao'])
      ->first();

    
     
      if ($infcartao){
        $taxa = $infcartao->taxa;
      }
      else{
        $taxa = 0;
      }
     
     
      $vtxcartao = ($totalVenda * $taxa)/100;
      $valorrecebido = ($totalVenda-  $vtxcartao);
   //   $datafaturamento =  date('Y/m/d');
      $datafaturamento =  date('Y-m-d H:i:s');
     
      $vencimento   = date('Y/m/d', strtotime('+ 2 days',strtotime($datafaturamento)));
      $vencimento   = $this->verificadiautil($vencimento);
            
              
      $vencimento  = $this->verificadiautil($this->parseDate($vencimento));
      $recebimento = $this->verificadiautil($this->parseDate($vencimento));

    }

    if( $venda['tipo_pagamento'] == '05'  ||  $venda['tipo_pagamento'] == '06'){
 
      $status = false;

    }
    else {
      $status = true;

    }
  
  
    $result = VendaCaixa::create([
      'cliente_id' => $venda['cliente'],
      'usuario_id' => get_id_user(),
      'natureza_id' => $config->nat_op_padrao,
      'data_registro' => $datavenda,
      'valor_total' => $totalVenda,
      'acrescimo' => str_replace(",", ".", $venda['acrescimo']),
      'troco' => str_replace(",", ".", $venda['troco']),
      'dinheiro_recebido' => str_replace(",", ".", $venda['dinheiro_recebido']),
      'forma_pagamento' => $venda['acao'] == 'credito' ? 'credito' : " ",
      'tipo_pagamento' => $venda['tipo_pagamento_1'] ? '99' : $venda['tipo_pagamento'],
      'estado' => 'DISPONIVEL',
      'NFcNumero' => 0,
      'chave' => '',
      'path_xml' => '',
      'nome' => $venda['nome'] ?? '',
      'cpf' => $venda['cpf'] ?? '',
      'observacao' => $venda['observacao'] ?? '',
      'desconto' => $venda['desconto'],
      'pedido_delivery_id' => isset($venda['delivery_id']) ? $venda['delivery_id'] : 0,
      'tipo_pagamento_1' => $venda['tipo_pagamento_1'] ?? '', 
      'valor_pagamento_1' => $venda['valor_pagamento_1'] ?? 0,
      'tipo_pagamento_2' => $venda['tipo_pagamento_2'] ?? '',
      'valor_pagamento_2' => $venda['valor_pagamento_2'] ?? 0,
      'tipo_pagamento_3' => $venda['tipo_pagamento_3'] ?? '',
      'valor_pagamento_3' => $venda['valor_pagamento_3'] ?? 0,
      'bandeira_cartao' => $venda['bandeira_cartao'],
      'cAut_cartao' => $venda['cAut_cartao'] ?? '',
      'cnpj_cartao' => $venda['cnpj_cartao'] ?? '',
      'descricao_pag_outros' => $venda['descricao_pag_outros'] ?? '',
      'txcartao'             => $vtxcartao,
      'bandeira_cartao_1'             => $venda['bandeira_cartao1'],
      'bandeira_cartao_2'             => $venda['bandeira_cartao2'],
      'bandeira_cartao_3'             => $venda['bandeira_cartao3'],
      
      
      
    ]);
    
    // começa operação para gerar previsão de recebimento

   
     
    if( $result->tipo_pagamento != '99'){
 
       $resultFatura = ContaReceber::create([
         'venda_caixa_id' => $result->id,
         'cliente_id' => $venda['cliente'],
     
         
      
        'data_vencimento'  => $vencimento ,
        'data_recebimento' => $recebimento ,
      
        'forma_recebimento'  =>  $venda['tipo_pagamento'],
      
        'valor_integral' => $valorParcela,
        'valor_recebido' => $valorrecebido,
        'status' => $status ,
        'referencia' => "Venda: " . $result->id,
        'categoria_id' => 2,
        'usuario_id' => get_id_user()
    ]);
    }
    else {


      // Grava forma de pagamento Multiplo Forma 1
      
      $vtxcartao1 = $this->gravatipopagamento_1($result);
  
           // Grava forma de pagamento Multiplo Forma 2
      $vtxcartao2 = $this->gravatipopagamento_2($result);

        // Grava forma de pagamento Multiplo Forma 3
      $vtxcartao3 = $this->gravatipopagamento_3($result);
      
      
    }

     
    

    if($venda['codigo_comanda'] > 0){
      $pedido = Pedido::
      where('id', $venda['codigo_comanda'])
      ->where('status', 0)
      ->where('desativado', 0)
      ->first();

      foreach($pedido->itens as $i){
        $i->status = 1;
        $i->save();
      }
      
      $pedido->status = 1;
      $pedido->desativado = 1;
      $pedido->save();
    }

    $itens = $venda['itens'];
    $stockMove = new StockMove();
    $totalcusto = 0;
    
    foreach ($itens as $i) {
      $custoproduto = 0; 
      $prod = Produto
      ::where('id', $i['id'])
      ->first();

      if ($prod->composto == 0){

        $custoproduto = $prod->valor_compra * (float) str_replace(",", ".", $i['quantidade']) ;
    
    } 
      else{

        $custoproduto = $prod->custoprodutofabricado($prod->id) * (float) str_replace(",", ".", $i['quantidade']) ;
    }

      //$custoproduto = (float) str_replace(",", ".", $i['quantidade']) * $prod->valor_compra;
     
     $resultitem = ItemVendaCaixa::create([
        'venda_caixa_id' => $result->id,
        'produto_id' => (int) $i['id'],
        'quantidade' => (float) str_replace(",", ".", $i['quantidade']),
        'valor' => (float) str_replace(",", ".", $i['valor']),
        'item_pedido_id' => isset($i['itemPedido']) ? $i['itemPedido'] : NULL,
        'observacao' => $i['obs'] ?? '',
        'custo_total' =>  $custoproduto
      ]);

      $totalcusto +=  $custoproduto;
      if(!isset($venda['delivery_id']) || $venda['delivery_id'] == 0){

        
        if(isset($venda['pizza']) && $i['pizza'] == 1){
          $sabores = explode(" | ", $i['nome']);
          $totalSabores = count($sabores);
          foreach($sabores as $sb){

            $produto = Produto::
            where('nome', $sb)
            ->first();

            $produtoPizza = ProdutoPizza::
            where('produto_id', $i['id'])
            ->where('valor', $i['valor'])
            ->first();


            if(!empty($produto->receita)){
              $receita = $produto->receita;
              foreach($receita->itens as $rec){

                $stockMove->downStock(
                  $rec->produto_id, 
                  (float) str_replace(",", ".", $i['quantidade']) 
                      * 
                  ((($rec->quantidade/$totalSabores)/$receita->pedacos)*$produtoPizza->tamanho->pedacos)/$receita->rendimento
                );
              }
            }


          }

        }else if(!empty($prod->receita)){


          $receita = $prod->receita; 

          foreach($receita->itens as $rec){


            if(!empty($rec->produto->receita)){


              $receita2 = $rec->produto->receita; 

              foreach($receita2->itens as $rec2){
                
                 $produtor = Produto::
                 where('id',$rec2->produto_id )
                ->first();
                 $stockMove->downStock( $rec2->produto_id, (float) str_replace(",", ".", $i['quantidade']) * 
                 ($rec2->quantidade/$receita2->rendimento), $produtor ->valor_compra,'PDV','Obs. '. 'N: '.'-'.  'Mov: '.  $resultitem->venda_caixa_id, $resultitem->id);

            
              }
            }else{
               
                
               // die();
                $produtor = Produto::
                where('id',$rec->produto_id )
               ->first();
             //   $stockMove->downStock( $rec->produto_id, (float) str_replace(",", ".", $i['quantidade']) * 
               // ($rec->quantidade/$rec->rendimento), $produtor ->valor_compra,'PDV','Obs. '. 'N: '.'-'.  'Mov: '.  $resultitem->venda_caixa_id, $resultitem->id);

               $stockMove->downStock( $rec->produto_id, (float) str_replace(",", ".", $i['quantidade']) * 
               $rec->quantidade, $produtor ->valor_compra,'PDV','Obs. '. 'N: '.'-'.  'Mov: '.  $resultitem->venda_caixa_id, $resultitem->id);

        
            
            }
          }

        }else{
         

          $stockMove->downStock((int) $i['id'],(float) str_replace(",", ".", $i['quantidade']), $prod->valor_compra,'PDV','Obs. '. 'N: '.'-'.  'Mov: '.  $resultitem->venda_caixa_id, $resultitem->id);

        }
      }

    }

        //DELIVERY
    if(isset($venda['delivery_id']) && $venda['delivery_id'] > 0){
      $pedidoDelivery = PedidoDelivery
      ::where('id', $venda['delivery_id'])
      ->first();

      foreach($pedidoDelivery->itens as $i){

        if(count($i->sabores) > 0){

          $totalSabores = count($i->sabores);
          foreach($i->sabores as $sb){
            if(!empty($sb->produto->produto->receita)){
              $receita = $sb->produto->produto->receita;
              foreach($receita->itens as $rec){

                $stockMove->downStock(
                  $rec->produto_id, 
                  (float) str_replace(",", ".", $i['quantidade']) 
                      * 
                  ((($rec->quantidade/$totalSabores)/$receita->pedacos)*$i->tamanho->pedacos)/$receita->rendimento
                );
              }
            }
          }
        }else{

          if(!empty($i->produto->produto->receita)){
            $receita = $i->produto->produto->receita; 
            foreach($receita->itens as $rec){

              if(!empty($rec->produto->receita)){ 

                $receita2 = $rec->produto->receita; 

                foreach($receita2->itens as $rec2){
                  $stockMove->downStock(
                    $rec2->produto_id, 
                    (float) str_replace(",", ".", $i['quantidade']) * 
                    ($rec2->quantidade/$receita2->rendimento)
                  );
                }
              }else{


                $stockMove->downStock(
                  $rec->produto_id, 
                  (float) str_replace(",", ".", $i['quantidade']) * 
                  ($rec->quantidade/$receita->rendimento)
                );
              }
            }



          }else{

            $stockMove->downStock(
              $i->produto->produto->id, 
              (float) str_replace(",", ".", $i['quantidade'])
            );
          }
        }

      }
    }




    $usuario = Usuario::find(get_id_user());
    if(isset($usuario->funcionario)){
      $percentual_comissao = $usuario->funcionario->percentual_comissao;
      $valorComissao = ($totalVenda * $percentual_comissao) / 100;
      ComissaoVenda::create(
        [
          'funcionario_id' => $usuario->funcionario->id,
          'venda_id' => $result->id,
          'tabela' => 'venda_caixas',
          'valor' => $valorComissao,
          'status' => 0
        ]
      );
    }

    if($agendamento_id > 0){

      $agendamento = Agendamento::find($agendamento_id);
      $valorComissao = $this->calculaComissao($agendamento);
      $agendamento->valor_comissao = $valorComissao;
      $agendamento->status = 1;
      $agendamento->save();
    }

    $result->custo_total = $totalcusto;
    $result->txcartao    = $vtxcartao + $vtxcartao1 + $vtxcartao2 + $vtxcartao3;
    
    $result->save();
    DB::commit();
    echo json_encode($result);
  }
  
  public function gravatipopagamento_1($result){

    $vtxcartao1 = 0;
    if ($result->tipo_pagamento_1=='01' || $result->tipo_pagamento_1=='04'){
        
      $valorParcela = $result->valor_pagamento_1;

      $vencimento   =  date('Y/m/d');
      $vencimento   =   $this->parseDate($vencimento);
      $recebimento  =   $vencimento ;
      $status       = True;
     
      $valorrecebido =   $valorParcela;
      ContaReceber::create([
        'venda_caixa_id' => $result->id,
     
     
     
       'data_vencimento'  => $vencimento ,
       'data_recebimento' => $recebimento ,
     
       'forma_recebimento'  =>  $result->tipo_pagamento_1,
     
       'valor_integral' => $valorParcela,
       'valor_recebido' => $valorrecebido,
       'status' => $status ,
       'referencia' => "Venda: " . $result->id,
       'categoria_id' => 2,
       'usuario_id' => get_id_user()   
      ]);
    }

    if ($result->tipo_pagamento_1=='02' || $result->tipo_pagamento_1=='03'){
      $status = true;
      $datafaturamento =  date('Y/m/d');
      if ($result->tipo_pagamento_1=='02'){
        $tipocartao = 'Debito';
        $vencimento   = date('Y/m/d', strtotime('+ 1 days',strtotime($datafaturamento)));
      }
      else{
       $tipocartao = 'Credito';
       $vencimento   = date('Y/m/d', strtotime('+ 2 days',strtotime($datafaturamento)));
 
      }
       $infcartao =  ConfigCartao::where('cartao', $tipocartao)
       ->where('bandeira',$result->bandeira_cartao_1)
       ->first();

       if ($infcartao){
         $taxa = $infcartao->taxa;
       }
       else{
         $taxa = 0;
       }

       $valorParcela = $result->valor_pagamento_1;
       $vtxcartao1 = ($valorParcela  * $taxa)/100;
       $valorrecebido = ($valorParcela -$vtxcartao1);
                     
       $vencimento  = $this->verificadiautil($this->parseDate($vencimento));
       $recebimento = $this->verificadiautil($this->parseDate($vencimento));
        
          

       ContaReceber::create([
           'venda_caixa_id' => $result->id,
        
        
        
           'data_vencimento'  => $vencimento ,
           'data_recebimento' => $recebimento ,
        
           'forma_recebimento'  =>  $result->tipo_pagamento_1,
        
           'valor_integral' => $valorParcela,
           'valor_recebido' => $valorrecebido,
           'status' => $status ,
          'referencia' => "Venda: " . $result->id,
          'categoria_id' => 2,
          'usuario_id' => get_id_user()   
         ]);
    
    }
    
    if ($result->tipo_pagamento_1=='05' || $result->tipo_pagamento_1=='06'){
        
          $valorParcela = $result->valor_pagamento_1;

        
          $vencimento   =  date('Y/m/d');
    
          $valorrecebido = 0;     
                
                  
          $vencimento  = $this->verificadiautil($this->parseDate($vencimento));
          $recebimento = $vencimento;
          $status = false;

          
          ContaReceber::create([
          'venda_caixa_id' => $result->id,
     
     
     
          'data_vencimento'  => $vencimento ,
          'data_recebimento' => $recebimento ,
     
          'forma_recebimento'  =>  $result->tipo_pagamento_1,
     
          'valor_integral' => $valorParcela,
          'valor_recebido' => $valorrecebido,
           'status' => $status ,
          'referencia' => "Venda: " . $result->id,
           'categoria_id' => 2,
           'usuario_id' => get_id_user()   
         ]);

        
    }

    
  return $vtxcartao1;
  }
  
    




  
 
  public function gravatipopagamento_2($result){

  
    $vtxcartao2 = 0;
    if ($result->tipo_pagamento_2=='01' || $result->tipo_pagamento_2=='04'){
        
      $valorParcela = $result->valor_pagamento_2;

      $vencimento   =  date('Y/m/d');
      $vencimento   =   $this->parseDate($vencimento);
      $recebimento  =   $vencimento ;
      $status       = True;
     
      $valorrecebido =   $valorParcela;
      ContaReceber::create([
        'venda_caixa_id' => $result->id,
     
     
     
       'data_vencimento'  => $vencimento ,
       'data_recebimento' => $recebimento ,
     
       'forma_recebimento'  =>  $result->tipo_pagamento_2,
     
       'valor_integral' => $valorParcela,
       'valor_recebido' => $valorrecebido,
       'status' => $status ,
       'referencia' => "Venda: " . $result->id,
       'categoria_id' => 2,
       'usuario_id' => get_id_user()   
      ]);
    }

    if ($result->tipo_pagamento_2=='02' || $result->tipo_pagamento_2=='03'){
      $status = true;
      $datafaturamento =  date('Y/m/d');
      if ($result->tipo_pagamento_2=='02'){
        $tipocartao = 'Debito';
        $vencimento   = date('Y/m/d', strtotime('+ 1 days',strtotime($datafaturamento)));
      }
      else{
       $tipocartao = 'Credito';
       $vencimento   = date('Y/m/d', strtotime('+ 2 days',strtotime($datafaturamento)));
 
       }
       $infcartao =  ConfigCartao::where('cartao', $tipocartao)
       ->where('bandeira',$result->bandeira_cartao_2)
       ->first();

       if ($infcartao){
         $taxa = $infcartao->taxa;
       }
       else{
         $taxa = 0;
       }

       $valorParcela = $result->valor_pagamento_2;
       $vtxcartao2 = ($valorParcela  * $taxa)/100;
       $valorrecebido = ($valorParcela -$vtxcartao2);
                     
       $vencimento  = $this->verificadiautil($this->parseDate($vencimento));
       $recebimento = $this->verificadiautil($this->parseDate($vencimento));
        
          

       ContaReceber::create([
           'venda_caixa_id' => $result->id,
        
        
        
           'data_vencimento'  => $vencimento ,
           'data_recebimento' => $recebimento ,
        
           'forma_recebimento'  =>  $result->tipo_pagamento_2,
        
           'valor_integral' => $valorParcela,
           'valor_recebido' => $valorrecebido,
           'status' => $status ,
          'referencia' => "Venda: " . $result->id,
          'categoria_id' => 2,
          'usuario_id' => get_id_user()   
         ]);
    }

    if ($result->tipo_pagamento_2=='05' || $result->tipo_pagamento_2=='06'){
        
      $valorParcela = $result->valor_pagamento_2;

    
      $vencimento   =  date('Y/m/d');

      $valorrecebido = 0;     
            
              
      $vencimento  = $this->verificadiautil($this->parseDate($vencimento));
      $recebimento = $vencimento;
      $status = false;

      
      ContaReceber::create([
      'venda_caixa_id' => $result->id,
 
 
 
      'data_vencimento'  => $vencimento ,
      'data_recebimento' => $recebimento ,
 
      'forma_recebimento'  =>  $result->tipo_pagamento_2,
 
      'valor_integral' => $valorParcela,
      'valor_recebido' => $valorrecebido,
       'status' => $status ,
      'referencia' => "Venda: " . $result->id,
       'categoria_id' => 2,
       'usuario_id' => get_id_user()   
     ]);

    
    }
  
  return  $vtxcartao2;
  }


  public function gravatipopagamento_3($result){
    
    $vtxcartao3 = 0;
    if ($result->tipo_pagamento_3=='01' || $result->tipo_pagamento_3=='04'){
        
      $valorParcela = $result->valor_pagamento_3;

      $vencimento   =  date('Y/m/d');
      $vencimento   =   $this->parseDate($vencimento);
      $recebimento  =   $vencimento ;
      $status       = True;
     
      $valorrecebido =   $valorParcela;
      ContaReceber::create([
        'venda_caixa_id' => $result->id,
     
     
     
       'data_vencimento'  => $vencimento ,
       'data_recebimento' => $recebimento ,
     
       'forma_recebimento'  =>  $result->tipo_pagamento_3,
     
       'valor_integral' => $valorParcela,
       'valor_recebido' => $valorrecebido,
       'status' => $status ,
       'referencia' => "Venda: " . $result->id,
       'categoria_id' => 2,
       'usuario_id' => get_id_user()   
      ]);
    }
    if ($result->tipo_pagamento_3=='02' || $result->tipo_pagamento_3=='03'){
      $status = true;
      $datafaturamento =  date('Y/m/d');
      if ($result->tipo_pagamento_3=='02'){
        $tipocartao = 'Debito';
        $vencimento   = date('Y/m/d', strtotime('+ 1 days',strtotime($datafaturamento)));
      }
      else{
       $tipocartao = 'Credito';
       $vencimento   = date('Y/m/d', strtotime('+ 2 days',strtotime($datafaturamento)));
 
       }
       $infcartao =  ConfigCartao::where('cartao', $tipocartao)
       ->where('bandeira',$result->bandeira_cartao_3)
       ->first();

       if ($infcartao){
         $taxa = $infcartao->taxa;
       }
       else{
         $taxa = 0;
       }

       $valorParcela = $result->valor_pagamento_3;
       $vtxcartao3 = ($valorParcela  * $taxa)/100;
       $valorrecebido = ($valorParcela -$vtxcartao3);
                     
       $vencimento  = $this->verificadiautil($this->parseDate($vencimento));
       $recebimento = $this->verificadiautil($this->parseDate($vencimento));
        
          

       ContaReceber::create([
           'venda_caixa_id' => $result->id,
        
        
        
           'data_vencimento'  => $vencimento ,
           'data_recebimento' => $recebimento ,
        
           'forma_recebimento'  =>  $result->tipo_pagamento_3,
        
           'valor_integral' => $valorParcela,
           'valor_recebido' => $valorrecebido,
           'status' => $status ,
          'referencia' => "Venda: " . $result->id,
          'categoria_id' => 2,
          'usuario_id' => get_id_user()   
         ]);
    }
    if ($result->tipo_pagamento_3=='05' || $result->tipo_pagamento_3=='06'){
        
      $valorParcela = $result->valor_pagamento_3;

    
      $vencimento   =  date('Y/m/d');

      $valorrecebido = 0;     
            
              
      $vencimento  = $this->verificadiautil($this->parseDate($vencimento));
      $recebimento = $vencimento;
      $status = false;

      
      ContaReceber::create([
      'venda_caixa_id' => $result->id,
 
 
 
      'data_vencimento'  => $vencimento ,
      'data_recebimento' => $recebimento ,
 
      'forma_recebimento'  =>  $result->tipo_pagamento_,
 
      'valor_integral' => $valorParcela,
      'valor_recebido' => $valorrecebido,
       'status' => $status ,
      'referencia' => "Venda: " . $result->id,
       'categoria_id' => 2,
       'usuario_id' => get_id_user()   
     ]);

    
    }
return  $vtxcartao3;
}





  private function calculaComissao($agendamento){
    $soma = 0;
    $somaDesconto = 0;
    $total = $agendamento->total + $agendamento->desconto;
    foreach($agendamento->itens as $key => $i){
      $tempDesc = 0;
      $valorServico = $i->servico->valor;
      
      if($key < sizeof($agendamento->itens)-1){

        $media = (((($valorServico - $total)/$total))*100);
        
        $media = 100 - ($media * -1);
        $tempDesc = ($agendamento->desconto*$media)/100;

        $somaDesconto += $tempDesc;

      }else{
        $tempDesc = $agendamento->desconto - $somaDesconto;
      }

      $comissao = $i->servico->comissao;

      $valorComissao = ($valorServico - $tempDesc) * ($comissao/100);
      $soma += $valorComissao;
    }

    return number_format($soma,2);
  }

  public function diaria(){
    $ab = AberturaCaixa::where('ultima_venda', 0)->orderBy('id', 'desc')->first();

    date_default_timezone_set('America/Sao_Paulo');
    $hoje = date("Y-m-d") . " 00:00:00";
    $amanha = date('Y-m-d', strtotime('+1 days')). " 00:00:00";
    $vendas = VendaCaixa::
    whereBetween('data_registro', [$ab->data_registro, 
     $amanha])
     ->where('ativo',true)
     ->get();

    foreach($vendas as $v){
      $v->tp_pag = VendaCaixa::getTipoPagamento($v->tipo_pagamento);
    }
    echo json_encode($vendas);
  }

  public  function infcartao($cartao,$bandeira){
		return ConfigCartao::
		where('cartao', $cartao)
		->where('bandeira', $bandeira)
		->get();
	}


  private function parseDate($date, $plusDay = false){
		if($plusDay == false)
			return date('Y-m-d', strtotime(str_replace("/", "-", $date)));
		else
			return date('Y-m-d', strtotime("+1 day",strtotime(str_replace("/", "-", $date))));
	}


  public function verificadiautil($data){

    
		$diaSemana = getdate(strtotime($data));
		
		$diaSemana  = $diaSemana["wday"];
		
		if ($diaSemana==6){
		
		$data = date('Y/m/d', strtotime('+ 2 days',strtotime($data)));
	
		}else if( $diaSemana==0){
			$data = date('Y/m/d', strtotime('+ 1 days',strtotime($data)));
	
		}
	
		// verificar se é feriado bancário
	
		$search_array = array('2021-09-07','2021-10-12','2021-11-02','2021-11-15','2021-12-25');
	
		if (in_array($data, $search_array)) {
			$data = date('Y/m/d', strtotime('+ 1 days',strtotime($data)));
		 }
		 if (in_array($data, $search_array)) {
			$data = date('Y/m/d', strtotime('+ 1 days',strtotime($data)));
		 }
	
		 return $data;
	
	
	}


}
