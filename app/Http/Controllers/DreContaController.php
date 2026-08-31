<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DreConta;

class DreContaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function __construct(){
        $this->middleware(function ($request, $next) {
         $value = session('user_logged');
         if(!$value){
            return redirect("/login");
        }
        return $next($request);
    });
    }
    
    public function index()
    {
        $dreconta = DreConta::all();
        return view('dreConta/list')
        ->with('drecontas', $dreconta)
        ->with('title', 'Contas do DRE');
        
    }

    public function new(){
        $tipo = DreConta::cTipo();
        return view('dreConta/register')
        ->with('title', 'Cadastrar Conta do DRE')
        ->with('tipo', $tipo);
    }

    
    public function save(Request $request){
        $conta = new DreConta();
        $this->_validate($request);

        $result = $conta->create($request->all());

        if($result){
            session()->flash("mensagem_sucesso", "Conta cadastrada com sucesso.");
        }else{
            session()->flash('mensagem_erro', 'Erro ao cadastrar Conta.');
        }
        
        return redirect('/contaDre');
    }


    private function _validate(Request $request){
        $rules = [
            'nome' => 'required|max:50'
        ];

        $messages = [
            'nome.required' => 'O campo nome é obrigatório.',
            'nome.max' => '50 caracteres maximos permitidos.'
        ];
        $this->validate($request, $rules, $messages);
    }


    public function update(Request $request){
        $drecontas = new DreConta();

        $id = $request->input('id');
        $resp = $drecontas
        ->where('id', $id)->first(); 

        $this->_validate($request);
        

        $resp->nome = $request->input('nome');
        $resp->tipo = $request->input('tipo');
        

        $result = $resp->save();
        if($result){
            session()->flash('mensagem_sucesso', 'Categoria editada com sucesso!');
        }else{
            session()->flash('mensagem_erro', 'Erro ao editar categoria!');
        }
        
        return redirect('/contaDre'); 
    }


    public function edit($id){
        $dreconta = new DreConta(); //Model
        $tipo = DreConta::cTipo();

        $resp =  $dreconta
        ->where('id', $id)->first();  

        return view('DreConta/register')
        ->with('dreconta', $resp)
        ->with('tipo', $tipo)
        ->with('title', 'Editar Contas do DRE');

    }

}
