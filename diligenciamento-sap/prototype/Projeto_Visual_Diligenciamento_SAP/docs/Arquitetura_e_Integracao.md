# Arquitetura e integração

## Camadas
- Interface: Power Apps Canvas.
- Dados: listas SharePoint DIL existentes.
- Automação: Power Automate.
- Comunicação: Outlook/Teams conforme os fluxos existentes.
- Análise: Power BI.

## Princípios
- Não recriar listas.
- Não alterar automações já validadas sem defeito reproduzível.
- Fazer mapeamento de esquema antes de escrever fórmulas contra uma lista.
- Não assumir nomes de colunas a partir de rótulos exibidos.
- Validar relações entre RC, PO e item conforme os dados existentes.
- Aplicar permissões tanto na interface quanto nas fontes/fluxos.
- Usar registros de teste controlados antes de liberar para usuários.

## Mapeamento pendente antes da conexão
Para cada lista que será exibida, documentar:
1. Nome exato da lista.
2. Nome interno e tipo de cada coluna necessária.
3. Chave/relacionamento com outras listas.
4. Campo de status e seus valores possíveis.
5. Campo de data relevante.
6. Perfil que pode consultar e editar.
7. Se a tela apenas consulta ou também grava.

## Integração com e-mails
DIL_10 já foi validado para o cenário de escalonamento descrito no histórico do projeto. O app deve consultar o estado das comunicações, não duplicar o envio. A edição de modelos exige um ajuste planejado do fluxo para buscar e aplicar o modelo configurado, com teste de idempotência antes de ativar em produção.

## Critério de pronto para cada tela
- Layout responsivo para desktop/tablet.
- Dados de teste reais exibidos corretamente.
- Estados vazio, carregando e erro.
- Permissões testadas.
- Nenhuma regressão em fluxos existentes.
