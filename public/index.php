<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

$config = require dirname(__DIR__) . '/config.php';
$connectionError = null;
$pdo = null;

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['db']['host'],
        $config['db']['port'],
        $config['db']['name'],
        $config['db']['charset']
    );
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $exception) {
    $connectionError = 'Não foi possível conectar ao banco de dados. Verifique se o MySQL está em execução e se as credenciais em <code>config.php</code> estão corretas.';
}

$services = [];
$barbers = [];
$formData = [
    'client_name' => '',
    'client_email' => '',
    'client_phone' => '',
    'service_id' => '',
    'barber_id' => '',
    'date' => '',
    'time' => '',
    'notes' => '',
];
$feedback = null;
$errors = [];

if ($pdo instanceof PDO) {
    $services = $pdo->query('SELECT id, name, description, duration_minutes, price FROM services ORDER BY price ASC')->fetchAll();
    $barbers = $pdo->query('SELECT id, name, experience_years, bio, avatar_url FROM barbers ORDER BY experience_years DESC')->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo instanceof PDO) {
    $formData = [
        'client_name' => trim($_POST['client_name'] ?? ''),
        'client_email' => trim($_POST['client_email'] ?? ''),
        'client_phone' => trim($_POST['client_phone'] ?? ''),
        'service_id' => $_POST['service_id'] ?? '',
        'barber_id' => $_POST['barber_id'] ?? '',
        'date' => $_POST['date'] ?? '',
        'time' => $_POST['time'] ?? '',
        'notes' => trim($_POST['notes'] ?? ''),
    ];

    if ($formData['client_name'] === '') {
        $errors['client_name'] = 'Informe o nome completo.';
    }

    if ($formData['client_email'] === '' || !filter_var($formData['client_email'], FILTER_VALIDATE_EMAIL)) {
        $errors['client_email'] = 'Insira um e-mail válido para contato.';
    }

    if ($formData['service_id'] === '') {
        $errors['service_id'] = 'Selecione um serviço.';
    } elseif (!array_filter($services, static fn ($service) => (string) $service['id'] === (string) $formData['service_id'])) {
        $errors['service_id'] = 'Serviço inválido selecionado.';
    }

    if ($formData['barber_id'] === '') {
        $errors['barber_id'] = 'Escolha o barbeiro de preferência.';
    } elseif (!array_filter($barbers, static fn ($barber) => (string) $barber['id'] === (string) $formData['barber_id'])) {
        $errors['barber_id'] = 'Barbeiro inválido selecionado.';
    }

    $datetime = null;
    if ($formData['date'] === '' || $formData['time'] === '') {
        $errors['date'] = 'Escolha uma data e horário disponíveis.';
    } else {
        $datetime = DateTime::createFromFormat('Y-m-d H:i', $formData['date'] . ' ' . $formData['time']);
        if (!($datetime instanceof DateTime)) {
            $errors['date'] = 'Data ou horário com formato inválido.';
        } elseif ($datetime < new DateTime('now')) {
            $errors['date'] = 'Escolha um horário no futuro.';
        }
    }

    if (!$errors && $datetime instanceof DateTime) {
        $checkStatement = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE barber_id = :barber_id AND appointment_at = :appointment_at AND status = "scheduled"');
        $checkStatement->execute([
            'barber_id' => $formData['barber_id'],
            'appointment_at' => $datetime->format('Y-m-d H:i:s'),
        ]);

        if ((int) $checkStatement->fetchColumn() > 0) {
            $errors['date'] = 'Este horário já está reservado para o barbeiro escolhido.';
        }
    }

    if (!$errors && $datetime instanceof DateTime) {
        $statement = $pdo->prepare('INSERT INTO appointments (service_id, barber_id, client_name, client_email, client_phone, appointment_at, notes) VALUES (:service_id, :barber_id, :client_name, :client_email, :client_phone, :appointment_at, :notes)');
        $statement->execute([
            'service_id' => $formData['service_id'],
            'barber_id' => $formData['barber_id'],
            'client_name' => $formData['client_name'],
            'client_email' => $formData['client_email'],
            'client_phone' => $formData['client_phone'] ?: null,
            'appointment_at' => $datetime->format('Y-m-d H:i:s'),
            'notes' => $formData['notes'] ?: null,
        ]);

        $feedback = [
            'type' => 'success',
            'message' => 'Agendamento criado com sucesso! Você receberá a confirmação por e-mail.',
        ];

        $formData = [
            'client_name' => '',
            'client_email' => '',
            'client_phone' => '',
            'service_id' => '',
            'barber_id' => '',
            'date' => '',
            'time' => '',
            'notes' => '',
        ];
    } elseif ($errors) {
        $feedback = [
            'type' => 'error',
            'message' => 'Corrija os campos destacados para finalizar o agendamento.',
        ];
    }
}

$upcomingAppointments = [];

if ($pdo instanceof PDO) {
    $query = $pdo->query('SELECT a.client_name, a.client_phone, a.appointment_at, s.name AS service_name, s.duration_minutes, b.name AS barber_name FROM appointments a INNER JOIN services s ON s.id = a.service_id INNER JOIN barbers b ON b.id = a.barber_id WHERE a.status = "scheduled" AND a.appointment_at >= NOW() ORDER BY a.appointment_at ASC LIMIT 6');
    $upcomingAppointments = $query->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barbearia Prime - Agenda Inteligente</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#ecfdf5',
                            100: '#d1fae5',
                            200: '#a7f3d0',
                            300: '#6ee7b7',
                            400: '#34d399',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                            800: '#065f46',
                            900: '#064e3b',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-950 text-slate-100">
    <div class="min-h-screen">
        <header class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-slate-900 to-black">
            <div class="absolute inset-0 opacity-40" style="background-image: radial-gradient(circle at 20% 20%, rgba(16,185,129,0.35), transparent 60%), radial-gradient(circle at 80% 30%, rgba(59,130,246,0.25), transparent 55%);"></div>
            <div class="relative mx-auto flex max-w-6xl flex-col gap-12 px-6 py-20 sm:px-12 lg:flex-row lg:items-center">
                <div class="w-full lg:w-1/2">
                    <span class="inline-flex items-center rounded-full border border-brand-300/40 bg-brand-500/10 px-4 py-1 text-sm font-medium text-brand-200 backdrop-blur">Experiência premium</span>
                    <h1 class="mt-6 text-4xl font-bold tracking-tight text-white sm:text-5xl lg:text-6xl">Agendamento inteligente para a sua próxima experiência na Barbearia Prime</h1>
                    <p class="mt-4 text-lg text-slate-300">Escolha o barbeiro, o serviço ideal e reserve em poucos cliques. Receba confirmação imediata e aproveite um atendimento personalizado pensado para você.</p>
                    <div class="mt-8 flex flex-wrap gap-4">
                        <a href="#agendamento" class="inline-flex items-center rounded-full bg-brand-500 px-6 py-3 text-sm font-semibold text-white transition hover:bg-brand-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-300">Agendar agora</a>
                        <a href="#servicos" class="inline-flex items-center rounded-full border border-white/20 px-6 py-3 text-sm font-semibold text-white/80 transition hover:border-white hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">Conhecer serviços</a>
                    </div>
                    <dl class="mt-10 grid grid-cols-2 gap-6 text-white/80 sm:grid-cols-4">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-400">Avaliação média</dt>
                            <dd class="mt-1 text-2xl font-semibold">4.9/5</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-400">Clientes fiéis</dt>
                            <dd class="mt-1 text-2xl font-semibold">+2k</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-400">Tempo médio</dt>
                            <dd class="mt-1 text-2xl font-semibold">45 min</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-400">Barbeiros experts</dt>
                            <dd class="mt-1 text-2xl font-semibold"><?php echo count($barbers); ?></dd>
                        </div>
                    </dl>
                </div>
                <div class="w-full rounded-3xl border border-white/10 bg-white/5 p-6 backdrop-blur lg:w-1/2">
                    <h2 class="text-xl font-semibold text-white">Agenda do dia</h2>
                    <p class="mt-2 text-sm text-slate-300">Visualize os próximos horários reservados para manter a operação sempre afiada.</p>
                    <ul class="mt-6 space-y-4">
                        <?php if (!$pdo instanceof PDO): ?>
                            <li class="rounded-2xl border border-red-400/40 bg-red-500/10 p-4 text-sm text-red-200">Sem conexão com o banco de dados. Configure o acesso em <code>config.php</code>.</li>
                        <?php elseif (empty($upcomingAppointments)): ?>
                            <li class="rounded-2xl border border-white/10 bg-black/30 p-4 text-sm text-slate-300">Nenhum agendamento para hoje ainda. Seja o primeiro a reservar!</li>
                        <?php else: ?>
                            <?php foreach ($upcomingAppointments as $appointment): ?>
                                <?php
                                $appointmentDate = new DateTime($appointment['appointment_at']);
                                ?>
                                <li class="flex items-start justify-between gap-3 rounded-2xl border border-white/10 bg-black/30 p-4 transition hover:border-brand-400/40 hover:bg-brand-500/5">
                                    <div>
                                        <p class="text-sm font-semibold text-white"><?php echo htmlspecialchars($appointment['client_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        <p class="text-xs text-slate-300"><?php echo $appointmentDate->format('d \d\e F \à\s H\hi'); ?></p>
                                        <p class="mt-1 text-xs text-slate-400"><?php echo htmlspecialchars($appointment['service_name'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo (int) $appointment['duration_minutes']; ?> min</p>
                                    </div>
                                    <div class="text-right text-xs text-slate-400">
                                        <p><?php echo htmlspecialchars($appointment['barber_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        <?php if (!empty($appointment['client_phone'])): ?>
                                            <p class="mt-1 font-medium text-slate-200"><?php echo htmlspecialchars($appointment['client_phone'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-6 py-16 sm:px-12" id="servicos">
            <section>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-3xl font-semibold text-white">Serviços sob medida para o seu estilo</h2>
                        <p class="mt-2 max-w-2xl text-lg text-slate-300">Nossos serviços combinam técnicas modernas e clássicas para oferecer um resultado impecável. Escolha o tratamento que mais combina com a sua personalidade.</p>
                    </div>
                    <div class="flex items-center gap-3 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm text-slate-200">
                        <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                        Agenda atualizada em tempo real
                    </div>
                </div>

                <div class="mt-10 grid gap-6 md:grid-cols-3">
                    <?php if (empty($services)): ?>
                        <p class="rounded-3xl border border-amber-400/30 bg-amber-500/10 p-6 text-sm text-amber-100">Cadastre os serviços no banco de dados para exibi-los aqui.</p>
                    <?php else: ?>
                        <?php foreach ($services as $service): ?>
                            <article class="group relative overflow-hidden rounded-3xl border border-white/10 bg-white/5 p-6 transition hover:border-brand-400/40 hover:bg-brand-500/5">
                                <div class="absolute inset-0 -z-10 opacity-0 transition group-hover:opacity-100" style="background: radial-gradient(circle at top left, rgba(16,185,129,0.25), transparent 55%);"></div>
                                <h3 class="text-xl font-semibold text-white"><?php echo htmlspecialchars($service['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                <p class="mt-2 text-sm text-slate-300"><?php echo htmlspecialchars($service['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <dl class="mt-4 flex items-center justify-between text-sm text-slate-300">
                                    <div>
                                        <dt class="text-xs uppercase tracking-wide text-slate-400">Duração média</dt>
                                        <dd class="mt-1 font-medium text-white"><?php echo (int) $service['duration_minutes']; ?> minutos</dd>
                                    </div>
                                    <div class="text-right">
                                        <dt class="text-xs uppercase tracking-wide text-slate-400">Investimento</dt>
                                        <dd class="mt-1 text-2xl font-semibold text-brand-300">R$ <?php echo number_format((float) $service['price'], 2, ',', '.'); ?></dd>
                                    </div>
                                </dl>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section id="agendamento" class="mt-20 grid gap-12 lg:grid-cols-2">
                <div>
                    <h2 class="text-3xl font-semibold text-white">Reserve seu horário em segundos</h2>
                    <p class="mt-3 text-lg text-slate-300">Selecione a data, horário, serviço e o profissional ideal. Nossa equipe cuida do resto e envia lembretes automáticos para você nunca perder o momento de relaxar.</p>
                    <ul class="mt-8 space-y-4 text-sm text-slate-300">
                        <li class="flex items-start gap-3">
                            <span class="mt-1 flex h-8 w-8 items-center justify-center rounded-full bg-brand-500/10 text-brand-300">1</span>
                            <span>
                                <strong class="text-white">Personalize a experiência.</strong> Escolha o serviço que combina com seu estilo e preferências.
                            </span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="mt-1 flex h-8 w-8 items-center justify-center rounded-full bg-brand-500/10 text-brand-300">2</span>
                            <span>
                                <strong class="text-white">Selecione o especialista.</strong> Conheça o perfil de cada barbeiro e escolha seu favorito.
                            </span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="mt-1 flex h-8 w-8 items-center justify-center rounded-full bg-brand-500/10 text-brand-300">3</span>
                            <span>
                                <strong class="text-white">Receba confirmações instantâneas.</strong> Um e-mail com todos os detalhes chega na hora.
                            </span>
                        </li>
                    </ul>
                </div>
                <div class="rounded-3xl border border-white/10 bg-white/5 p-8 shadow-2xl shadow-black/40">
                    <h3 class="text-xl font-semibold text-white">Formulário de agendamento</h3>
                    <p class="mt-2 text-sm text-slate-300">Preencha os dados abaixo e garanta o melhor horário para você.</p>

                    <?php if ($feedback): ?>
                        <div class="mt-6 rounded-2xl border <?php echo $feedback['type'] === 'success' ? 'border-brand-400/40 bg-brand-500/10 text-brand-100' : 'border-red-400/40 bg-red-500/10 text-red-100'; ?> p-4 text-sm">
                            <?php echo htmlspecialchars($feedback['message'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($connectionError): ?>
                        <div class="mt-6 rounded-2xl border border-red-400/40 bg-red-500/10 p-4 text-sm text-red-100">
                            <?php echo $connectionError; ?>
                        </div>
                    <?php else: ?>
                        <form method="post" class="mt-6 space-y-5">
                            <div>
                                <label for="client_name" class="text-sm font-medium text-slate-200">Nome completo</label>
                                <input type="text" id="client_name" name="client_name" value="<?php echo htmlspecialchars($formData['client_name'], ENT_QUOTES, 'UTF-8'); ?>" class="mt-2 w-full rounded-2xl border <?php echo isset($errors['client_name']) ? 'border-red-400/60 focus:border-red-400 focus:ring-red-400/40' : 'border-white/10 focus:border-brand-400 focus:ring-brand-400/20'; ?> bg-black/40 px-4 py-3 text-sm text-white shadow-inner shadow-black/40 outline-none transition focus:ring-2" placeholder="Como devemos chamá-lo?" required>
                                <?php if (isset($errors['client_name'])): ?>
                                    <p class="mt-1 text-xs text-red-200"><?php echo htmlspecialchars($errors['client_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="client_email" class="text-sm font-medium text-slate-200">E-mail</label>
                                    <input type="email" id="client_email" name="client_email" value="<?php echo htmlspecialchars($formData['client_email'], ENT_QUOTES, 'UTF-8'); ?>" class="mt-2 w-full rounded-2xl border <?php echo isset($errors['client_email']) ? 'border-red-400/60 focus:border-red-400 focus:ring-red-400/40' : 'border-white/10 focus:border-brand-400 focus:ring-brand-400/20'; ?> bg-black/40 px-4 py-3 text-sm text-white shadow-inner shadow-black/40 outline-none transition focus:ring-2" placeholder="para@contato.com" required>
                                    <?php if (isset($errors['client_email'])): ?>
                                        <p class="mt-1 text-xs text-red-200"><?php echo htmlspecialchars($errors['client_email'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <label for="client_phone" class="text-sm font-medium text-slate-200">Telefone (opcional)</label>
                                    <input type="tel" id="client_phone" name="client_phone" value="<?php echo htmlspecialchars($formData['client_phone'], ENT_QUOTES, 'UTF-8'); ?>" class="mt-2 w-full rounded-2xl border border-white/10 bg-black/40 px-4 py-3 text-sm text-white shadow-inner shadow-black/40 outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-400/20" placeholder="(11) 99999-8888">
                                </div>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="service_id" class="text-sm font-medium text-slate-200">Serviço</label>
                                    <div class="relative mt-2">
                                        <select id="service_id" name="service_id" class="w-full appearance-none rounded-2xl border <?php echo isset($errors['service_id']) ? 'border-red-400/60 focus:border-red-400 focus:ring-red-400/40' : 'border-white/10 focus:border-brand-400 focus:ring-brand-400/20'; ?> bg-black/40 px-4 py-3 text-sm text-white outline-none transition focus:ring-2" required>
                                            <option value="" disabled <?php echo $formData['service_id'] === '' ? 'selected' : ''; ?>>Escolha uma opção</option>
                                            <?php foreach ($services as $service): ?>
                                                <option value="<?php echo (int) $service['id']; ?>" <?php echo (string) $formData['service_id'] === (string) $service['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($service['name'], ENT_QUOTES, 'UTF-8'); ?> · R$ <?php echo number_format((float) $service['price'], 2, ',', '.'); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-slate-400">▾</span>
                                    </div>
                                    <?php if (isset($errors['service_id'])): ?>
                                        <p class="mt-1 text-xs text-red-200"><?php echo htmlspecialchars($errors['service_id'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <label for="barber_id" class="text-sm font-medium text-slate-200">Barbeiro</label>
                                    <div class="relative mt-2">
                                        <select id="barber_id" name="barber_id" class="w-full appearance-none rounded-2xl border <?php echo isset($errors['barber_id']) ? 'border-red-400/60 focus:border-red-400 focus:ring-red-400/40' : 'border-white/10 focus:border-brand-400 focus:ring-brand-400/20'; ?> bg-black/40 px-4 py-3 text-sm text-white outline-none transition focus:ring-2" required>
                                            <option value="" disabled <?php echo $formData['barber_id'] === '' ? 'selected' : ''; ?>>Selecione o profissional</option>
                                            <?php foreach ($barbers as $barber): ?>
                                                <option value="<?php echo (int) $barber['id']; ?>" <?php echo (string) $formData['barber_id'] === (string) $barber['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($barber['name'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo (int) $barber['experience_years']; ?> anos de experiência</option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-slate-400">▾</span>
                                    </div>
                                    <?php if (isset($errors['barber_id'])): ?>
                                        <p class="mt-1 text-xs text-red-200"><?php echo htmlspecialchars($errors['barber_id'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="date" class="text-sm font-medium text-slate-200">Data</label>
                                    <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($formData['date'], ENT_QUOTES, 'UTF-8'); ?>" class="mt-2 w-full rounded-2xl border <?php echo isset($errors['date']) ? 'border-red-400/60 focus:border-red-400 focus:ring-red-400/40' : 'border-white/10 focus:border-brand-400 focus:ring-brand-400/20'; ?> bg-black/40 px-4 py-3 text-sm text-white shadow-inner shadow-black/40 outline-none transition focus:ring-2" required min="<?php echo (new DateTime('today'))->format('Y-m-d'); ?>">
                                </div>
                                <div>
                                    <label for="time" class="text-sm font-medium text-slate-200">Horário</label>
                                    <input type="time" id="time" name="time" value="<?php echo htmlspecialchars($formData['time'], ENT_QUOTES, 'UTF-8'); ?>" class="mt-2 w-full rounded-2xl border <?php echo isset($errors['date']) ? 'border-red-400/60 focus:border-red-400 focus:ring-red-400/40' : 'border-white/10 focus:border-brand-400 focus:ring-brand-400/20'; ?> bg-black/40 px-4 py-3 text-sm text-white shadow-inner shadow-black/40 outline-none transition focus:ring-2" required>
                                    <?php if (isset($errors['date'])): ?>
                                        <p class="mt-1 text-xs text-red-200"><?php echo htmlspecialchars($errors['date'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <label for="notes" class="text-sm font-medium text-slate-200">Observações adicionais</label>
                                <textarea id="notes" name="notes" rows="3" class="mt-2 w-full rounded-2xl border border-white/10 bg-black/40 px-4 py-3 text-sm text-white shadow-inner shadow-black/40 outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-400/20" placeholder="Preferências especiais, alergias ou referências de estilo."><?php echo htmlspecialchars($formData['notes'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-full bg-brand-500 px-6 py-3 text-sm font-semibold text-white transition hover:bg-brand-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-300">Confirmar agendamento</button>
                        </form>
                    <?php endif; ?>
                </div>
            </section>
        </main>

        <footer class="border-t border-white/5 bg-black/60">
            <div class="mx-auto flex max-w-6xl flex-col gap-6 px-6 py-10 text-sm text-slate-400 sm:flex-row sm:items-center sm:justify-between sm:px-12">
                <p>&copy; <?php echo date('Y'); ?> Barbearia Prime. Todos os direitos reservados.</p>
                <div class="flex gap-4 text-xs uppercase tracking-wide text-slate-500">
                    <span>Experiência premium</span>
                    <span>Atendimento com hora marcada</span>
                    <span>Ambiente exclusivo</span>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
