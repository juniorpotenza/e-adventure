# Changelog

## [0.4.0-candidato-v6] — 2026-10-21

### Evidência nova
- `.msapp` salvo pelo usuário no Studio com as listas conectadas: lido o Verificador (`AppCheckerResult.sarif`,
  817 itens: 741 de acessibilidade, 45 avisos de desempenho/delegação, **31 erros de fórmula**) e o esquema real
  das fontes (`References/DataSources.json`). Esquema do Studio importado como fonte oficial
  (`schema/esquema_studio.json`, sem GUIDs): **todas as colunas das 14 listas usadas pelo app confirmadas**.
- Versões dos controles clássicos fixadas como o Studio grava (`Classic/Button@2.2.0` etc.).

### Corrigido (31 erros)
- `User().FirstName` não existe → primeiro nome extraído de `User().FullName`.
- `DIL_Acoes.Prazo` é **texto**: coluna derivada `PrazoData = IfError(DateTimeValue(Prazo), Blank())` para filtros,
  ordenação e cores; nova ação grava `Prazo` como texto ISO (`yyyy-mm-dd`).
- `Navigate` não é permitido em `OnVisible` (Dossiê, Gestão, Administração) → painel de bloqueio com botão.
- Campos do fornecedor no Dossiê: busca direta em `colForn` (`locForn`) em vez de coluna aninhada na carteira.
- ComboBox de responsável: `SearchItems` explícito (o padrão do Studio apontava para dados de amostra);
  `IsSearchable` removido (não existe no clássico).
- Validador: novas regras para `User().<propriedade>`, `Navigate` em `OnVisible` e comparação de coluna de texto.

### Observações
- `DIL_Acoes_1`: fonte duplicada adicionada no Studio, não usada pelo app — pode ser removida.
- `DIL_Marcos.Ativo` confirmado como texto (o app não usa essa coluna).

## [0.3.0-candidato-v5] — 2026-10-21

### Adicionado
- Tipos de `DIL_Requisicoes`, `DIL_Fornecedores` e `DIL_Itens_Requisicao` confirmados pelo usuário.
- Carteira: escopo **"Minha carteira (responsável pela RC)"** via `Responsavel_Diligencia` (Pessoa); padrão
  automático quando o usuário é responsável por alguma RC; responsável e prioridade na grade; busca por responsável.
- Dossiê > Resumo em duas colunas: responsável, prioridade/risco e status da RC, utilização/avaliação do item da RC,
  fornecedor (CNPJ, contato, telefone, e-mail, status e risco). Somente leitura.
- App passa a usar 14 listas. Pendente: `DIL_Documentos` (colunas não recebidas).

## [0.2.0-candidato-v4] — 2026-10-21

### Adicionado
- Tipos de `DIL_Compromissos` confirmados pelo usuário (`schema/confirmacoes_usuario.json`, aplicado pelo extrator).
- Dossiê: aba **Compromissos** — registrar compromisso do fornecedor e reprogramar data (grava
  `Data_Compromisso_Anterior`). O marco continua sendo criado/atualizado pelo **DIL_05** (inalterado), que
  registra o histórico; o app não duplica esse registro.
- App passa a usar 11 listas (`DIL_Compromissos` com gravação).

## [0.1.3-candidato] — 2026-10-21

### Validado
- **Candidato v3 abriu no Power Apps Studio** (relato do usuário). Diagnóstico por bissecção com 13 pacotes
  de teste (A–M): todas as partes abriam isoladas; o candidato 195717 não abria.
- Única diferença do pacote que falhava: arquivos mesclados com comentário extra no topo e lista
  `ScreensOrder` sem indentação. `montar_msapp.py` passou a gravar no formato da v3 (sem comentário,
  listas indentadas); o build novo é idêntico à v3, exceto a data em `packed.json`.
  A causa exata não foi isolada: as duas formas são YAML válido, e pode ter sido falha no download.

## [0.1.2-candidato] — 2026-10-21

### Corrigido
- Abertura no Studio falhou com PA2108 ("Unknown property 'Fill' for control type 'Button'" etc.): no pa.yaml,
  `Button`, `TextInput`, `DropDown`, `ComboBox` e `DatePicker` sem prefixo são os controles **modernos**.
  O gerador passou a usar `Classic/Button`, `Classic/TextInput`, `Classic/DropDown`, `Classic/ComboBox` e
  `Classic/DatePicker`. O validador agora reprova esses nomes sem prefixo.
- Novo candidato: `Sistema_de_Diligenciamento__candidato__20261021-195717.msapp`.

## [0.1.1-candidato] — 2026-10-21

- Recebido o `.msapp` original (sha256 `f81cb237…`; app em branco com `scrInicio` vazia).
- Gerado o candidato `Sistema_de_Diligenciamento__candidato__20261021-190639.msapp` com `scripts/montar_msapp.py`:
  9 telas, tema original mantido, `packed.json` com `LoadFromYaml=true`, round-trip das fontes idêntico.
- Pendente: abertura no Power Apps Studio e testes no tenant.

## [0.1.0-candidato-fontes] — 2026-10-21

### Auditoria
- Inventário: `.msapp` original e HTML avulso **ausentes**; `Projeto_Visual_Diligenciamento_SAP.zip` e `DIL.zip`
  localizados no Google Drive (sha256 `48cb71cd…` e `c40f37d6…`).
- PAC CLI 2.14.1 instalado no ambiente de nuvem; método `unpack/pack --layout SourceCode` confirmado na
  documentação-fonte oficial (comandos em Preview e marcados como preteridos) e testado com `.msapp` público.

### Adicionado
- `scripts/extrair_esquema.py`: esquema real de 16 listas (nomes de exibição, `field_N`, tipos com evidência).
- `scripts/gerar_fontes.py` e `app-source/Src`: 9 telas, cabeçalho, menu por perfil, gravações em
  Ocorrências/Ações/Histórico.
- `scripts/validar_fontes.py`, `scripts/checar_sintaxe_fx.py`: validação offline (schema, controles, colunas,
  salvaguardas DIL_10, sintaxe Power Fx).
- `scripts/montar_msapp.py`: geração de candidato com backup, mescla preservadora e round-trip.
- Documentação em `docs/` e `STATUS_ATUAL.md`.

### Não alterado
- Listas DIL, colunas e fluxos DIL_01–DIL_10 (incluindo DIL_10).

### Pendente
- `.msapp` candidato (aguarda o original), validação no Studio e testes no tenant.
