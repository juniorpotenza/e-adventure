# Armazenamento de dados e estado

## Código e histórico de desenvolvimento

- O código-fonte do plugin está em `wooadventure-integration/`.
- O planejamento e as decisões técnicas ficam em `docs/`.
- Cada entrega é salva em um commit Git no branch atual. O histórico pode ser consultado com `git log --oneline`.
- Pull requests registram o resumo e a validação de cada entrega; eles não substituem o código nem o histórico Git.

## Dados no WordPress

| Dado | Local de armazenamento | Observação |
| --- | --- | --- |
| Saída | Post type privado `wcai_departure` e metadados `_wcai_*` | Produto, início, capacidade, guia e status. |
| Reserva | Tabela `{prefix}wcai_reservations` | Quantidade, status, expiração e vínculos com pedido/item. |
| Participante | Tabela `{prefix}wcai_participantes` | CPF, nascimento, ticket e check-in/check-out. |
| Auditoria | Tabela `{prefix}wcai_audit_log` | Não deve guardar CPF, e-mail, nascimento ou assinatura no contexto. |
| Pedido e pagamento | Tabelas nativas do WooCommerce/WordPress | O WooCommerce continua como origem de carrinho, pagamento e pedido. |
| Assinatura | CPT `wcai_assinatura`, post meta e diretório de uploads do WordPress | O arquivo PNG fica em `wp-content/uploads/`; referências ficam no post meta. |
| Configurações | Tabela `{prefix}options` | Prefixo `wcai_`, incluindo versão de migração e configurações administrativas. |

`{prefix}` é o prefixo configurado na instalação WordPress, normalmente `wp_`. Assim, por exemplo, a tabela de reservas costuma ser `wp_wcai_reservations`.

## Dados que não devem ser permanentes

- Fila e manifesto offline do scanner ficam no `localStorage` do navegador/dispositivo até sincronização ou limpeza.
- Nonces e sessões são temporários e não devem ser usados como fonte de verdade operacional.
- Bloqueios de vaga devem expirar e não podem ser tratados como reserva confirmada sem pedido válido.

## Backup e retenção

O operador deve manter backup do banco WordPress e de `wp-content/uploads/`. Políticas de retenção, anonimização e exclusão para dados pessoais e documentos jurídicos devem ser definidas antes do lançamento do dossiê jurídico.
