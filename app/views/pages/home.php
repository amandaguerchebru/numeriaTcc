<?php
// O acompanhamento aparece apenas para alunos conectados.
$resumoAluno = null;

if (isset($_SESSION['aluno_id'])) {
    require_once __DIR__ . '/../../config/conexao.php';

    $idAluno = (int) $_SESSION['aluno_id'];

    // Busca o total de XP do aluno.
    $consultaXP = $conexao->prepare(
        'SELECT COALESCE(SUM(xp_total), 0)
         FROM partida_participante
         WHERE id_aluno = :aluno'
    );
    $consultaXP->execute(['aluno' => $idAluno]);
    $totalXP = (int) $consultaXP->fetchColumn();

    // Conta as partidas finalizadas do aluno.
    $consultaPartidas = $conexao->prepare(
        "SELECT COUNT(*)
         FROM partida_participante
         WHERE id_aluno = :aluno
           AND id_partida IN (
               SELECT id_partida
               FROM partida
               WHERE status = 'finalizada'
           )"
    );
    $consultaPartidas->execute(['aluno' => $idAluno]);
    $totalPartidas = (int) $consultaPartidas->fetchColumn();

    // Soma os acertos registrados.
    $consultaAcertos = $conexao->prepare(
        'SELECT COALESCE(SUM(acertos), 0)
         FROM partida_participante
         WHERE id_aluno = :aluno'
    );
    $consultaAcertos->execute(['aluno' => $idAluno]);
    $totalAcertos = (int) $consultaAcertos->fetchColumn();

    // Soma os erros registrados.
    $consultaErros = $conexao->prepare(
        'SELECT COALESCE(SUM(erros), 0)
         FROM partida_participante
         WHERE id_aluno = :aluno'
    );
    $consultaErros->execute(['aluno' => $idAluno]);
    $totalErros = (int) $consultaErros->fetchColumn();

    $resumoAluno = [
        'xp' => $totalXP,
        'partidas' => $totalPartidas,
        'acertos' => $totalAcertos,
        'erros' => $totalErros
    ];
}
?>

<main>
    <div class="container">
        <section class="hero" aria-labelledby="titulo-principal">
            <span class="badge">Projeto educacional</span>


            <?php if (isset($_SESSION['aluno_id'])): ?>

                <p>
                    Olá,
                    <?= htmlspecialchars(
                        $_SESSION['aluno_nome'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>!
                </p>

                <p>Que bom ter você de volta ao Numéria!</p>

            <?php else: ?>

                <p>
                    Entre na sua conta ou cadastre-se pelo menu superior
                    para começar sua aventura matemática!
                </p>

            <?php endif; ?>

            <h2 id="titulo-principal">Bem-vindo ao Numéria</h2>
            <p>
                Uma plataforma de aprendizagem de matemática voltada para alunos do Ensino Fundamental II,
                com foco em jogos, progresso e acompanhamento de dificuldades.
            </p>
        </section>

        <section id="jogos" aria-labelledby="jogos-titulo" style="margin-top: 32px;">
            <h2 id="jogos-titulo">Jogos disponíveis</h2>

            <div class="grid">
                <article class="card" aria-label="Batalha na Floresta">
                    <h3>Batalha na Floresta</h3>
                    <p>Desafios de adição e subtração em modo individual ou dupla.</p>

                    <?php if (isset($_SESSION['aluno_id'])): ?>
                        <a href="?pagina=batalha">Jogar</a>
                    <?php else: ?>
                        <a href="?pagina=login">Entrar para jogar</a>
                    <?php endif; ?>
                </article>

                <article class="card" aria-label="Poções Mágicas">
                    <h3>Poções Mágicas</h3>
                    <p>Aprendizado de frações com desafios de identificação e comparação.</p>

                    <?php if (isset($_SESSION['aluno_id'])): ?>
                        <a href="?pagina=pocoes">Jogar</a>
                    <?php else: ?>
                        <a href="?pagina=login">Entrar para jogar</a>
                    <?php endif; ?>
                </article>
            </div>
        </section>


        <?php if ($resumoAluno !== null): ?>
            <section id="acompanhamento"
                aria-labelledby="acompanhamento-titulo"
                style="margin-top: 32px;">

                <h2 id="acompanhamento-titulo">Meu acompanhamento</h2>

                <p>Acompanhe seu progresso na aventura matemática!</p>

                <div class="grid">
                    <article class="card">
                        <h3>XP acumulado</h3>
                        <p>
                            <?= number_format($resumoAluno['xp'], 0, ',', '.') ?> XP
                        </p>
                    </article>

                    <article class="card">
                        <h3>Partidas finalizadas</h3>
                        <p><?= $resumoAluno['partidas'] ?></p>
                    </article>

                    <article class="card">
                        <h3>Acertos</h3>
                        <p><?= $resumoAluno['acertos'] ?></p>
                    </article>

                    <article class="card">
                        <h3>Erros</h3>
                        <p><?= $resumoAluno['erros'] ?></p>
                    </article>
                </div>
            </section>
        <?php endif; ?>

        <?php if (isset($_SESSION['aluno_id'])): ?>
            <p style="margin-top: 20px;">
                <a href="?pagina=historico">
                    Ver meu histórico de partidas
                </a>
            </p>
        <?php endif; ?>

        <section id="sobre" aria-labelledby="sobre-titulo" style="margin-top: 32px;">
            <h2 id="sobre-titulo">Objetivo da plataforma</h2>
            <p>
                O Numéria é uma plataforma educacional desenvolvida para
                apoiar alunos do Ensino Fundamental II no aprendizado
                de matemática por meio de jogos interativos.
            </p>

            <p>
                A plataforma busca tornar o aprendizado mais envolvente,
                registrar o progresso dos estudantes e ajudar a identificar
                conteúdos que precisam de mais atenção.
            </p>

            <p>
                O projeto é desenvolvido pela XGuerTech, com a proposta
                de transformar desafios matemáticos em oportunidades
                de exploração, prática e descoberta.
            </p>
        </section>
    </div>
</main>