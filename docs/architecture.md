# Arquitetura WooAdventure

## Fonte de dados canônica

O WooCommerce continua responsável por carrinho, pagamento e pedido. O plugin é responsável pelos dados operacionais:

- **Saída:** data, capacidade, guia e condições operacionais de uma atividade.
- **Reserva:** vínculo transacional entre item de pedido e saída, com quantidade, estado e expiração de bloqueio.
- **Participante:** pessoa vinculada a uma reserva operacional; o pedido/item permanece como referência transacional e histórica.
- **Documento jurídico:** texto/versionamento que pode ser aceito pelo participante.
- **Aceite jurídico:** evidência do participante aceitando uma versão exata do documento, no contexto de uma reserva.
- **Assinatura:** evidência gráfica ligada ao Participant/Reservation; não é a fonte de verdade da versão do documento.
- **Evento de auditoria:** ação administrativa ou operacional sem dados pessoais sensíveis.

Durante a transição, leituras de metadados históricos de pedidos permanecem ativas. Recursos novos devem gravar no modelo canônico e podem espelhar dados legados somente quando necessário para compatibilidade.

## Relação operacional canônica

Departure → Reservation → Participant

O relacionamento jurídico deriva dessa identidade:

Participant → Reservation → Legal Acceptance → Document Version

O order_id continua armazenado para rastreabilidade do WooCommerce, mas não deve ser usado como identidade operacional primária do participante.

## Estado do participante

O estado operacional e o estado jurídico são independentes.

**Operacional**
- aguardando/presente conforme fluxo operacional;
- confirmado;
- check-in;
- checkout;
- cancelado.

**Jurídico**
- pending: não existe aceite da versão vigente;
- accepted: a versão vigente está aceita;
- version_outdated: existe aceite histórico, mas para uma versão diferente da vigente.

A coluna legada termo_assinado ainda é lida pela interface de Minha Conta. Ela não é gravada como fonte de verdade. O aceite versionado em wcai_legal_acceptances é a fonte de verdade para documentos. Remova a leitura de termo_assinado antes de eliminar a coluna.

## Estados da reserva

| Estado | Significado | Ocupa vaga |
| --- | --- | --- |
| hold | Bloqueio temporário criado no checkout. Expira em 15 minutos. | Sim |
| pending | Reserva aguardando pagamento. | Sim |
| confirmed | Pagamento confirmado. Só este estado assina, gera ingresso e aparece na agenda e no scanner. | Sim |
| cancelled | Cancelada. Reconfirmar exige nova checagem de vagas. | Não |
| refunded | Reembolsada. | Não |
| expired | Hold vencido. O cron wcai_expire_holds roda a cada 5 minutos. | Não |

A passagem de hold para confirmed ocorre dentro do GET_LOCK da saída, com nova checagem de capacidade.

## Segurança e privacidade

- Cada endpoint deve validar nonce e aplicar controle de acesso compatível com sua superfície.
- Funções de guia usam capabilities wcai_*, nunca manage_woocommerce como substituto permanente.
- CPFs, e-mails, nascimentos, IPs e assinaturas não entram em texto livre de auditoria.
- Evidências de IP e user-agent devem ser armazenadas como hash quando necessárias.
- O aplicativo de campo deve manter somente dados mínimos e expirar o cache local.
- O fluxo público de assinatura usa nonce e sessão de assinatura vinculada por HMAC ao pedido/participante. O cookie HMAC leva expiração (exp).
- Prova de posse: o cliente envia o order_key do pedido, além do CPF. O servidor devolve o mesmo erro para vínculo inválido e sessão inválida.
- O PNG da assinatura fica em wp-content/uploads/wcai-private/. Um endpoint com capability serve o arquivo.
- Menor de 18 anos exige nome e CPF do responsável. O servidor grava os dois no aceite (CPF em hash).
- Dados pessoais têm exportador e eliminador do WordPress (LGPD).

## Evolução

1. Saídas, capabilities, auditoria e reservas.
2. Checkout, agenda e scanner usando reservas.
3. Núcleo jurídico versionado e identidade canônica do participante.
4. Portal do participante, ficha pré-atividade, incidentes, seguro e comunicação.

Consulte docs/data-storage.md para a localização de cada classe de dado, dados temporários e responsabilidades de backup.
