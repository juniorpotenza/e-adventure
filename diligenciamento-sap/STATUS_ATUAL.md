# STATUS ATUAL — Sistema de Diligenciamento SAP (Canvas App)

**Data:** 21/10/2026 · **Situação geral:** fontes do app geradas e validadas offline; **`.msapp` candidato gerado** a partir do original (recebido por e-mail). Candidato **não aberto no Power Apps Studio** e nada testado no tenant.

Legenda: ✅ confirmado com evidência · 🟡 inferido · ⏳ pendente · ⛔ inacessível · 🧪 não testado

---

## 1. Inventário de arquivos

| Artefato | Situação | Onde / evidência |
|---|---|---|
| `Sistema de Diligenciamento.msapp` | ✅ recebido | Anexo do e-mail "ss" no Gmail (12.608 bytes, sha256 `f81cb237…`, CRC OK). App em branco: só `scrInicio` vazia, tema `PowerAppsTheme`, 1366×768 paisagem, nenhuma fonte de dados, formato moderno com `Src/*.pa.yaml`. Cópia intocada em `artifacts/original/` (não versionada). |
| `Prototipo_Diligenciamento_SAP.html` (avulso) | ⛔ **AUSENTE** como arquivo avulso | Existe `prototipo/index.html` dentro do pacote visual (abaixo). Não confirmei se é idêntico ao HTML avulso citado. |
| `Projeto_Visual_Diligenciamento_SAP.zip` | ✅ presente | Google Drive (12.091 bytes, sha256 `48cb71cd…`). Integridade conferida (CRC OK). Copiado para `prototype/`. |
| `DIL.zip` (não citado no pedido) | ✅ presente | Google Drive (sha256 `c40f37d6…`): **16 CSVs exportados das listas + 10 pacotes de fluxo** (DIL_01 a DIL_10). Base de todo o mapeamento de colunas. Mantido só em `local/` (contém e-mails e nomes reais; não versionado). |
| Repositório `juniorpotenza/e-adventure` | ✅ | É um plugin WordPress sem relação com o projeto. Todo o trabalho ficou isolado em `diligenciamento-sap/`. **O repositório é público** — ver §7. |

## 2. Ferramentas e método de empacotamento

| Item | Situação |
|---|---|
| `pac` na máquina do usuário | ⛔ "pac não é reconhecido" (relato do usuário; não reverificado). |
| `pac` neste contêiner | ✅ **Power Platform CLI 2.14.1** (.NET 10, Linux) instalado via NuGet; `canvas unpack/pack` e `power-fx run` funcionando. |
| Documentação oficial (lida no repositório-fonte `MicrosoftDocs`, pois `learn.microsoft.com` está bloqueado pela rede) | ✅ `pac canvas pack/unpack` estão **marcados como preteridos e em Preview**; a recomendação para controle de versão é a *Power Platform Git Integration*. A fonte oficial do app são os arquivos `Src/*.pa.yaml` (schema v3.0); os JSON do `.msapp` não devem ser usados como fonte. |
| Git Integration | 🟡 Provavelmente indisponível: exige apps em soluções (Dataverse) e a licença é Power Apps for Office 365. ⏳ Confirmar no ambiente. |
| Método adotado | ✅ `pac canvas unpack --layout SourceCode` → substituir/mesclar `Src/*.pa.yaml` → `pac canvas pack --layout SourceCode`. O pacote leva `packed.json` com `"LoadFromYaml": true`; o Studio reconstrói o app a partir do YAML. O PAC exige: *"must be validated first by opening the app for edit within the Power Apps studio"*. |
| Teste do método | ✅ Pipeline completo executado com um `.msapp` **público de teste da Microsoft** (PowerApps-Tooling): desempacotar, preservar tela/componente/tema originais, empacotar, `LoadFromYaml=true`, round-trip das fontes idêntico. 🧪 **Abertura no Studio não testada** (sem acesso). |

## 3. Base DIL — o que está confirmado

| Item | Situação |
|---|---|
| 16 das 17 listas conhecidas | ✅ esquema (nomes de exibição/ordem) extraído dos CSVs |
| `DIL_Documentos` | ⛔ **não consta** do `DIL.zip` → app não usa essa lista |
| Coluna `Chave_*` de cada lista | ✅ é o `Title` renomeado (fluxos gravam `Title` com o prefixo da chave) |
| Nomes internos `field_N` | ✅ 9 listas foram criadas por planilha (Acoes, Comunicacoes, Historico, Ocorrencias, Marcos, Compromissos, Fornecedores, SLA, Integracao_Execucao). Mapeamento `field_N ↔ nome` verificado contra o que os fluxos gravam (ex.: DIL_10 grava `field_3=Envio_Email` e o CSV mostra `Tipo_Evento=Envio_Email`). |
| GUIDs das listas | ✅ 15 GUIDs associados às listas (4 informados + 11 deduzidos das ações dos fluxos). Guardados só em `local/tenant_local.json`. |
| Tipos (Escolha/Pessoa/Booleano/Data) | ✅ quando o fluxo grava `/Value`, `/Claims`, `True`, `utcNow()` ou filtra `eq 1`; 🟡 demais inferidos dos valores do CSV |
| Detalhe completo | `docs/Mapeamento_Listas_DIL.md` (gerado) · checklist do que o app usa sem evidência direta: `docs/Checklist_Colunas_Inferidas.md` |

**Atualização 21/10/2026:** tipos de `DIL_Compromissos`, `DIL_Requisicoes`, `DIL_Fornecedores` e `DIL_Itens_Requisicao` confirmados pelo usuário (`schema/confirmacoes_usuario.json`) e incorporados (v4/v5). Itens abaixo sobre essas listas estão resolvidos.

**Ambiguidades encontradas (o app NÃO usa estas colunas até confirmação):**
- `DIL_Marcos.Ativo`: o fluxo grava `True`, mas há `Sim` no CSV e o DIL_06 testa `'true'` **ou** `'sim'` → tipo real incerto.
- `DIL_Requisicoes.Prioridade`/`Nivel_Risco`/`Responsavel_Diligencia`: tipo incerto (valores-padrão sugerem Escolha). Por isso a "Situação" da carteira é **calculada** a partir de ocorrências e marcos.
- `DIL_Compromissos.Tipo_Compromisso`/`Status_Compromisso`, `DIL_SLA.Tipo_SLA`/`Prioridade`: tipo incerto.
- `DIL_Configuracao`: nenhum fluxo usa a lista → nomes internos não confirmados.

## 4. Fluxos — o que os pacotes mostram

| Fluxo | Observação |
|---|---|
| DIL_01–04 | Importação/normalização: o app **não grava** em `DIL_Importacao_SAP`, `DIL_Integracao_Execucao`, `DIL_Requisicoes`, `DIL_Itens_*`, `DIL_Pedidos`, `DIL_Vinculo_RC_PO`, `DIL_Fornecedores` (só leitura). Sem risco de regressão por gravação. |
| DIL_08 | ✅ Horas de SLA **fixas no fluxo**: Crítica 4 h, Alta 8 h, Média 24 h, demais 48 h desde `Data_Ocorrencia`. `DIL_SLA` não define essas horas. Escala qualquer ocorrência ativa/aberta que tenha **ação vinculada** (`Chave_Ocorrencia`). |
| DIL_09 | Cria `COM-SLA-*` para ações `Escalonamento` abertas. Grava em `Comunicacoes.Codigo_Fornecedor` (`field_5`) a **chave da ocorrência**, não o código do fornecedor (observação de qualidade de dado; não alterado). |
| DIL_10 | O pacote contém a **V8**; a V9 ativa (com `runAfter` corrigido) não está no `DIL.zip`. A lógica de idempotência é a mesma: filtra `COM-SLA-*` com `field_13 eq 1` e procura no histórico `field_2 = chave` e `field_4 = 'Envio_Email'`. **Nada foi alterado no DIL_10.** |

## 5. App — o que foi construído (fontes reais `*.pa.yaml`)

- ✅ 9 telas: Início, Minha carteira, Dossiê, Ocorrências, Ações, Agenda/Marcos, Comunicações, Visão da gestão, Administração (`app-source/Src`, geradas por `scripts/gerar_fontes.py`).
- ✅ Cabeçalho, menu lateral por perfil e identidade visual do protótipo aprovado.
- ✅ Gravações: registrar ocorrência, alterar status de ocorrência, registrar ação, atualizar andamento de ação — todas com registro em `DIL_Historico` e tratamento de erro.
- ✅ Salvaguardas: o app não envia e-mail, não grava `Envio_Email`, não cria ação `Escalonamento`.
- ✅ Validação offline: schema oficial v3.0 + enum oficial de controles (9 telas, 414 controles, 0 erros), colunas conferidas contra o esquema (55 colunas), **3.731 fórmulas sem erro de sintaxe** no motor Power Fx do PAC; testes negativos confirmam que os verificadores reprovam defeitos.
- 🟡 Perfis: proposta lida de `DIL_Configuracao` (`Categoria="Perfil_Usuario"`), padrão Diligenciador. ⏳ requer aprovação e ⏳ permissões SharePoint.
- Administração é **somente leitura** nesta versão (tipos de `DIL_SLA`/`DIL_Configuracao` não confirmados).

## 6. Pendências

| # | Pendência | Tipo |
|---|---|---|
| P1 | ~~Anexar o `.msapp` original~~ → recebido; candidato `Sistema_de_Diligenciamento__candidato__20261021-190639.msapp` gerado (`LoadFromYaml=true`, round-trip idêntico) | ✅ |
| P2a | 1ª abertura no Studio (candidato 190639) **falhou** com PA2108: controles clássicos sem prefixo `Classic/`. Corrigido no gerador → candidato 195717 | ✅ corrigido; ⏳ reabrir |
| P2b | Candidato **v3 abre no Studio** ✅ (relato do usuário, 21/10/2026) | ✅ |
| P2 | Abrir o candidato no Power Apps Studio, adicionar as 10 listas SharePoint e zerar erros de fórmula | ⏳ PENDENTE — requer execução no ambiente do usuário |
| P3 | Conferir no SharePoint as colunas de `docs/Checklist_Colunas_Inferidas.md` | ⏳ ambiente do usuário |
| P4 | Confirmar se a escolha `Power Apps` existe em `DIL_Historico.Origem_Evento` (o app grava esse valor) | ⏳ ambiente do usuário |
| P5 | Aprovar o modelo de perfis em `DIL_Configuracao` e restringir edição dessa lista a administradores | ⏳ decisão + configuração |
| P6 | Testes de aceite e regressão (`docs/Plano_de_Testes.md`), incluindo DIL_10 sem duplicidade e importação sem regressão | ⏳ ambiente do usuário |
| P7 | Enviar o pacote da **V9** do DIL_10 para manter o registro técnico atualizado (opcional; não bloqueia) | ⏳ |
| P8 | Modelos de e-mail editáveis e SLA configurável: mudanças separadas em DIL_10/DIL_08, com plano de regressão | ⏳ fora do escopo desta entrega |

## 7. Segurança e privacidade do repositório

O repositório `juniorpotenza/e-adventure` é **público**. Para não expor dados corporativos, **não foram versionados**: URL do site SharePoint, GUIDs das listas, CSVs, definições dos fluxos, e-mails e nomes de pessoas, nem arquivos `.msapp`. Esses insumos ficam em `local/` (ignorado pelo git). Os nomes de listas/colunas e as fontes do app estão versionados. **Recomendação:** mover este projeto para um repositório privado.

## 8. Plano técnico curto

1. ✅ Auditar insumos e extrair o esquema real (`scripts/extrair_esquema.py`).
2. ✅ Gerar as fontes `*.pa.yaml` (`scripts/gerar_fontes.py`) usando só colunas mapeadas.
3. ✅ Validar offline (`validar_fontes.py`, `checar_sintaxe_fx.py`).
4. ✅ `scripts/montar_msapp.py` gerou o candidato a partir do original (backup + mescla + pack + round-trip).
5. ⏳ Validar no Studio, conectar as listas uma a uma, executar o plano de testes.
6. ⏳ Publicar como app separado; promover só após aceite (rollback = app original intacto).

## 9. Próximos passos que dependem de você

1. Abrir o candidato no Power Apps Studio (*Arquivo > Abrir > Procurar*), salvar com outro nome e seguir `docs/Guia_Importacao.md` (conectar as 10 listas, limite de linhas 2000, zerar erros).
2. Relatar qualquer erro de carga/fórmula (tela, controle, propriedade, mensagem) para correção no gerador.
3. Opcional: envie o pacote exportado da V9 do DIL_10.
