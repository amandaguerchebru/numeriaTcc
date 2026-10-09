
<?php

require_once __DIR__ . '/../config/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ?pagina=cadastro');
    exit;
}

$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';
$confirmarSenha = $_POST['confirmar_senha'] ?? '';

if ($nome === '' || $email === '' || $senha === '' || $confirmarSenha === '') {
    $_SESSION['erro_cadastro'] = 'Preencha todos os campos.';
    header('Location: ?pagina=cadastro');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['erro_cadastro'] = 'Informe um e-mail válido.';
    header('Location: ?pagina=cadastro');
    exit;
}

if (strlen($senha) < 8) {
    $_SESSION['erro_cadastro'] = 'A senha deve ter pelo menos 8 caracteres.';
    header('Location: ?pagina=cadastro');
    exit;
}

if ($senha !== $confirmarSenha) {
    $_SESSION['erro_cadastro'] = 'As senhas não coincidem.';
    header('Location: ?pagina=cadastro');
    exit;
}

try {
    $consulta = $conexao->prepare(
        'SELECT id_aluno FROM aluno WHERE email = :email LIMIT 1'
    );
    $consulta->execute(['email' => $email]);

    if ($consulta->fetch()) {
        $_SESSION['erro_cadastro'] = 'Este e-mail já está cadastrado.';
        header('Location: ?pagina=cadastro');
        exit;
    }

    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

    $inserir = $conexao->prepare(
        'INSERT INTO aluno (nome, email, senha)
         VALUES (:nome, :email, :senha)'
    );

    $inserir->execute([
        'nome' => $nome,
        'email' => $email,
        'senha' => $senhaHash
    ]);

    $_SESSION['sucesso_login'] = 'Conta criada! Agora entre com seu e-mail e senha.';
    header('Location: ?pagina=login');
    exit;
} catch (PDOException $e) {
    $_SESSION['erro_cadastro'] = 'Não foi possível criar a conta. Tente novamente.';
    header('Location: ?pagina=cadastro');
    exit;
}
