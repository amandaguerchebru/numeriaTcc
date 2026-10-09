
<?php
$mensagemErro = $_SESSION['erro_cadastro'] ?? '';
unset($_SESSION['erro_cadastro']);
?>

<main class="container">
    <section class="card">
        <h1>Criar conta no Numéria</h1>
        <p>Cadastre-se para começar sua aventura matemática!</p>

        <?php if ($mensagemErro !== ''): ?>
            <p role="alert">
                <?= htmlspecialchars($mensagemErro, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <form action="<?= htmlspecialchars(app_url('?pagina=cadastro')) ?>" method="POST">
            <label for="nome">Nome</label>
            <input
                type="text"
                id="nome"
                name="nome"
                maxlength="100"
                required
            >

            <label for="email">E-mail</label>
            <input
                type="email"
                id="email"
                name="email"
                maxlength="150"
                required
            >

            <label for="senha">Senha</label>
            <input
                type="password"
                id="senha"
                name="senha"
                minlength="8"
                required
            >

            <label for="confirmar_senha">Confirmar senha</label>
            <input
                type="password"
                id="confirmar_senha"
                name="confirmar_senha"
                minlength="8"
                required
            >

            <button type="submit">Criar conta</button>
        </form>

        <p>
            Já tem uma conta?
            <a href="<?= htmlspecialchars(app_url('?pagina=login')) ?>">
                Entrar
            </a>
        </p>
    </section>
</main>