function pagar() {
  
    alert('Escolher a Bandeira do Cartão !')
}


function setaBaixa(id,valorbaixa) {
    
    $('#idbaixa').val(id);
    $('#valorbaixa').val(valorbaixa);

    const hoje = new Date()
    const dia = hoje.getDate().toString().padStart(2,'0')
    const mes = String(hoje.getMonth() + 1).padStart(2,'0')
    const ano = hoje.getFullYear()
    const dataAtual = dia + "/" + mes + "/" + ano; 
                       
    
    $('.data_pagamento').val(dataAtual)
    
    $('#modal-baixa').modal('show');

}

function salvarbaixa(id){
    let token = $('#_token').val();
    let datateste= $('.data_pagamento').val();
 

    $.ajax({

        type: 'POST',
        url: path + 'contasPagar/pagar',
        dataType: 'json',
        data:{ idbaixa:  $('#idbaixa').val(), databaixa:  $('.data_pagamento').val(), valorpago:  $('#valorbaixa').val(), _token: token
         },
        
        
        success: function(e) {
      //      console.log(data)
        
          
            $('#modal-obs').modal('hide');
            $('#idbaixa').val('');
             swal("Sucesso", " Baixa Realizada!", "success")
             location.reload();

        },
        error: function(e) {
            console.log(e)
            swal("Erro", "Erro ao realizar Baixa!", "error")

        }


    })

}