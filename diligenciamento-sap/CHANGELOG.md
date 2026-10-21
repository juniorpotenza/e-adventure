# Changelog

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
