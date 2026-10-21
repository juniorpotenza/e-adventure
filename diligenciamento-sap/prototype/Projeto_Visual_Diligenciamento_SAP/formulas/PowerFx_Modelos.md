# Modelos Power Fx — não copiar sem mapear o esquema

Os exemplos abaixo são padrões. Antes de usar, substitua os nomes entre `<...>` pelos nomes reais de listas, colunas e controles. Não conectar nem inventar nomes de campos.

## Navegação entre telas
Exemplo de botão de carteira:
```powerfx
Navigate(scrCarteira; ScreenTransition.Fade)
```

## Abrir dossiê a partir de uma galeria
Na propriedade `OnSelect` do item:
```powerfx
Set(varProcessoSelecionado; ThisItem);;
Navigate(scrDossie; ScreenTransition.Fade)
```
`varProcessoSelecionado` deve guardar um registro de uma fonte de dados real; confirmar se o item da galeria já é o registro principal ou uma junção.

## Pesquisa e filtro — modelo
```powerfx
Filter(
    <FonteDeDados>;
    IsBlank(txtBusca.Text) ||
    StartsWith(<ColunaPesquisavel>; txtBusca.Text)
)
```
Ajustar nomes de colunas, delegação e tipos de dados. Evitar funções não delegáveis em grandes listas.

## Exibir menu por perfil — modelo de interface
```powerfx
varPerfilUsuario = "Administrador"
```
Não usar isso como única barreira de segurança; proteger também os dados e operações.

## Validação de formulário — padrão
```powerfx
If(
    frmRegistro.Valid;
    SubmitForm(frmRegistro);
    Notify("Revise os campos obrigatórios."; NotificationType.Error)
)
```

## Salvaguardas
- Confirmar se o ambiente usa `;` para argumentos e `;;` para encadear ações.
- Conferir o nome real do controle/formulário.
- Testar com dados de homologação.
- Não usar contagens globais de SharePoint como indicador executivo sem considerar limites de delegação.
