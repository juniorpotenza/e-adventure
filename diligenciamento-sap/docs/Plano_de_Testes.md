# Plano de testes

Status: ✅ executado com evidência · ⏳ **PENDENTE — requer execução no ambiente do usuário** · ❌ falhou

## A. Testes executados offline (ambiente de nuvem, 21/10/2026)

| ID | Objetivo | Como | Resultado |
|---|---|---|---|
| OFF-01 | Fontes obedecem ao schema oficial pa.yaml v3.0 | `scripts/validar_fontes.py` (schema publicado pela Microsoft) | ✅ 9 arquivos de tela + App + EditorState, 0 erros |
| OFF-02 | Tipos de controle existem na biblioteca oficial | enum `ControlTypeId-1P-controls` | ✅ 414 controles, todos válidos |
| OFF-03 | Nomes únicos, `Navigate` para telas existentes, `ScreensOrder` completo | validador | ✅ |
| OFF-04 | Toda coluna usada existe no esquema real | validador × `schema/dil_schema.json` | ✅ 55 colunas; pendências de tipo em `Checklist_Colunas_Inferidas.md` |
| OFF-05 | Salvaguardas DIL_10 (sem envio, sem `Envio_Email` gravado, sem ação `Escalonamento`) | validador + regra no `OnSelect` | ✅ |
| OFF-06 | Sintaxe de todas as fórmulas | `scripts/checar_sintaxe_fx.py` (motor Power Fx do PAC 2.14.1) | ✅ 3.731 fórmulas, 0 erros de sintaxe |
| OFF-07 | Verificadores detectam defeitos | defeitos injetados em cópia: tipo de controle, coluna, `Envio_Email` em `Patch`, `Navigate` inválido, parêntese aberto, operador faltando | ✅ todos reprovados |
| OFF-08 | Pipeline de empacotamento | `montar_msapp.py` com `.msapp` público de teste da Microsoft | ✅ backup idêntico (sha256), tela/componente/tema originais preservados, `LoadFromYaml=true`, round-trip idêntico |

Limite destes testes: não resolvem nomes de fontes de dados, tipos reais das colunas nem comportamento do Studio.

## B. Abertura e conexão (tenant)

| ID | Passos | Esperado | Status |
|---|---|---|---|
| T-01 | Abrir candidato no Studio (*Arquivo > Abrir > Procurar*) | Carrega 9 telas sem erro de carga | ⏳ |
| T-02 | Adicionar as 10 listas (ordem do `Guia_Importacao.md` §3) | Nomes idênticos às listas | ⏳ |
| T-03 | Verificador de aplicativo | 0 erros de fórmula | ⏳ |
| T-04 | Executar OnStart e navegar por todas as telas | Sem erro; menu muda conforme perfil | ⏳ |
| T-05 | Conferir checklist de colunas inferidas | Nome e tipo conferem | ⏳ |

## C. Funcionais (usar registros de teste)

| ID | Passos | Esperado | Status |
|---|---|---|---|
| F-01 | Início: comparar KPIs com contagens feitas no SharePoint | Iguais (até o limite de 2.000 linhas) | ⏳ |
| F-02 | Carteira: buscar por RC, PO, material, fornecedor; filtrar situação/fornecedor/escopo | Resultados corretos; "Com ações sob minha responsabilidade" usa `Responsavel_Pessoa` | ⏳ |
| F-03 | Carteira → Dossiê de um item cuja RC está ligada a 2 POs (existe caso assim nos dados de teste de `DIL_Vinculo_RC_PO`) | Dossiê mostra só o par PO/item selecionado; marcos/ocorrências/ações/comunicações/histórico corretos | ⏳ |
| F-04 | Registrar ocorrência sem preencher descrição | Bloqueado com mensagem | ⏳ |
| F-05 | Registrar ocorrência completa | 1 item em `DIL_Ocorrencias` (`OCOR-APP-…`, Status Aberta, Ativo Sim, Responsavel_Pessoa = usuário) + 1 item em `DIL_Historico` (Inclusão) | ⏳ |
| F-06 | Alterar status de ocorrência para Resolvida | Status e `Data_Resolucao` gravados; histórico "Status" | ⏳ |
| F-07 | Registrar ação vinculada à ocorrência | 1 item em `DIL_Acoes` (`ACAO-APP-…`, Aberta) + histórico; tipo "Escalonamento" não aparece | ⏳ |
| F-07b | Dossiê > Compromissos > Novo compromisso (Entrega, data D+7) | 1 item em `DIL_Compromissos` (`COMP-APP-…`, Ativo Sim); em até ~2 min o DIL_05 cria `MARCO-COMP-COMP-APP-…` em `DIL_Marcos` e registra Inclusão no histórico; após **Atualizar dados** o marco aparece na Agenda | ⏳ |
| F-07c | Reprogramar o compromisso para outra data | `Data_Compromisso_Anterior` = data antiga; DIL_05 atualiza a data do marco (sem criar outro) e registra Alteração no histórico | ⏳ |
| F-20 | Minha fila: conferir motivos e ordem (atrasado > vence em 7 d > ação vencida > prioritário > sem contato) | Itens e motivos corretos | ⏳ |
| F-21 | Registrar contato "Somente contato" | 1 item em `DIL_Comunicacoes` `COM-APP-…` com Assunto `[Contato] — <meio>`; nenhum e-mail enviado; "último contato" atualiza | ⏳ |
| F-22 | "Nova data negociada" (antes da remessa SAP) | compromisso de Entrega criado/atualizado com data anterior; ganho = SAP − nova (dias) aparece na fila, carteira e gestão; DIL_05 atualiza o marco | ⏳ |
| F-23 | "Material entregue" em item atrasado | compromisso `Cumprido`; item sai da fila; DIL_05 conclui o marco e DIL_07 encerra a ocorrência de atraso e suas ações | ⏳ |
| F-24 | "Chegou com divergência na NF" e depois "Material entregue" | entre os dois, item aparece na fila como "Recebido: acompanhar regularização da NF"; depois sai | ⏳ |
| F-25 | "NF emitida — material em trânsito" | situação física "NF emitida — em trânsito"; cobrança continua | ⏳ |
| F-08 | Atualizar andamento sendo/não sendo o responsável | Responsável e coordenação podem; outro diligenciador vê aviso e não edita pelo app | ⏳ |
| F-09 | Agenda: navegar meses, clicar em dia com marco | Lista filtra o dia; atrasados em vermelho | ⏳ |
| F-10 | Comunicações: comparar "Enviada pelo DIL_10" com `DIL_Historico` (`Envio_Email`) | Coincidem; não existe botão de envio | ⏳ |
| F-11 | Falha de gravação (usuário sem permissão de escrita) | Mensagem de erro; nenhum histórico órfão | ⏳ |
| F-12 | Origem `Power Apps` em `DIL_Historico.Origem_Evento` | Valor aceito pela coluna de escolha (senão ajustar `nfOrigemApp` ou a escolha, com aprovação) | ⏳ |

## D. Perfis e permissões efetivas

| ID | Conta de teste | Verificar | Status |
|---|---|---|---|
| S-01 | Diligenciador (sem linha em `DIL_Configuracao`) | Menu sem Gestão/Administração; acesso direto às telas é bloqueado (`OnVisible`) | ⏳ |
| S-02 | Coordenador / Gestor / Auditor / Administrador | Menu e telas conforme `Matriz_de_Permissoes.md`; Auditor não grava | ⏳ |
| S-03 | **Permissão efetiva**: com a conta Diligenciador, tentar editar `DIL_Configuracao` e as listas de importação diretamente no SharePoint | Negado. Se permitido, o perfil pode ser autoelevado → corrigir permissões antes de publicar | ⏳ |
| S-04 | Conta sem acesso às listas | App não exibe dados; nenhuma gravação | ⏳ |

## E. Regressão dos fluxos (não alterados)

| ID | Passos | Esperado | Status |
|---|---|---|---|
| R-01 | Após uso do app, executar DIL_10 (aguardar recorrência) | Nenhum e-mail novo para comunicações já registradas com `Envio_Email`; nenhum registro duplicado no histórico | ⏳ |
| R-02 | Ocorrência criada no app (Crítica) **com** ação vinculada, mantida aberta > 4 h | DIL_08 cria **uma** `ACAO-SLA-…`; DIL_09 cria **uma** `COM-SLA-…`; DIL_10 envia **um** e-mail e registra `Envio_Email`; reexecução não duplica | ⏳ |
| R-03 | Ocorrência criada no app **sem** ação | Não escala (comportamento atual do DIL_08) — confirmar se é o desejado | ⏳ |
| R-04 | Importação de arquivo SAP pequeno (fluxo usual DIL_01→04) | Contagens iguais às de antes; app não grava em listas de importação/normalização | ⏳ |
| R-05 | Marco atrasado → DIL_06 → ocorrência/ação; regularização → DIL_07 encerra | Sem interferência das gravações do app (prefixos `OCOR-APP-`/`ACAO-APP-` não são tratados pelo DIL_07) | ⏳ |

## F. Critério de aceite

Aprovado quando B, C, D e E estiverem ✅. Até lá a entrega é **candidata à validação**.
