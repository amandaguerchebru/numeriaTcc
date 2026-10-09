<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <meta name="description" content="Plataforma educacional Numéria">
    <link rel="stylesheet" href="<?php echo asset('assets/css/style.css'); ?>">
</head>

<body>
    <header>
        <div class="container brand">
            <h1><?php echo APP_NAME; ?></h1>
            <nav aria-label="Navegação principal">
                <a href="<?= htmlspecialchars(app_url('?pagina=home')) ?>">Início</a>
                <a href="<?= htmlspecialchars(app_url('?pagina=home#jogos')) ?>">Jogos</a>
                <a href="<?= htmlspecialchars(app_url('?pagina=home#sobre')) ?>">Sobre</a>

                <?php if (isset($_SESSION['aluno_id'])): ?>

                    <a href="?pagina=sair">Sair da conta</a>

                <?php else: ?>

                    <a href="?pagina=login">Entrar</a>
                    <a href="?pagina=cadastro">Criar conta</a>

                <?php endif; ?>
            </nav>
        </div>
    </header>