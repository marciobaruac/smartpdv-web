-- ============================================================================
--  Migração de vendas de PDV do dia 2026-09-15
--  Origem : pantanal27092026   (banco do .env)
--  Destino: pantanal4   (troque com Find/Replace `pantanal4` se quiser outro banco)
--  Estratégia: ids recebem +1000000 (offset) para não colidir com o destino.
--  Todos os ids do conjunto usam o MESMO offset, então as FKs continuam ligadas.
--  Rode no phpMyAdmin/HeidiSQL/mysql. É uma transação: ou entra tudo, ou nada.
-- ============================================================================
SET SESSION sql_mode='';            -- dados legados têm datas 0000-00-00
SET @off := 1000000;
START TRANSACTION;

-- venda_caixas
INSERT INTO `pantanal4`.`venda_caixas`
  (`id`, `cliente_id`, `usuario_id`, `natureza_id`, `data_registro`, `valor_total`, `dinheiro_recebido`, `troco`, `desconto`, `acrescimo`, `forma_pagamento`, `tipo_pagamento`, `estado`, `NFcNumero`, `chave`, `path_xml`, `nome`, `cpf`, `observacao`, `pedido_delivery_id`, `tipo_pagamento_1`, `valor_pagamento_1`, `tipo_pagamento_2`, `valor_pagamento_2`, `tipo_pagamento_3`, `valor_pagamento_3`, `bandeira_cartao`, `cnpj_cartao`, `cAut_cartao`, `descricao_pag_outros`, `created_at`, `updated_at`, `txcartao`, `txantcartao`, `custo_total`, `imposto_total`, `bandeira_cartao_2`, `bandeira_cartao_1`, `bandeira_cartao_3`, `ativo`, `motivo_cancelamento`, `data_cancelamento`, `intencao_venda_id`)
SELECT
  `id`+@off, `cliente_id`, `usuario_id`, `natureza_id`, `data_registro`, `valor_total`, `dinheiro_recebido`, `troco`, `desconto`, `acrescimo`, `forma_pagamento`, `tipo_pagamento`, `estado`, `NFcNumero`, `chave`, `path_xml`, `nome`, `cpf`, `observacao`, `pedido_delivery_id`, `tipo_pagamento_1`, `valor_pagamento_1`, `tipo_pagamento_2`, `valor_pagamento_2`, `tipo_pagamento_3`, `valor_pagamento_3`, `bandeira_cartao`, `cnpj_cartao`, `cAut_cartao`, `descricao_pag_outros`, `created_at`, `updated_at`, `txcartao`, `txantcartao`, `custo_total`, `imposto_total`, `bandeira_cartao_2`, `bandeira_cartao_1`, `bandeira_cartao_3`, `ativo`, `motivo_cancelamento`, `data_cancelamento`, `intencao_venda_id`
FROM `pantanal27092026`.`venda_caixas`
WHERE DATE(data_registro)='2026-09-15';

-- item_venda_caixas
INSERT INTO `pantanal4`.`item_venda_caixas`
  (`id`, `venda_caixa_id`, `produto_id`, `item_pedido_id`, `quantidade`, `valor`, `observacao`, `created_at`, `updated_at`, `custo_total`)
SELECT
  `id`+@off, `venda_caixa_id`+@off, `produto_id`, `item_pedido_id`, `quantidade`, `valor`, `observacao`, `created_at`, `updated_at`, `custo_total`
FROM `pantanal27092026`.`item_venda_caixas`
WHERE venda_caixa_id IN (SELECT id FROM `pantanal27092026`.`venda_caixas` WHERE DATE(data_registro)='2026-09-15');

-- conta_recebers
INSERT INTO `pantanal4`.`conta_recebers`
  (`id`, `venda_id`, `usuario_id`, `categoria_id`, `referencia`, `valor_integral`, `valor_recebido`, `date_register`, `data_vencimento`, `data_recebimento`, `status`, `created_at`, `updated_at`, `forma_recebimento`, `venda_caixa_id`, `ativo`)
SELECT
  `id`+@off, `venda_id`, `usuario_id`, `categoria_id`, `referencia`, `valor_integral`, `valor_recebido`, `date_register`, `data_vencimento`, `data_recebimento`, `status`, `created_at`, `updated_at`, `forma_recebimento`, `venda_caixa_id`+@off, `ativo`
FROM `pantanal27092026`.`conta_recebers`
WHERE venda_caixa_id IN (SELECT id FROM `pantanal27092026`.`venda_caixas` WHERE DATE(data_registro)='2026-09-15');

-- estoque_movs
INSERT INTO `pantanal4`.`estoque_movs`
  (`id`, `produto_id`, `tipomov`, `origem`, `descricao`, `quantidade`, `valor`, `usuario_id`, `created_at`, `updated_at`)
SELECT
  `id`+@off, `produto_id`, `tipomov`, `origem`, `descricao`, `quantidade`, `valor`, `usuario_id`, `created_at`, `updated_at`
FROM `pantanal27092026`.`estoque_movs`
WHERE id IN (SELECT estoquemov_id FROM `pantanal27092026`.`estoquemovpdvs` WHERE estoquemov_id IS NOT NULL AND estoquepdv_id IN (SELECT id FROM `pantanal27092026`.`item_venda_caixas` WHERE venda_caixa_id IN (SELECT id FROM `pantanal27092026`.`venda_caixas` WHERE DATE(data_registro)='2026-09-15')));

-- estoquemovpdvs
INSERT INTO `pantanal4`.`estoquemovpdvs`
  (`id`, `estoquemov_id`, `estoquepdv_id`, `updated_at`, `created_at`)
SELECT
  `id`+@off, `estoquemov_id`+@off, `estoquepdv_id`+@off, `updated_at`, `created_at`
FROM `pantanal27092026`.`estoquemovpdvs`
WHERE estoquepdv_id IN (SELECT id FROM `pantanal27092026`.`item_venda_caixas` WHERE venda_caixa_id IN (SELECT id FROM `pantanal27092026`.`venda_caixas` WHERE DATE(data_registro)='2026-09-15'));

-- Confira os números antes de confirmar:
SELECT (SELECT COUNT(*) FROM `pantanal4`.`venda_caixas` WHERE id>=@off) AS venda_caixas,
       (SELECT COUNT(*) FROM `pantanal4`.`item_venda_caixas` WHERE id>=@off) AS itens,
       (SELECT COUNT(*) FROM `pantanal4`.`conta_recebers` WHERE id>=@off) AS contas,
       (SELECT COUNT(*) FROM `pantanal4`.`estoque_movs` WHERE id>=@off) AS estoque_movs,
       (SELECT COUNT(*) FROM `pantanal4`.`estoquemovpdvs` WHERE id>=@off) AS estoquemovpdvs;

-- Se os números baterem (95 / 139 / 96 / 139 / 139), confirme:
COMMIT;
-- Se algo estiver errado, use no lugar do COMMIT:
-- ROLLBACK;
