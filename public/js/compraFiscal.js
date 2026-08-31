var codigo = "";
var nome = "";
var ncm = "";
var cfop = "";
var unidade = "";
var valor = "";
var quantidade = "";
var codBarras = "";
var cfopEntrda = "";

var semRegitro;
$(function() {
    semRegitro = $('#prodSemRegistro').val();
    if (semRegitro == 0) {
        $('#salvarNF').removeClass("disabled");
        $('.sem-registro').css('display', 'none');
    }
    verificaProdutoSemRegistro();
});

function verificaProdutoSemRegistro() {
    if (semRegitro == 0) {
        $('#salvarNF').removeClass("disabled");
        $('.sem-registro').css('display', 'none');
    } else {
        $('.prodSemRegistro').html(semRegitro);
    }
}

$('#valor_margemcustoEdit').keyup(() => {
    let valorCompra = parseFloat($('#valor_compraEdit').val().replace(',', '.'));
   // let valorVenda = parseFloat($('#valor_venda').val().replace(',', '.'));
    let valorMargem = parseFloat($('#valor_margemcustoEdit').val().replace(',', '.'));
    
  
    if(valorCompra > 0 && valorMargem > 0){
      let vvalormargem =    (valorCompra * valorMargem) /100;
      let vvenda = ( vvalormargem + valorCompra) ;
      vvenda = formatReal(vvenda);
      vvenda = vvenda.replace('.','')
      vvenda = vvenda.substring(3, vvenda.length)
  
      $('#valor_vendaEdit').val(vvenda)
    }else{
      $('#valor_vendaEdit   ').val('0')
    }
  })

  function formatReal(v){
    return v.toLocaleString('pt-br', {style: 'currency', currency: 'BRL', minimumFractionDigits: 2});
  }


function _construct(codigo, nome, codBarras, ncm, cfop, unidade, valor, quantidade, cfop_entrada) {

    this.codigo = codigo;
    this.nome = nome;
    this.ncm = ncm;
    this.cfop = cfop;
    this.unidade = unidade;
    this.valor = valor;
    this.quantidade = quantidade;
    this.codBarras = codBarras;
    this.cfopEntrda = cfop_entrada;
}

function cadProd(codigo, nome, codBarras, ncm, cfop, unidade, valor, quantidade, cfop_entrada) {

    _construct(codigo, nome, codBarras, ncm, cfop, unidade, valor, quantidade, cfop_entrada);
    $('#nome').val(nome);
    $("#nome").focus();

    getUnidadeMedida((data) => {
        let achouUnidade = false;
        data.map((v) => {
            if (v == unidade) {
                achouUnidade = true;
            }
        })

        if (!achouUnidade) {

            swal('', "Unidade de compra deste produto não corresponde a nenhuma pré-determinada\n" +
                    "Unidade: " + unidade, 'warning')
                .then(s => {


                    if (unidade == 'M3C') {
                        unidade = 'M3';
                        swal('', 'M3C alterado para ' + unidade, 'warning')

                    } else if (unidade == 'M2C') {
                        unidade = 'M2';
                        swal('', 'M2C alterado para ' + unidade, 'warning')

                    } else if (unidade == 'MC') {
                        unidade = 'M';
                        swal('', 'MC alterado para ' + unidade, 'warning')
                    } else if (unidade == 'UN') {
                        unidade = 'UNID';
                        swal('', 'UN alterado para ' + unidade, 'warning')

                    } else {
                        unidade = 'UNID';
                        swal('', 'UN alterado para ' + unidade, 'warning')

                    }
                })

        }

        $('#ncm').val(ncm);
        $("#ncm").trigger("click");

        $('#cfop').val(cfop);
        console.log(unidade)

        $('#un_compra').val(unidade);
        $('#unidade_venda option[value="' + unidade + '"]').prop("selected", true);

        $('#valor').val(valor);

        $('#quantidade').val(quantidade);
        $('#conv_estoque').val('1');
        $('#valor_venda').val('0');
        $('#cfop_entrada').val(cfop_entrada);
        $("#quantidade").trigger("click");

        $('#modal1').modal('toggle');

    })

}

function deleteProd(item) {
    if (confirm('Deseja excluir este item, se confirmar sua NF ficará informal?')) {
        var tr = $(item).closest('tr');
        console.log(tr)
        tr.fadeOut(500, function() {
            tr.remove();
            verificaTabelaVazia();
            verificacemRegistro();
        });

        return false;
    }
}

function editProd(id,valor,margemcusto) {
    _construct(id, valor,margemcusto);
    let produtoId = $('#th_prod_id_' + id).html();
    $('#idEdit').val(id)
    $.ajax({
        type: 'GET',
        url: path + 'produtos/getProdutoTabela/' + produtoId,
        dataType: 'json',
        success: function(e) {
            console.log(e)
            $("#nomeEdit").val(e.nome)
            
            $("#CodigoEdit").val(produtoId)
           // $("#conv_estoqueEdit").val(e.conversao_unitaria)
            $("#valor_compraEdit").val(valor)

            $("#valor_margemcustoEdit").val(margemcusto)
            
            
            $("#valor_vendaEdit").val(e.valor_venda)
            
            $('#modal2').modal('show');
        },
        error: function(e) {
            console.log(e);
        }
    });
}

function verificaTabelaVazia() {
    if ($('table tbody tr').length == 0) {
        $('#salvarNF').addClass("disabled");
    }
}

$('#salvarEdit').click(() => {
   // let id = $('#idEdit').val();
  //  $('#th_' + id).html($('#nomeEdit').val());
   // $('#th_prod_conv_unit_' + id).html($('#conv_estoqueEdit').val());
   // $('#modal2').modal('hide');
   $('#preloader').css('display', 'block');
   $("#th_" + this.codigo).removeClass("red-text");
   $("#th_" + this.codigo).html($('#nome').val());
 //  let valorVenda = $('#valor_venda').val();
  // let valor_compra = $('#valor_compra').val();
//   let unidadeVenda = $('#unidade_venda').val();
   
   let produtoid = $('#CodigoEdit').val();
//
  // let conversaoEstoque = $('#conv_estoqueEdit').val();
   
   let valorVenda = $('#valor_vendaEdit').val();
   
  //let valorVenda = 10;

 //  let categoria_id = $('#categoria_id').val();
 //  let cor = $('#cor').val();
 //  let cfop = $('#cfop').val();

//   let CST_CSOSN = $('#CST_CSOSN').val();
 //  let CST_PIS = $('#CST_PIS').val();
 //  let CST_COFINS = $('#CST_COFINS').val();
 //  let CST_IPI = $('#CST_IPI').val();

   let prod = {
  //     valorVenda: valorVenda,
   //    unidadeVenda: unidadeVenda,
    //   conversao_unitaria: conversaoEstoque,
       produtoid: produtoid,
       valorVenda: valorVenda
   
       //   categoria_id: categoria_id,
    //   cor: cor,
     //  valorCompra: valor_compra,
     //  nome: $('#nome').val(),
     //  ncm: this.ncm,
     //  cfop: cfop,
    //   referencia: this.codigo,
     //  unidadeCompra: this.unidade,
     //  valor: this.valor,
     //  quantidade: this.quantidade,
      // codBarras: this.codBarras,
      // CST_CSOSN: CST_CSOSN,
      // CST_PIS: CST_PIS,
      // CST_COFINS: CST_COFINS,
      // CST_IPI: CST_IPI,
      // valorCompra: this.valor
   }
   console.log(prod)
  // semRegitro--;
  // verificaProdutoSemRegistro();
   //console.log(this.semRegitro)

   let token = $('#_token').val();

   $.ajax({
       type: 'POST',
       data: {
           produto: prod,
           _token: token
       },
       url: path + 'produtos/salvarEdicaoProduto',
       dataType: 'json',
       success: function(e) {
           let cfop_entrada = $('#cfop_entrada').val()
           $("#th_prod_id_" + codigo).html(e.id);
           $("#cfop_entrada_" + codigo).html(cfop_entrada);
           $("#th_acao1_" + codigo).css('display', 'none');
           $("#th_acao2_" + codigo).css('display', 'block');
           $("#n_" + codigo).removeClass('text-danger');
           $('#preloader').css('display', 'none');
           $('#modal2').modal('hide');
           console.log(e.id)
           console.log(e)
           location.reload();
           swal('Sucesso', 'Item salvo', 'success')
       },
       error: function(e) {
           console.log(e)
           $('#preloader').css('display', 'none');
       }
   });
})



$('#salvar').click(() => {
    $('#preloader').css('display', 'block');
    $("#th_" + this.codigo).removeClass("red-text");
    $("#th_" + this.codigo).html($('#nome').val());
    let valorVenda = $('#valor_venda').val();
    let valor_compra = $('#valor_compra').val();
    let unidadeVenda = $('#unidade_venda').val();
    let conversaoEstoque = $('#conv_estoque').val();
    let categoria_id = $('#categoria_id').val();
    let cor = $('#cor').val();
    let cfop = $('#cfop').val();

    let CST_CSOSN = $('#CST_CSOSN').val();
    let CST_PIS = $('#CST_PIS').val();
    let CST_COFINS = $('#CST_COFINS').val();
    let CST_IPI = $('#CST_IPI').val();

    let prod = {
        valorVenda: valorVenda,
        unidadeVenda: unidadeVenda,
        conversao_unitaria: conversaoEstoque,
        categoria_id: categoria_id,
        cor: cor,
        valorCompra: valor_compra,
        nome: $('#nome').val(),
        ncm: this.ncm,
        cfop: cfop,
        referencia: this.codigo,
        unidadeCompra: this.unidade,
        valor: this.valor,
        quantidade: this.quantidade,
        codBarras: this.codBarras,
        CST_CSOSN: CST_CSOSN,
        CST_PIS: CST_PIS,
        CST_COFINS: CST_COFINS,
        CST_IPI: CST_IPI,
        valorCompra: this.valor
    }
    console.log(prod)
    semRegitro--;
    verificaProdutoSemRegistro();
    //console.log(this.semRegitro)

    let token = $('#_token').val();

    $.ajax({
        type: 'POST',
        data: {
            produto: prod,
            _token: token
        },
        url: path + 'produtos/salvarProdutoDaNota',
        dataType: 'json',
        success: function(e) {
            let cfop_entrada = $('#cfop_entrada').val()
            $("#th_prod_id_" + codigo).html(e.id);
            $("#cfop_entrada_" + codigo).html(cfop_entrada);
            $("#th_acao1_" + codigo).css('display', 'none');
            $("#th_acao2_" + codigo).css('display', 'block');
            $("#n_" + codigo).removeClass('text-danger');
            $('#preloader').css('display', 'none');
            $('#modal1').modal('hide');
            console.log(e.id)
            console.log(e)
            location.reload();
            swal('Sucesso', 'Item salvo', 'success')
        },
        error: function(e) {
            console.log(e)
            $('#preloader').css('display', 'none');
        }
    });
})




$('#salvarNF').click(() => {
    $('#preloader2').css('display', 'block');

    salvarNF((data) => {
        if (data.id) {
            salvarItens(data.id, (v) => { //data.id codigo da compra

                if (v) {
                    salvarFatura(data.id, (f) => {
                        $('#modal1').modal('hide');
                        $('#preloader2').css('display', 'none');
                        sucesso();

                    })
                }
            })
        }
    })
})

function salvarFatura(compra_id, call) {
    let fatura = $('#fatura').val();
    fatura = JSON.parse(fatura);
    retorno = [];
    let token = $('#_token').val();
    let cont = 0;

    if (fatura.length > 0) {
        fatura.map((item) => {
            cont++;
            item.numero = item.numero[0];
            item.referencia = "Parcela " + cont + ", da NF " + $('#nNf').val();
            item.compra_id = compra_id;

            console.log(item)
            $.ajax({
                type: 'POST',
                data: {
                    parcela: item,
                    _token: token
                },
                url: path + 'contasPagar/salvarParcela',
                dataType: 'json',
                success: function(e) {
                    console.log(e)
                    call(e)

                },
                error: function(e) {
                    console.log(e)
                    $('#preloader2').css('display', 'none');
                }

            });
        })
    } else {
        sucesso();
        $('#preloader2').css('display', 'none');
    }
}


function sucesso() {
    console.log("sucesso")
    $('#content').css('display', 'none');
    $('#anime').css('display', 'block');
    setTimeout(() => {
        location.href = path + 'compras';
    }, 4000)
}

function salvarNF(call) {

    let js = {
        fornecedor_id: $('#idFornecedor').val(),
        nNf: $('#nNf').val(),
        valor_nf: $('#valorDaNF').html(),
        observacao: '*',
        desconto: $('#vDesc').val(),
        xml_path: $('#pathXml').val(),
        chave: $('#chave').val(),
    }
    let token = $('#_token').val();

    $.ajax({
        type: 'POST',
        data: {
            nf: js,
            _token: token
        },
        url: path + 'compraFiscal/salvarNfFiscal',
        dataType: 'json',
        success: function(e) {
            call(e)

        },
        error: function(e) {
            console.log(e)
            $('#preloader2').css('display', 'none');
        }

    });
}

function getUnidadeMedida(call) {
    $.ajax({
        type: 'GET',
        url: path + 'produtos/getUnidadesMedida',
        dataType: 'json',
        success: function(e) {
            console.log(e)
            call(e)

        },
        error: function(e) {
            console.log(e)
        }

    });
}

function salvarItens(id, call) {

    let token = $('#_token').val();
    $('table tbody tr').each(function() {
        let js = {
            cod_barras: $(this).find('.codBarras').html(),
            nome: $(this).find('.nome').html(),
            produto_id: parseInt($(this).find('.cod').html()),
            compra_id: id,
            unidade: $(this).find('.unidade').html(),
            quantidade: $(this).find('.quantidade').html(),
            valor: $(this).find('.valor').html(),
            cfop_entrada: $(this).find('#cfop_entrada_input').val(),
            said: $(this).find('#codigo_siad_input').val(),
        }

        console.log(js)
        $.ajax({
            type: 'POST',
            data: {
                produto: js,
                _token: token
            },
            url: path + 'compraFiscal/salvarItem',
            dataType: 'json',
            success: function(e) {

            },
            error: function(e) {
                console.log(e)
                $('#preloader2').css('display', 'none');
            }

        });
    });
    call(true)

}

$('#salvar-compra').click(() => {

    let js = {
		fornecedor_id: $('#idFornecedor').val(),
		nNf: $('#nNf').val(),
		valor_nf: $('#valorDaNF').html(),
		observacao: '*',
		desconto: $('#vDesc').val(),
		xml_path: $('#pathXml').val(),
		chave: $('#chave').val(),
	}
	let token = $('#_token').val();
    let itens = JSON.parse($('#itens').val())
    
    $.post(path +  'compraFiscal/salvarCompra', 
	{

		itens: itens,
	    nf: js,
		
		_token: token
	}
	).done((success) => {
		console.log(success)
		sucesso()
	}).fail((err) => {
		console.log(err)
		swal("Erro", err.responseText, "error")
	})

})

function sucesso(){
	console.log("sucesso")
	$('#content').css('display', 'none');
	$('#anime').css('display', 'block');
	setTimeout(() => {
		location.href = path+'dfe';
	}, 4000)
}
