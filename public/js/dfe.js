var array = [];

var codigo = "";
var nome = "";
var ncm = "";
var cfop = "";
var unidade = "";
var valor = "";
var valorCompra = "";
var quantidade = "";
var codBarras = "";
var nNf = 0;
var semRegitro;


$('#valor_margem').keyup(() => {
    let valorCompra = parseFloat($('#valor_compra').val().replace(',', '.'));
    let valorVenda = parseFloat($('#valor_venda').val().replace(',', '.'));
    let valorMargem = parseFloat($('#valor_margem').val().replace(',', '.'));
    
  
    if(valorCompra > 0 && valorMargem > 0){
      let vvalormargem =    (valorCompra * valorMargem) /100;
      let vvenda = ( vvalormargem + valorCompra) ;
      vvenda = formatReal(vvenda);
      vvenda = vvenda.replace('.','')
      vvenda = vvenda.substring(3, vvenda.length)
  
      $('#valor_venda').val(vvenda)
    }else{
      $('#valor_venda').val('0')
    }
  })
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
 

  function formatReal(v){
    return v.toLocaleString('pt-br', {style: 'currency', currency: 'BRL', minimumFractionDigits: 2});
  }

$(function() {

    let uri = window.location.pathname;
    if (uri.split('/')[2] == 'novaConsulta') {
        filtrar();
    } else {
        array = JSON.parse($('#docs').val());
    }
});

$('#tipo_evento').change(() => {
    let tipo = $('#tipo_evento').val();
    if (tipo == 3 || tipo == 4) {
        $('#div-just').css('display', 'block')
    } else {
        $('#div-just').css('display', 'none')
    }
})

function filtrar() {
    $.get(path + 'dfe/getDocumentosNovos')
        .done(value => {
            console.log(value)
            $('#preloader1').css('display', 'none')
            $('#aguarde').css('display', 'none')

            if (value.length > 0) {
                montaTabela(value, (html) => {
                    console.log(html)
                    $('table tbody').html(html)
                    $('#table').css('display', 'block')
                })
                swal("Sucesso", "Foram encontrados " + value.length + " novos registros!", "success")
            } else {
                swal("Sucesso", "A requisição obteve sucesso, porém sem novos registros!!", "success")
                $('#sem-resultado').css('display', 'block')

            }

        })
        .fail(err => {
            console.log(err)
            $('#preloader1').css('display', 'none')
            $('#aguarde').css('display', 'none')
            swal("Erro", "Erro ao realizar consulta", "warning")
        })
}

function montaTabela(array, call) {
    let html = '';
    array.map(v => {
        console.log(v)
        html += '<tr class="datatable-row">';
        html += '<td class="datatable-cell"><span class="codigo" style="width: 300px;" id="id">' +
            v.nome[0] + '</span></td>'
        html += '<td class="datatable-cell"><span class="codigo" style="width: 100px;" id="id">' +
            v.documento[0] + '</span></td>'
        html += '<td class="datatable-cell"><span class="codigo" style="width: 100px;" id="id">' +
            v.valor[0] + '</span></td>'
        html += '<td class="datatable-cell"><span class="codigo" style="width: 200px;" id="id">' +
            v.chave[0] + '</span></td>'
        html += '</tr>';
    })

    call(html)
}

function setarEvento(chave) {
    console.log(array)
    array.map((element) => {
        if (element.chave == chave) {
            console.log(element)
            $('#nome').val(element.nome)
            $('#cnpj').val(element.documento)
            $('#valor').val(element.valor)
            $('#data_emissao').val(element.data_emissao)
            $('#num_prot').val(element.num_prot)
            $('#chave').val(element.chave)
        }

    })

}

function _construct(codigo, nome, codBarras, ncm, cfop, unidade, valor, quantidade, valorCompra, nNf) {
    this.codigo = codigo;
    this.nome = nome;
    this.ncm = ncm;
    this.cfop = cfop;
    this.unidade = unidade;
    this.valor = valor;
    this.valorCompra = valorCompra;
    this.quantidade = quantidade;
    this.nNf = nNf;
    this.codBarras = codBarras.substring(0, 13);

}

function cadProd(codigo, nome, codBarras, ncm, cfop, unidade, valor, quantidade, valorCompra, nNf,cst,cstpadrao,cest,regime,cstpis,cstcofins) {
    _construct(codigo, nome, codBarras, ncm, cfop, unidade, valor, quantidade, valorCompra, nNf,cst,cstpadrao,cest,regime,cstpis,cstcofins);

    $('#nome').val(nome);
    $("#nome").focus();
 
    
    $('#ncm').val(ncm);
    $('#cest').val(cest);
    
    $("#ncm").trigger("click");

    $('#cfop').val(cfop);
    console.log(unidade)

    $('#un_compra').val(unidade);
    $('#unidade_venda option[value="' + unidade + '"]').prop("selected", true);

    $('#valor_compra').val(valor);
    
    
    
    if (regime != 1) {

        if ((cst=='10') || (cst=='60')) {
            $('#CST_CSOSN').val(500);
        }
        else if (cst=='00') {
            
            $('#CST_CSOSN').val('102');
        }else{

            $('#CST_CSOSN').val(cstpadrao);

        }

       
        


    }
    else{

        if ((cst=='10') || (cst=='60')) {
            $('#CST_CSOSN').val('60');
        }
        else if (cst=='00') {
            
            $('#CST_CSOSN').val('00');
        }else{

            $('#CST_CSOSN').val(cstpadrao);

        }

        if (cstpis != ''){
        
            $('#CST_PIS').val(cstpis);
        
        }else {
            $('#CST_PIS').val('01');
        
        }
   
        
        if (cstcofins != ''){
        
            $('#CST_COFINS').val(cstcofins);
        
        }
        else {
            $('#CST_COFINS').val('01');
            
        }
        

        
    }

//        if ((cst=='101') || (cst=='102')) {
//          $('#CST_CSOSN').val(cst);
//    }
  //  else if (cst=='500') {
       
     //   $('#CST_CSOSN').val(cst);
    //}

    

    $('#quantidade').val(quantidade);
    $('#conv_estoque').val('1');
    $('#valor_venda').val('0');
    $("#quantidade").trigger("click");
    $('#modal1').modal('show');

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

    })

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

function editProd(id,valor,margemcusto) {
    _construct(id, valor,margemcusto);
    let produtoId = id
    $('#idEdit').val(id)
    $.ajax({
        type: 'GET',
        url: path + 'produtos/getProdutoTabela/' + produtoId,
        dataType: 'json',
        success: function(e) {
            console.log(e)
            $("#nomeEdit").val(e.nome)
            
            $("#CodigoEdit").val(produtoId)
            $("#conv_estoqueEdit").val(e.conversao_unitaria)
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

function setEstoque(codigo, nome, quantidade) {

    swal("Alerta", "Deseja atribuir estoque a este produto? " + nome, "warning")
        .then(sim => {
            if (sim) {
                let js = {
                    nome: nome,
                    quantidade: quantidade
                }

                $.ajax({
                    type: 'POST',
                    data: {
                        produto: prod,
                        _token: token
                    },
                    url: path + 'produtos/salvarProdutoDaNota',
                    dataType: 'json',
                    success: function(e) {
                        console.log(e)

                        swal("Sucesso", "Produto inserido o estoque quantidade: " + quantidade, "success")
                            .then(sim => {
                                location.reload();
                            });

                    },
                    error: function(e) {
                        console.log(e)
                        swal("Erro", "Erro ao importar estoque do produto")
                    }
                });

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

$('#salvar').click(() => {
    $('#preloader').css('display', 'block');
   // $("#th_" + this.codigo).removeClass("red-text");
    //$("#th_" + this.codigo).html($('#nome').val());
    let valorVenda = $('#valor_venda').val();
    let valorCompra = $('#valor_compra').val();
    let unidadeVenda = $('#unidade_venda').val();
    let conversaoEstoque = $('#conv_estoque').val();
    let categoria_id = $('#categoria_id').val();
    let cor = '--';

    let CST_CSOSN = $('#CST_CSOSN').val();
    let CST_PIS = $('#CST_PIS').val();
    let CST_COFINS = $('#CST_COFINS').val();
    let CST_IPI = $('#CST_IPI').val();
    let cfop = $('#cfop').val();
    let cest = $('#cest').val();

    let prod = {
        valorVenda: valorVenda,
        valorCompra: valorCompra,
        unidadeVenda: unidadeVenda,
        conversao_unitaria: conversaoEstoque,
        categoria_id: categoria_id,
        cor: cor,
        nome: $('#nome').val(),
        ncm: this.ncm,
        cest: cest,
        cfop: cfop,
        unidadeCompra: this.unidade,
        valor: this.valor,
        quantidade: this.quantidade,
        codBarras: this.codBarras,
        numero_nfe: this.nNf,
        CST_CSOSN: CST_CSOSN,
        CST_PIS: CST_PIS,
        CST_COFINS: CST_COFINS,
        CST_IPI: CST_IPI,
        referencia: this.codigo,

    }
    console.log(prod.quantidade)

    console.log(prod)

    console.log(this.semRegitro)

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
         //    $("#th_prod_id_" + codigo).html(e.id);
           //  $("#th_acao1_" + codigo).css('display', 'none');
            // $("#th_acao2_" + codigo).css('display', 'block');
             //$("#th_estoque_" + codigo).addClass('disabled');

            $('#preloader').css('display', 'none');
            $('#modal1').modal('hide');
            // alert("Produto Saldo, e inserido o estoque quantidade: " + prod.quantidade)
            swal("Sucesso", 'Produto Salvo', "success")
                .then(sim => {
                    location.reload();

                });

        },
        error: function(e) {
            console.log(e)
            $('#preloader').css('display', 'none');
        }
    });
})

function salvarEstoque(id, valor, quantidade, numero_nfe) {
    swal("Alerta", "Deseja atribuir estoque a este produto?", "warning")
        .then(sim => {
            if (sim) {
                let token = $('#_token').val();
                $.ajax({
                    type: 'POST',
                    data: {
                        produto: id,
                        quantidade: quantidade,
                        valor: valor,
                        numero_nfe: numero_nfe,
                        _token: token
                    },
                    url: path + 'produtos/setEstoque',
                    dataType: 'json',
                    success: function(e) {
                        $("#th_estoque_" + id).addClass('disabled');

                        swal("Sucesso", "Inserido o estoque quantidade: " + quantidade, "success")
                            .then(() => {
                                location.reload()
                            })


                    },
                    error: function(e) {
                        console.log(e)
                        $('#preloader').css('display', 'none');
                    }
                });
            }
        })
}


$('#salvar-compra').click(() => {
    let fornecedor = JSON.parse($('#fornecedor').val())
    let itens = JSON.parse($('#itens').val())
    let fatura = JSON.parse($('#fatura').val())

    let infos = JSON.parse($('#infos').val())

    let nf = JSON.parse($('#nf').val())

    let dfe_id = $('#dfe_id').val()


   

    let token = $('#_token').val();

    

    $.post(path + 'dfe/salvarCompra', {
        fornecedor: fornecedor,
        itens: itens,
        fatura: fatura,
        dfe_id: dfe_id,
        nf: nf,
     
        _token: token
    }).done((success) => {
        console.log(success)
        sucesso()

    }).fail((err) => {
        console.log(err)
        swal("Erro", err.responseText, "error")
    })

})

function sucesso() {
    console.log("sucesso")
    $('#content').css('display', 'none');
    $('#anime').css('display', 'block');
    setTimeout(() => {
        location.href = path + 'dfe';
    }, 4000)
}
