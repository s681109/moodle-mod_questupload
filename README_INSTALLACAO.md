# Formulário com Anexos (`mod_questupload`)

## Objetivo

O `mod_questupload` é um módulo de atividade Moodle baseado no Questionnaire, com suporte adicional a perguntas do tipo **Envio de arquivo**. Ele permite coletar respostas e anexos dos usuários, exportar relatórios e baixar os arquivos enviados em pacotes ZIP organizados.

O nome público exibido ao usuário é **Formulário com Anexos**. O componente interno permanece `mod_questupload`.

## Requisitos

- Moodle 4.4.4+ ou compatível com `$plugin->requires` em `version.php`.
- Permissão administrativa para instalar módulos em `mod/`.
- PHP e extensões exigidas pela própria instalação Moodle.

## Instalação

1. Copie a pasta do plugin para:

```text
mod/questupload
```

2. Acesse a administração do Moodle pelo navegador para concluir a atualização, ou execute via CLI a partir da raiz do Moodle:

```bash
php admin/cli/upgrade.php --non-interactive
```

Exemplo em uma instalação local Windows/WampServer:

```powershell
D:\wamp64\bin\php\php8.1.33\php.exe D:\wamp64\www\moodle44\admin\cli\upgrade.php --non-interactive
```

3. Limpe os caches após a instalação ou atualização:

```bash
php admin/cli/purge_caches.php
```

Exemplo em uma instalação local Windows/WampServer:

```powershell
D:\wamp64\bin\php\php8.1.33\php.exe D:\wamp64\www\moodle44\admin\cli\purge_caches.php
```

## Criação da atividade

1. Entre em um curso com perfil que possa editar atividades.
2. Ative o modo de edição.
3. Adicione a atividade **Formulário com Anexos**.
4. Configure nome, disponibilidade, tentativas, visibilidade de respostas e demais opções do formulário.
5. Salve e acesse a aba de perguntas.

## Configuração da pergunta Envio de arquivo

1. Na edição de perguntas, adicione uma pergunta do tipo **Envio de arquivo**.
2. Configure os tipos aceitos, por exemplo imagens, documentos, planilhas, PDF ou arquivos compactados.
3. Defina a quantidade máxima de arquivos permitidos.
4. Defina o tamanho máximo por arquivo, quando necessário.
5. Salve a pergunta e faça um envio de teste com um usuário respondente.

## Download de relatórios e anexos

Na tela de relatório da atividade, a opção principal é:

```text
Baixar relatório CSV + arquivos em ZIP
```

Esse ZIP pode conter:

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

A opção **Incluir PDFs individuais das respostas no ZIP combinado** adiciona um `resposta.pdf` em cada pasta de resposta. O PDF apresenta as perguntas/respostas em destaque e mantém os dados administrativos em um bloco menor.

Também existe a opção secundária:

```text
Baixar somente arquivos enviados em ZIP
```

Use essa opção quando a equipe precisar apenas dos anexos, sem relatório CSV e sem PDFs individuais.

## Permissões

O download de respostas e arquivos usa a capacidade:

```text
mod/questupload:downloadresponses
```

Os links internos de arquivos exportados no CSV passam por `pluginfile.php` e respeitam autenticação, contexto da atividade e permissões do Moodle. Usuários sem acesso adequado não devem conseguir baixar anexos diretamente.

## Backup e restore

O plugin inclui suporte a backup/restore dos arquivos enviados. Após restaurar um curso ou atividade, recomenda-se validar:

- se as perguntas do tipo Envio de arquivo foram restauradas;
- se as respostas continuam disponíveis;
- se os anexos aparecem em `myreport.php` e nos relatórios administrativos;
- se o ZIP combinado gera CSV, PDFs e anexos corretamente.

## Observações finais

- O plugin pode coexistir com o `mod_questionnaire`, pois o componente interno é independente.
- Não renomeie a pasta `questupload` nem o componente `mod_questupload`.
- O repositório remoto recomendado é `s681109/moodle-mod_questupload`, com branch estável `MOODLE_404_STABLE`.
- Antes de instalar em produção, valide permissões, backup/restore, exclusão de respostas e geração de ZIP em uma instância de homologação.
