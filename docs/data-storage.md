# Armazenamento de dados e estado

## Código e histórico de desenvolvimento

- O código-fonte do plugin está em wooadventure-integration/.
- O planejamento e as decisões técnicas ficam em docs/.
- Cada entrega é salva em um commit Git no branch atual. O histórico pode ser consultado com git log --oneline.
- Pull requests registram o resumo e a validação de cada entrega; eles não substituem o código nem o histórico Git.

## Dados no WordPress

| Dado | Local de armazenamento | Observação |
| --- | --- | --- |
| Saída | Post type privado wcai_departure e metadados _wcai_* | Produto, início, capacidade, guia e status. |
| Reserva | Tabela {prefix}wcai_reservations | Quantidade, status, expiração e vínculos com pedido/item. |
| Participante | Tabela {prefix}wcai_participantes | CPF, nascimento, ticket, check-in/check-out e vínculo canônico com reservation_id. |
| Documento jurídico | CPT wcai_legal_document + metadados de versão/vigência/hash | O hash identifica a combinação salva de título, conteúdo e versão. |
| Aceite jurídico | Tabela {prefix}wcai_legal_acceptances | Participante, reserva, saída, documento/versão/hash, responsável legal e evidências técnicas em hash. |
| Assinatura | CPT wcai_assinatura, post meta e diretório de uploads do WordPress | Ligada ao participant_id e reservation_id; o pedido fica como referência histórica. |
| Auditoria | Tabela {prefix}wcai_audit_log | Não deve guardar CPF, e-mail, nascimento ou assinatura no contexto. |
| Pedido e pagamento | Tabelas nativas do WooCommerce/WordPress | O WooCommerce continua como origem de carrinho, pagamento e pedido. |
| Configurações | Tabela {prefix}options | Prefixo wcai_, incluindo versão de migração e configurações administrativas. |

{prefix} é o prefixo configurado na instalação WordPress, normalmente wp_. Assim, por exemplo, a tabela de reservas costuma ser wp_wcai_reservations.

## Dados que não devem ser permanentes

- Fila e manifesto offline do scanner ficam no localStorage do navegador/dispositivo até sincronização ou limpeza.
- Nonces e sessões são temporários e não devem ser usados como fonte de verdade operacional.
- Bloqueios de vaga devem expirar e não podem ser tratados como reserva confirmada sem pedido válido.

## Backup e retenção

O operador deve manter backup do banco WordPress e de wp-content/uploads/. Políticas de retenção, anonimização e exclusão para dados pessoais, assinaturas e documentos jurídicos devem ser definidas antes do lançamento do dossiê jurídico.

## Relacionamento operacional e jurídico

A relação operacional é:

Departure → Reservation → Participant

A relação jurídica é:

Participant → Reservation → Legal Acceptance → Document

A tabela de participantes mantém order_id e item_id para rastreabilidade do WooCommerce. A tabela de aceite também mantém order_id e departure_id como evidências históricas, mas participant_id + reservation_id são os vínculos canônicos.

Migrações de upgrade preenchem reservation_id de participantes e aceites quando existe correspondência confiável na base operacional.
