# Sistema de Agendamento - Barbearia Prime

Aplicação web em PHP com MySQL que permite visualizar serviços, barbeiros disponíveis e realizar agendamentos on-line com confirmação imediata. O layout utiliza TailwindCSS via CDN com foco em uma experiência moderna e responsiva.

## Recursos principais

- Catálogo de serviços com preços, duração e descrição.
- Listagem dinâmica dos barbeiros com destaque para experiência profissional.
- Formulário de agendamento validado no servidor com prevenção de conflitos de horário.
- Painel de próximos agendamentos atualizado em tempo real.
- Interface responsiva com componentes modernos e microinterações de foco.

## Requisitos

- PHP 8.1 ou superior com extensão PDO para MySQL habilitada.
- Servidor MySQL 5.7+ ou MariaDB equivalente.
- Composer não é obrigatório (nenhuma dependência externa é utilizada).

## Preparando o banco de dados

1. Crie a base de dados e tabelas executando o script [`database.sql`](database.sql):

   ```bash
   mysql -u root -p < database.sql
   ```

2. Ajuste as credenciais do banco de dados em [`config.php`](config.php) conforme o seu ambiente (host, porta, usuário e senha).

## Executando o projeto localmente

1. Inicie o servidor de desenvolvimento PHP dentro da pasta `public`:

   ```bash
   php -S 0.0.0.0:8000 -t public
   ```

2. Acesse [http://localhost:8000](http://localhost:8000) no navegador para visualizar a interface da barbearia.

3. Preencha o formulário de agendamento. Um novo registro será salvo na tabela `appointments` e automaticamente exibido na área "Agenda do dia".

## Personalização

- Adicione ou edite serviços e barbeiros diretamente no banco para refletir novas opções na interface.
- Ajuste a paleta de cores no bloco `tailwind.config` dentro de [`public/index.php`](public/index.php).
- Inclua novas seções ou integrações (ex.: envio de e-mails, notificações SMS) utilizando a mesma estrutura de validação existente.

## Estrutura do projeto

```
├── config.php          # Configurações de conexão com o banco de dados
├── database.sql        # Script SQL com tabelas e dados iniciais
├── public/
│   └── index.php       # Interface principal e lógica de agendamento
└── README.md
```

## Boas práticas implementadas

- Uso de declarações preparadas `PDO` para evitar SQL Injection.
- Sanitização de dados ao exibir informações vindas do banco ou do formulário.
- Feedback visual para erros e sucessos seguindo técnicas de UX.
- Layout responsivo com TailwindCSS e foco em acessibilidade (cores, estados de foco, hierarquia visual).

## Próximos passos sugeridos

- Implementar autenticação para a equipe gerenciar agendamentos (confirmação, cancelamento).
- Envio de e-mails transacionais e lembretes automáticos via cron.
- Dashboard separado para métricas da barbearia (ocupação, ticket médio, retenção).
