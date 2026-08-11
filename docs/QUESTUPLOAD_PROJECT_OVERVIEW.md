# Projeto mod_questupload

## Objetivo

O `mod_questupload` é um fork independente do plugin Moodle Questionnaire (`mod_questionnaire`), criado para permitir instalação lado a lado com o plugin original, sem sobrescrever, remover ou migrar automaticamente uma instalação existente de Questionnaire.

O objetivo principal do fork é manter a base funcional do Questionnaire e acrescentar um novo tipo de pergunta com upload de arquivos, usando a File API nativa do Moodle.

## Componente Moodle

- Tipo: modulo de atividade
- Componente: `mod_questupload`
- Pasta de instalacao: `mod/questupload`
- Base de desenvolvimento atual: Moodle 4.4 local em `D:\wamp64\www\moodle44`
- Build local atual: `2026060808`
- Repositorio remoto recomendado: `s681109/moodle-mod_questupload`
- Branch estavel recomendada: `MOODLE_404_STABLE`
- Compatibilidade minima declarada: Moodle 4.4.4+
- Compatibilidade alvo do projeto: linha Moodle 4.4

## Decisoes de arquitetura

1. O fork deve ser um plugin novo, nao uma alteracao direta em `mod_questionnaire`.
2. O nome do componente, tabelas, strings, classes e fileareas devem usar `questupload`/`mod_questupload`.
3. A nova funcionalidade de upload deve usar recursos nativos do Moodle, especialmente:
   - File API
   - filemanager/filepicker
   - pluginfile.php via `mod_questupload_pluginfile()`
   - backup/restore nativo
   - privacy provider
4. O plugin original `mod_questionnaire` deve poder permanecer instalado na mesma instancia.

## Funcionalidades implementadas

- Novo tipo de pergunta `fileupload`.
- Upload por filemanager nativo do Moodle.
- Controle por pergunta de:
  - tipos de arquivos aceitos;
  - quantidade maxima de arquivos;
  - tamanho maximo por arquivo.
- Armazenamento de arquivos em `mod_questupload`, filearea `response_file`.
- Metadados de resposta em `questupload_response_file`.
- Exibicao de respostas em `myreport.php`:
  - preview embutido para imagens web, PDF e midias suportadas pelo Moodle;
  - botao/link de download para arquivos que o navegador nao renderiza bem, como DOCX/XLSX.
- Exportacao de relatorio com link interno para download dos anexos.
- Download ZIP somente com anexos, organizado por usuario/resposta/pergunta/arquivo.
- Download ZIP combinado com `relatorio_respostas.csv` e anexos.
- Opcao extra no ZIP combinado para incluir `resposta.pdf` individual em cada pasta de resposta.
- Backup e restore incluindo os arquivos enviados.
- Privacy provider incluindo metadados e remocao dos arquivos de resposta.
- Traducao `pt_br` criada para o plugin.

## Estado atual

O plugin esta funcional na instancia local Moodle 4.4. O ultimo ajuste aplicado foi o ZIP combinado com opcao de PDFs individuais por resposta, mantendo o CSV na raiz e organizando anexos/PDFs por respondente e resposta.

Ainda nao foi gerado pacote final apos os ajustes recentes. O pacote final deve ser gerado apenas quando solicitado.

## Proxima sessao

Validar manualmente:

- Upload de `.jpg` e `.jpeg` com a categoria `Imagens` selecionada.
- Upload de `.xls`, `.xlsx` e `.ods` com a categoria `Planilhas` selecionada.
- Download e visualizacao dos anexos em `myreport.php`.
- Exportacao do relatorio com links internos dos anexos.

Depois da validacao, revisar se a categoria `Documentos` deve ser mantida como grupo amplo do Moodle ou substituida/renomeada para categorias mais especificas.
## Melhoria planejada: download ZIP dos anexos

A implementacao deve adicionar ao relatorio administrativo uma opcao para baixar todos os anexos enviados nas perguntas `File upload` em um unico arquivo ZIP.

Formato definido para os caminhos internos do ZIP:

```text
Nome Sobrenome_3/
  resposta_1/
    Q01_Upload de documento/
      arquivo.pdf
      planilha.xlsx
```

Decisoes:

- Organizar por usuario/resposta/pergunta/arquivo para evitar colisao de nomes e facilitar auditoria administrativa.
- Usar `core_files\archive_writer` para gerar o ZIP por streaming, seguindo a estrategia do `mod_assign`.
- Criar um downloader proprio em `mod_questupload\classes\downloader.php`.
- Adicionar uma action em `report.php` para acionar o download.
- Adicionar um link/botao na pagina de download do relatorio.
- Adicionar strings em `lang/en/questupload.php` e `lang/pt_br/questupload.php`.
- Reutilizar a capability `mod/questupload:downloadresponses`.

Validacoes esperadas:

- Apenas usuarios com permissao de baixar respostas conseguem gerar o ZIP.
- O filtro de grupo atual do relatorio deve ser respeitado.
- Arquivos inexistentes ou registros inconsistentes devem ser ignorados sem quebrar o ZIP.
- Se nao houver anexos, o usuario deve receber uma mensagem informativa e retornar para a pagina de download.
- O pacote final do plugin nao deve ser gerado automaticamente apos essa implementacao.

## Melhoria implementada: ZIP combinado com PDFs individuais

A tela de download do relatorio possui uma opcao extra para incluir PDFs individuais no ZIP combinado. Quando marcada, o ZIP contem:

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

Essa estrutura foi adotada para evitar colisao de nomes, aproximar anexos e resposta textual, e facilitar auditoria administrativa por usuario/resposta/pergunta/arquivo.
## Validacao executada: backup/restore com anexos

Em 2026-06-08 foi executado teste local de ciclo de vida usando a atividade original `questupload id=1`, `cmid=3`, com 1 resposta e 4 anexos.

Resultado:

- Backup da atividade gerado via `admin/cli/backup.php --cmid=3`.
- Restore executado via `admin/cli/restore_backup.php` no curso de teste `QUP_RESTORE_20260608_101457` (`courseid=3`).
- Atividade restaurada criada como `questupload id=4`, `cmid=8`.
- Respostas restauradas: 1.
- Anexos restaurados: 4.
- ZIP combinado apos restore validado com `relatorio_respostas.csv`, 1 `resposta.pdf` e 4 anexos em `respondentes/.../resposta_1/anexos/...`.
- PDF gerado em memoria validado com assinatura `%PDF`.
- CSV restaurado validado com 13 colunas, 1 linha de dados e cabecalho em portugues.

A pasta temporaria usada para armazenar o `.mbz` foi removida apos a validacao. O curso restaurado foi mantido para eventual conferencia manual.
## Nomenclatura publica

A partir do build `2026060804`, a nomenclatura exibida ao usuario passa a ser `Formulario com Anexos` / `Form with Attachments`, mantendo internamente o componente `mod_questupload`, a pasta `questupload` e as tabelas `questupload_*`.

Tambem foi ajustada a hierarquia visual da tela de download:

- `Baixar relatorio CSV + arquivos em ZIP` passa a ser a acao principal.
- `Baixar somente arquivos enviados em ZIP` permanece como opcao secundaria para quando a equipe administrativa desejar apenas os anexos.

A avaliacao sobre um futuro `Baixar relatorio XLSX + arquivos em ZIP` fica registrada como melhoria posterior, sem implementacao neste build.
## Correcao executada: codificacao e ZIP invalido

No build `2026060805`, foi corrigido um problema de codificacao no arquivo `lang/pt_br/questupload.php` que gerava textos como `QuestÃµes`, `SumÃ¡rio` e `OpÃ§Ãµes` na interface.

Tambem foram removidos BOMs UTF-8 de arquivos PHP regravados recentemente (`report.php`, `version.php`, `lang/en/questupload.php`, `lang/pt_br/questupload.php`). O BOM em `report.php` podia enviar bytes antes do stream do ZIP e tornar o arquivo baixado invalido no Windows.

Validacao tecnica executada:

- Nenhum arquivo PHP do plugin permaneceu com BOM.
- `ZipArchive` abriu com sucesso um ZIP gerado pelo proprio `mod_questupload\downloader`.
- O ZIP testado iniciou com assinatura `PK` e continha `relatorio_respostas.csv`, `resposta.pdf` e os anexos esperados.
## Acabamento executado: nomenclatura, PDF e README

No build `2026060806`, as strings publicas em portugues foram mantidas em UTF-8 sem BOM com acentos corretos, incluindo `Formulário com Anexos` e `Baixar relatório CSV + arquivos em ZIP`.

O PDF individual foi ajustado para apresentar as perguntas/respostas como conteudo principal e mover dados administrativos para um bloco menor ao final do arquivo. Assim o PDF fica mais legivel para equipes administrativas sem perder metadados uteis para auditoria.

Foi criado o arquivo `README_INSTALLACAO.md` com instrucoes simples para instalacao, upgrade, criacao da atividade, configuracao da pergunta Envio de arquivo, download de relatorios/ZIP e observacoes sobre permissoes.
## Revisao de robustez executada

No build `2026060807`, foi executada uma revisao de robustez cobrindo permissoes, exclusao de respostas, backup/restore complementar, strings e checklist final.

Resultados principais:

- `pluginfile()` para `response_file` e `response_attachment` foi endurecido: o anexo agora pode ser acessado pelo dono da resposta ou por usuarios com `mod/questupload:downloadresponses`, `mod/questupload:readallresponseanytime` ou `mod/questupload:viewsingleresponse`.
- Foi removido o uso de `mod/questupload:readallresponses` como permissao direta para baixar anexos, pois essa capability pode existir em papel de estudante conforme configuracao herdada.
- Validacao de permissao confirmou: dono da resposta acessa; administrador acessa; estudante nao dono nao acessa, mesmo tendo `readallresponses`.
- Exclusao de resposta em atividade restaurada de teste removeu 4 registros de `questupload_response_file` e 8 registros da File API em `response_file`.
- Backup/restore complementar restaurou 1 resposta e 4 anexos; ZIP combinado gerou CSV, 1 PDF individual e 4 anexos.
- Strings `pt_br` foram normalizadas para `Formulário com Anexos`, removendo termos publicos herdados como `enquete`.
## Pre-pacote/checklist final

O build `2026060807` foi validado como candidato pre-pacote. A validacao incluiu lint completo, checagem de schema, limpeza de cache, resolucao de strings criticas via Moodle, varredura de BOM/temporarios e teste operacional do ZIP combinado.

Nenhum pacote ZIP final foi gerado. A proxima etapa, quando autorizada, e gerar o pacote final com raiz `questupload/` e conferir seu conteudo.

## Organizacao do repositorio

Para alinhar o projeto ao padrao usado no tema Moodle da instituicao, o repositorio deve usar o nome `moodle-mod_questupload` no GitHub, mantendo internamente:

- componente Moodle: `mod_questupload`;
- pasta de instalacao: `mod/questupload`;
- branch estavel da linha Moodle 4.4: `MOODLE_404_STABLE`;
- requisito minimo: Moodle 4.4.4+.

No build `2026060808`, `version.php` foi ajustado para declarar Moodle 4.4.4+ como requisito minimo (`2024042204`) e release `4.4.4-uploadfork`.
