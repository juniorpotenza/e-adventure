# Mapa oficial de telas — versão inicial

## Estrutura global
1. Cabeçalho persistente: marca do sistema, título, notificações e usuário.
2. Menu lateral: Início, Minha Carteira, Ocorrências, Ações, Agenda, Comunicações, Consultar Processo, Indicadores e Administração.
3. Área de conteúdo: muda conforme a tela selecionada.
4. Feedback: estados de carregamento, vazio, erro e confirmação.
5. Identidade visual: azul-marinho institucional, fundo cinza-claro, cartões brancos e cores semânticas para risco.

## Tela 1 — Início / Dashboard operacional
- Saudação e data.
- Cartões: Minha carteira, Críticos, Em risco, Atrasados, Ações pendentes, Próximos vencimentos.
- Lista de prioridades com RC/PO/fornecedor, prazo, risco e próxima ação.
- Lista de marcos próximos.
- Os indicadores devem ser calculados a partir de dados reais após o mapeamento das colunas.

## Tela 2 — Minha Carteira
- Busca textual.
- Filtros por status, risco, fornecedor, comprador, diligenciador e período.
- Grade de processos/itens.
- Seleção abre o Dossiê.
- Não presumir que RC e PO sejam uma relação 1:1; respeitar o modelo real de dados.

## Tela 3 — Dossiê do Processo
- Cabeçalho de identificação RC/PO/item e status.
- Abas: Resumo, Marcos, Ocorrências, Ações, Comunicações e Histórico.
- Timeline ordenada por data.
- Ações disponíveis variam segundo perfil e estado do registro.

## Tela 4 — Ocorrências
- Filtros: abertas, em tratamento, aguardando terceiro, resolvidas e vencidas, conforme valores reais existentes.
- Detalhe e registro de ocorrência.
- Validação de campos obrigatórios baseada na regra definida pelo administrador.

## Tela 5 — Ações
- Filtros por responsável, prazo, prioridade e status.
- Atualizar andamento, registrar resultado e concluir, de acordo com permissões.
- Mudanças relevantes devem ser rastreáveis em DIL_Historico quando esse for o padrão já implementado.

## Tela 6 — Agenda / Marcos
- Calendário mensal/semanal e lista de próximos marcos.
- Clique em uma data filtra compromissos.
- Datas e tipos devem usar os campos reais das listas existentes.

## Tela 7 — Comunicações
- Pendentes, enviadas, destinatário, assunto, processo e data.
- Leitura de registros existentes em DIL_Comunicacoes.
- DIL_10 permanece como motor de envio; o aplicativo não deve enviar uma segunda cópia por fora do fluxo.

## Tela 8 — Indicadores / Gestão
- Visão por equipe/área, riscos, atrasos, SLA e tendência.
- Escopo de dados limitado pelas permissões do usuário.
- Indicadores executivos complexos podem ser apresentados em Power BI.

## Tela 9 — Administração
- Usuários/perfis.
- Parâmetros funcionais.
- Modelos de comunicação.
- Regras SLA.
- Opções de campos e validações.
- Configurações de integração e consulta de logs.
- Antes de implementar, verificar as colunas atuais de DIL_Configuracao e DIL_SLA. Não criar colunas por suposição.

## Componentes compartilhados
- `cmpCabecalho`
- `cmpMenuLateral`
- `cmpCartaoIndicador`
- `cmpEtiquetaStatus`
- `cmpBarraFiltros`
- `cmpEstadoVazio`
- `cmpConfirmacao`

A possibilidade e o modo de empacotar componentes reutilizáveis devem ser confirmados no ambiente Power Apps utilizado.
