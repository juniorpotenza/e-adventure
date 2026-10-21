# Projeto Visual — Sistema de Diligenciamento SAP

## Objetivo
Este pacote é a especificação visual e funcional inicial para construir o Canvas App do Sistema de Diligenciamento SAP. Ele NÃO é um arquivo `.msapp` e não deve ser importado como aplicativo Power Apps.

## Preservação do trabalho existente
- Não recriar nem renomear listas DIL existentes.
- Não alterar o fluxo DIL_10, que já foi validado no cenário de escalonamento e proteção contra duplicidade.
- Conectar as fontes de dados somente depois de conferir os nomes e tipos reais das colunas.
- Campos ainda não confirmados são identificados como pendentes de mapeamento.

## Conteúdo
- `prototipo/index.html`: protótipo navegável de interface para desktop, com menu, dashboard, carteira, dossiê, gestão e administração demonstrativas.
- `docs/Mapa_de_Telas.md`: inventário de telas e componentes.
- `docs/Perfis_e_Permissoes.md`: matriz inicial de perfis.
- `docs/Arquitetura_e_Integracao.md`: arquitetura e cuidados de integração.
- `formulas/PowerFx_Modelos.md`: fórmulas-modelo Power Fx; precisam ser ajustadas aos nomes reais dos controles e colunas.

## Limitação importante
O protótipo HTML serve para validar o desenho e a navegação visual. Ele não é uma aplicação conectada ao SharePoint e não envia e-mails. O arquivo `.msapp` final deve ser gerado/exportado e validado no Power Apps Studio ou com ferramentas oficiais do Power Platform.
