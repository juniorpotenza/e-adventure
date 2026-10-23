# Guia de geração, importação e rollback

> O resultado deste processo é um **candidato à validação**. Só vira versão de produção depois de aberto no
> Power Apps Studio sem erros e aprovado no `Plano_de_Testes.md`.

## 0. Pré-requisitos

| Item | Onde |
|---|---|
| `.msapp` original exportado do Studio | Power Apps Studio → app *Sistema de Diligenciamento* → **Arquivo > Salvar como > Este computador** |
| Acesso de edição ao app e leitura/gravação às listas DIL | Tenant |
| Para gerar o candidato **no seu computador** (opcional): .NET SDK 8 ou superior, `dotnet tool install --global Microsoft.PowerApps.CLI.Tool`, Python 3.10+ e `pip install -r scripts/requirements.txt` | Máquina local |

Alternativa sem instalar nada: anexe o `.msapp` na conversa com o assistente; o candidato é gerado no ambiente
de nuvem, onde o PAC 2.14.1 já está instalado.

## 1. Gerar o candidato

```bash
cd diligenciamento-sap
bash scripts/verificar_tudo.sh                       # regenera e valida as fontes
python3 -I scripts/montar_msapp.py "/caminho/Sistema de Diligenciamento.msapp"
```

O script:
1. valida as fontes (aborta se houver erro);
2. copia o original intacto para `artifacts/original/<nome>__<sha12>.msapp`;
3. `pac canvas unpack --layout SourceCode`;
4. guarda `Src` do original em `artifacts/candidate/<nome>__<data>__Src_original/`;
5. copia as 9 telas geradas (substitui `scrInicio` **somente se estiver vazia**; outras telas do original são preservadas);
6. mescla `App.pa.yaml` (mantém as propriedades do original; define `Formulas`, `OnStart`, `StartScreen`) e `_EditorState`;
7. `pac canvas pack --layout SourceCode` → `artifacts/candidate/<nome>__candidato__<data>.msapp`;
8. confere `packed.json` (`LoadFromYaml: true`) e faz round-trip (desempacota de novo e compara as fontes);
9. grava o manifesto `.json` com hashes.

## 2. Abrir no Power Apps Studio (validação obrigatória)

1. make.powerapps.com → **Criar > Aplicativo de tela em branco** (ou abra qualquer app) → **Arquivo > Abrir > Procurar** → selecione o candidato.
2. Ao abrir, o Studio carrega as telas a partir de `Src/*.pa.yaml`. Anote qualquer mensagem de carga.
3. **Salve com outro nome**: *Sistema de Diligenciamento – Candidato*. Não sobrescreva o app original nesta fase.

## 3. Conectar as fontes (uma por vez)

**Dados > Adicionar dados > SharePoint >** site das listas DIL **>** marque e adicione, nesta ordem, validando a cada passo:

| Ordem | Lista | Uso no app | Validar após adicionar |
|---|---|---|---|
| 1 | `DIL_Configuracao` | perfil do usuário (leitura); Administração grava modelos de e-mail e status SAP | `OnStart` sem erro |
| 2 | `DIL_Vinculo_RC_PO` | carteira (leitura) | |
| 3 | `DIL_Itens_Pedido` | carteira (leitura) | |
| 4 | `DIL_Ocorrencias` | leitura e **gravação** | |
| 5 | `DIL_Acoes` | leitura e **gravação** | |
| 6 | `DIL_Marcos` | agenda/riscos (leitura) | |
| 7 | `DIL_Comunicacoes` | consulta (leitura) | |
| 8 | `DIL_Historico` | timeline/auditoria (leitura) e **gravação** de eventos manuais | |
| 9 | `DIL_SLA` | administração (leitura) | |
| 10 | `DIL_Integracao_Execucao` | administração (leitura) | |
| 12 | `DIL_Requisicoes` | carteira e dossiê (leitura): responsável, prioridade, risco | filtro "Minha carteira" |
| 13 | `DIL_Itens_Requisicao` | dossiê (leitura) | aba Resumo |
| 14 | `DIL_Fornecedores` | dossiê (leitura): contato do fornecedor | aba Resumo |
| 11 | `DIL_Compromissos` | dossiê: leitura e **gravação** (registrar/reprogramar compromisso) | aba Compromissos do Dossiê |
| 15 | `DIL_Pedidos` | grupo de compradores (leitura) — **nova na v10** | filtros da Visão da gestão |

O nome da fonte no Studio precisa ser **exatamente** o nome da lista (ex.: `DIL_Acoes`). Se o Studio criar outro nome
(ex.: `DIL_Acoes_1`), remova e adicione de novo, ou ajuste o gerador — não edite fórmulas à mão.

## 4. Configurações do app

1. **Configurações > Geral > Limite de linhas de dados** = **2000** (padrão 500). As telas carregam coleções;
   acima de 2.000 registros ativos por lista a carteira ficaria incompleta (ver `Arquitetura.md`, delegação).
2. **Verificador de aplicativo** (ícone do estetoscópio): zerar erros de fórmula. Avisos de delegação esperados
   estão listados em `Arquitetura.md`.
3. Executar **OnStart** (… > Executar OnStart) e navegar por todas as telas.
4. Conferir `docs/Checklist_Colunas_Inferidas.md` em *Configurações da lista* no SharePoint.

Se aparecer erro: registre tela, controle, propriedade e mensagem e devolva para correção **no gerador**
(`scripts/gerar_fontes.py`). Edições feitas só no Studio se perdem na próxima geração.

## 5. Publicar

Somente após `Plano_de_Testes.md` aprovado:
1. Publicar o app candidato; compartilhar com contas de teste de cada perfil.
2. Após aceite, decidir: (a) manter o app novo e aposentar o antigo, ou (b) abrir o candidato e **Salvar como** sobre o app original.

## 6. Rollback

| Situação | Ação |
|---|---|
| Candidato com problema | Não publicar / excluir o app *Candidato*. O app original não foi alterado. |
| Problema após substituir o original | make.powerapps.com → app → **Detalhes > Versões** → **Restaurar** a versão anterior. |
| Precisa do arquivo exato anterior | `artifacts/original/<nome>__<sha12>.msapp` (guarde cópia fora do repositório) → *Arquivo > Abrir > Procurar*. |
| Registros de teste nas listas | Filtrar por chave com prefixo `OCOR-APP-`, `ACAO-APP-`, `HIST-APP-` e desativar/excluir conforme a política. |

Os fluxos DIL_01–DIL_10 **não** são alterados por este processo; não há rollback de fluxo envolvido.
