<?php

$mensagemErro = $_SESSION['erro_login'] ?? '';
unset($_SESSION['erro_login']);

?>

<main class="container">
    <section class="card">

        <?php if (!empty($_SESSION['sucesso_login'])): ?>
            <p role="status">
                <?= htmlspecialchars(
                    $_SESSION['sucesso_login'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>
            <?php unset($_SESSION['sucesso_login']); ?>
        <?php endif; ?>

        <h1>Entrar no Numéria</h1>

        <p>
            Entre na sua conta para continuar
            sua aventura matemática!
        </p>

        <?php if ($mensagemErro !== ''): ?>
            <p role="alert">
                <?= htmlspecialchars(
                    $mensagemErro,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>
        <?php endif; ?>

        <form
            action="<?= htmlspecialchars(
                        app_url('?pagina=login'),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
            method="POST">

            <label for="email">E-mail</label>
            <input
                type="email"
                id="email"
                name="email"
                maxlength="150"
                required>

            <label for="senha">Senha</label>
            <input
                type="password"
                id="senha"
                name="senha"
                required>

            <button type="submit">
                Entrar
            </button>

        </form>

        <p>
            Ainda não tem uma conta?
            <a href="<?= htmlspecialchars(
                            app_url('?pagina=cadastro'),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>">
                Criar conta
            </a>
        </p>

    </section>
</main>