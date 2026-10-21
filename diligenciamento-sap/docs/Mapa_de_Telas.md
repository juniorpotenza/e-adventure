# Mapa de telas (implementado — v7)

> v7: o app é orientado ao trabalho diário. Fluxo principal: **Minha fila → Diligenciar item → Registrar contato / resultado**. Detalhes no `CHANGELOG.md` (0.5.0).

Layout comum (todas as telas): cabeçalho azul-marinho com marca, usuário · perfil e **Atualizar dados**; menu
lateral filtrado por perfil (`nfMenu`); área de conteúdo à direita. Paleta do protótipo aprovado (named formulas
`nfCor*`). Referência visual: `prototype/Projeto_Visual_Diligenciamento_SAP/prototipo/index.html`.

| Tela | Fontes (leitura) | Gravação | Conteúdo | Perfis |
|---|---|---|---|---|
| `scrInicio` — Central do Diligenciador | coleções | — | 6 KPIs (carteira, críticos, em risco, marcos atrasados, ações pendentes, minhas ações vencidas); prioridades (abre o Dossiê); próximos marcos (15 dias) | todos |
| `scrCarteira` — Minha carteira | `colCarteira`, `colAcoes` | — | busca (RC, PO, material, descrição, fornecedor); filtros situação/fornecedor/escopo (**"Minha carteira" = `Responsavel_Diligencia` da RC**, "com ações sob minha responsabilidade", todos); responsável e prioridade da RC na grade; grade com 1ª remessa e pendências | todos |
| `scrDossie` — Dossiê do processo | coleções + `DIL_Historico` por chave | `DIL_Compromissos` | identificação RC/PO/item; abas Resumo (inclui responsável, prioridade/risco da RC, item da RC e contato/status do fornecedor), **Compromissos (registrar e reprogramar; o DIL_05 gera/atualiza o marco)**, Marcos, Ocorrências, Ações, Comunicações, Histórico (timeline); atalho "Registrar ocorrência" | todos (atalho só para quem edita) |
| `scrOcorrencias` | `colOcor`, `colAcoes`, `colCarteira` | `DIL_Ocorrencias`, `DIL_Historico` | lista com busca/status/severidade; detalhe; alterar status; nova ocorrência (item, tipo, severidade, descrição); atalho "nova ação para esta ocorrência" | consulta: todos · grava: Diligenciador, Coordenador, Gestor, Administrador |
| `scrAcoes` | `colAcoes`, `colOcor` | `DIL_Acoes`, `DIL_Historico` | filtros minhas/todas, status, prioridade, prazo; detalhe; andamento (status + observação) pelo responsável ou coordenação; nova ação (ocorrência, tipo ≠ Escalonamento, prioridade, responsável, prazo) | idem |
| `scrAgenda` — Agenda/Marcos | `colMarcos` | — | calendário mensal (atrasados em vermelho) e lista do dia / próximos 30 dias | todos |
| `scrComunicacoes` | `colComun`, `colEnvios` | — | situação de envio (Enviada pelo DIL_10, Aguardando DIL_10, Fora do escopo, Inativa); detalhe com corpo registrado e anexo. **Sem botão de envio** | todos |
| `scrGestao` — Visão da gestão | coleções | — | 5 KPIs (inclui escalonamentos e e-mails enviados); barras por situação, severidade e responsável; nota Power BI | Coordenador, Gestor, Auditor, Administrador |
| `scrAdmin` — Administração | `DIL_Integracao_Execucao`, `DIL_Configuracao`, `DIL_SLA`, `DIL_Historico` | — (somente leitura) | Integrações (últimas 30 execuções), Parâmetros e perfis, Regras SLA (com aviso sobre horas fixas no DIL_08), Modelos de e-mail (situação e mudança pendente), Auditoria (100 eventos recentes) | Gestor, Administrador |

Estados: listas vazias exibem mensagem; gravações exibem confirmação ou erro (`Notify`); telas restritas
redirecionam para o Início com aviso.

## Diferenças em relação ao protótipo

- "Consultar processo" do protótipo virou o Dossiê aberto a partir da carteira/prioridades.
- Prioridade/risco vindos de `DIL_Requisicoes` foram substituídos pela situação **calculada** (tipos não confirmados).
- Administração ainda não edita (perfis, SLA e modelos dependem de decisões/mudanças em fluxos).
