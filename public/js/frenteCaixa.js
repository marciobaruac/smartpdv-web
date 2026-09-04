



var TOTAL = 0;
var ITENS = [];
var caixaAberto = false;
var PRODUTO = null;
var CLIENTE = null;
var TOTALEMABERTOCLIENTE = null;
var COMANDA = 0;
var VALORBAIRRO = 0;
var VALORACRESCIMO = 0;
var OBSERVACAO = "";
var OBSERVACAOITEM = "";
var DESCONTO = 0;
var LISTAID = 0;
var PDV_VALOR_RECEBIDO = 0;

var VALORPAG1 = 0
var VALORPAG2 = 0
var VALORPAG3 = 0
var TIPOPAG1 = ''
var TIPOPAG2 = ''
var TIPOPAG3 = ''
var PRODUTOS = [];
var CATEGORIAS = [];
var CLIENTES = [];
var ID_COD_BARRAS_BALANCA = 0;
var IMPRIMIR_CUPOM_AUTOMATICO = 0;
var ORCAMENTOID = 0;
var ATALHOS = null;



window.onload = function () {


    const selectCliente = document.getElementById('kt_select2_1');
    if (selectCliente) {
        selectCliente.focus();
        $('#kt_select2_1').select2('open');
    }

    $('#valor_recebido').attr('disabled', 'true');
    $('#bandeira_cartao').attr('disabled', 'true');
}

function alterarSenha() {
    let token = $('#_token').val();

    $.ajax({
        type: 'POST',
        url: path + 'login/alterarsenha',
        dataType: 'json',
        data: {
            novasenha: $('#novasenha').val(),

            _token: token
        },
        success: function (e) {

            caixaAberto = true;
            $('#modalsenha').modal('hide');
            $('#novasenha').val('');

            swal("Sucesso", "Senha Alterada com Sucesso!", "success")

        },
        error: function (e) {
            console.log(e)
            swal("Erro", "Erro ao Alterar a Senha!", "error")

        }

    });
}

$('#modalsenha').on('shown.bs.modal', function () {
    $('#novasenha').focus();
})

$(function () {
    try {
        ATALHOS = JSON.parse($('#ATALHOS').val())
    } catch { }


    montaAtalhos()
    ID_COD_BARRAS_BALANCA = $('#ID_COD_BARRAS_BALANCA').val();
    IMPRIMIR_CUPOM_AUTOMATICO = $('#IMPRIMIR_CUPOM_AUTOMATICO').val() != null ? $('#IMPRIMIR_CUPOM_AUTOMATICO').val() : false;

    var w = window.innerWidth
    if (w < 900) {
        $('#grade').trigger('click')
    }

    novaHora();
    novaData();
    $('#codBarras').val('')
    try {
        PRODUTOS = JSON.parse($('#produtos').val())

        console.log(PRODUTOS)
        CATEGORIAS = JSON.parse($('#categorias').val())
        CLIENTES = JSON.parse($('#clientes').val())
    } catch {

    }
    let semCertificado = $('#semCertificado').val()
    if (semCertificado) {
        swal("Aviso", "Para habilitar o cupom fiscal, realize o upload do certificado digital!!", "warning")
    }

    PDV_VALOR_RECEBIDO = $('#PDV_VALOR_RECEBIDO').val()

    let valor_entrega = $('#valor_entrega').val();

    VALORACRESCIMO = parseFloat(valor_entrega);
    let obs = $('#obs').val();
    if (obs) OBSERVACAO = obs;

    verificaCaixa((v) => {
        console.log(v)
        caixaAberto = v >= 0 ? true : false;
        if (v < 0) {
            $('#modal1').modal('show');
        }
    })

    let itensPedido = $('#itens_pedido').val();

    //Verifica se os dados estao vindo da comanda
    //Controller Pedido
    if (itensPedido) {
        itensPedido = JSON.parse(itensPedido);

        if ($('#bairro').val() != 0) {
            console.log($('#bairro').val())
            let bairro = JSON.parse($('#bairro').val());

            VALORBAIRRO = parseFloat(bairro.valor_entrega);
        }
        let cont = 1;
        itensPedido.map((v) => {
            console.log(v)
            let nome = '';
            let valorUnit = 0;
            if (v.sabores && v.sabores.length > 0) {

                let cont = 0;
                v.sabores.map((sb) => {
                    cont++;
                    valorUnit = v.valor;
                    nome += sb.produto.produto.nome +
                        (cont == v.sabores.length ? '' : ' | ')
                })
                valorUnit = v.maiorValor

            } else {
                if (typeof v.produto !== 'undefined') {
                    nome = v.produto.nome;
                    valorUnit = v.produto.valor_venda
                } else {
                    nome = v.nome;
                    valorUnit = v.valor_venda
                }
            }

            let item = null
            if (typeof v.produto !== 'undefined') {
                item = {
                    cont: cont++,
                    id: v.produto_id,
                    nome: nome,
                    quantidade: v.quantidade,
                    valor: parseFloat(valorUnit) + parseFloat(v.valorAdicional),
                    pizza: v.maiorValor ? true : false,
                    itemPedido: v.item_pedido
                }
            } else {
                item = {
                    cont: cont++,
                    id: v.id,
                    nome: nome,
                    quantidade: 1,
                    valor: parseFloat(valorUnit),
                    pizza: false,
                    itemPedido: null
                }
            }


            ITENS.push(item)


            TOTAL += parseFloat((item.valor * item.quantidade));

        });
        let t = montaTabela();

        let valor_total = $('#valor_total').val();
        if (valor_total > TOTAL) {
            TOTAL = valor_total
            VALORACRESCIMO = 0;
        }

        if (PDV_VALOR_RECEBIDO) {
            $('#valor_recebido').val(TOTAL)
        }

        atualizaTotal();
        $('#body').html(t);
        let codigo_comanda = $('#codigo_comanda_hidden').val();

        COMANDA = codigo_comanda;
    }

});

$('#desconto').keyup(() => {
    $('#acrescimo').val('0')
    let desconto = $('#desconto').val();
    // if(!desconto){ $('#desconto').val('0'); desconto = 0}

    if (desconto) {
        desconto = parseFloat(desconto.replace(",", "."))
        DESCONTO = 0;
        if (desconto > TOTAL && $('#desconto').val().length > 2) {
            // Materialize.toast('ERRO, Valor desconto maior que o valor total', 4000)
            $('#desconto').val("");
        } else {
            DESCONTO = desconto;

            atualizaTotal();
        }
    }
})

function pad(s) {
    return (s < 10) ? '0' + s : s;
}

function categoria(cat) {

    desmarcarCategorias(() => {
        $('#cat_' + cat).addClass('ativo')
    })

    produtosDaCategoria(cat, (res) => {
        console.log(res)
        montaProdutosPorCategoria(res, (html) => {
            $('#prods').html(html)
        })
    })
}

function desmarcarCategorias(call) {
    CATEGORIAS.map((v) => {
        $('#cat_' + v.id).removeClass('ativo')
        $('#cat_' + v.id).removeClass('desativo')
    })
    $('#cat_todos').removeClass('desativo')
    $('#cat_todos').removeClass('ativo')

    call(true)
}

function produtosDaCategoria(cat, call) {
    let lista_id = $('#lista_id').val();
    $('#codBarras').focus()
    temp = [];
    aux = [];
    if (lista_id == 0) {
        aux = PRODUTOS;
    } else {
        aux = PRODUTOS.filter(produto => (produto.lista == lista_id))
    }
    if (cat != 'todos') {
        aux.map((v) => {



            if (v.categoria_id == cat) {
                temp.push(v)
            }

        })
    } else {
        if (lista_id == 0) {


            temp = PRODUTOS

        } else {
            temp = PRODUTOS.filter(produto => (produto.lista == lista_id))

        }
    }
    call(temp)
}

function montaProdutosPorCategoria(produtos, call) {
    $('#prods').html('')
    let lista_id = $('#lista_id').val();

    let html = '';
    produtos.map((p) => {
        console.log(p)
        html += '<div class="col-sm-12 col-lg-6 col-md-6 col-xl-4" id="atalho_add" '
        html += 'onclick="adicionarProdutoRapido2(\'' + p.id + '\')">'
        html += '<div class="card card-custom gutter-b example example-compact">'
        html += '<div class="card-header" style="height: 180px;">'
        if (p.imagem == '') {
            html += '<img class="img-prod" src="/imgs/no_image.png">'
        } else {
            html += '<img class="img-prod" src="/imgs_produtos/' + p.imagem + '">'
        }
        html += '<h6 style="font-size: 12px;" class="kt-widget__label">'
        html += p.nome + '</h6>'
        html += '<h6 style="font-size: 12px;" class="text-danger" class="kt-widget__label">'
        if (lista_id == 0) {
            html += formatReal(p.valor) + '</h6>'
        } else {
            let v = 0;
            p.lista_preco.map((l) => {
                if (lista_id == l.lista_id) {
                    html += formatReal(l.valor) + '</h6>'

                }
            })
        }

        html += '</div></div></div>'
    })

    call(html)
}

function adicionarProdutoRapido(produto) {
    console.log(produto)
    console.log(produto.nome)
    produto = JSON.parse(produto)
    PRODUTO = produto
    console.log(produto.valor_venda)
    $('#valor_item').val(produto.valor)
    $('#quantidade').val(produto.quantidade_minima)
    addItem()
}

function adicionarProdutoRapido2(id) {
    PRODUTOS.map((p) => {
        if (p.id == id) {
            PRODUTO = p
            $('#valor_item').val(p.valor_venda)
            $('#quantidade').val(1)
            addItem()
        }
    })
}

function novaHora() {

    var date = new Date();
    let v = [date.getHours(), date.getMinutes()].map(pad).join(':');
    $('#horas').html(v);
}

function novaData() {
    var date = new Date();
    let v = [date.getDate(), date.getMonth() + 1, date.getFullYear()].map(pad).join('/');
    $('#data').html(v);
}

function apontarObs() {
    let obs = $('#obs').val();
    OBSERVACAO = obs;

    $('#modal-obs').modal('hide')
}

function setarObservacaoItem() {
    let obs = $('#obs-item').val();
    OBSERVACAOITEM = obs;

    $('#modal-obs-item').modal('hide')
}

$('#autocomplete-cliente').on('keyup', () => {
    $('#cliente-nao').css('display', 'block');
    CLIENTE = null;
})


function formatReal(v) {
    return v.toLocaleString('pt-br', { style: 'currency', currency: 'BRL' });;
}

function getProdutos(data) {
    $.ajax({
        type: 'GET',
        url: path + 'produtos/all',
        dataType: 'json',
        success: function (e) {
            data(e)

        },
        error: function (e) {
            console.log(e)
        }

    });
}

function getClientes(data) {
    $.ajax({
        type: 'GET',
        url: path + 'clientes/all',
        dataType: 'json',
        success: function (e) {
            data(e)
        },
        error: function (e) {
            console.log(e)
        }

    });
}

function getCliente(id, data) {
    $.ajax({
        type: 'GET',
        url: path + 'clientes/find/' + id,
        dataType: 'json',
        success: function (e) {
            data(e)
        },
        error: function (e) {
            console.log(e)
        }

    });
}

function getVendasEmAbertoContaCredito(id, data) {
    $.ajax({
        type: 'GET',
        url: path + 'vendasEmCredito/somaVendas/' + id,
        dataType: 'json',
        success: function (e) {
            data(e)
        },
        error: function (e) {
            console.log(e)
        }

    });
}

$('#codBarras').keyup((v) => {
    setTimeout(() => {

        let cod = v.target.value
        let tamanho = ITENS.length;

        if (cod.length > 7) {
            $('#codBarras').val('')
            getProdutoCodBarras(cod, (data) => {
                if (data) {
                    setTimeout(() => {
                        addItem();

                    }, 400)
                } else {
                    let id = parseInt(cod.substring(1, ID_COD_BARRAS_BALANCA));

                    $.get(path + 'produtos/getProduto/' + id)
                        .done((res) => {

                            let dif = 10 - ID_COD_BARRAS_BALANCA;
                            let valor = cod.substring(ID_COD_BARRAS_BALANCA, 12);

                            let temp = valor.substring(0, dif) + '.' + valor.substring(dif, dif + 2);
                            valor = parseFloat(temp)

                            PRODUTO = JSON.parse(res);

                            $('#nome-produto').html(PRODUTO.nome);
                            let quantidade = 1;
                            if (PRODUTO.unidade_venda == 'KG') {
                                let valor_venda = PRODUTO.valor_venda;
                                quantidade = valor / valor_venda;
                                quantidade = quantidade.toFixed(3);
                                valor = valor_venda;
                            }
                            $('#valor_item').val(valor);
                            $('#quantidade').val(quantidade);
                            let tamanho2 = ITENS.length;
                            if (tamanho2 == tamanho) {
                                console.log("inserindo");
                                $('#adicionar-item').trigger('click');
                            }

                        })
                        .fail((err) => {
                            alert('err')
                            swal("Erro", 'Produto nao encontrado!', "warning").then(() => {
                                $('#codBarras').focus()

                            })



                        })
                }
            })

        }
    }, 500)
})

$('#focus-codigo').click(() => {
    $('#codBarras').focus()
})


$('#lista_id').change(() => {
    let lista = $('#lista_id').val();
    categoria('todos')
})

function getProduto(id, data) {

    console.log(LISTAID)
    $.ajax({
        type: 'GET',
        url: path + 'produtos/getProdutoVenda/' + id + '/' + LISTAID,
        dataType: 'json',
        success: function (e) {
            data(e)
        },
        error: function (e) {
            console.log(e)
        }
    });
}

$('#kt_select2_1').change(() => {
    let id = $('#kt_select2_1').val()
    let lista_id = $('#lista_id').val()
    PRODUTOS.map((p) => {
        if (p.idprecoproduto == id) {
            PRODUTO = p
            if (lista_id == 0) {
                $('#valor_item').val(p.valor)
                $('#quantidade').val(p.quantidade_minima)

            } else {
                p.lista_preco.map((l) => {
                    if (lista_id == l.lista_id) {
                        $('#valor_item').val(l.valor)

                    }
                })
            }
            //      $('#quantidade').val(1);

            //document.getElementById("#adicionar-item").focus();
            //	$('#adicionar-item').focus();
            $('#kt_select2_1').blur();
            $('#quantidade').focus();
        }
    })
})

$('#kt_select2_1').keyup(() => {
    $('#quantidade').focus();
})


function retirarFoco() {
    $('#quantidade').focus();
}

$('#finalizar-venda').click(() => {


    //   document.getElementById('btn_nao_fiscal').focus();
    $('#modal-fechamento').modal('show')
    //var select = document.getElementById('bandeira_cartao');
    //	var option = select.options[select.selectedIndex];
    //  let bandeira = option.text
    //  let formapagamento = ($('#tipo-pagamento').val()); 
    //if (formapagamento === '--') {
    //	swal("Aviso", 'Escolher a Forma de Pagamento', "warning")
    //	$('#tipo-pagamento').focus()
    //}
    //else
    //{
    //	$('#modal-venda').modal('show')
    //}
    $('.modal').on('shown.bs.modal', function () {
        $(this).find('[autofocus]').focus();
    }
    );

})

$('#btnfechamento').click(() => {


    let tipo = $('#tipo-pagamento').val();
    let token = $('#_token').val();

    if (tipo == '') {

        alert(' Escolher a forma de pagamento !');
        $('#tipo-pagamento').focus();
        return false;

    }

    if (tipo == '07') {
        let parcel_cred_valid         = $('#parcel_cred').val();

        if(parcel_cred_valid == '' || isNaN(parcel_cred_valid)){
            alert('Quantidade de parcelas invÃ¡lida');
            $('#parcel_cred').focus();
            return false;
        }
    }

    if (tipo == '01') {

        let t = TOTAL - DESCONTO;
        let v = $('#valor_recebido').val();
        v = v.replace(",", ".");

        if (v < t) {

            alert('Valor Recebido Ã© menor que Valor da Venda !');
            $('#valor_recebido').focus();
            return false;


        }
        $('#modal-vendad').modal('show')

        let troco = v - (TOTAL - DESCONTO);
        if (troco > 0) {

            $('#v-troco').html(formatReal(troco))
        } else {
            $('#v-troco').html('R$ 0,00')
        }

    } else if (tipo == '02' || tipo == '03') {
        
        $('#modal-vendacartao').modal('show')


    } else if (tipo == '05') {

        if (CLIENTE == null) {


            alert('Informar o Cliente da Conta !')
            $('#modal-fechamento').modal('hide')
            $('#modal-cliente').modal('show')

            //     $('#bandeira_cartao1').focus();
            return false;

        }

        $('#modal-venda').modal('show');


    } else if (tipo == '04') {

        $('#modal-vendacartao').modal('show');

    } else if (tipo == '07' || tipo == '08' || tipo == '09') {
        $('#modal-vendacartao').modal('show');
    }else {
        $('#modal-venda').modal('show')

    }

    //var select = document.getElementById('bandeira_cartao');
    //	var option = select.options[select.selectedIndex];
    //  let bandeira = option.text
    //  let formapagamento = ($('#tipo-pagamento').val()); 
    //if (formapagamento === '--') {
    //	swal("Aviso", 'Escolher a Forma de Pagamento', "warning")
    //	$('#tipo-pagamento').focus()
    //}
    //else
    //{
    //	$('#modal-venda').modal('show')
    //}
    $('.modal').on('shown.bs.modal', function () {
        $(this).find('[autofocus]').focus();
    });

    document.getElementById('btn_nao_fiscal').focus();

});

function showTefOverlay(texto) {
    $('#tefOverlayTexto').text(texto);
    $('#tefOverlay').css('display', 'flex');
}

function updateTefOverlayText(texto) {
    $('#tefOverlayTexto').text(texto);
}

function hideTefOverlay() {
    $('#tefOverlay').css('display', 'none');
}

function reabrirModalCpfTef() {
    $('#conclusaoBtnCPF').prop('disabled', false);
    $('#conclusaoBtnCNPJ').prop('disabled', false);
    $('#btn-cpf').prop('disabled', false);
    $('#btn-cpf').removeClass('disabled');
    $('#btn-cpf').removeClass('spinner');
    $('#modal-cpf-nota').modal('show');
}

function checkAutorizationTEF(intencaoVendaId, inicio, flag) {

    if (flag < 20) {

        setTimeout(function () {

            let token = $('#_token').val();

            $.ajax({
                url: path + 'tef/getIntencaoVendaTEF',
                type: 'POST',
                data: {
                    INTENCAO_VENDA_ID: intencaoVendaId,
                    _token: token
                },
                success: function (json) {
                    var data = $.parseJSON(json);

                    if (typeof data.intencoesVendas != 'undefined' && data.intencoesVendas.length > 0) {
                        var detIntencaoVenda = data.intencoesVendas[0];

                        // transaÃ§Ã£o pendente
                        if (detIntencaoVenda.intencaoVendaStatus.id == 5 || detIntencaoVenda.intencaoVendaStatus.id == 6) {
                            checkAutorizationTEF(detIntencaoVenda.id, 3, (flag + 1));

                        } else if (detIntencaoVenda.intencaoVendaStatus.id == 10) {
                            //transaÃ§Ã£o aprovada
                            var detPagamentosExternos = detIntencaoVenda.pagamentosExternos[0];
                            var dadosIntencaoVendaToAdd = {};

                            console.log(detPagamentosExternos);

                            updateTefOverlayText('Pagamento aprovado! Finalizando venda...');
                            addTEFBySession(detIntencaoVenda, detPagamentosExternos);

                            // showSuccess("TransaÃ§Ã£o aprovada e recebida com sucesso");
                        } else if (detIntencaoVenda.intencaoVendaStatus.id == 15) {
                            hideTefOverlay();
                            reabrirModalCpfTef();
                            swal('Erro', 'Transação expirada', 'error');
                            // showAlert("TransaÃ§Ã£o expirada");
                        } else if (detIntencaoVenda.intencaoVendaStatus.id == 18) {
                            hideTefOverlay();
                            reabrirModalCpfTef();
                            swal('Erro', 'Processo de cancelamento foi solicitado para essa transação', 'error');
                            //showAlert("Processo de cancelamento foi solicitado para essa transaÃ§Ã£o");
                        } else if (detIntencaoVenda.intencaoVendaStatus.id == 19) {
                            hideTefOverlay();
                            reabrirModalCpfTef();
                            swal('Erro', 'Sistema de pagamento recebeu a solicitação de cancelamento para essa transação', 'error');
                            //showAlert("Sistema de pagamento recebeu a solicitaÃ§Ã£o de cancelamento para essa transaÃ§Ã£o");
                        } else if (detIntencaoVenda.intencaoVendaStatus.id == 20) {

                            hideTefOverlay();
                            reabrirModalCpfTef();
                            swal('Erro', 'Cancelamento concluído para essa transação', 'error');

                            //showAlert("Cancelamento concluÃ­do para essa transaÃ§Ã£o");
                        } else if (detIntencaoVenda.intencaoVendaStatus.id == 25) {

                            hideTefOverlay();
                            reabrirModalCpfTef();
                            swal('Erro', 'Pagamento não aprovado pela adquirente ou banco emissor', 'error');
                            //showErro("Pagamento nÃ£o aprovado pela adquirente ou banco emissor");
                        }

                    } else {
                        checkAutorizationTEF(intencaoVendaId, 3, (flag + 1));
                    }
                },
                error: function () {
                    checkAutorizationTEF(intencaoVendaId, 3, (flag + 1));
                }
            });
        }, 1000 * inicio);

    } else {
        hideTefOverlay();
        reabrirModalCpfTef();
        swal('Erro', 'TEF ficou pendente no PayGo. Verifique se existe transação presa no PayGo e tente consultar/cancelar na lista de TEF.', 'error');
    }
}

function addTEFBySession(detIntencaoVenda, detPagamentosExternos) {
    $("#tipo-pagamento option:selected").attr('data-nsu-tef', detPagamentosExternos.nsuTid);
    $("#tipo-pagamento option:selected").attr('data-cod-aut-tef', detPagamentosExternos.autorizacao);
    $("#tipo-pagamento option:selected").attr('data-parcelas-tef', detPagamentosExternos.quantidadeParcelas);
    $("#tipo-pagamento option:selected").attr('data-bandeira-tef', detPagamentosExternos.bandeira);
    $("#tipo-pagamento option:selected").attr('data-nome-titular-tef', detPagamentosExternos.nomeTitularCartao);
    $("#tipo-pagamento option:selected").attr('data-id-pagamento-tef', detPagamentosExternos.idPagamento);
    $("#tipo-pagamento option:selected").attr('data-intencao-venda-tef', detIntencaoVenda.id);

    // Chamando finalizaÃ§Ã£o da venda sem validar o TEF, pois foi aprovado
    finalizarVenda('fiscal', 1);
}




function addItem() {
    if (caixaAberto) {
        $('#codBarras').focus();
        if (PRODUTO != null && $('#valor_item').val() > 0) {
            verificaProdutoIncluso((call) => {

                console.log("cal", call)
                if (call >= 0) {
                    let quantidade = $('#quantidade').val() ? $('#quantidade').val() : '1.00';
                    quantidade = quantidade.replace(",", ".");
                    let valor = $('#valor_item').val();
                    console.log("teste", (parseFloat(quantidade) + parseFloat(call)));
                    if (PRODUTO.gerenciar_estoque == 1 && (parseFloat(quantidade) + parseFloat(call)) > PRODUTO.estoque_atual) {
                        swal("Erro", 'O estoque atual deste produto Ã© de ' + PRODUTO.estoque_atual, "warning")
                        $('#quantidade').val('1')

                    } else {

                        if (quantidade.length > 0 && parseFloat(quantidade.replace(",", ".")) > 0 && valor.length > 0 && parseFloat(valor.replace(",", ".")) > 0 && PRODUTO != null) {
                            TOTAL += parseFloat(valor.replace(',', '.')) * (quantidade.replace(',', '.'));

                            let item = {
                                cont: (ITENS.length + 1),
                                obs: OBSERVACAOITEM,
                                id: PRODUTO.id,
                                nome: PRODUTO.nome,
                                quantidade: $('#quantidade').val(),
                                valor: $('#valor_item').val()
                            }

                            console.log(item)

                            $('#body').html("");
                            ITENS.push(item);

                            console.log(ITENS)

                            limparCamposFormProd();
                            atualizaTotal();

                            let v = $('#valor_recebido').val();
                            v = v.replace(",", ".");

                            if (PDV_VALOR_RECEBIDO == 1) {
                                $('#valor_recebido').val(TOTAL)
                                // Materialize.updateTextFields();
                            }

                            if (ITENS.length > 0 && ((parseFloat(v) >= TOTAL))) {
                                $('#finalizar-venda').attr('disabled');
                            } else {
                                $('#finalizar-venda').removeAttr('disabled');
                            }

                            let t = montaTabela();

                            $('#body').html(t);


                            PRODUTO = null;
                            $('#obs-item').val('');
                            OBSERVACAOITEM = "";
                            const produtoInput = document.getElementById('kt_select2_1');
                            if (produtoInput) {
                                produtoInput.focus();
                            }


                        }
                    }
                } else {
                    swal('Cuidado', 'Informe corretamente para continuar', 'warning')
                }
            });
        } else {
            swal('Cuidado', 'Informe corretamente para continuar', 'warning')
        }
    } else {
        swal("Erro", "Abra o caixa para vender!!", "error")
    }
}

function setaObservacao() {
    $('#modal-obs').modal('show')

}



function setaDesconto() {
    swal({
        title: 'Valor desconto?',
        text: 'Ultiliza ponto(.) ao invÃ©s de virgula!',
        content: "input",
        button: {
            text: "Ok",
            closeModal: false,
            type: 'error'
        }
    }).then(v => {
        if (v) {
            DESCONTO = parseFloat(v)
            $('#valor_desconto').html(formatReal(DESCONTO))
            atualizaTotal()
        }
        swal.close()

    });
}

function setaAcresicmo() {
    swal({
        title: 'Valor acrescimo?',
        text: 'Ultiliza ponto(.) ao invÃ©s de virgula!',
        content: "input",
        button: {
            text: "Ok",
            closeModal: false,
            type: 'error'
        }
    }).then(v => {
        if (v) {

            let acrescimo = v;
            if (acrescimo > 0) {
                DESCONTO = 0;
                $('#valor_desconto').html(formatReal(DESCONTO))
            }

            let total = TOTAL + VALORBAIRRO;

            if (acrescimo.substring(0, 1) == "%") {

                let perc = acrescimo.substring(1, acrescimo.length);

                VALORACRESCIMO = total * (perc / 100);


            } else {
                acrescimo = acrescimo.replace(",", ".")
                VALORACRESCIMO = parseFloat(acrescimo)
            }

            if (acrescimo.length == 0) VALORACRESCIMO = 0;
            atualizaTotal();
            VALORACRESCIMO = parseFloat(VALORACRESCIMO)
            $('#valor_acrescimo').html(formatReal(VALORACRESCIMO))

            atualizaTotal()
        }
        swal.close()

    });
}

$('#adicionar-item').click(() => {
    addItem();
})

function atualizaTotal() {

    let valor_recebido = $('#valor_recebido').val();
    if (!valor_recebido) valor_recebido = 0;
    if (valor_recebido > 0) {
        valor_recebido = valor_recebido.replace(",", ".");
        valor_recebido = parseFloat(valor_recebido)
    }
    console.log(TOTAL + VALORBAIRRO + VALORACRESCIMO - DESCONTO)
    if ((TOTAL + VALORBAIRRO + VALORACRESCIMO - DESCONTO) > valor_recebido) {
        $('#finalizar-venda').attr('disabled')
    } else {
        $('#finalizar-venda').removeAttr('disabled')
    }
    console.log(valor_recebido)
    if (!$('#valor_recebido').val()) {
        $('#finalizar-venda').attr('disabled')
    }
    // $('#total-venda').html(formatReal(TOTAL + VALORBAIRRO + VALORACRESCIMO - DESCONTO));
    console.log(VALORACRESCIMO)
    $('#total-venda').html(formatReal(TOTAL + VALORBAIRRO + VALORACRESCIMO - DESCONTO));
}

function montaTabela() {
    let t = "";
    let quantidades = 0;


    ITENS.map((v) => {
        console.log(v)

        t += '<tr class="datatable-row" style="left: 0px;">'
        t += '<td class="datatable-cell">'
        t += '<span class="codigo" style="width: 50px;">'
        t += v.cont + '</span>'
        t += '</td>'

        t += '<td class="datatable-cell">'
        t += '<span class="codigo" style="width: 50px;">'
        t += v.id
        t += '</span></td>'

        t += '<td class="datatable-cell">'
        t += '<span class="codigo" style="width: 200px;">'
        t += v.nome + (v.obs ? " [OBS: " + v.obs + "]" : "")
        t += '</span></td>'

        t += '<td class="datatable-cell">'
        t += '<span class="codigo" style="width: 120px;">'
        t += '<div class="form-group mb-2">'
        t += '<div class="input-group">'
        t += '<div class="input-group-prepend">'
        t += '<button onclick="subtraiItem(' + v.cont + ')" class="btn btn-danger" type="button">-</button>'
        t += '</div>'
        t += '<input type="text" readonly class="form-control" value="' + v.quantidade + '">'
        t += '<div class="input-group-append">'
        t += '<button onclick="incrementaItem(' + v.cont + ')" class="btn btn-success" type="button">+</button>'
        t += '</div></div></div></span></td>'

        t += '<td class="datatable-cell">'
        t += '<span class="codigo" style="width: 120px;">'
        t += formatReal(v.valor)
        t += '</span></td>'

        t += '<td class="datatable-cell">'
        t += '<span class="codigo" style="width: 120px;">'
        t += formatReal(v.valor * v.quantidade)
        t += '</span></td>'
        t += '</tr>'

        quantidades += parseInt(v.quantidade);
    });

    $('#qtd-itens').html(ITENS.length);
    $('#_qtd').html(quantidades);
    return t
}

function subtraiItem(id) {
    let temp = [];
    let soma = 0
    ITENS.map((v) => {
        if (v.cont != id) {
            temp.push(v)
            try {
                soma += parseFloat(v.valor.replace(',', '.')) * (v.quantidade);
            } catch {
                soma += parseFloat(v.valor * v.quantidade);
            }
        } else {
            if (v.quantidade > 1) {
                v.quantidade = parseFloat(v.quantidade) - 1;
                try {
                    soma += parseFloat(v.valor.replace(',', '.') * v.quantidade);
                } catch {
                    soma += parseFloat(v.valor * v.quantidade);
                }
                temp.push(v)
            }
        }
    });
    TOTAL = soma
    ITENS = temp
    let t = montaTabela();
    atualizaTotal();
    $('#body').html(t);
    if (PDV_VALOR_RECEBIDO) {
        $('#valor_recebido').val(TOTAL)
    }
}

$('#click-client').click(() => {
    $('#modal-cliente').modal('show')
})

function novoCliente() {
    $('#modal-clientenovo').modal('show')
}

function selecionarCliente() {
    let cliente = $('#kt_select2_3').val();
    CLIENTES.map((c) => {
        if (c.id == cliente) {
            CLIENTE = c
        }
    })
    $('#conta_credito-btn').removeClass('disabled')
    $('#modal-cliente').modal('hide')
}

function incrementaItem(id) {
    let temp = [];
    let soma = 0
    console.log(ITENS)
    ITENS.map((v) => {
        if (v.cont != id) {
            temp.push(v)
            try {
                soma += parseFloat(v.valor.replace(',', '.')) * (v.quantidade);
            } catch {
                console.log("quantidade", v.quantidade)
                soma += parseFloat(v.valor) * (v.quantidade);
            }
        } else {
            v.quantidade = parseFloat(v.quantidade) + 1;
            try {
                console.log("quantidade", v.quantidade)
                soma += parseFloat(v.valor.replace(',', '.') * v.quantidade);
            } catch {

                soma += parseFloat(v.valor * v.quantidade);
            }
            temp.push(v)
        }
    });
    console.log(soma)
    TOTAL = soma
    ITENS = temp
    let t = montaTabela();
    atualizaTotal();
    $('#body').html(t);
    if (PDV_VALOR_RECEBIDO) {
        $('#valor_recebido').val(TOTAL)
    }
}

function limparCamposFormProd() {
    $('#autocomplete-produto').val('');
    $('#quantidade').val('1');
    $('#valor_item').val('0,00');
}

function verificaProdutoIncluso(call) {
    let cont = 0;
    ITENS.map((rs) => {
        if (PRODUTO.id == rs.id) {
            cont += parseFloat(rs.quantidade);
        }
    })
    call(cont);
}

function getProdutoCodBarras(cod, data) {
    let tamanho = ITENS.length;
    console.log(tamanho)
    $.ajax({
        type: 'GET',
        url: path + 'produtos/getProdutoCodBarras/' + cod,
        dataType: 'json',
        success: function (e) {
            data(e)
            if (e) {
                PRODUTO = e;
                $('#nome-produto').html(e.nome);
                $('#valor_item').val(e.valor_venda);
            } else {
                if (cod.length == 13) {
                    //validar pelo cod balanÃ§a

                    let id = parseInt(cod.substring(ID_COD_BARRAS_BALANCA, 5));

                    console.log(id)

                    $.get(path + 'produtos/getProduto/' + id)
                        .done((res) => {

                            let valor = cod.substring(ID_COD_BARRAS_BALANCA, 12);

                            let temp = valor.substring(0, 3) + '.' + valor.substring(3, 5);
                            valor = parseFloat(temp)
                            console.log(valor)

                            PRODUTO = JSON.parse(res);

                            $('#nome-produto').html(PRODUTO.nome);
                            let quantidade = 1;
                            if (PRODUTO.unidade_venda == 'KG') {
                                let valor_venda = PRODUTO.valor_venda;
                                quantidade = valor / valor_venda;
                                quantidade = quantidade.toFixed(3);
                                valor = valor_venda;
                            }
                            $('#valor_item').val(valor);
                            $('#quantidade').val(quantidade);
                            let tamanho2 = ITENS.length;
                            if (tamanho2 == tamanho) {
                                console.log("inserindo");
                                $('#adicionar-item').trigger('click');
                            }

                        })
                        .fail((err) => {
                            // alert('Produto nao encontrado!')
                            // swal("Erro", 'Produto nao encontrado!', "warning")

                            $('#autocomplete-produto').val('')

                        })



                }
            }

        },
        error: function (e) {
            console.log(e)
        }

    });
}

function verificaCaixa(data) {
    $.ajax({
        type: 'GET',
        url: path + 'aberturaCaixa/verificaHoje',
        dataType: 'json',
        success: function (e) {
            console.log(e)
            data(e)

        },
        error: function (e) {
            console.log(e)
        }

    });
}

function abrirCaixa() {
    let token = $('#_token').val();
    let valor = $('#valor').val();
    document.getElementById("btn-abrecaixa").disabled = true


    valor = valor.length >= 0 ? valor.replace(",", ".") : 0;
    if (parseFloat(valor) >= 0) {
        $.ajax({
            type: 'POST',
            url: path + 'aberturaCaixa/abrir',
            dataType: 'json',
            data: {
                valor: $('#valor').val(),
                _token: token
            },
            success: function (e) {
                caixaAberto = true;
                $('#modal1').modal('hide');
                swal("Sucesso", "Caixa aberto", "success")


            },
            error: function (e) {
                $('#modal1').modal('hide');
                swal("Erro", "Erro ao abrir caixa", "error")
                console.log(e)
            }

        });
    } else {
        // alert('Insira um valor vÃ¡lido')
        document.getElementById("btn-abrecaixa").disabled = false

        swal("Erro", 'Insira um valor vÃ¡lido', "warning")

    }

}

function sangriaCaixa() {
    let token = $('#_token').val();

    $.ajax({
        type: 'POST',
        url: path + 'sangriaCaixa/save',
        dataType: 'json',
        data: {
            valor: $('#valor_sangria').val(),
            obs: $('#obs_sangria').val(),
            _token: token
        },
        success: function (e) {

            caixaAberto = true;
            $('#modal2').modal('hide');
            $('#valor_sangria').val('');
            $('#obs_sangria').val('');
            swal("Sucesso", "Sangria realizada!", "success")

        },
        error: function (e) {
            console.log(e)
            swal("Erro", "Erro ao realizar sangria!", "error")

        }

    });
}

function suprimentoCaixa() {
    let token = $('#_token').val();

    $.ajax({
        type: 'POST',
        url: path + 'suprimentoCaixa/save',
        dataType: 'json',
        data: {
            valor: $('#valor_suprimento').val(),
            obs: $('#obs_suprimento').val(),
            _token: token
        },
        success: function (e) {

            $('#modal-supri').modal('hide');
            $('#valor_suprimento').val('');
            $('#obs_suprimento').val('');
            swal("Sucesso", "suprimento realizado!", "success")

        },
        error: function (e) {
            console.log(e)
            swal("Erro", "Erro ao realizar suprimento de caixa!", "error")

        }

    });
}

function getSangriaDiaria(data) {
    $.ajax({
        type: 'GET',
        url: path + 'sangriaCaixa/diaria',
        dataType: 'json',
        success: function (e) {
            data(e)

        },
        error: function (e) {
            console.log(e)
        }

    });
}

function getSuprimentoDiario(data) {
    $.ajax({
        type: 'GET',
        url: path + 'suprimentoCaixa/diaria',
        dataType: 'json',
        success: function (e) {
            data(e)

        },
        error: function (e) {
            console.log(e)
        }

    });
}

function getAberturaDiaria(data) {

    $.ajax({
        type: 'GET',
        url: path + 'aberturaCaixa/verificaHoje',
        dataType: 'json',
        success: function (e) {
            console.log(e)
            data(e)

        },
        error: function (e) {
            console.log(e)
        }

    });
}

function getVendaDiaria(data) {
    $.ajax({
        type: 'GET',
        url: path + 'vendasCaixa/diaria',
        dataType: 'json',
        success: function (e) {
            data(e)

        },
        error: function (e) {
            console.log(e)
        }

    });
}

function fluxoDiario() {
    $('#preloader1').css('display', 'block');
    getSangriaDiaria((sangrias) => {
        getSuprimentoDiario((suprimentos) => {

            let elem = "";
            let totalSangria = 0;
            let totalSuprimento = 0;
            sangrias.map((v) => {

                elem += "<p> Horario: "
                elem += "<strong>" + v.data_registro.substring(10, 16) + "</strong>, Valor: "
                elem += "<strong> R$ " + formatReal(v.valor) + "</strong>, Usuario: "
                elem += "<strong>" + v.nome_usuario + "</strong>, Obs: "
                elem += "<strong class='text-info'>" + v.observacao + "</strong>"

                elem += "</p>";
                totalSangria += parseFloat(v.valor);
            })

            elem += "<h6>Total: <strong class='text-danger'>" + formatReal(totalSangria) + "</strong></h6>";
            elem += "<hr>"
            $('#fluxo_sangrias').html(elem)
            elem = ""
            suprimentos.map((v) => {

                elem += "<p> Horario: "
                elem += "<strong>" + v.created_at.substring(10, 16) + "</strong>, Valor: "
                elem += "<strong> R$ " + formatReal(v.valor) + "</strong>, Usuario: "
                elem += "<strong class='text-info'>" + v.nome_usuario + "</strong>, Obs: "
                elem += "<strong class='text-info'>" + v.observacao + "</strong>"
                elem += "</p>";
                totalSuprimento += parseFloat(v.valor);
            })
            elem += "<h6>Total: <strong class='text-danger'>" + formatReal(totalSuprimento) + "</strong></h6>";
            elem += "<hr>"

            $('#fluxo_suprimentos').html(elem)

            getAberturaDiaria((abertura) => {
                abertura = abertura.replace(",", ".")
                elem = "<p> Valor: ";
                elem += "<strong class='text-danger'>R$ " + formatReal(abertura) + "</strong>";
                elem += "</p>";
                elem += "<hr>"

                $('#fluxo_abertura_caixa').html(elem);
                getVendaDiaria((vendas) => {

                    elem = "";
                    let totalVendas = 0;
                    vendas.map((v) => {
                        console.log(v)
                        elem += "<p> Horario: "
                        elem += "<strong>" + v.data_registro.substring(10, 16) + "</strong>, Valor: "
                        elem += "<strong> R$ " + formatReal(parseFloat(v.valor_total)) + "</strong>, Tipo Pagamento: "
                        elem += "<strong>" + v.tp_pag + "</strong>"
                        elem += "</p>";
                        totalVendas += parseFloat(parseFloat(v.valor_total));
                    })
                    elem += "<h6>Total: <strong class='text-primary'>" + formatReal(totalVendas) + "</strong></h6>";
                    elem += "<hr>";
                    $('#fluxo_vendas').html(elem);
                    $('#total_caixa').html(formatReal((totalVendas + parseFloat(abertura)) - totalSangria + totalSuprimento));

                    $('#preloader1').css('display', 'none');
                });
            })
        })
    })
    if (caixaAberto) {
        $('#modal3').modal('open');
    } else {

        // var $toastContent = $('<span>Por favor abra o caixa!</span>').add($('<button class="btn-flat toast-action">OK</button>'));
        // Materialize.toast($toastContent, 5000);
        location.reload();
    }
}



function duasCasas(valor) {
    return parseFloat(valor.toFixed(2));
}

$('#autocomplete-produto').on('keyup', () => {
    let val = $('#autocomplete-produto').val();
    if ($.isNumeric(val) && val.length > 6) {
        getProdutoCodBarras(val, (data) => {
            setTimeout(() => {
                addItem();

            }, 400)
        })
    }
})

function verificaCliente() {
    if (CLIENTE == null) {
        $('#modal-venda').modal('hide');
        $('#modal-cpf-nota').modal('show');
    } else {
        finalizarVenda('fiscal')
    }
}

function verificaClientecnpj() {
    if (CLIENTE == null) {
        $('#modal-venda').modal('hide');
        $('#modal-cnpj-nota').modal('show');
    } else {
        finalizarVenda('fiscal')
    }
}

function validaCpf() {

    if (CLIENTE != null) return true;

    let strCPF = $('#cpf').val();
    let nome = $('#nome').val();
    if (strCPF.length == 0) return true;

    // if(nome == '' || nome == null || nome.length == 0) return false;

    strCPF = strCPF.replace(".", "");
    strCPF = strCPF.replace(".", "");
    strCPF = strCPF.replace("-", "");
    var Soma;
    var Resto;
    Soma = 0;
    if (strCPF == "00000000000") return false;

    for (i = 1; i <= 9; i++) Soma = Soma + parseInt(strCPF.substring(i - 1, i)) * (11 - i);
    Resto = (Soma * 10) % 11;

    if ((Resto == 10) || (Resto == 11)) Resto = 0;
    if (Resto != parseInt(strCPF.substring(9, 10))) return false;;

    Soma = 0;
    for (i = 1; i <= 10; i++) Soma = Soma + parseInt(strCPF.substring(i - 1, i)) * (12 - i);
    Resto = (Soma * 10) % 11;

    if ((Resto == 10) || (Resto == 11)) Resto = 0;
    if (Resto != parseInt(strCPF.substring(10, 11))) return false;;

    return true;
}

function validaCNPJ() {

    if (CLIENTE != null) return true;
    cnpj = $('#cnpj').val();
    cnpj = cnpj.replace(/[^\d]+/g, '');

    if (cnpj == '') return false;

    if (cnpj.length != 14)
        return false;

    // Elimina CNPJs invalidos conhecidos
    if (cnpj == "00000000000000" ||
        cnpj == "11111111111111" ||
        cnpj == "22222222222222" ||
        cnpj == "33333333333333" ||
        cnpj == "44444444444444" ||
        cnpj == "55555555555555" ||
        cnpj == "66666666666666" ||
        cnpj == "77777777777777" ||
        cnpj == "88888888888888" ||
        cnpj == "99999999999999")
        return false;

    // Valida DVs
    tamanho = cnpj.length - 2
    numeros = cnpj.substring(0, tamanho);
    digitos = cnpj.substring(tamanho);
    soma = 0;
    pos = tamanho - 7;
    for (i = tamanho; i >= 1; i--) {
        soma += numeros.charAt(tamanho - i) * pos--;
        if (pos < 2)
            pos = 9;
    }
    resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
    if (resultado != digitos.charAt(0))
        return false;

    tamanho = tamanho + 1;
    numeros = cnpj.substring(0, tamanho);
    soma = 0;
    pos = tamanho - 7;
    for (i = tamanho; i >= 1; i--) {
        soma += numeros.charAt(tamanho - i) * pos--;
        if (pos < 2)
            pos = 9;
    }
    resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
    if (resultado != digitos.charAt(1))
        return false;

    return true;

}


$('#tipo-pagamento').change(() => {
    $('#valor_recebido').val('');

    let tipo = $('#tipo-pagamento').val();


    if(tipo == '07'){
        $('#divParcelasCredTEF').css('display', 'block');
    }else{
        $('#divParcelasCredTEF').css('display', 'none');
    }

    if (tipo == '99') {
        $('#modal-fechamento').modal('hide')
        $('#modal-pag-mult').modal('show')
        $('#v-multi').html(formatReal(TOTAL))

    }

    if (tipo == '01') {
        $('#valor_recebido').removeAttr('disabled');
        //   $('#finalizar-venda').attr('disabled');
        $('#bandeira_cartao').attr('disabled', 'true');

        $('#valor_recebido').focus();

    } else {
        $('#valor_recebido').attr('disabled', 'true');

        //    $('#finalizar-venda').removeAttr('disabled');

    }
    //$('#finalizar-venda').focus();	
})

$('#tipo_pagamento_1').change(() => {


    let tipo = $('#tipo_pagamento_1').val();

    if (tipo == '02' || tipo == '03') {

        $('#modal-cartao1').modal('show')
        $('#bandeira_cartao1').removeAttr('disabled');
        $('#bandeira_cartao1').focus();

    }

    //if (tipo == '99') {
    //      $('#modal-fechamento').modal('hide')
    //      $('#modal-pag-mult').modal('show')
    //      $('#v-multi').html(formatReal(TOTAL))

    //  }

    // if (tipo == '01') {
    //      $('#valor_recebido').removeAttr('disabled');
    //   $('#finalizar-venda').attr('disabled');
    //       $('#bandeira_cartao').attr('disabled', 'true');

    //       $('#valor_recebido').focus();

    //  } else {
    //      $('#valor_recebido').attr('disabled', 'true');

    //    $('#finalizar-venda').removeAttr('disabled');

    //  }
    //$('#finalizar-venda').focus();	
})

$('#tipo_pagamento_2').change(() => {


    let tipo = $('#tipo_pagamento_2').val();

    if (tipo == '02' || tipo == '03') {

        $('#modal-cartao2').modal('show')
        $('#bandeira_cartao2').removeAttr('disabled');
        $('#bandeira_cartao2').focus();

    }

    //if (tipo == '99') {
    //      $('#modal-fechamento').modal('hide')
    //      $('#modal-pag-mult').modal('show')
    //      $('#v-multi').html(formatReal(TOTAL))

    //  }

    // if (tipo == '01') {
    //      $('#valor_recebido').removeAttr('disabled');
    //   $('#finalizar-venda').attr('disabled');
    //       $('#bandeira_cartao').attr('disabled', 'true');

    //       $('#valor_recebido').focus();

    //  } else {
    //      $('#valor_recebido').attr('disabled', 'true');

    //    $('#finalizar-venda').removeAttr('disabled');

    //  }
    //$('#finalizar-venda').focus();	
})

$('#tipo_pagamento_3').change(() => {


    let tipo = $('#tipo_pagamento_2').val();

    if (tipo == '02' || tipo == '03') {

        $('#modal-cartao3').modal('show')
        $('#bandeira_cartao3').removeAttr('disabled');
        $('#bandeira_cartao3').focus();

    }

    //if (tipo == '99') {
    //      $('#modal-fechamento').modal('hide')
    //      $('#modal-pag-mult').modal('show')
    //      $('#v-multi').html(formatReal(TOTAL))

    //  }

    // if (tipo == '01') {
    //      $('#valor_recebido').removeAttr('disabled');
    //   $('#finalizar-venda').attr('disabled');
    //       $('#bandeira_cartao').attr('disabled', 'true');

    //       $('#valor_recebido').focus();

    //  } else {
    //      $('#valor_recebido').attr('disabled', 'true');

    //    $('#finalizar-venda').removeAttr('disabled');

    //  }
    //$('#finalizar-venda').focus();	
})


function finalizarVenda(acao, noValidateTef) {
    
    var tipoPagamento = $('#tipo-pagamento').val();

    if((tipoPagamento == '07' || tipoPagamento == '08' || tipoPagamento == '09') && typeof noValidateTef == 'undefined'){

        let validCpf = validaCpf();

        if (validCpf == true || acao != 'fiscal') {

            let tipo = $('#tipo-pagamento').val();
            let token = $('#_token').val();
            
            let valor = TOTAL;
          
            let parcel_cred         = $('#parcel_cred').val();
            // valor = valor.replace(".", ",");
            let formaPagamentoTEF = '';
            // let terminalID          = $('#terminalId').val();
            let terminalID = 0;
        
            if (tipo == '07') {
                formaPagamentoTEF   = 21;
            } else if(tipo == '08') {
                formaPagamentoTEF   = 22;
                parcel_cred         = 1;
            }else if(tipo == '09'){
                formaPagamentoTEF   = 24;
                parcel_cred         = 1;
            }


            $('#conclusaoBtnCPF').prop('disabled', true);
            $('#conclusaoBtnCNPJ').prop('disabled', true);
            $('#btn-cnpj').prop('disabled', true);
            $('#btn-cpf').prop('disabled', true);

            $('#modal-cpf-nota').modal('hide');
            showTefOverlay('Aguardando retorno do TEF...');

            $.ajax({
                url: path + 'tef/postTransacaoTEF',
                type: 'POST',
                data: {
                    FORMA_PAGAMENTO_ID: formaPagamentoTEF,
                    TERMINAL_ID: terminalID,
                    PARCELAS: parcel_cred,
                    VALOR_TOTAL: valor,
                    CLIENTE_ID: 0,
                    _token: token
                },
                success: function (json) {
                    var data = $.parseJSON(json);

                    $('#conclusaoBtnCPF').prop('disabled', false);
                    $('#conclusaoBtnCNPJ').prop('disabled', false);
                    $('#btn-cnpj').prop('disabled', true);
                    $('#btn-cpf').prop('disabled', true);

                    if (data && data.intencaoVenda && data.intencaoVenda.id) {
                        checkAutorizationTEF(data.intencaoVenda.id, 10, 0);
                    } else {
                        hideTefOverlay();
                        reabrirModalCpfTef();
                        swal('Erro', (data && data.message) ? data.message : 'Não foi possível iniciar a transação no TEF.', 'error');
                    }
                },
                error: function () {
                    hideTefOverlay();
                    reabrirModalCpfTef();
                    swal('Erro', 'Falha de comunicação ao iniciar a transação no TEF.', 'error');
                }
            });

        }else{
            swal('Erro', 'CPF Inválido!', 'error');
        }
    }else{

        if (acao == 'fiscal') {
            document.getElementById("btn-cpf").disabled = true
            $('#btn-cpf').addClass('disabled')
            $('#btn-cpf').addClass('spinner')
    
            $('#modal-cpf-nota').modal('hide')
    
    
        }
        else {
    
            document.getElementById("btn_nao_fiscal").disabled = true
    
        }
    
        document.getElementById("finalizar-venda").disabled = true
    
        $('#modal-vendad').modal('hide');
        $('#modal-vendacartao').modal('hide');
        $('#modal-venda').modal('hide');
    
        $('#finalizar-venda').addClass('disabled')
        // verifica o valor da escolhido da bandeira
        var select = document.getElementById('bandeira_cartao');
        var option = select.options[select.selectedIndex];
        let bandeira = option.text
    
        var select = document.getElementById('bandeira_cartao1');
        var option = select.options[select.selectedIndex];
        let bandeira1 = option.text
    
        var select = document.getElementById('bandeira_cartao2');
        var option = select.options[select.selectedIndex];
        let bandeira2 = option.text
    
    
        var select = document.getElementById('bandeira_cartao3');
        var option = select.options[select.selectedIndex];
        let bandeira3 = option.text
    
        let validCpf = validaCpf();
        if (validCpf == true || acao != 'fiscal') {
    
            let valorRecebido = $('#valor_recebido').val();
            let troco = 0;
            if (valorRecebido.length > 0 && parseFloat(valorRecebido) > (TOTAL + VALORACRESCIMO + VALORBAIRRO - DESCONTO)) {
                troco = parseFloat(valorRecebido) - (TOTAL + VALORACRESCIMO + VALORBAIRRO - DESCONTO);
            }
    
            let desconto = DESCONTO;
    
            let obs = $('#obs').val();
    
            let js = {
                itens: ITENS,
                cliente: CLIENTE != null ? CLIENTE.id : null,
                valor_total: TOTAL,
                acrescimo: VALORBAIRRO + VALORACRESCIMO,
                troco: troco,
                tipo_pagamento: $('#tipo-pagamento').val(),
                forma_pagamento: '',
                dinheiro_recebido: valorRecebido,
                acao: acao,
                nome: $('#nome').val(),
                cpf: $('#cpf').val(),
                delivery_id: $('#delivery_id').val(),
                pedido_local: $('#pedidoLocal').val() ? true : false,
                codigo_comanda: COMANDA,
                desconto: desconto ? desconto : 0,
                observacao: obs,
                tipo_pagamento_1: TIPOPAG1,
                tipo_pagamento_2: TIPOPAG2,
                tipo_pagamento_3: TIPOPAG3,
                valor_pagamento_1: VALORPAG1,
                valor_pagamento_2: VALORPAG2,
                valor_pagamento_3: VALORPAG3,
                agendamento_id: $('#agendamento_id').val(),
                orcamento_id: ORCAMENTOID,
                //bandeira_cartao: $('#bandeira_cartao').val() ? $('#bandeira_cartao').val() : '99',
                bandeira_cartao: bandeira,
                bandeira_cartao1: bandeira1,
                bandeira_cartao2: bandeira2,
                bandeira_cartao3: bandeira3,
    
                cAut_cartao: $('#cAut_cartao').val() ? $('#cAut_cartao').val() : '',
                cnpj_cartao: $('#cnpj_cartao').val() ? $('#cnpj_cartao').val() : '',
                descricao_pag_outros: $('#descricao_pag_outros').val() ? $('#descricao_pag_outros').val() : '',
    
    
            }
    
    
            if ($('#tipo-pagamento').val() == '07' || $('#tipo-pagamento').val() == '08' || $('#tipo-pagamento').val() == '09') {
                js.dados_tef = {
                    nsu_tef: $("#tipo-pagamento option:selected").attr('data-nsu-tef'),
                    cod_aut_tef: $("#tipo-pagamento option:selected").attr('data-cod-aut-tef'),
                    parcelas_tef: $("#tipo-pagamento option:selected").attr('data-parcelas-tef'),
                    bandeira_tef: $("#tipo-pagamento option:selected").attr('data-bandeira-tef'),
                    nome_titular_tef: $("#tipo-pagamento option:selected").attr('data-nome-titular-tef'),
                    id_pagamento_tef: $("#tipo-pagamento option:selected").attr('data-id-pagamento-tef'),
                    intencao_venda_id: $("#tipo-pagamento option:selected").attr('data-intencao-venda-tef')
                };
            }
    
            console.log(js)
            let token = $('#_token').val();
    
            if (acao != 'credito') {
                $('#btn_nao_fiscal').addClass('disabled')
                $.ajax({
                    type: 'POST',
                    url: path + 'vendasCaixa/save',
                    dataType: 'json',
                    data: {
                        venda: js,
                        _token: token
                    },
                    success: function (e) {
                        if (acao == 'fiscal') {
                            $('#preloader2').css('display', 'block');
                            $('#preloader9').css('display', 'block');
    
                            emitirNFCe(e.id);
    
                            if (IMPRIMIR_CUPOM_AUTOMATICO == 1) {
    
                                alert(' Venda realalizada com Sucesso !');
    
                                printPage(path + 'nfce/imprimirNaoFiscal/' + e.id);
    
                                //   setTimeout(() => {
                                //     printPage(path + 'nfce/imprimir/' + e.id);
                                //      }, 8000)
    
    
                                //  printPage(path + 'nfce/imprimir/' + e.id);
    
                                setTimeout(() => {
                                    window.open(path + 'nfce/imprimir/' + e.id, '_blank');
                                }, 4000)
    
    
    
                                // window.open(path + 'nfce/imprimir/' + e.id, '_blank');
    
                                setTimeout(() => {
                                    location.href = path + 'frenteCaixa';
                                }, 5000)
    
    
                            }
    
    
                        } else {
    
    
    
                            if (IMPRIMIR_CUPOM_AUTOMATICO == 1) {
    
                                alert(' Venda realalizada com Sucesso !');
    
                                printPage(path + 'nfce/imprimirNaoFiscal/' + e.id);
    
    
                                setTimeout(() => {
                                    location.href = path + 'frenteCaixa';
                                }, 5000)
    
    
                            }
                            else if (IMPRIMIR_CUPOM_AUTOMATICO == 2) {
    
                                alert(' Venda realalizada com Sucesso !');
    
                                window.open(path + 'nfce/imprimirNaoFiscal/' + e.id, '_blank');
    
    
    
                                location.reload();
    
    
    
                            }
                            else if (IMPRIMIR_CUPOM_AUTOMATICO == 3) {
    
                                alert(' Venda realalizada com Sucesso !');
    
                                printPage(path + 'nfce/imprimirNaoFiscal/' + e.id);
    
    
    
                                setTimeout(() => {
                                    location.href = path + 'frenteCaixa';
                                }, 4000)
    
    
    
    
    
                            }
                            else if (IMPRIMIR_CUPOM_AUTOMATICO == 4) {
    
                                alert(' Venda realalizada com Sucesso !');
    
                                window.open(path + 'nfce/imprimirNaoFiscal/' + e.id, '_blank');
    
    
    
                                location.reload();
    
    
    
                            }
                            else if (IMPRIMIR_CUPOM_AUTOMATICO == 0) {
                                swal({
                                    title: "Sucesso",
                                    text: "Deseja imprimir comprovante?",
                                    icon: "success",
                                    buttons: ["NÃ£o", 'Imprimir'],
                                    dangerMode: true,
                                })
                                    .then((v) => {
                                        if (v) {
                                            if (IMPRIMIR_CUPOM_AUTOMATICO == 0) {
                                                window.open(path + 'nfce/imprimirNaoFiscal/' + e.id, '_blank');
                                                location.href = path + 'frenteCaixa';
    
    
                                            } else {
                                                gerarArquivoPDV(e.id, 'nao_fiscal');
                                                printPage('http://localhost:8000/impressao_pdv/2261.pdf');
                                                //  printPage(path+ 'impressao_pdv/' + '2261.pdf');
                                                // printPage(path+ 'impressao_pdv/' + e.id + '.pdf');
                                                //  setTimeout(() => {
                                                //    location.href = path + 'frenteCaixa';
                                                //}, 1000)
    
                                                location.href = path + 'frenteCaixa';
                                            }
                                        } else {
                                            location.href = path + 'frenteCaixa';
                                        }
                                    });
    
    
    
                            }
                            else {
    
                                swal({
                                    title: "Sucesso",
                                    text: "Deseja imprimir comprovante?",
                                    icon: "success",
                                    buttons: ["NÃ£o", 'Imprimir'],
                                    dangerMode: true,
                                })
                                    .then((v) => {
                                        if (v) {
                                            if (IMPRIMIR_CUPOM_AUTOMATICO == 0) {
                                                window.open(path + 'nfce/imprimirNaoFiscal/' + e.id, '_blank');
                                                location.href = path + 'frenteCaixa';
    
    
                                            } else {
                                                gerarArquivoPDV(e.id, 'nao_fiscal');
                                                printPage('http://localhost:8000/impressao_pdv/2261.pdf');
                                                //  printPage(path+ 'impressao_pdv/' + '2261.pdf');
                                                // printPage(path+ 'impressao_pdv/' + e.id + '.pdf');
                                                //  setTimeout(() => {
                                                //    location.href = path + 'frenteCaixa';
                                                //}, 1000)
    
                                                location.href = path + 'frenteCaixa';
                                            }
                                        } else {
                                            location.href = path + 'frenteCaixa';
                                        }
                                    });
    
    
                            }
    
    
    
                        }
    
                    },
                    error: function (e) {
                        console.log(e)
                        $('#preloader2').css('display', 'none');
                        $('#preloader9').css('display', 'none');
                        hideTefOverlay();
                        $('#modal-venda').modal('hide')
                        swal('Erro', 'Falha ao salvar a venda. Tente novamente.', 'error');
                    }

                });
            } else {
                // let valorUltrapassadoConfirma = true;
                // if(CLIENTE.limite_venda < TOTALEMABERTOCLIENTE+TOTAL){
                // 	valorUltrapassadoConfirma = confirm("Valor do limite de conta crÃ©dito ultrapassado, confirma venda?!");
                // }
                if (CLIENTE == null) {
                    swal("Alerta", "Informe um cliente para conta crÃ©dito", "warning")
                } else {
                    if (CLIENTE.limite_venda < parseFloat(CLIENTE.totalEmAberto) + TOTAL) {
                        swal({
                            text: "Valor do limite de conta crÃ©dito ultrapassado, confirma a venda?!",
                            title: 'Cuidado',
                            icon: 'warning',
                            buttons: ["NÃ£o", "Vender"],
                        }).then(sim => {
                            if (sim) {
                                salvarCredito(js, token)
                            } else {
                                $('#preloader2').css('display', 'none');
                                $('#preloader9').css('display', 'none');
                                $('#modal-venda').modal('hide')
                            }
                        });
    
                    } else {
                        salvarCredito(js, token)
                    }
                }
    
            }
        } else {
            // Materialize.toast('CPF InvÃ¡lido!', 5000);
            swal('Erro', 'CPF InvÃ¡lido!', 'error')
        }
    }
 

}

function finalizarVendaCNPJ(acao, src) {

    if (acao == 'fiscal') {
        document.getElementById("btn-cnpj").disabled = true
        $('#btn-cnpj').addClass('disabled')
        $('#btn-npj').addClass('spinner')

        $('#modal-cnpj-nota').modal('hide')


    }

    document.getElementById("finalizar-venda").disabled = true



    $('#modal-vendad').modal('hide');
    $('#modal-vendacartao').modal('hide');
    $('#modal-venda').modal('hide');




    $('#finalizar-venda').addClass('disabled')
    // verifica o valor da escolhido da bandeira
    var select = document.getElementById('bandeira_cartao');
    var option = select.options[select.selectedIndex];
    let bandeira = option.text

    var select = document.getElementById('bandeira_cartao1');
    var option = select.options[select.selectedIndex];
    let bandeira1 = option.text

    var select = document.getElementById('bandeira_cartao2');
    var option = select.options[select.selectedIndex];
    let bandeira2 = option.text


    var select = document.getElementById('bandeira_cartao3');
    var option = select.options[select.selectedIndex];
    let bandeira3 = option.text

    let validCNPJ = validaCNPJ();
    if (validCNPJ == true || acao != 'fiscal') {

        let valorRecebido = $('#valor_recebido').val();
        let troco = 0;
        if (valorRecebido.length > 0 && parseFloat(valorRecebido) > (TOTAL + VALORACRESCIMO + VALORBAIRRO - DESCONTO)) {
            troco = parseFloat(valorRecebido) - (TOTAL + VALORACRESCIMO + VALORBAIRRO - DESCONTO);
        }

        let desconto = DESCONTO;

        let obs = $('#obs').val();

        let js = {
            itens: ITENS,
            cliente: CLIENTE != null ? CLIENTE.id : null,
            valor_total: TOTAL,
            acrescimo: VALORBAIRRO + VALORACRESCIMO,
            troco: troco,
            tipo_pagamento: $('#tipo-pagamento').val(),
            forma_pagamento: '',
            dinheiro_recebido: valorRecebido,
            acao: acao,
            nome: $('#nome').val(),
            cpf: $('#cnpj').val(),
            delivery_id: $('#delivery_id').val(),
            pedido_local: $('#pedidoLocal').val() ? true : false,
            codigo_comanda: COMANDA,
            desconto: desconto ? desconto : 0,
            observacao: obs,
            tipo_pagamento_1: TIPOPAG1,
            tipo_pagamento_2: TIPOPAG2,
            tipo_pagamento_3: TIPOPAG3,
            valor_pagamento_1: VALORPAG1,
            valor_pagamento_2: VALORPAG2,
            valor_pagamento_3: VALORPAG3,
            agendamento_id: $('#agendamento_id').val(),
            orcamento_id: ORCAMENTOID,
            //bandeira_cartao: $('#bandeira_cartao').val() ? $('#bandeira_cartao').val() : '99',
            bandeira_cartao: bandeira,
            bandeira_cartao1: bandeira1,
            bandeira_cartao2: bandeira2,
            bandeira_cartao3: bandeira3,

            cAut_cartao: $('#cAut_cartao').val() ? $('#cAut_cartao').val() : '',
            cnpj_cartao: $('#cnpj_cartao').val() ? $('#cnpj_cartao').val() : '',
            descricao_pag_outros: $('#descricao_pag_outros').val() ? $('#descricao_pag_outros').val() : '',


        }

        console.log(js)
        let token = $('#_token').val();

        if (acao != 'credito') {
            $('#btn_nao_fiscal').addClass('disabled')
            $.ajax({
                type: 'POST',
                url: path + 'vendasCaixa/save',
                dataType: 'json',
                data: {
                    venda: js,
                    _token: token
                },
                success: function (e) {
                    if (acao == 'fiscal') {
                        $('#preloader2').css('display', 'block');
                        $('#preloader9').css('display', 'block');

                        emitirNFCe(e.id);

                        if (IMPRIMIR_CUPOM_AUTOMATICO == 1) {

                            alert(' Venda realalizada com Sucesso !');

                            printPage(path + 'nfce/imprimirNaoFiscal/' + e.id);


                            setTimeout(() => {
                                location.href = path + 'frenteCaixa';
                            }, 5000)


                        }


                    } else {



                        if (IMPRIMIR_CUPOM_AUTOMATICO == 1) {

                            alert(' Venda realalizada com Sucesso !');

                            printPage(path + 'nfce/imprimirNaoFiscal/' + e.id);


                            setTimeout(() => {
                                location.href = path + 'frenteCaixa';
                            }, 5000)


                        }
                        else if (IMPRIMIR_CUPOM_AUTOMATICO == 2) {

                            alert(' Venda realalizada com Sucesso !');

                            window.open(path + 'nfce/imprimirNaoFiscal/' + e.id, '_blank');



                            location.reload();



                        }
                        else if (IMPRIMIR_CUPOM_AUTOMATICO == 3) {

                            alert(' Venda realalizada com Sucesso !');

                            printPage(path + 'nfce/imprimirNaoFiscal/' + e.id);



                            setTimeout(() => {
                                location.href = path + 'frenteCaixa';
                            }, 4000)





                        }
                        else if (IMPRIMIR_CUPOM_AUTOMATICO == 4) {

                            alert(' Venda realalizada com Sucesso !');

                            window.open(path + 'nfce/imprimirNaoFiscal/' + e.id, '_blank');



                            location.reload();



                        }
                        else if (IMPRIMIR_CUPOM_AUTOMATICO == 0) {
                            swal({
                                title: "Sucesso",
                                text: "Deseja imprimir comprovante?",
                                icon: "success",
                                buttons: ["NÃ£o", 'Imprimir'],
                                dangerMode: true,
                            })
                                .then((v) => {
                                    if (v) {
                                        if (IMPRIMIR_CUPOM_AUTOMATICO == 0) {
                                            window.open(path + 'nfce/imprimirNaoFiscal/' + e.id, '_blank');
                                            location.href = path + 'frenteCaixa';


                                        } else {
                                            gerarArquivoPDV(e.id, 'nao_fiscal');
                                            printPage('http://localhost:8000/impressao_pdv/2261.pdf');
                                            //  printPage(path+ 'impressao_pdv/' + '2261.pdf');
                                            // printPage(path+ 'impressao_pdv/' + e.id + '.pdf');
                                            //  setTimeout(() => {
                                            //    location.href = path + 'frenteCaixa';
                                            //}, 1000)

                                            location.href = path + 'frenteCaixa';
                                        }
                                    } else {
                                        location.href = path + 'frenteCaixa';
                                    }
                                });



                        }
                        else {

                            swal({
                                title: "Sucesso",
                                text: "Deseja imprimir comprovante?",
                                icon: "success",
                                buttons: ["NÃ£o", 'Imprimir'],
                                dangerMode: true,
                            })
                                .then((v) => {
                                    if (v) {
                                        if (IMPRIMIR_CUPOM_AUTOMATICO == 0) {
                                            window.open(path + 'nfce/imprimirNaoFiscal/' + e.id, '_blank');
                                            location.href = path + 'frenteCaixa';


                                        } else {
                                            gerarArquivoPDV(e.id, 'nao_fiscal');
                                            printPage('http://localhost:8000/impressao_pdv/2261.pdf');
                                            //  printPage(path+ 'impressao_pdv/' + '2261.pdf');
                                            // printPage(path+ 'impressao_pdv/' + e.id + '.pdf');
                                            //  setTimeout(() => {
                                            //    location.href = path + 'frenteCaixa';
                                            //}, 1000)

                                            location.href = path + 'frenteCaixa';
                                        }
                                    } else {
                                        location.href = path + 'frenteCaixa';
                                    }
                                });


                        }



                    }

                },
                error: function (e) {
                    console.log(e)
                    $('#preloader2').css('display', 'none');
                    $('#preloader9').css('display', 'none');
                    $('#modal-venda').modal('hide')
                }

            });
        } else {
            // let valorUltrapassadoConfirma = true;
            // if(CLIENTE.limite_venda < TOTALEMABERTOCLIENTE+TOTAL){
            // 	valorUltrapassadoConfirma = confirm("Valor do limite de conta crÃ©dito ultrapassado, confirma venda?!");
            // }
            if (CLIENTE == null) {
                swal("Alerta", "Informe um cliente para conta crÃ©dito", "warning")
            } else {
                if (CLIENTE.limite_venda < parseFloat(CLIENTE.totalEmAberto) + TOTAL) {
                    swal({
                        text: "Valor do limite de conta crÃ©dito ultrapassado, confirma a venda?!",
                        title: 'Cuidado',
                        icon: 'warning',
                        buttons: ["NÃ£o", "Vender"],
                    }).then(sim => {
                        if (sim) {
                            salvarCredito(js, token)
                        } else {
                            $('#preloader2').css('display', 'none');
                            $('#preloader9').css('display', 'none');
                            $('#modal-venda').modal('hide')
                        }
                    });

                } else {
                    salvarCredito(js, token)
                }
            }

        }
    } else {
        // Materialize.toast('CPF InvÃ¡lido!', 5000);
        swal('Erro', 'CPF InvÃ¡lido!', 'error')
    }

}

function salvarCredito(js, token) {
    $.ajax({
        type: 'POST',
        url: path + 'vendas/salvarCrediario',
        dataType: 'json',
        data: {
            venda: js,
            _token: token
        },
        success: function (e) {
            $('#modal-venda').modal('hide')

            window.open(path + 'nfce/imprimirNaoFiscalCredito/' + e.id, '_blank');
            // $('#modal-credito').modal('open');
            // $('#evento-conta-credito').html('Venda salva na conta crÃ©dito do cliente ' +
            // 	CLIENTE.razao_social)
            swal("Sucesso", "Venda salva na conta crÃ©dito do cliente " + CLIENTE.razao_social, "success")
                .then(() => {
                    location.href = path + 'frenteCaixa'
                })

        },
        error: function (e) {
            console.log(e)
            $('#preloader2').css('display', 'none');
            $('#preloader9').css('display', 'none');
            $('#modal-venda').modal('hide')
        }

    });
}

function emitirNFCe(vendaId) {

    // $('#modal-venda').modal('close')
    // $('#preloader_'+vendaId).css('display', 'inline-block');
    //  $('#btn-cpf').addClass('spinner')
    // $('#btn-cpf').addClass('disabled')
    $('#btn_envia_' + vendaId).addClass('spinner')
    $('#btn_envia_' + vendaId).addClass('disabled')
    $('#btn_envia_grid_' + vendaId).addClass('spinner')
    $('#btn_envia_grid_' + vendaId).addClass('disabled')

    let token = $('#_token').val();
    $.ajax({
        type: 'POST',
        url: path + 'nfce/gerar',
        dataType: 'json',
        timeout: 65000,
        data: {
            vendaId: vendaId,
            _token: token
        },
        success: function (e) {
            console.log(e)
            hideTefOverlay();
            $('#modal-cpf-nota').modal('hide')
            // $('#preloader_'+vendaId).css('display', 'none');
            $('#btn-cpf').removeClass('spinner')
            $('#btn-cpf').removeClass('disabled')
            $('#btn_envia_' + vendaId).removeClass('spinner')
            $('#btn_envia_' + vendaId).removeClass('disabled')
            $('#btn_envia_grid_' + vendaId).removeClass('spinner')
            $('#btn_envia_grid_' + vendaId).removeClass('disabled')


            let recibo = e;
            if (typeof recibo === 'string' && recibo.indexOf('NuvemPendente:') === 0) {
                swal("Processando", "NFC-e enviada para Nuvem Fiscal e estÃ¡ pendente de autorizaÃ§Ã£o. ID: " + recibo.replace('NuvemPendente:', ''), "info")
                    .then(() => {
                        location.reload()
                    })
                return;
            }
            if (typeof recibo === 'string' && recibo.indexOf('NuvemAprovado:') === 0) {
                swal("Sucesso", "NFC-e autorizada via Nuvem Fiscal. Protocolo/ID: " + recibo.replace('NuvemAprovado:', ''), "success")
                    .then(() => {
                        location.reload()
                    })
                return;
            }
            let retorno = recibo.substring(0, 4);
            let mensagem = recibo.substring(5, recibo.length);
            if (retorno == 'Erro') {
                try {
                    let m = JSON.parse(mensagem);
                    // $('#modal-alert-erro').modal('open');
                    // $('#evento-erro').html("[" + m.protNFe.infProt.cStat + "] : " + m.protNFe.infProt.xMotivo)
                    swal("Algo deu errado!", "[" + m.protNFe.infProt.cStat + "] : " + m.protNFe.infProt.xMotivo, "error")
                        .then(() => {
                            location.reload()
                        })
                } catch {
                    // $('#modal-alert-erro').modal('open');
                    // $('#evento-erro').html(e)
                    swal("Algo deu errado!", e, "error").then(() => {
                        location.reload()
                    })
                }


            } else if (retorno == 'erro') {
                // $('#modal-alert-erro').modal('show');
                // $('#evento-erro').html("WebService sefaz em manutenÃ§Ã£o, falha de comunicaÃ§Ã£o SOAP")
                swal("Algo deu errado!", "WebService sefaz em manutenÃ§Ã£o, falha de comunicaÃ§Ã£o SOAP", "error").then(() => {
                    location.reload()
                })


            } else if (e == 'Apro') {
                swal("Cuidado", "Esta NF já está aprovada. Vamos abrir a impressão.", "warning").then(() => {
                    window.open(path + 'nfce/imprimir/' + vendaId, '_blank');
                    location.reload()
                })
                // NFC-e já aprovada: apenas imprime e recarrega.
                // 
            } else {
                $('#modal-venda').modal('hide')

                if (IMPRIMIR_CUPOM_AUTOMATICO != 1) {

                    swal("Sucesso", "NFCe gerada com sucesso RECIBO: " + recibo, "success")
                        .then(() => {
                            // window.open(path + 'nfce/imprimir/'+vendaId, '_blank');
                            // location.reload()

                            if (IMPRIMIR_CUPOM_AUTOMATICO == 0) {
                                // printPage(path + 'nfce/imprimirNaoFiscal/' + vendaId);
                                // window.open(path + 'nfce/imprimirNaoFiscal/' + vendaId, '_blank');


                                window.open(path + 'nfce/imprimir/' + vendaId, '_blank');
                                //location.href = path + 'frenteCaixa';
                                location.reload();

                                //  sucesso();
                            }

                            else if (IMPRIMIR_CUPOM_AUTOMATICO == 1) {

                                printPage(path + 'nfce/imprimirNaoFiscal/' + vendaId);


                                setTimeout(() => {
                                    location.href = path + 'frenteCaixa';
                                }, 5000)


                            }

                            else if (IMPRIMIR_CUPOM_AUTOMATICO == 2) {

                                //printPage(path + 'nfce/imprimirNaoFiscal/' + vendaId);
                                window.open(path + 'nfce/imprimirNaoFiscal/' + vendaId, '_blank');


                                //window.open(path + 'nfce/imprimir/' + vendaId, '_blank');
                                //location.href = path + 'frenteCaixa';
                                //  location.reload();

                                location.reload();



                            }

                            else if (IMPRIMIR_CUPOM_AUTOMATICO == 3) {


                                window.open(path + 'nfce/imprimir/' + vendaId, '_blank');
                                //location.href = path + 'frenteCaixa';
                                //  location.reload();

                                printPage(path + 'nfce/imprimirNaoFiscal/' + vendaId);
                                // window.open(path + 'nfce/imprimirNaoFiscal/' + vendaId, '_blank');


                                setTimeout(() => {
                                    location.href = path + 'frenteCaixa';
                                }, 4000)



                            } else if (IMPRIMIR_CUPOM_AUTOMATICO == 4) {

                                window.open(path + 'nfce/imprimir/' + vendaId, '_blank');

                                window.open(path + 'nfce/imprimirNaoFiscal/' + vendaId, '_blank');



                                setTimeout(() => {
                                    location.href = path + 'frenteCaixa';
                                }, 4000)


                            }

                            else {
                                gerarArquivoPDV(vendaId, 'fiscal');
                                setTimeout(() => {
                                    location.href = path + 'frenteCaixa';
                                }, 1000)
                            }
                        })

                }


                // if (IMPRIMIR_CUPOM_AUTOMATICO == 4) {

                //   swal("Sucesso", "ImpressÃ£o de Comprovante: " + recibo, "success")
                //  .then(() => {

                //   window.open(path + 'nfce/imprimir/' + vendaId, '_blank');

                // window.open(path + 'nfce/imprimirNaoFiscal/' + vendaId, '_blank');

                // location.reload();

                //})


                // }

                // $('#evento').html("NFCe gerada com sucesso RECIBO: " +recibo)

            }
            $('#btn_envia_' + vendaId).removeClass('spinner')
            $('#btn_envia_grid_' + vendaId).removeClass('spinner')
            // $('#preloader2').css('display', 'none');
            // $('#preloader9').css('display', 'none');
            // $('#preloader1').css('display', 'none');
        },
        error: function (err) {

            hideTefOverlay();
            // $('#preloader_'+vendaId).css('display', 'none');
            $('#btn-cpf').removeClass('spinner')
            $('#btn-cpf').removeClass('disabled')
            $('#btn_envia_' + vendaId).removeClass('spinner')
            $('#btn_envia_' + vendaId).removeClass('disabled')
            $('#btn_envia_grid_' + vendaId).removeClass('spinner')
            $('#btn_envia_grid_' + vendaId).removeClass('disabled')


            let js = err && err.responseJSON ? err.responseJSON : null;
            console.log(js || err);

            if (err && err.statusText === 'timeout') {
                swal("Tempo esgotado", "A SEFAZ demorou para responder. Tente novamente em instantes.", "warning");
            } else if (js && js.message) {
                swal("Algo errado", js.message, "error");
            } else if (Array.isArray(js)) {
                let msg = "";
                js.map((v) => {
                    msg += v + "\n";
                });
                swal("Erro", msg, "warning");
            } else {
                swal("Algo errado", "Erro ao enviar NFC-e", "error");
            }
            $('#btn-cpf').removeClass('spinner')


            // $('#preloader1').css('display', 'none');

        }
    })

}

function deletarVenda(id) {
    $.get(path + 'nfce/deleteVenda/' + id)
        .done((data) => {
            console.log(data)
        })
        .fail((err) => {
            console.log(err)
        })

}

function redireciona() {
    location.href = path + 'frenteCaixa';
}

function modalCancelar(id) {
    $('#modal').modal('show');
    $('#venda_id').val(id)
}

function modalCancelarnaofiscal(id) {
    $('#modalcancelarnaofiscal').modal('show');
    $('#venda_id').val(id)
}


function cancelar() {

    $('#btn_cancelar_nfce').addClass('spinner');

    let justificativa = $('#justificativa').val();
    let id = $('#venda_id').val();
    let token = $('#_token').val();
    $.ajax({
        type: 'POST',
        data: {
            id: id,
            justificativa: justificativa,
            _token: token
        },
        url: path + 'nfce/cancelar',
        dataType: 'json',
        success: function (e) {
            console.log(e)
            $('#btn_cancelar_nfce').removeClass('spinner');
            let msg = getCancelMessage(e, true);
            swal("Sucesso", msg, "success")
                .then((v) => {
                    location.reload()
                })

        },
        error: function (e) {
            $('#btn_cancelar_nfce').removeClass('spinner');

            console.log(e)
            let js = e.responseJSON;
            let msg = getCancelMessage(js, false);
            swal("Erro", msg, "warning")
        }
    });
}

function cancelarnaofiscal() {

    $('#btn_cancelar_nfce').addClass('spinner');

    let justificativa = $('#justificativanaofiscal').val();
    let id = $('#venda_id').val();
    let token = $('#_token').val();
    $.ajax({
        type: 'POST',
        data: {
            id: id,
            justificativa: justificativa,
            _token: token
        },
        url: path + 'frenteCaixa/deleteVenda',
        dataType: 'json',
        success: function (e) {
            console.log(e)
            $('#btn_cancelar_nfce').removeClass('spinner');

            // alert(e.retEvento.infEvento.xMotivo)
            swal("Sucesso", 'Venda Cancelada', "success")
                .then((v) => {
                    location.reload()
                })

        },
        error: function (e) {
            $('#btn_cancelar_nfce').removeClass('spinner');

            console.log(e)
            let js = e.responseJSON;
            if (e.status == 404) {
                // alert(js.mensagem)
                swal("Erro", js.mensagem, "warning")

            } else {
                // alert(js.retEvento.infEvento.xMotivo)
                swal("Erro", js.retEvento.infEvento.xMotivo, "warning")

                // Materialize.toast('Erro de comunicaÃ§Ã£o contate o desenvolvedor', 5000)

            }
        }
    });
}

function cancelarSubstituicao() {

    $('#btn_cancelar_nfce2').addClass('spinner');

    let justificativa = $('#justificativa2').val();
    let chaveRef = $('#chaveRef').val();
    let id = $('#venda_id').val();
    let token = $('#_token').val();
    $.ajax({
        type: 'POST',
        data: {
            id: id,
            justificativa: justificativa,
            chaveRef: chaveRef,
            _token: token
        },
        url: path + 'nfce/cancelarSubstituicao',
        dataType: 'json',
        success: function (e) {
            console.log(e)
            $('#btn_cancelar_nfce2').removeClass('spinner');
            let msg = getCancelMessage(e, true);
            swal("Sucesso", msg, "success")
                .then((v) => {
                    location.reload()
                })

        },
        error: function (e) {
            $('#btn_cancelar_nfce2').removeClass('spinner');

            let js = e.responseJSON;
            console.log(js)
            let msg = getCancelMessage(js, false);
            swal("Erro", msg, "warning")
        }
    });
}

function getCancelMessage(payload, successFlow) {
    if (!payload || typeof payload !== 'object') {
        return successFlow ? 'Operacao concluida.' : 'Falha ao processar cancelamento.';
    }

    // Formato legado SEFAZ
    if (payload.retEvento && payload.retEvento.infEvento && payload.retEvento.infEvento.xMotivo) {
        return payload.retEvento.infEvento.xMotivo;
    }

    // Formato novo Nuvem Fiscal
    if (payload.motivo_status) return payload.motivo_status;
    if (payload.mensagem) return payload.mensagem;
    if (payload.message) return payload.message;

    if (successFlow && payload.status) {
        let st = String(payload.status).toLowerCase();
        if (st === 'cancelado' || st === 'cancelada') return 'NFC-e cancelada com sucesso.';
        if (st === 'pendente' || payload.pendente === true) return 'Pedido de cancelamento enviado. Aguarde o processamento.';
    }

    return successFlow ? 'Operacao concluida.' : 'Falha ao processar cancelamento.';
}

function verItens() {
    $('#modal-itens').modal('open');
    let t = montaTabela();
    $('#body-modal').html(t);

}

function modalWhatsApp() {
    $('#modal-whatsApp').modal('show')
}

function enviarWhatsApp() {
    let celular = $('#celular').val();
    let texto = $('#texto').val();

    let mensagem = texto.split(" ").join("%20");

    let celularEnvia = '55' + celular.replace(' ', '');
    celularEnvia = celularEnvia.replace('-', '');
    let api = 'https://api.whatsapp.com/send?phone=' + celularEnvia +
        '&text=' + mensagem;
    window.open(api)
}

$('#btn-apontar2').click(() => {
    let tipo = $('#tipo-pesquisa-pedido').val()
    let cod = $('#cod-comanda2').val()
    if (tipo == 'comanda') {
        apontarComanda3(cod)
    } else {
        apontarMesa2(cod)
    }
})

function apontarMesa2(cod) {
    console.clear()

    $.get(path + 'mesas/emAberto')
        .done((data) => {
            DATAMESA = data
            console.log(DATAMESA)
            $('#modal-mesa').modal('show')
            $('.prt').css('display', 'none')
            verDetalhes(cod)
        })
        .fail((err) => {
            console.log(err)
            swal("Erro", "Erro ao buscar mesas", "error")
        })
}

function apontarComanda3(cod) {
    console.log(cod)
    $.get(path + 'pedidos/itensParaFrenteCaixa', { cod: cod })
        .done((success) => {

            montarComanda(success, (rs) => {
                console.log(rs)
                if (rs) {
                    if (success[0]) {
                        COMANDA = success[0].pedido_id;
                        $('#modal-comanda').modal('hide')
                        swal("", "Comanda setada!!!", "success")
                    } else {
                        swal("", "Comanda sem nenhum item!!", "warning")
                    }

                }
            })
        })
        .fail((err) => {
            if (err.status == 401) {
                swal("", "Nada encontrado!!!", "error")
            }
            console.log(err)
        })
}

function apontarComanda() {
    let cod = $('#cod-comanda').val()
    $.get(path + 'pedidos/itensParaFrenteCaixa', { cod: cod })
        .done((success) => {

            montarComanda(success, (rs) => {
                if (rs) {
                    if (success[0]) {
                        COMANDA = success[0].pedido_id;
                        $('#modal-comanda').modal('hide')
                        swal("", "Comanda setada!!!", "success")
                    } else {
                        swal("", "Comanda sem nenhum item!!", "warning")
                    }

                }
            })
        })
        .fail((err) => {
            if (err.status == 401) {
                swal("", "Nada encontrado!!!", "error")
            }
            console.log(err)
        })
}

function montarComanda(itens, call) {
    let cont = 0;
    itens.map((v) => {
        let nome = '';
        let valorUnit = 0;
        if (v.sabores.length > 0) {

            let cont = 0;
            v.sabores.map((sb) => {
                cont++;
                valorUnit = v.maiorValor;
                nome += sb.produto.produto.nome +
                    (cont == v.sabores.length ? '' : ' | ')
            })


        } else {
            nome = v.produto.nome;
            valorUnit = v.produto.valor_venda
        }

        let item = {
            cont: cont + 1,
            id: v.produto_id,
            nome: nome,
            quantidade: v.quantidade,
            valor: (parseFloat(valorUnit) + parseFloat(v.valorAdicional)) + "",
            pizza: v.maiorValor ? true : false,
            itemPedido: v.item_pedido
        }

        ITENS.push(item)
        TOTAL += parseFloat(item.valor) * (item.quantidade);
        cont++;
    });
    let t = montaTabela();

    atualizaTotal();
    $('#body').html(t);
    call(true)
}

$('#acrescimo').keyup(() => {
    let acrescimo = $('#acrescimo').val();
    if (acrescimo > 0) $('#desconto').val('0')

    let total = TOTAL + VALORBAIRRO;

    if (acrescimo.substring(0, 1) == "%") {

        let perc = acrescimo.substring(1, acrescimo.length);

        VALORACRESCIMO = total * (perc / 100);


    } else {
        acrescimo = acrescimo.replace(",", ".")
        VALORACRESCIMO = parseFloat(acrescimo)
    }

    if (acrescimo.length == 0) VALORACRESCIMO = 0;
    atualizaTotal();


})

function consultarNFCe(id) {
    $('#btn_consulta_' + id).addClass('spinner')
    $('#btn_consulta_grid_' + id).addClass('spinner')
    $.get(path + 'nfce/consultar/' + id)
        .done((data) => {
            $('#btn_consulta_' + id).removeClass('spinner')
            $('#btn_consulta_grid_' + id).removeClass('spinner')

            console.log(data)
            let js = JSON.parse(data)
            console.log(js)
            swal("Consulta", "[" + js.protNFe.infProt.cStat + "] " + js.protNFe.infProt.xMotivo, "success");
        })
        .fail((err) => {
            $('#btn_consulta_' + id).removeClass('spinner')
            $('#btn_consulta_grid_' + id).removeClass('spinner')
            console.log(err)
        })
}


$('#btn-plus').click((target) => {
    let quantidade = parseInt($('#quantidade').val());
    $('#quantidade').val(quantidade + 1)
})

$('#click-multi').click(() => {
    $('#modal-pag-mult').modal('show')
    $('#v-multi').html(formatReal(TOTAL))

    if (TOTAL <= 0) {
        swal("Erro", "Valor da venda deve ser maior que Zero!!", "error")
            .then(() => {
                $('#modal-pag-mult').modal('hide')
            })
    }
    $('#total-multi').html(formatReal(TOTAL))
})

$('#btn-ok-multi').click(() => {

    VALORPAG1 = $('#valor_pagamento_1').val() ? parseFloat($('#valor_pagamento_1').val()) : 0;
    VALORPAG2 = $('#valor_pagamento_2').val() ? parseFloat($('#valor_pagamento_2').val()) : 0;
    VALORPAG3 = $('#valor_pagamento_3').val() ? parseFloat($('#valor_pagamento_3').val()) : 0;

    TIPOPAG1 = $('#tipo_pagamento_1').val()
    TIPOPAG2 = $('#tipo_pagamento_2').val()
    TIPOPAG3 = $('#tipo_pagamento_3').val()
    if (TIPOPAG1 === '') {

        alert('Informar o Tipo de Pagamento 1')
        //swal("Aviso", 'Escolher a Bandeira do CartÃ£o', "warning")
        //swal.close();
        $('#tipo_pagamento_1').focus();
        return false;

    }

    let soma = VALORPAG1 + VALORPAG2 + VALORPAG3;
    soma = soma.toFixed(2);
    if (soma != TOTAL) {
        alert('Informado valor diferente, do total da Venda')
        $('#valor_pagamento_1').focus();
        return false;
    }

    $('#modal-pag-mult').modal('hide')
    console.log(VALORPAG1, VALORPAG2, VALORPAG3)
    console.log(TIPOPAG1, TIPOPAG2, TIPOPAG3)
    $('#modal-venda').modal('show')
})

$('#valor_pagamento_1').keyup((target) => {
    somaMultiplo();
})
$('#valor_pagamento_2').keyup((target) => {
    somaMultiplo();
})
$('#valor_pagamento_3').keyup((target) => {
    somaMultiplo();
})

$('#valor_pagamento_1').change((target) => {
    somaMultiplo();
})
$('#valor_pagamento_2').change((target) => {
    somaMultiplo();
})
$('#valor_pagamento_3').change((target) => {
    somaMultiplo();
})

function somaMultiplo() {
    let v1 = $('#valor_pagamento_1').val() ? parseFloat($('#valor_pagamento_1').val()) : 0;
    let v2 = $('#valor_pagamento_2').val() ? parseFloat($('#valor_pagamento_2').val()) : 0;
    let v3 = $('#valor_pagamento_3').val() ? parseFloat($('#valor_pagamento_3').val()) : 0;

    let soma = v1 + v2 + v3;
    soma = soma.toFixed(2);
    if (soma == TOTAL) {
        $('#btn-ok-multi').removeAttr('disabled')
    }
    else if (soma > TOTAL) {
        // swal("Alerta", "Valor de pagamentos ultrapassou o valor da venda", "warning")
        $('#btn-ok-multi').attr('disabled')
    } else {
        $('#btn-ok-multi').attr('disabled')
    }
}

$('#close-multi').click(() => {
    $('#modal-pag-mult').modal('hide')
    VALORPAG1 = 0
    VALORPAG2 = 0
    VALORPAG3 = 0
    TIPOPAG1 = ''
    TIPOPAG2 = ''
    TIPOPAG3 = ''
})
//modal-venda

function gerarArquivoPDV(id, tipo) {
    $.get(path + 'nfce/gerarArquivo', { id: id, tipo: tipo })
        .done((res) => {
            console.log(res)
        })
        .fail((fail) => {
            console.log(fail)
            swal("Erro", "Erro ao gerar arquivo de impressÃ£o", "error");
        })
}

function apontarOrcamento() {
    let id = $('#kt_select2_4').val();
    if (id) {
        buscaOrcamento(id)
    }
}

function buscaOrcamento(id) {
    $.get(path + 'orcamentoVenda/find/' + id)
        .done((res) => {
            ITENS = [];
            ORCAMENTOID = id
            let cont = 1;
            let total = 0
            res.itens.map((i) => {
                console.log(i)
                let item = {
                    cont: cont++,
                    id: i.produto.id,
                    nome: i.produto.nome,
                    quantidade: i.quantidade,
                    valor: parseFloat(i.valor),
                    pizza: false,
                    itemPedido: null
                }

                ITENS.push(item)
                total += parseFloat(i.valor) * i.quantidade
            })

            let t = montaTabela();

            TOTAL = total

            atualizaTotal();
            $('#body').html(t);
            let codigo_comanda = $('#codigo_comanda_hidden').val();

            $('#modal-orcamento').modal('hide')
        })
        .fail((err) => {
            console.log(err)
            swal("Erro", "Erro ao buscar orÃ§amento", "error")
        })
}

var DATAMESA = []
$('#btn-modal-mesa').click(() => {
    $.get(path + 'mesas/emAberto')
        .done((data) => {
            DATAMESA = data
            let html = ''
            data.map((rs) => {
                html += '<button style="margin-left: 5px;" class="btn btn-light-success" onclick="verDetalhes(' + rs.id + ')">' +
                    rs.nome + ' => R$ ' + rs.soma +
                    '</button>'
            })
            setTimeout(() => {
                $('#body-mesas').html(html)
            }, 500)
        })
        .fail((err) => {
            console.log(err)
            swal("Erro", "Erro ao buscar mesas", "error")
        })
})

var COMANDASMESA = []

function verDetalhes(id) {
    let js = DATAMESA.filter((x) => {
        return x.id == id
    })
    js = js[0]
    let comandas = js.pedidos
    console.log(comandas)
    COMANDASMESA = comandas
    $('#body-mesas').css('display', 'none')

    $('#comandas-mesa').css('display', 'block')
    let html = '<p>Comandas da mesa: ' + js.nome + '</p>'

    html += '<button class="btn btn-light-danger" onclick="verMesas()">Ver mesas</button><br>'

    html += '<br><div class="col-12">'
    comandas.map((mp) => {
        console.log(mp)
        if (mp.status == 0 && mp.desativado == 0) {
            html += '<button style="margin-left: 5px;" class="btn btn-light-info" onclick="verPedido(' + mp.id + ')">Comanda: ' +
                mp.comanda + ' => ' + formatReal(mp.soma) +
                '</button>'
        }
    })
    html += '</div>'
    $('#comandas-mesa').html(html)

}

function verMesas() {
    $('#modal-mesa').modal('hide')
    $('#comandas-mesa').css('display', 'none')
    $('#body-mesas').css('display', 'block')
    setTimeout(() => {
        $('#btn-modal-mesa').trigger('click')
    }, 500)

}

var COMANDAID = 0

function verPedido(id) {
    let js = COMANDASMESA.filter((x) => {
        return x.id == id
    })
    js = js[0]
    let itens = js.itens
    COMANDAID = id
    console.log(js)
    $('#modal-mesa').modal('hide')
    $('#modal-itens').modal('show')

    html = ''
    itens.map((i) => {
        html += '<tr class="datatable-row">'

        html += '<td class="datatable-cell" style="width: 300px;">'
        html += i.produto.nome
        html += '</td>'

        html += '<td class="datatable-cell" style="width: 120px;">'
        html += i.quantidade
        html += '</td>'

        html += '<td class="datatable-cell" style="width: 120px;">'
        html += i.valor
        html += '</td>'

        html += '<td class="datatable-cell" style="width: 120px;">'
        html += i.valor * i.quantidade
        html += '</td>'

        html += '</tr>'
    })

    $('#table-itens').html(html)
}

function apontarComanda2() {
    console.log("comanda: ", COMANDAID)
    if (COMANDAID > 0) {
        $.get(path + 'pedidos/itensParaFrenteCaixa2', { id: COMANDAID })
            .done((success) => {

                montarComanda(success, (rs) => {
                    if (rs) {
                        if (success[0]) {
                            COMANDA = success[0].pedido_id;
                            $('#modal-comanda').modal('hide')
                            swal("", "Comanda setada!!!", "success")
                        } else {
                            swal("", "Comanda sem nenhum item!!", "warning")
                        }

                    }
                })
                $('#modal-itens').modal('hide')
            })
            .fail((err) => {
                if (err.status == 401) {
                    swal("", "Nada encontrado!!!", "error")
                }
                swal("", "Erro inesperado!!!", "error")
                console.log(err)
                $('#modal-itens').modal('hide')
            })
    }
}

function montaAtalhos() {
    if (ATALHOS != null) {
        if (ATALHOS.finalizar != "") {
            Mousetrap.bind(ATALHOS.finalizar, function (e) {
                e.preventDefault();
                let v = $('#valor_recebido').val();
                v = v.replace(",", ".");
                if (ITENS.length > 0 && ((parseFloat(v) >= TOTAL))) {
                    $('#finalizar-venda').trigger('click');
                } else {
                    swal("Cuidado", "Venda sem itens, ou valor recebido inferior ao total da venda", "warning")
                }
            });
        }
        if (ATALHOS.reiniciar != "") {
            Mousetrap.bind(ATALHOS.reiniciar, function (e) {
                e.preventDefault();
                location.href = '/frenteCaixa'
            });
        }
        if (ATALHOS.editar_desconto != "") {
            Mousetrap.bind(ATALHOS.editar_desconto, function (e) {
                e.preventDefault();
                setaDesconto()
            });
        }
        if (ATALHOS.editar_acrescimo != "") {
            Mousetrap.bind(ATALHOS.editar_acrescimo, function (e) {
                e.preventDefault();
                setaAcresicmo()
            });
        }
        if (ATALHOS.editar_observacao != "") {
            Mousetrap.bind(ATALHOS.editar_observacao, function (e) {
                e.preventDefault();
                setaObservacao()
            });
        }
        if (ATALHOS.setar_valor_recebido != "") {
            Mousetrap.bind(ATALHOS.setar_valor_recebido, function (e) {
                e.preventDefault();
                $('#valor_recebido').focus();
                $('#valor_recebido').val(TOTAL)
                $('#finalizar-venda').removeAttr('disabled');

            });
        }

        if (ATALHOS.forma_pagamento_dinheiro != "") {
            Mousetrap.bind(ATALHOS.forma_pagamento_dinheiro, function (e) {
                e.preventDefault();
                $('#tipo-pagamento').val('01').change()
                $('#valor_recebido').val(TOTAL)
                $('#valor_recebido').focus();
            });
        }
        if (ATALHOS.forma_pagamento_debito != "") {
            Mousetrap.bind(ATALHOS.forma_pagamento_debito, function (e) {
                e.preventDefault();
                $('#tipo-pagamento').val('04').change()
                $('#valor_recebido').val(TOTAL)

                let v = $('#valor_recebido').val();
                v = v.replace(",", ".");
                if (ITENS.length > 0 && ((parseFloat(v) >= TOTAL))) {
                    $('#finalizar-venda').trigger('click');
                } else {
                    swal("Cuidado", "Venda sem itens, ou valor recebido inferior ao total da venda", "warning")
                }
            });
        }
        if (ATALHOS.forma_pagamento_credito != "") {
            Mousetrap.bind(ATALHOS.forma_pagamento_credito, function (e) {
                e.preventDefault();
                $('#tipo-pagamento').val('03').change()
                $('#valor_recebido').val(TOTAL)
                let v = $('#valor_recebido').val();
                v = v.replace(",", ".");
                if (ITENS.length > 0 && ((parseFloat(v) >= TOTAL))) {
                    $('#finalizar-venda').trigger('click');
                } else {
                    swal("Cuidado", "Venda sem itens, ou valor recebido inferior ao total da venda", "warning")
                }

            });
        }
        if (ATALHOS.forma_pagamento_pix != "") {
            Mousetrap.bind(ATALHOS.forma_pagamento_pix, function (e) {
                e.preventDefault();
                $('#tipo-pagamento').val('17').change()
                $('#valor_recebido').val(TOTAL)
                let v = $('#valor_recebido').val();
                v = v.replace(",", ".");
                if (ITENS.length > 0 && ((parseFloat(v) >= TOTAL))) {
                    $('#finalizar-venda').trigger('click');
                } else {
                    swal("Cuidado", "Venda sem itens, ou valor recebido inferior ao total da venda", "warning")
                }
            });
        }

        if (ATALHOS.setar_leitor != "") {
            Mousetrap.bind(ATALHOS.setar_leitor, function (e) {
                e.preventDefault();
                $('#codBarras').focus()
            });
        }
    }
}

$('#cancelar-subtituicao').click(() => {
    $('#modal').modal('hide')
    $('#modal2').modal('show')
})


$('#salvar-cartao1').click(() => {
    //var select = document.getElementById('bandeira_cartao');
    //var option = select.options[select.selectedIndex];
    //  let bandeira = option.text




    let bandeira_cartao1 = ($('#bandeira_cartao1').val());
    if (bandeira_cartao1 === '') {

        alert('Escolher a Bandeira do CartÃ£o')
        //swal("Aviso", 'Escolher a Bandeira do CartÃ£o', "warning")
        //swal.close();
        $('#bandeira_cartao1').focus();
        return false;

    }


})

$('#salvar-cartao2').click(() => {
    //var select = document.getElementById('bandeira_cartao');
    //var option = select.options[select.selectedIndex];
    //  let bandeira = option.text




    let bandeira_cartao2 = ($('#bandeira_cartao2').val());
    if (bandeira_cartao2 === '') {

        alert('Escolher a Bandeira do CartÃ£o')
        //swal("Aviso", 'Escolher a Bandeira do CartÃ£o', "warning")
        //swal.close();
        $('#bandeira_cartao2').focus();
        return false;

    }


})

$('#salvar-cartao3').click(() => {
    //var select = document.getElementById('bandeira_cartao');
    //var option = select.options[select.selectedIndex];
    //  let bandeira = option.text




    let bandeira_cartao3 = ($('#bandeira_cartao3').val());
    if (bandeira_cartao3 === '') {

        alert('Escolher a Bandeira do CartÃ£o')
        //swal("Aviso", 'Escolher a Bandeira do CartÃ£o', "warning")
        //swal.close();
        $('#bandeira_cartao3').focus();
        return false;

    }


})


$(document).ready(function () {

    if ($.fn && $.fn.validate) {
        $("#formCartao").validate({

            rules: {

                bandeira_cartao: {
                    required: true
                }
            },
            messages: {
                bandeira_cartao: {
                    required: 'Campo Requerido.'
                }

            },

        })
    }


})

$(document).on('keyup', function (e) {
    var keyCode = e.keyCode || e.which;
    if (keyCode === 113)
        $('#finalizar-venda').trigger('click');

    if (keyCode === 119) {

        $('#modal-cpf-nota').modal('show');

        $('#modal-cpf-nota').on('shown.bs.modal', function () {
            $('#cpf').focus();
        })
    }

    if (keyCode === 120) {

        $('#modal-cnpj-nota').modal('show');

        $('#modal-cnpj-nota').on('shown.bs.modal', function () {
            $('#cnpj').focus();
        })

    }
});

function salvarCliente() {
    let js = {
        razao_social: $('#razao_social2').val(),
        nome_fantasia: $('#nome_fantasia2').val() ? $('#nome_fantasia2').val() : '',
        rua: $('#rua').val() ? $('#rua').val() : '',
        cpf_cnpj: $('#cpf_cnpj').val() ? $('#cpf_cnpj').val() : '',
        ie_rg: $('#ie_rg').val() ? $('#ie_rg').val() : '',
        bairro: $('#bairro').val() ? $('#bairro').val() : '',
        cep: $('#cep').val() ? $('#cep').val() : '',
        consumidor_final: $('#consumidor_final').val() ? $('#consumidor_final').val() : '',
        contribuinte: $('#contribuinte').val() ? $('#contribuinte').val() : '',
        limite_venda: $('#limite_venda').val() ? $('#limite_venda').val() : '',
        cidade_id: $('#kt_select2_4').val() ? $('#kt_select2_4').val() : '',
        telefone: $('#telefone').val() ? $('#telefone').val() : '',
        celular: $('#celular').val() ? $('#celular').val() : '',
    }

    if (js.razao_social == '') {
        swal("Erro", "Informe a razÃ£o social", "warning")
    }

    if (js.razao_social == '') {
        swal("Erro", "Informe a razÃ£o social", "warning")
    } else {

        let token = $('#_token').val();
        $.post(path + 'clientes/quickSave', {
            _token: token,
            data: js
        })
            .done((res) => {
                CLIENTE = res;
                console.log(res)
                $('#cliente').append('<option value="' + res.id + '">' +
                    res.razao_social + '</option>').change();
                $('#cliente').val(res.id).change();
                swal("Sucesso", "Cliente adicionado!!", 'success')
                    .then(() => {
                        $('#modal-clientenovo').modal('hide')
                    })
                location.href = path + 'frenteCaixa';
            })
            .fail((err) => {
                console.log(err)
            })
    }

    console.log(js)
}

function closePrint() {
    document.body.removeChild(this.__container__);
}


function setPrint() {
    this.contentWindow.__container__ = this;
    this.contentWindow.onbeforeunload = closePrint;
    this.contentWindow.onafterprint = closePrint;
    this.contentWindow.focus(); // Required for IE
    this.contentWindow.print();
}

function printPage(sURL) {
    var oHiddFrame = document.createElement("iframe");
    oHiddFrame.onload = setPrint;
    oHiddFrame.style.position = "fixed";
    oHiddFrame.style.right = "0";
    oHiddFrame.style.bottom = "0";
    oHiddFrame.style.width = "0";
    oHiddFrame.style.height = "0";
    oHiddFrame.style.border = "0";
    oHiddFrame.src = sURL;
    document.body.appendChild(oHiddFrame);
}

function sucesso() {
    $('#content').css('display', 'none');
    $('#anime').css('display', 'block');



    setTimeout(() => {
        location.href = path + 'frenteCaixa';
    }, 8000)
}


function reprocessamento() {
    swal({
        title: "AtenÃ§Ã£o",
        text: "Deseja realmente reprocessar as vendas?",
        icon: "warning",
        buttons: ["NÃ£o", 'Sim'],
        dangerMode: true,
    })
        .then((v) => {
            if (v) {
                let token = $('#_token').val();

                $('#loadingModal').modal('show');

                let dataInicial = document.getElementsByName('data_inicial')[0].value;
                let dataFinal = document.getElementsByName('data_final')[0].value;
                let statusFiltro = $('#status_reprocessamento').val() || 'rejeitado_pendente';

                $.ajax({
                    type: 'POST',
                    url: path + 'nfce/reprocessamento',
                    dataType: 'json',
                    data: {
                        dataIni: dataInicial,
                        dataFim: dataFinal,
                        statusFiltro: statusFiltro,
                        _token: token
                    },
                    success: function (e) {
                        $('#loadingModal').modal('hide');
                        swal("Sucesso", (typeof e === 'string' ? e : "Reprocessamento concluído com sucesso."), "success")
                            .then(() => {
                                location.reload();
                            });
                    },
                    error: function (e) {
                        $('#loadingModal').modal('hide');
                        swal("Erro", e.responseText, "error");
                        //$('#sucessoModal').modal('show');
                    }

                });
            }
        });


    //alert('teste');
}

