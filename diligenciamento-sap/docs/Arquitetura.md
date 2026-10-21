# Arquitetura

## Camadas (licença Power Apps for Office 365, conectores Standard)

| Camada | Componente | Papel |
|---|---|---|
| Interface | Canvas App (este projeto) | Operação e consulta; gravação apenas em Ocorrências, Ações e Histórico |
| Dados | Listas SharePoint DIL existentes | Fonte única; não recriadas nem alteradas |
| Automação | Power Automate DIL_01–DIL_10 | Importação, normalização, marcos, ocorrências, SLA, comunicações — **inalterados** |
| Comunicação | Outlook (DIL_10) | Único ponto de envio de e-mail |
| Análise | Power BI | Indicadores executivos (pendente) |

Sem Dataverse, sem conectores Premium, sem SAP Premium/RPA. Integração SAP: SAP GUI → Excel/CSV → SharePoint.

## Cadeia de fluxos (lida dos pacotes em `DIL.zip`)

```
Arquivo SAP (Excel) ─DIL_01─▶ DIL_Importacao_SAP (staging, Pendente) + DIL_Integracao_Execucao (Iniciada)
DIL_02 ─▶ normaliza staging em DIL_Pedidos, DIL_Itens_Pedido, DIL_Fornecedores, DIL_Requisicoes
DIL_03 ─▶ fecha a execução (Concluída/Parcial/Com erro)
DIL_04 ─▶ DIL_Itens_Requisicao + DIL_Vinculo_RC_PO (RC/item ↔ PO/item)
DIL_Compromissos ─DIL_05─▶ DIL_Marcos (MARCO-COMP-*) + DIL_Historico
DIL_Marcos atrasado ─DIL_06─▶ DIL_Ocorrencias (OCOR-MARCO-*) + DIL_Acoes (ACAO-MARCO-*, cobrança)
Marco regularizado ─DIL_07─▶ encerra OCOR-MARCO-* e suas ações + histórico
Ocorrência aberta com ação, além do SLA ─DIL_08─▶ DIL_Acoes (ACAO-SLA-*, Escalonamento) + histórico
Ação Escalonamento aberta ─DIL_09─▶ DIL_Comunicacoes (COM-SLA-*) + histórico
COM-SLA-* ativa sem Envio_Email no histórico ─DIL_10─▶ e-mail Outlook + DIL_Historico (Envio_Email)
```

## Modelo de relacionamento usado pelo app

- A unidade da carteira é o **par RC/item ↔ PO/item** de `DIL_Vinculo_RC_PO` (não se presume 1:1 entre RC e PO).
- Dados do item vêm de `DIL_Itens_Pedido` por (`Numero_PO`, `Item_PO`).
- Marcos, ocorrências, ações e comunicações se ligam ao item por (`Numero_PO`, `Item_PO`); ações também por `Chave_Ocorrencia`.
- Histórico se liga por `Chave_Entidade` = chave (`Title`) do registro.
- Todas as chaves são texto (os fluxos filtram com aspas).

## Carga de dados e delegação

`App.OnStart` (e o botão **Atualizar dados**) carrega coleções:

| Coleção | Origem | Filtro no servidor (delegável) |
|---|---|---|
| `colVinc` | DIL_Vinculo_RC_PO | `Ativo = true` |
| `colItensPO` | DIL_Itens_Pedido | `Ativo = true` |
| `colOcor` | DIL_Ocorrencias | `Ativo = true` |
| `colAcoes` | DIL_Acoes | `Ativo = true` |
| `colMarcos` | DIL_Marcos | nenhum (tipo de `Ativo` ambíguo) |
| `colComun` | DIL_Comunicacoes | nenhum |
| `colEnvios` | DIL_Historico | `Tipo_Evento.Value = "Envio_Email"` |
| `colCarteira` | calculada | situação Crítico / Em risco / Normal |

Busca, filtros e indicadores rodam sobre as coleções (sem limite de delegação dentro delas). **Limite:** cada
coleção recebe no máximo o *limite de linhas de dados* (configurar **2000**). Volumes atuais são pequenos
(dezenas de registros por lista operacional). Se alguma lista operacional passar de ~1.500 ativos, trocar a carga
por filtros no servidor por responsável/período antes de chegar ao limite. O Dossiê busca o histórico por chave
(`Chave_Entidade = chave`, delegável) e a Administração lê os 30/100 itens mais recentes (`Sort` por `ID`, delegável).

Avisos de delegação esperados no Studio: nenhum para as cargas acima; possíveis avisos em `LookUp` de perfil
(`DIL_Configuracao`, lista pequena).

## Regras de negócio implementadas no app

- **Situação da carteira:** Crítico se há ocorrência aberta com severidade Crítica; Em risco se há ocorrência aberta
  ou marco atrasado (sem `Data_Realizada`, `Data_Planejada` < hoje, status ≠ Concluído/Cancelado); senão Normal.
- **Gravações** com chave própria (`OCOR-APP-`, `ACAO-APP-`, `HIST-APP-` + carimbo de data/hora + sufixo aleatório),
  interrompidas no primeiro erro (`IfError` em etapas) e sempre seguidas de registro em `DIL_Historico`.
- **Interação com os fluxos:** ocorrência aberta com ação vinculada entra na regra de SLA do DIL_08 (escalona após
  4/8/24/48 h conforme severidade) → DIL_09 → DIL_10. Isso é o comportamento atual dos fluxos e consta como teste R-02.
- **Proibições verificadas no build:** envio de e-mail pelo app, gravação de `Envio_Email`, ação do tipo Escalonamento.

## Geração das fontes

`scripts/gerar_fontes.py` descreve o app em Python e gera `app-source/Src/*.pa.yaml` (formato oficial).
Cabeçalho e menu são repetidos por tela (componentes ficaram de fora para reduzir risco de carga via YAML).
Fórmulas usam sintaxe invariante (`,` e `;`); o Studio em pt-BR exibe `;` e `;;`.
