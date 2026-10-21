# Sistema de Diligenciamento SAP — Canvas App

Aplicativo Power Apps Canvas (desktop/tablet) sobre as listas SharePoint DIL e os fluxos DIL_01–DIL_10 existentes.
**Situação:** fontes geradas e validadas offline; `.msapp` candidato aguardando o arquivo original — ver
[`STATUS_ATUAL.md`](STATUS_ATUAL.md).

## Estrutura

```
diligenciamento-sap/
  STATUS_ATUAL.md            o que está confirmado, inferido, pendente, inacessível, não testado
  CHANGELOG.md
  app-source/Src/*.pa.yaml   FONTE do app (formato oficial pa.yaml v3.0) — gerada, não editar à mão
  schema/dil_schema.json     esquema real das listas DIL (gerado do DIL.zip; sem dados do tenant)
  schema/microsoft/          schema oficial pa.yaml e enum de controles (MIT, Microsoft)
  scripts/
    extrair_esquema.py       DIL.zip (CSVs + fluxos) -> schema/ e docs/Mapeamento_Listas_DIL.md
    gerar_fontes.py          descreve as 9 telas e gera app-source/Src
    validar_fontes.py        schema, controles, nomes, colunas, salvaguardas do DIL_10
    checar_sintaxe_fx.py     sintaxe de todas as fórmulas no motor Power Fx do PAC
    montar_msapp.py          original.msapp + fontes -> .msapp candidato (backup, mescla, pack, round-trip)
    verificar_tudo.sh        regenera e valida tudo
  docs/                      arquitetura, telas, permissões, mapeamento, testes, guia de importação
  prototype/                 protótipo visual aprovado (referência; não é convertido em .msapp)
  artifacts/                 original/ candidate/ release/ (.msapp não versionados)
  local/                     insumos com dados do tenant (ignorado pelo git)
```

## Uso rápido

```bash
pip install -r scripts/requirements.txt
bash scripts/verificar_tudo.sh                                    # gera e valida
python3 -I scripts/montar_msapp.py "Sistema de Diligenciamento.msapp"   # requer pac
```

Depois: abrir o candidato no Power Apps Studio e seguir [`docs/Guia_Importacao.md`](docs/Guia_Importacao.md).

## Regras do projeto

- Listas DIL, colunas e fluxos existentes **não** são criados, renomeados ou alterados por este projeto.
- O app nunca envia e-mail; o DIL_10 continua sendo o único motor de envio (o build verifica isso).
- Nenhum `.msapp` é dado como importável sem abertura no Power Apps Studio.
- Mudanças no app são feitas no gerador (`scripts/gerar_fontes.py`), que é a fonte versionada.
- Repositório público: não versionar URL do site, GUIDs, CSVs, definições de fluxo, e-mails ou `.msapp`.
