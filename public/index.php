
<?php

session_start();

require __DIR__ . '/../app/config/app.php';

$pagina = $_GET['pagina'] ?? 'home';

$paginasPermitidas = [
    'home',
    'cadastro',
    'login',
    'sair',
    'batalha',
    'pocoes',
    'desafio'
];

// VERIFICAR SE A PÁGINA É PERMITIDA
if (!in_array($pagina, $paginasPermitidas, true)) {
    http_response_code(404);
    exit('Página não encontrada.');
}

// PROTEGER ACESSO AOS JOGOS
if (in_array($pagina, ['batalha', 'pocoes'], true)
    && !isset($_SESSION['aluno_id'])) {

    header('Location: ?pagina=login');
    exit;
}

// ENCERRAR SESSÃO
if ($pagina === 'sair') {
    $_SESSION = [];
    session_destroy();

    header('Location: ?pagina=home');
    exit;
}

// PROCESSAR FORMULÁRIOS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($pagina === 'cadastro') {
        require __DIR__ . '/../app/controllers/CadastroController.php';
    }

    if ($pagina === 'login') {
        require __DIR__ . '/../app/controllers/loginController.php';
    }
}

// EXIBIR PÁGINA
include __DIR__ . '/../app/views/layout/header.php';

include __DIR__ . '/../app/views/pages/' . $pagina . '.php';

include __DIR__ . '/../app/views/layout/footer.php';