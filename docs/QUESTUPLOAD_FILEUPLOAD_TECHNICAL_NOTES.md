# Nota tecnica: pergunta File upload

## Arquivos principais

- `classes/question/fileupload.php`: renderizacao da pergunta, opcoes do filemanager, exibicao das respostas e controles de configuracao.
- `classes/responsetype/fileupload.php`: persistencia das respostas e associacao entre respostas e arquivos.
- `db/install.xml`: tabela `questupload_response_file`.
- `db/install.php` e `db/upgrade.php`: registro do tipo de pergunta `fileupload`.
- `lib.php`: entrega dos arquivos via `mod_questupload_pluginfile()`.
- `backup/moodle2/backup_questupload_stepslib.php`: inclusao dos arquivos e registros no backup.
- `backup/moodle2/restore_questupload_stepslib.php`: restauracao dos arquivos e remapeamento dos ids.
- `classes/privacy/provider.php`: metadados, exportacao/exclusao de dados pessoais e limpeza de arquivos.
- `report.php`: exportacao de respostas com links internos dos anexos.
- `lang/en/questupload.php` e `lang/pt_br/questupload.php`: strings da interface.

## Modelo de dados

A resposta textual/numerica do Questionnaire nao era suficiente para anexos. Por isso foi criada a tabela:

`questupload_response_file`

Campos principais:

- `id`: identificador do registro de resposta de arquivo.
- `response_id`: resposta do usuario no questionario.
- `question_id`: pergunta do tipo fileupload.
- `fileid`: id do arquivo armazenado pela File API.

Os arquivos fisicos ficam na File API com:

- component: `mod_questupload`
- filearea: `response_file`
- itemid: registro correspondente em `questupload_response_file`

## Fluxo de envio

1. A pergunta renderiza um filemanager em `classes/question/fileupload.php`.
2. O draft itemid e enviado no campo `q{questionid}`.
3. Na submissao, `classes/responsetype/fileupload.php` salva os arquivos do draft para a area definitiva `response_file`.
4. Para cada arquivo salvo, e criado/atualizado um registro em `questupload_response_file`.
5. A resposta passa a referenciar os arquivos pelo id salvo na File API.

## Opcoes configuraveis por pergunta

### Tipos aceitos

Campo: `acceptedtypes`, salvo em `extradata` como JSON.

Opcoes atuais do seletor:

- `image`: Imagens
- `document`: Documentos gerais do Moodle
- `audio`: Audio
- `video`: Video
- `archive`: Arquivos compactados
- `.pdf`: PDF
- `wordprocessing`: Documentos Word/texto
- `spreadsheet`: Planilhas

### Quantidade maxima

Campo: `precise`.

Atualmente limitado entre 1 e 10 arquivos.

### Tamanho maximo

Campo: `length`.

Interpretado como megabytes. Valor 0 usa o limite global do Moodle.

## Normalizacao de tipos aceitos

O Moodle aceita grupos como `image`, `document` e `spreadsheet` em algumas partes da interface, mas o repositorio de upload faz validacao final por MIME. Para evitar rejeicoes indevidas, o plugin normaliza os tipos aceitos para extensoes concretas antes de entrega-los ao filemanager.

Exemplos verificados localmente:

- `image` expande para extensoes como `.jpg`, `.jpeg`, `.png`, `.gif`, `.svg`, `.tif`, `.tiff`.
- `document` expande para `.doc`, `.docx`, `.epub`, `.gdoc`, `.odt`, `.ott`, `.oth`, `.pdf`, `.rtf`.
- `wordprocessing` expande para `.doc`, `.docx`, `.odt`.
- `spreadsheet` expande para `.xls`, `.xlsx`, `.ods`.

Tambem ha suporte a valores legados salvos com virgula, como `.xls,.xlsx,.ods` e `.doc,.docx,.odt`.

## Exibicao das respostas

Em `myreport.php`, a exibicao e feita pela pergunta `fileupload`:

- Imagens web: renderizadas como imagem embutida.
- PDF: renderizado com visualizador embutido quando suportado.
- Midias suportadas: renderizadas pelo `core_media_manager`.
- Demais formatos: exibidos como link/botao de download.

Essa decisao evita tentar embutir arquivos Office que o navegador normalmente nao renderiza sem extensoes ou servicos externos.

## Exportacao de relatorio

O relatorio exportado inclui o nome do arquivo e um link interno de download via pluginfile.php. O link respeita as permissoes da instancia Moodle e exige usuario autenticado com acesso adequado.

## Pontos de atencao

- O plugin ainda deve ser validado manualmente com arquivos `.jpg` e `.jpeg` apos a ultima normalizacao de imagens.
- A categoria `Documentos` e ampla por herdar o grupo nativo `document` do Moodle.
- Antes do pacote final, revisar se a UI deve trocar `Documentos` por `Documentos gerais` para reduzir ambiguidade.
- Nao gerar ZIP final sem solicitacao explicita.
## Download ZIP de anexos implementado

Foi adicionada uma classe `mod_questupload\downloader` para gerar um ZIP com os arquivos enviados nas perguntas `File upload`.

Formato dos caminhos internos:

```text
Usuario Nome_3/
  resposta_1/
    Q01_Texto da pergunta/
      arquivo.pdf
```

A action `zipfiles` em `report.php` usa `mod/questupload:downloadresponses` e `sesskey`. O link fica disponivel na tela de opcoes de download do relatorio, junto ao download em formato de dados.

A implementacao usa `core_files\archive_writer` para streaming do ZIP e ignora registros inconsistentes sem interromper o download.

## Correcao de upload de imagens em perguntas sem extradata

Foi corrigido o fallback de `get_accepted_types()` para que perguntas `File upload` sem `extradata` valido tambem expandam os grupos padrao `image` e `document` para extensoes concretas antes da validacao do filemanager.

Impacto:

- Perguntas antigas/default passam a entregar `.jpg`, `.jpeg`, `.png` e demais extensoes do grupo de imagens ao repositório de upload.
- Perguntas configuradas explicitamente sem a opcao `Imagens` continuam rejeitando imagens por desenho da configuracao.

## ZIP combinado com relatorio CSV e anexos

Foi adicionada uma opcao extra no formulario de download do relatorio para gerar um unico ZIP contendo:

```text
relatorio_respostas.csv
anexos/
  Usuario_3/
    resposta_1/
      Q01_Texto da pergunta/
        arquivo.pdf
```

O fluxo antigo de download do relatorio e o ZIP somente com anexos permanecem disponiveis. A opcao combinada usa os mesmos checkboxes do formulario de download para gerar o CSV.

## ZIP combinado com PDFs individuais

Foi adicionada uma opcao desmarcada por padrao no formulario de download: `Incluir PDFs individuais das respostas no ZIP combinado`.

Quando essa opcao e marcada junto ao botao `Baixar relatorio CSV + arquivos em ZIP`, o pacote passa a incluir o CSV na raiz e um PDF por resposta dentro da estrutura auditavel por respondente:

```text
relatorio_respostas.csv
respondentes/
  Nome Sobrenome_3/
    resposta_1/
      resposta.pdf
      anexos/
        Q01_Upload de documento/
          arquivo.pdf
```

Regras da implementacao:

1. A opcao nao substitui o ZIP combinado ja validado; ela apenas acrescenta os PDFs e reorganiza os anexos no modo completo.
2. O PDF e gerado em memoria com TCPDF/pdflib do Moodle, sem gravar arquivos temporarios permanentes.
3. Cada PDF contem cabecalho com nome do questionario, respondente, data de envio, curso, id da resposta, dados do relatorio e lista dos anexos vinculados.
4. A mesma capability `mod/questupload:downloadresponses` e o mesmo filtro de grupo do relatorio sao respeitados.
5. O visual do PDF e simples e administrativo, podendo evoluir depois sem alterar o modelo de dados.
## Hierarquia visual dos downloads

A tela de download do relatorio prioriza o fluxo combinado, pois ele entrega o CSV e os anexos em um unico pacote. O ZIP somente com anexos foi mantido como opcao secundaria para cenarios em que o relatorio nao e necessario.
## Permissoes dos anexos de resposta

Os arquivos em `response_file` e `response_attachment` sao servidos por `questupload_pluginfile()`.

Regra validada no build `2026060807`:

- o dono da resposta pode baixar os proprios anexos;
- usuarios com `mod/questupload:downloadresponses`, `mod/questupload:readallresponseanytime` ou `mod/questupload:viewsingleresponse` podem baixar anexos administrativos;
- `mod/questupload:readallresponses` nao e usada isoladamente para autorizar download direto de anexos, evitando que papeis com visualizacao ampla herdada acessem arquivos indevidamente.