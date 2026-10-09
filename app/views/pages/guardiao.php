
<?php

require_once __DIR__ . '/../../config/conexao.php';

if (empty($_SESSION['aluno_id'])) {
    header('Location: ?pagina=login');
    exit;
}

$idAluno = (int) $_SESSION['aluno_id'];
$mensagem = '';
$resultado = null;

// ============================================
// 1. INICIAR UMA NOVA PARTIDA
// ============================================

if (
    !isset($_SESSION['guardiao_partida_id']) ||
    isset($_GET['novo'])
) {
    unset(
        $_SESSION['guardiao_partida_id'],
        $_SESSION['guardiao_participante_id'],
        $_SESSION['guardiao_vidas_jogador'],
        $_SESSION['guardiao_vidas_guardiao'],
        $_SESSION['guardiao_questoes'],
        $_SESSION['guardiao_xp'],
        $_SESSION['guardiao_acertos'],
        $_SESSION['guardiao_erros'],
        $_SESSION['guardiao_dificuldade']
    );
}

// Seleção de dificuldade e criação da partida.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['iniciar'])
) {
    $idDificuldade = filter_input(
        INPUT_POST,
        'id_dificuldade',
        FILTER_VALIDATE_INT
    );

    $consultaDificuldade = $conexao->prepare(
        'SELECT id_dificuldade, nome
         FROM dificuldade
         WHERE id_dificuldade = :id'
    );

    $consultaDificuldade->execute([
        'id' => $idDificuldade
    ]);

    $dificuldade = $consultaDificuldade->fetch(PDO::FETCH_ASSOC);

    if (!$dificuldade) {
        $mensagem = 'Selecione uma dificuldade válida.';
    } else {
        $consultaModo = $conexao->prepare(
            'SELECT id_modo_jogo
             FROM modo_jogo
             WHERE nome = :nome
             LIMIT 1'
        );

        $consultaModo->execute([
            'nome' => 'Contra o Guardião'
        ]);

        $idModo = $consultaModo->fetchColumn();

        if ($idModo === false) {
            $mensagem = 'O modo Contra o Guardião não foi cadastrado no banco.';
        } else {
            $consultaPartida = $conexao->prepare(
                "INSERT INTO partida
                    (id_jogo, id_modo_jogo, tempo_segundos, status)
                 VALUES
                    (1, :modo, NULL, 'em_andamento')"
            );

            $consultaPartida->execute([
                'modo' => (int) $idModo
            ]);

            $idPartida = (int) $conexao->lastInsertId();

            $consultaParticipante = $conexao->prepare(
                'INSERT INTO partida_participante
                    (id_partida, id_aluno, xp_total, acertos, erros)
                 VALUES
                    (:partida, :aluno, 0, 0, 0)'
            );

            $consultaParticipante->execute([
                'partida' => $idPartida,
                'aluno' => $idAluno
            ]);

            $_SESSION['guardiao_partida_id'] = $idPartida;
            $_SESSION['guardiao_participante_id'] =
                (int) $conexao->lastInsertId();

            $_SESSION['guardiao_dificuldade'] =
                (int) $dificuldade['id_dificuldade'];

            $_SESSION['guardiao_vidas_jogador'] = 3;
            $_SESSION['guardiao_vidas_guardiao'] = 3;
            $_SESSION['guardiao_questoes'] = 0;
            $_SESSION['guardiao_xp'] = 0;
            $_SESSION['guardiao_acertos'] = 0;
            $_SESSION['guardiao_erros'] = 0;

            header('Location: ?pagina=guardiao');
            exit;
        }
    }
}

// ============================================
// 2. ESTADO DA PARTIDA
// ============================================

$partidaAtiva = !empty($_SESSION['guardiao_partida_id']);

if ($partidaAtiva) {
    $vidasJogador = (int) $_SESSION['guardiao_vidas_jogador'];
    $vidasGuardiao = (int) $_SESSION['guardiao_vidas_guardiao'];
    $totalQuestoes = (int) $_SESSION['guardiao_questoes'];

    $fimDeJogo = (
        $vidasJogador <= 0 ||
        $vidasGuardiao <= 0 ||
        $totalQuestoes >= 10
    );

    // ========================================
    // 3. PROCESSAR RESPOSTA
    // ========================================

    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['responder']) &&
        !$fimDeJogo
    ) {
        $idQuestao = filter_input(
            INPUT_POST,
            'id_questao',
            FILTER_VALIDATE_INT
        );

        $idAlternativa = filter_input(
            INPUT_POST,
            'id_alternativa',
            FILTER_VALIDATE_INT
        );

        $idParticipante = (int) $_SESSION['guardiao_participante_id'];
        $idPartida = (int) $_SESSION['guardiao_partida_id'];
        $idDificuldade = (int) $_SESSION['guardiao_dificuldade'];

        // Confirma que a questão pertence ao jogo e à dificuldade escolhida.
        $consultaQuestao = $conexao->prepare(
            'SELECT id_questao
             FROM questao
             WHERE id_questao = :questao
               AND id_jogo = 1
               AND id_dificuldade = :dificuldade
               AND ativo = 1
               AND id_questao IN (
                   SELECT id_questao
                   FROM alternativa
                   WHERE id_alternativa = :alternativa
               )'
        );

        $consultaQuestao->execute([
            'questao' => $idQuestao,
            'dificuldade' => $idDificuldade,
            'alternativa' => $idAlternativa
        ]);

        if ($consultaQuestao->fetchColumn()) {
            // Impede responder novamente a uma questão já registrada nesta partida.
            $consultaJaRespondida = $conexao->prepare(
                'SELECT COUNT(*)
                 FROM resposta
                 WHERE id_participante = :participante
                   AND id_questao = :questao'
            );

            $consultaJaRespondida->execute([
                'participante' => $idParticipante,
                'questao' => $idQuestao
            ]);

            if ((int) $consultaJaRespondida->fetchColumn() === 0) {
                $consultaCorreta = $conexao->prepare(
                    'SELECT correta
                     FROM alternativa
                     WHERE id_alternativa = :alternativa
                       AND id_questao = :questao'
                );

                $consultaCorreta->execute([
                    'alternativa' => $idAlternativa,
                    'questao' => $idQuestao
                ]);

                $correta = $consultaCorreta->fetchColumn();

                if ($correta !== false) {
                    $acertou = ((int) $correta === 1);
                    $xpGanho = 0;

                    if ($acertou) {
                        $consultaXP = $conexao->prepare(
                            'SELECT xp_por_acerto
                             FROM dificuldade
                             WHERE id_dificuldade = :dificuldade'
                        );

                        $consultaXP->execute([
                            'dificuldade' => $idDificuldade
                        ]);

                        $xpGanho = (int) $consultaXP->fetchColumn();

                        $_SESSION['guardiao_vidas_guardiao']--;
                        $_SESSION['guardiao_acertos']++;
                        $_SESSION['guardiao_xp'] += $xpGanho;

                        $mensagem = 'Resposta correta! O Guardião perdeu uma vida. Você ganhou '
                            . $xpGanho . ' XP!';
                    } else {
                        $_SESSION['guardiao_vidas_jogador']--;
                        $_SESSION['guardiao_erros']++;

                        $mensagem = 'Resposta incorreta! Você perdeu uma vida.';
                    }

                    $_SESSION['guardiao_questoes']++;

                    $consultaRegistro = $conexao->prepare(
                        'INSERT INTO resposta
                            (id_participante, id_questao, id_alternativa, acertou, xp_ganho)
                         VALUES
                            (:participante, :questao, :alternativa, :acertou, :xp)'
                    );

                    $consultaRegistro->execute([
                        'participante' => $idParticipante,
                        'questao' => $idQuestao,
                        'alternativa' => $idAlternativa,
                        'acertou' => $acertou ? 1 : 0,
                        'xp' => $xpGanho
                    ]);

                    $consultaAtualiza = $conexao->prepare(
                        'UPDATE partida_participante
                         SET xp_total = xp_total + :xp,
                             acertos = acertos + :acertos,
                             erros = erros + :erros
                         WHERE id_participante = :participante'
                    );

                    $consultaAtualiza->execute([
                        'xp' => $xpGanho,
                        'acertos' => $acertou ? 1 : 0,
                        'erros' => $acertou ? 0 : 1,
                        'participante' => $idParticipante
                    ]);
                } else {
                    $mensagem = 'Alternativa inválida.';
                }
            } else {
                $mensagem = 'Essa questão já foi respondida. Tente outra.';
            }
        } else {
            $mensagem = 'Questão ou alternativa inválida.';
        }

        // Atualiza o estado e encerra se uma condição de fim foi atingida.
        $vidasJogador = (int) $_SESSION['guardiao_vidas_jogador'];
        $vidasGuardiao = (int) $_SESSION['guardiao_vidas_guardiao'];
        $totalQuestoes = (int) $_SESSION['guardiao_questoes'];

        $fimDeJogo = (
            $vidasJogador <= 0 ||
            $vidasGuardiao <= 0 ||
            $totalQuestoes >= 10
        );

        if ($fimDeJogo) {
            $consultaFim = $conexao->prepare(
                "UPDATE partida
                 SET status = 'finalizada',
                     data_fim = NOW()
                 WHERE id_partida = :partida
                   AND status = 'em_andamento'"
            );

            $consultaFim->execute([
                'partida' => $idPartida
            ]);
        }
    }

    // Recalcula o estado após processar a resposta.
    $vidasJogador = (int) $_SESSION['guardiao_vidas_jogador'];
    $vidasGuardiao = (int) $_SESSION['guardiao_vidas_guardiao'];
    $totalQuestoes = (int) $_SESSION['guardiao_questoes'];

    $fimDeJogo = (
        $vidasJogador <= 0 ||
        $vidasGuardiao <= 0 ||
        $totalQuestoes >= 10
    );

    // Busca a próxima questão sem repetir as já respondidas nesta partida.
    $questao = null;
    $alternativas = [];

    if (!$fimDeJogo) {
        $consultaQuestao = $conexao->prepare(
            'SELECT id_questao, enunciado
             FROM questao
             WHERE id_jogo = 1
               AND id_dificuldade = :dificuldade
               AND ativo = 1
               AND id_questao NOT IN (
                   SELECT id_questao
                   FROM resposta
                   WHERE id_participante = :participante
               )
             ORDER BY RAND()
             LIMIT 1'
        );

        $consultaQuestao->execute([
            'dificuldade' => (int) $_SESSION['guardiao_dificuldade'],
            'participante' => (int) $_SESSION['guardiao_participante_id']
        ]);

        $questao = $consultaQuestao->fetch(PDO::FETCH_ASSOC);

        if ($questao) {
            $consultaAlternativas = $conexao->prepare(
                'SELECT id_alternativa, texto
                 FROM alternativa
                 WHERE id_questao = :questao
                 ORDER BY id_alternativa'
            );

            $consultaAlternativas->execute([
                'questao' => (int) $questao['id_questao']
            ]);

            $alternativas = $consultaAlternativas->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $fimDeJogo = true;

            $consultaFim = $conexao->prepare(
                "UPDATE partida
                 SET status = 'finalizada',
                     data_fim = NOW()
                 WHERE id_partida = :partida
                   AND status = 'em_andamento'"
            );

            $consultaFim->execute([
                'partida' => (int) $_SESSION['guardiao_partida_id']
            ]);
        }
    }

    if ($fimDeJogo) {
        if ($vidasGuardiao <= 0) {
            $resultado = 'Você venceu o Guardião!';
        } elseif ($vidasJogador <= 0) {
            $resultado = 'O Guardião venceu desta vez.';
        } else {
            $resultado = 'Fim da batalha!';
        }
    }
}

?>

<main class="container">
    <section class="hero">
        <span class="badge">Batalha na Floresta</span>
        <h2>Contra o Guardião</h2>

        <?php if ($mensagem !== ''): ?>
            <p role="status">
                <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <?php if (!$partidaAtiva): ?>

            <p>Escolha a dificuldade para iniciar a batalha.</p>

            <?php
            $consultaDificuldades = $conexao->query(
                'SELECT id_dificuldade, nome
                 FROM dificuldade
                 ORDER BY id_dificuldade'
            );
            $dificuldades = $consultaDificuldades->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <form method="POST">
                <label for="id_dificuldade">Dificuldade</label>
                <select name="id_dificuldade" id="id_dificuldade" required>
                    <option value="">Selecione</option>
                    <?php foreach ($dificuldades as $dificuldade): ?>
                        <option value="<?= (int) $dificuldade['id_dificuldade'] ?>">
                            <?= htmlspecialchars($dificuldade['nome'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" name="iniciar" value="1">
                    Iniciar batalha
                </button>
            </form>

        <?php elseif ($fimDeJogo): ?>

            <h3><?= htmlspecialchars($resultado ?? 'Batalha encerrada', ENT_QUOTES, 'UTF-8') ?></h3>

            <p>Questões respondidas: <?= (int) $_SESSION['guardiao_questoes'] ?> de 10</p>
            <p>Acertos: <?= (int) $_SESSION['guardiao_acertos'] ?></p>
            <p>Erros: <?= (int) $_SESSION['guardiao_erros'] ?></p>
            <p>XP conquistado: <?= (int) $_SESSION['guardiao_xp'] ?></p>

            <a href="?pagina=guardiao&novo=1" class="btn">
                Jogar novamente
            </a>

            <a href="?pagina=batalha" class="btn">
                Voltar aos modos
            </a>

        <?php else: ?>

            <p>Vidas do jogador: <strong><?= $vidasJogador ?></strong> / 3</p>
            <p>Vidas do Guardião: <strong><?= $vidasGuardiao ?></strong> / 3</p>
            <p>Questões respondidas: <strong><?= $totalQuestoes ?></strong> / 10</p>
            <p>XP: <strong><?= (int) $_SESSION['guardiao_xp'] ?></strong></p>

            <?php if ($questao): ?>
                <article class="card">
                    <h3><?= htmlspecialchars($questao['enunciado'], ENT_QUOTES, 'UTF-8') ?></h3>

                    <?php if ($alternativas): ?>
                        <form method="POST">
                            <input type="hidden" name="id_questao" value="<?= (int) $questao['id_questao'] ?>">

                            <?php foreach ($alternativas as $alternativa): ?>
                                <label>
                                    <input
                                        type="radio"
                                        name="id_alternativa"
                                        value="<?= (int) $alternativa['id_alternativa'] ?>"
                                        required>
                                    <?= htmlspecialchars($alternativa['texto'], ENT_QUOTES, 'UTF-8') ?>
                                </label>
                                <br>
                            <?php endforeach; ?>

                            <button type="submit" name="responder" value="1">
                                Responder
                            </button>
                        </form>
                    <?php else: ?>
                        <p>Esta questão não possui alternativas.</p>
                    <?php endif; ?>
                </article>
            <?php else: ?>
                <p>Não há mais questões disponíveis nesta dificuldade.</p>
            <?php endif; ?>

        <?php endif; ?>

        <p><a href="?pagina=batalha">Voltar à Batalha na Floresta</a></p>
    </section>
</main>