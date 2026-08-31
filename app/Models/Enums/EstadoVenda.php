<?php

namespace App\Models\Enums;


abstract class EstadoVenda {
  const DISPONIVEL = 'DISPONIVEL';
  const REJEITADO = 'REJEITADO';
  const CONTINGENCIA = 'CONTINGENCIA';
  const APROVADO = 'APROVADO';
  const CANCELADO = 'CANCELADO';
}
