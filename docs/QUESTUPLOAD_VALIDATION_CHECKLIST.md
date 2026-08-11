# Checklist de validacao e pacote final

## Regra combinada

Nao gerar pacote final do plugin sem solicitacao explicita.

## Validacao rapida de codigo

Executar a partir da instancia Moodle 4.4 local:

```powershell
D:\wamp64\bin\php\php8.1.33\php.exe -l D:\wamp64\www\moodle44\mod\questupload\classes\question\fileupload.php
D:\wamp64\bin\php\php8.1.33\php.exe -l D:\wamp64\www\moodle44\mod\questupload\version.php
D:\wamp64\bin\php\php8.1.33\php.exe D:\wamp64\www\moodle44\admin\cli\purge_caches.php
D:\wamp64\bin\php\php8.1.33\php.exe D:\wamp64\www\moodle44\admin\cli\check_database_schema.php
```

## Validacao manual recomendada

### Criacao da pergunta

1. Entrar como usuario com permissao de edicao.
2. Criar ou editar um questionario `questupload`.
3. Adicionar uma pergunta do tipo `File upload` / `Envio de arquivo`.
4. Configurar:
   - tipos aceitos;
   - numero maximo de arquivos;
   - tamanho maximo por arquivo.
5. Salvar e confirmar que as configuracoes permanecem selecionadas ao reeditar a pergunta.

### Tipos de arquivo

Testar upload com as categorias abaixo:

- Imagens: `.jpg`, `.jpeg`, `.png`.
- Planilhas: `.xls`, `.xlsx`, `.ods`.
- Word/texto: `.doc`, `.docx`, `.odt`.
- PDF: `.pdf`.
- Arquivos compactados, se habilitado: `.zip`.

Para cada envio, confirmar:

- O arquivo e aceito quando a categoria correta esta selecionada.
- O arquivo e rejeitado quando nao pertence aos tipos selecionados.
- A mensagem de erro e compreensivel.

### Multiplos arquivos

1. Configurar `Numero maximo de arquivos` maior que 1.
2. Enviar dois ou mais arquivos permitidos.
3. Confirmar que todos aparecem na resposta.
4. Confirmar que exceder o limite e bloqueado.

### Tamanho maximo

1. Configurar limite baixo, por exemplo 1 MB.
2. Enviar arquivo maior que o limite.
3. Confirmar rejeicao.
4. Enviar arquivo menor que o limite.
5. Confirmar aceite.

### Visualizacao em myreport.php

Confirmar:

- Imagens aparecem embutidas.
- PDF aparece em visualizador embutido quando suportado pelo navegador/Moodle.
- DOC/DOCX/XLS/XLSX/ODS aparecem como link/botao de download.
- Usuario respondente consegue ver suas respostas conforme permissao.
- Usuario administrador/professor consegue ver respostas conforme permissao.

### Exportacao de relatorio

1. Acessar o relatorio do questionario.
2. Exportar respostas em XLSX/CSV conforme fluxo disponivel.
3. Confirmar que a coluna da pergunta de upload traz:
   - nome do arquivo;
   - link interno de download.
4. Abrir o link autenticado no navegador.
5. Confirmar que o download respeita permissao de acesso.

### Download ZIP de anexos

1. Acessar a tela de download do relatorio.
2. Usar o download `Baixar arquivos enviados em ZIP`.
3. Confirmar estrutura `Usuario/resposta/pergunta/arquivo`.
4. Confirmar que o filtro de grupo atual e respeitado.

### ZIP combinado com CSV

1. Acessar a tela de download do relatorio.
2. Usar o botao `Baixar relatorio CSV + arquivos em ZIP` com a opcao de PDF desmarcada.
3. Confirmar que o ZIP contem `relatorio_respostas.csv` e a pasta `anexos/`.
4. Confirmar que o CSV abre corretamente e os anexos estao presentes.

### ZIP combinado com PDFs individuais

1. Marcar `Incluir PDFs individuais das respostas no ZIP combinado`.
2. Usar o botao `Baixar relatorio CSV + arquivos em ZIP`.
3. Confirmar que o ZIP contem `relatorio_respostas.csv` e a pasta `respondentes/`.
4. Confirmar a estrutura `respondentes/Nome Sobrenome_3/resposta_1/resposta.pdf`.
5. Confirmar que os anexos da mesma resposta ficam em `anexos/Qxx_Texto da pergunta/`.
6. Abrir um `resposta.pdf` e validar cabecalho, dados da resposta e lista de anexos.
7. Repetir com filtro de grupo, quando aplicavel.
### Backup e restore

1. Fazer backup de um curso contendo questionario com uploads.
2. Restaurar em outro curso/instancia local.
3. Confirmar que perguntas, respostas e anexos foram restaurados.
4. Confirmar que links/visualizacao dos anexos funcionam apos restore.

### Privacidade e exclusao

1. Excluir uma resposta com arquivo.
2. Confirmar que registros de `questupload_response_file` sao removidos.
3. Confirmar que arquivos da filearea `response_file` sao removidos.
4. Se possivel, validar exportacao/exclusao de dados pessoais via ferramentas de privacidade do Moodle.

## Antes de gerar pacote final

1. Revisar `version.php` e decidir se o build deve ser incrementado.
2. Confirmar que `version.php` declara compatibilidade minima com Moodle 4.4.4+.
3. Confirmar que a branch de manutencao publicada e `MOODLE_404_STABLE`.
4. Confirmar que o repositorio remoto usa o padrao `moodle-mod_questupload`.
5. Rodar lint PHP dos arquivos alterados.
6. Rodar `purge_caches.php`.
7. Rodar `upgrade.php --non-interactive` se houver incremento de build.
8. Rodar `check_database_schema.php`.
9. Confirmar que nao ha arquivos temporarios `.codex_*` dentro do plugin.
10. Gerar ZIP apenas quando solicitado.
11. Conferir o ZIP:
   - raiz deve ser `questupload/`;
   - nao deve conter `.git/` nem `.codex_*`;
   - deve conter `lang/pt_br/questupload.php`.

## Comando de pacote, quando solicitado

```powershell
Compress-Archive -LiteralPath 'D:\wamp64\www\moodle44\mod\questupload' -DestinationPath 'D:\wamp64\www\moodle44\mod_questupload-VERSAO.zip' -Force
```

Substituir `VERSAO` pelo build definido em `version.php`.
## Registro de validacao

### Backup/restore com anexos validado em 2026-06-08

- Origem: `questupload id=1`, `cmid=3`, curso `C1`.
- Destino: curso `QUP_RESTORE_20260608_101457`, `courseid=3`.
- Atividade restaurada: `questupload id=4`, `cmid=8`.
- Resultado do restore: 1 resposta e 4 anexos preservados.
- ZIP combinado apos restore: CSV gerado, 1 PDF individual gerado, 4 anexos em estrutura `respondentes/.../resposta_1/anexos/...`.
- Estrutura de banco: validada anteriormente com `check_database_schema.php` sem divergencias.
### Revisao de robustez validada em 2026-06-08

- Permissoes de anexo: dono da resposta `YES`, administrador `YES`, estudante nao dono `NO`.
- Ajuste de seguranca: `readallresponses` nao autoriza mais download direto de anexos via `pluginfile()`.
- Exclusao de resposta: resposta de teste restaurada excluida com sucesso; registros `questupload_response_file` foram de 4 para 0; arquivos na File API foram de 8 para 0.
- Backup/restore complementar: atividade restaurada no curso `QUP_RESTORE_ROBUST_20260608_134451`; nova atividade `questupload id=6`, `cmid=11`; 1 resposta, 4 anexos, 1 PDF e CSV gerados corretamente.
- Varredura de strings `pt_br`: termos publicos principais normalizados para `Formulário com Anexos`; nao restaram ocorrencias de `enquete` nem `Questionnaire Upload` no arquivo de idioma `pt_br`.
- Build revisado: `2026060807`.
### Pre-pacote/checklist final executado em 2026-06-08

- Build candidato: `2026060808`.
- `version.php`: componente `mod_questupload`, requires `2024042204`, release `4.4.4-uploadfork (Build - 2026060808)`, maturity `MATURITY_STABLE`.
- Branch estavel planejada: `MOODLE_404_STABLE`.
- Repositorio remoto planejado: `s681109/moodle-mod_questupload`.
- Lint completo: 121 arquivos PHP verificados com `php -l`, sem erros.
- Banco: `check_database_schema.php` retornou `Database structure is ok`.
- Cache: `purge_caches.php` executado antes da checagem final de strings.
- Strings criticas resolvidas pelo Moodle:
  - `Formulário com Anexos`.
  - `Baixar respostas e arquivos enviados`.
  - `Baixar relatório CSV + arquivos em ZIP`.
  - `Dados administrativos`.
- Varredura de artefatos dentro do plugin:
  - nenhum `.codex_*`.
  - nenhum `.git/`.
  - nenhum PHP com BOM UTF-8.
  - nenhum ZIP/backup temporario gerado pelo desenvolvimento.
- Observacao: existe `tests/fixtures/backup-activity-questupload.mbz`, mantido como fixture de teste do plugin.
- ZIP operacional validado sem gerar pacote final: stream iniciou com assinatura `PK`, `ZipArchive=OK`, 6 entradas e primeira entrada `relatorio_respostas.csv`.
- Cursos locais de teste ainda existentes na instancia Moodle local:
  - `courseid=3`, `QUP_RESTORE_20260608_101457`.
  - `courseid=4`, `QUP_RESTORE_ROBUST_20260608_134451`.
  Esses cursos nao ficam dentro do plugin nem entram no pacote final, mas podem ser removidos da instancia local quando nao forem mais necessarios.
- Pacote final nao foi gerado nesta etapa.
### Pacote final gerado em 2026-06-08

- Arquivo: `D:\wamp64\www\moodle44\mod_questupload-2026060807.zip`.
- Tamanho: 648007 bytes.
- Raiz do ZIP: `questupload/`.
- Entradas: 272.
- Arquivos obrigatorios conferidos:
  - `questupload/version.php`.
  - `questupload/README_INSTALLACAO.md`.
  - `questupload/lang/pt_br/questupload.php`.
  - `questupload/classes/downloader.php`.
  - `questupload/classes/question/fileupload.php`.
  - `questupload/classes/responsetype/fileupload.php`.
- Artefatos ausentes no pacote: `.git/`, `.github/`, `.codex_*`, `.zip`, `.tmp`, `.bak`, `.log`.
- PHP com BOM dentro do ZIP: 0.
- `version.php` dentro do ZIP: `2026060807`, componente `mod_questupload`, release `4.4.0-uploadfork (Build - 2026060807)`, maturity `MATURITY_STABLE`.
- Observacao: este ZIP foi gerado antes da alteracao de compatibilidade minima para Moodle 4.4.4+ e deve ser regenerado antes de uma nova distribuicao.
- Observacao: `tests/fixtures/backup-activity-questupload.mbz` foi mantido por ser fixture de teste do plugin.
