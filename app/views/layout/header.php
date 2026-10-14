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
            <div class="logo-area">
                <a href="?pagina=home" class="logo-link" aria-label="Numéria Início">
                    <span class="logo-icone" aria-hidden="true">✨🦉</span>
                    <span class="logo-texto"><?php echo APP_NAME; ?></span>
                </a>
                <span class="slogan-header">Reino Mágico da Matemática · XGuerTech</span>
            </div>

            <nav aria-label="Navegação principal" class="nav-principal">
                <a href="?pagina=home">Início</a>
                <a href="?pagina=home#jogos">Jogos</a>
                <?php if (isset($_SESSION['aluno_id'])): ?>
                    <a href="?pagina=historico">Meu Desempenho</a>
                    <span class="nav-aluno-ola">Olá, <strong><?php echo htmlspecialchars($_SESSION['aluno_nome'] ?? 'Aventureiro', ENT_QUOTES, 'UTF-8'); ?></strong></span>
                    <a href="?pagina=sair" class="nav-conta nav-sair">Sair</a>
                <?php else: ?>
                    <a href="?pagina=login" class="nav-conta">Entrar</a>
                    <a href="?pagina=cadastro" class="nav-conta nav-cadastro">
                        Criar conta
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    </header>