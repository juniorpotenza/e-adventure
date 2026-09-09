# Arquitetura WooAdventure

## Fonte de dados canônica

O WooCommerce continua responsável por carrinho, pagamento e pedido. O plugin é responsável pelos dados operacionais:

- **Saída:** data, capacidade, guia e condições operacionais de uma atividade.
- **Participante:** pessoa vinculada ao pedido e, futuramente, à reserva de uma saída.
- **Reserva:** vínculo transacional entre item de pedido e saída; será incluída antes de o checkout passar a controlar capacidade.
- **Documento:** evidência versionada de aceite e termos.
- **Evento de auditoria:** ação administrativa ou operacional sem dados pessoais sensíveis.

Durante a transição, as leituras de metadados históricos de pedidos permanecem ativas. Recursos novos devem gravar no modelo canônico e podem espelhar dados legados somente quando necessário para compatibilidade.

## Segurança e privacidade

- Cada endpoint deve validar autenticação, capability e nonce quando aplicável.
- Funções de guia usam capabilities `wcai_*`, nunca `manage_woocommerce` como substituto permanente.
- CPFs, e-mails, nascimentos e assinaturas não entram no contexto de auditoria.
- O aplicativo de campo deve manter somente dados mínimos, mascarar identificadores e expirar o cache local.

## Evolução

1. Saídas, capabilities e auditoria.
2. Reservas e capacidade transacional.
3. Checkout, agenda e scanner usando reservas.
4. Documentos jurídicos versionados, incidentes, seguro e comunicação.
