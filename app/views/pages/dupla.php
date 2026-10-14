<?php

require_once __DIR__ . '/../../config/conexao.php';

if (empty($_SESSION['aluno_id'])) {
    header('Location: ?pagina=login');
    exit;
}

$idAluno = (int) $_SESSION['aluno_id'];
$mensagem = '';
$mensagemTipo = '';
$explicacao = '';

// ============================================
// 1. REINICIAR / LIMPAR ESTADO SE SOLICITADO
// ============================================
if (isset($_GET['novo'])) {
    unset(
        $_SESSION['dupla_partida_id'],
        $_SESSION['dupla_p1_participante_id'],
        $_SESSION['dupla_p2_participante_id'],
        $_SESSION['dupla_p1_nome'],
        $_SESSION['dupla_p2_nome'],
        $_SESSION['dupla_dificuldade'],
        $_SESSION['dupla_turno'],
        $_SESSION['dupla_p1_questoes'],
        $_SESSION['dupla_p2_questoes'],
        $_SESSION['dupla_p1_acertos'],
        $_SESSION['dupla_p1_erros'],
        $_SESSION['dupla_p1_xp'],
        $_SESSION['dupla_p2_acertos'],
        $_SESSION['dupla_p2_erros'],
        $_SESSION['dupla_p2_xp'],
        $_SESSION['dupla_feedback']
    );
    header('Location: ?pagina=dupla');
    exit;
}

// ============================================
// 2. VALIDAR SE PARTIDA ATIVA CONTINUA EM ANDAMENTO NO BANCO
// ============================================
if (!empty($_SESSION['dupla_partida_id'])) {
    $consultaPartidaValida = $conexao->prepare(
        "SELECT status
         FROM partida
         WHERE id_partida = :partida
           AND EXISTS (
               SELECT 1
               FROM partida_participante
               WHERE partida_participante.id_partida = partida.id_partida
                 AND partida_participante.id_aluno = :aluno
           )
         LIMIT 1"
    );
    $consultaPartidaValida->execute([
        'partida' => (int) $_SESSION['dupla_partida_id'],
        'aluno' => $idAluno
    ]);
    $statusAtual = $consultaPartidaValida->fetchColumn();

    if ($statusAtual !== 'em_andamento') {
        unset(
            $_SESSION['dupla_partida_id'],
            $_SESSION['dupla_p1_participante_id'],
            $_SESSION['dupla_p2_participante_id'],
            $_SESSION['dupla_p1_nome'],
            $_SESSION['dupla_p2_nome'],
            $_SESSION['dupla_dificuldade'],
            $_SESSION['dupla_turno'],
            $_SESSION['dupla_p1_questoes'],
            $_SESSION['dupla_p2_questoes'],
            $_SESSION['dupla_p1_acertos'],
            $_SESSION['dupla_p1_erros'],
            $_SESSION['dupla_p1_xp'],
            $_SESSION['dupla_p2_acertos'],
            $_SESSION['dupla_p2_erros'],
            $_SESSION['dupla_p2_xp'],
            $_SESSION['dupla_feedback']
        );
    }
}

// ============================================
// 3. INICIAR UMA NOVA PARTIDA EM DUPLA
// ============================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['iniciar_dupla'])
) {
    $idDificuldade = filter_input(
        INPUT_POST,
        'id_dificuldade',
        FILTER_VALIDATE_INT
    );

    $apelidoP2 = trim($_POST['apelido_jogador2'] ?? '');
    if ($apelidoP2 === '') {
        $apelidoP2 = 'Jogador 2';
    }
    // Limita tamanho do apelido
    if (mb_strlen($apelidoP2) > 40) {
        $apelidoP2 = mb_substr($apelidoP2, 0, 40);
    }

    $consultaDificuldade = $conexao->prepare(
        'SELECT id_dificuldade, nome, xp_por_acerto
         FROM dificuldade
         WHERE id_dificuldade = :id'
    );
    $consultaDificuldade->execute(['id' => $idDificuldade]);
    $dificuldade = $consultaDificuldade->fetch(PDO::FETCH_ASSOC);

    if (!$dificuldade) {
        $mensagem = 'Selecione uma dificuldade válida.';
        $mensagemTipo = 'erro';
    } else {
        $consultaModo = $conexao->prepare(
            'SELECT id_modo_jogo
             FROM modo_jogo
             WHERE nome = :nome
             LIMIT 1'
        );
        $consultaModo->execute(['nome' => 'Dupla Local']);
        $idModo = $consultaModo->fetchColumn();

        if ($idModo === false) {
            $mensagem = 'Modo Dupla Local não encontrado no banco.';
            $mensagemTipo = 'erro';
        } else {
            // Cria a partida (sem cronômetro, tempo_segundos = 0)
            $consultaPartida = $conexao->prepare(
                "INSERT INTO partida (id_jogo, id_modo_jogo, tempo_segundos, status)
                 VALUES (1, :modo, 0, 'em_andamento')"
            );
            $consultaPartida->execute(['modo' => (int) $idModo]);
            $idPartida = (int) $conexao->lastInsertId();

            // Nome do Jogador 1 (aluno autenticado)
            $nomeP1 = $_SESSION['aluno_nome'] ?? 'Jogador 1';

            // Jogador 1: aluno autenticado
            $consultaP1 = $conexao->prepare(
                'INSERT INTO partida_participante (id_partida, id_aluno, apelido, xp_total, acertos, erros)
                 VALUES (:partida, :aluno, :apelido, 0, 0, 0)'
            );
            $consultaP1->execute([
                'partida' => $idPartida,
                'aluno' => $idAluno,
                'apelido' => $nomeP1
            ]);
            $idP1Participante = (int) $conexao->lastInsertId();

            // Jogador 2: participante local sem conta (id_aluno = NULL)
            $consultaP2 = $conexao->prepare(
                'INSERT INTO partida_participante (id_partida, id_aluno, apelido, xp_total, acertos, erros)
                 VALUES (:partida, NULL, :apelido, 0, 0, 0)'
            );
            $consultaP2->execute([
                'partida' => $idPartida,
                'apelido' => $apelidoP2
            ]);
            $idP2Participante = (int) $conexao->lastInsertId();

            $_SESSION['dupla_partida_id'] = $idPartida;
            $_SESSION['dupla_p1_participante_id'] = $idP1Participante;
            $_SESSION['dupla_p2_participante_id'] = $idP2Participante;
            $_SESSION['dupla_p1_nome'] = $nomeP1;
            $_SESSION['dupla_p2_nome'] = $apelidoP2;
            $_SESSION['dupla_dificuldade'] = (int) $dificuldade['id_dificuldade'];
            $_SESSION['dupla_turno'] = 1; // Jogador 1 começa
            $_SESSION['dupla_p1_questoes'] = 0;
            $_SESSION['dupla_p2_questoes'] = 0;
            $_SESSION['dupla_p1_acertos'] = 0;
            $_SESSION['dupla_p1_erros'] = 0;
            $_SESSION['dupla_p1_xp'] = 0;
            $_SESSION['dupla_p2_acertos'] = 0;
            $_SESSION['dupla_p2_erros'] = 0;
            $_SESSION['dupla_p2_xp'] = 0;
            unset($_SESSION['dupla_feedback']);

            header('Location: ?pagina=dupla');
            exit;
        }
    }
}

// ============================================
// 4. ESTADO DA PARTIDA EM DUPLA
// ============================================
$partidaAtiva = !empty($_SESSION['dupla_partida_id']);
$fimDeJogo = false;
$vencedorTexto = '';

if ($partidaAtiva) {
    $idP1 = (int) $_SESSION['dupla_p1_participante_id'];
    $idP2 = (int) $_SESSION['dupla_p2_participante_id'];
    $idPartida = (int) $_SESSION['dupla_partida_id'];
    $idDificuldade = (int) $_SESSION['dupla_dificuldade'];
    $nomeP1 = $_SESSION['dupla_p1_nome'];
    $nomeP2 = $_SESSION['dupla_p2_nome'];

    $qP1 = (int) $_SESSION['dupla_p1_questoes'];
    $qP2 = (int) $_SESSION['dupla_p2_questoes'];

    // Fim de jogo: ambos responderam 10 questões
    $fimDeJogo = ($qP1 >= 10 && $qP2 >= 10);

    // ============================================
    // 5. PROCESSAR RESPOSTA DO TURNO ATUAL
    // ============================================
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['responder_dupla']) &&
        !$fimDeJogo
    ) {
        $idQuestao = filter_input(INPUT_POST, 'id_questao', FILTER_VALIDATE_INT);
        $idAlternativa = filter_input(INPUT_POST, 'id_alternativa', FILTER_VALIDATE_INT);
        $turnoAtual = (int) ($_SESSION['dupla_turno'] ?? 1);

        $idParticipanteAtual = ($turnoAtual === 1) ? $idP1 : $idP2;
        $nomeJogadorAtual = ($turnoAtual === 1) ? $nomeP1 : $nomeP2;

        // Confirma que a questão pertence ao jogo 1 e à dificuldade da partida
        $consultaQuestao = $conexao->prepare(
            'SELECT id_questao, explicacao_curta
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
        $dadosQuestao = $consultaQuestao->fetch(PDO::FETCH_ASSOC);

        if ($dadosQuestao) {
            // Verifica se a alternativa é correta
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
                        'SELECT xp_por_acerto FROM dificuldade WHERE id_dificuldade = :dificuldade'
                    );
                    $consultaXP->execute(['dificuldade' => $idDificuldade]);
                    $xpGanho = (int) $consultaXP->fetchColumn();
                }

                // Registra a resposta no banco associada ao participante do turno
                $consultaRegistro = $conexao->prepare(
                    'INSERT INTO resposta (id_participante, id_questao, id_alternativa, acertou, xp_ganho)
                     VALUES (:participante, :questao, :alternativa, :acertou, :xp)'
                );
                $consultaRegistro->execute([
                    'participante' => $idParticipanteAtual,
                    'questao' => $idQuestao,
                    'alternativa' => $idAlternativa,
                    'acertou' => $acertou ? 1 : 0,
                    'xp' => $xpGanho
                ]);

                // Atualiza estatísticas do participante na tabela partida_participante
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
                    'participante' => $idParticipanteAtual
                ]);

                // Atualiza contadores na sessão
                if ($turnoAtual === 1) {
                    $_SESSION['dupla_p1_questoes']++;
                    if ($acertou) {
                        $_SESSION['dupla_p1_acertos']++;
                        $_SESSION['dupla_p1_xp'] += $xpGanho;
                    } else {
                        $_SESSION['dupla_p1_erros']++;
                    }
                } else {
                    $_SESSION['dupla_p2_questoes']++;
                    if ($acertou) {
                        $_SESSION['dupla_p2_acertos']++;
                        $_SESSION['dupla_p2_xp'] += $xpGanho;
                    } else {
                        $_SESSION['dupla_p2_erros']++;
                    }
                }

                // Mensagem de feedback
                if ($acertou) {
                    $feedbackMsg = "Acertou! $nomeJogadorAtual conquistou $xpGanho XP!";
                    $feedbackTipo = 'acerto';
                } else {
                    $feedbackMsg = "Não foi dessa vez para $nomeJogadorAtual! Tudo bem errar, continue firme!";
                    $feedbackTipo = 'erro';
                }

                $_SESSION['dupla_feedback'] = [
                    'tipo' => $feedbackTipo,
                    'texto' => $feedbackMsg,
                    'explicacao' => $dadosQuestao['explicacao_curta'] ?? ''
                ];

                // Alterna o turno
                $novaQ1 = (int) $_SESSION['dupla_p1_questoes'];
                $novaQ2 = (int) $_SESSION['dupla_p2_questoes'];

                if ($turnoAtual === 1) {
                    // Passa a vez para o jogador 2 se ainda tiver questões
                    if ($novaQ2 < 10) {
                        $_SESSION['dupla_turno'] = 2;
                    }
                } else {
                    // Passa a vez para o jogador 1 se ainda tiver questões
                    if ($novaQ1 < 10) {
                        $_SESSION['dupla_turno'] = 1;
                    }
                }

                // Se ambos completaram 10 questões, finaliza a partida
                if ($novaQ1 >= 10 && $novaQ2 >= 10) {
                    $consultaFim = $conexao->prepare(
                        "UPDATE partida
                         SET status = 'finalizada',
                             data_fim = NOW()
                         WHERE id_partida = :partida
                           AND status = 'em_andamento'"
                    );
                    $consultaFim->execute(['partida' => $idPartida]);
                }

                header('Location: ?pagina=dupla');
                exit;
            }
        }
    }

    // Recalcula contadores e fim de jogo
    $qP1 = (int) $_SESSION['dupla_p1_questoes'];
    $qP2 = (int) $_SESSION['dupla_p2_questoes'];
    $fimDeJogo = ($qP1 >= 10 && $qP2 >= 10);

    // Recupera feedback da rodada anterior, se houver
    if (!empty($_SESSION['dupla_feedback'])) {
        $mensagem = $_SESSION['dupla_feedback']['texto'];
        $mensagemTipo = $_SESSION['dupla_feedback']['tipo'];
        $explicacao = $_SESSION['dupla_feedback']['explicacao'];
        unset($_SESSION['dupla_feedback']);
    }

    $questao = null;
    $alternativas = [];

    if (!$fimDeJogo) {
        $turnoAtual = (int) ($_SESSION['dupla_turno'] ?? 1);

        // Busca questão sem repetir nenhuma que já foi respondida nesta partida (por nenhum dos 2 jogadores)
        $consultaQ = $conexao->prepare(
            'SELECT id_questao, enunciado
             FROM questao
             WHERE id_jogo = 1
               AND id_dificuldade = :dificuldade
               AND ativo = 1
               AND id_questao NOT IN (
                   SELECT id_questao
                   FROM resposta
                   WHERE id_participante IN (:p1, :p2)
               )
             ORDER BY RAND()
             LIMIT 1'
        );
        $consultaQ->execute([
            'dificuldade' => $idDificuldade,
            'p1' => $idP1,
            'p2' => $idP2
        ]);
        $questao = $consultaQ->fetch(PDO::FETCH_ASSOC);

        if ($questao) {
            $consultaAlt = $conexao->prepare(
                'SELECT id_alternativa, texto
                 FROM alternativa
                 WHERE id_questao = :questao
                 ORDER BY id_alternativa'
            );
            $consultaAlt->execute(['questao' => (int) $questao['id_questao']]);
            $alternativas = $consultaAlt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Sem mais questões disponíveis nesta dificuldade
            $fimDeJogo = true;
            $consultaFim = $conexao->prepare(
                "UPDATE partida
                 SET status = 'finalizada',
                     data_fim = NOW()
                 WHERE id_partida = :partida
                   AND status = 'em_andamento'"
            );
            $consultaFim->execute(['partida' => $idPartida]);
        }
    }

    if ($fimDeJogo) {
        $acertos1 = (int) $_SESSION['dupla_p1_acertos'];
        $acertos2 = (int) $_SESSION['dupla_p2_acertos'];

        if ($acertos1 > $acertos2) {
            $vencedorTexto = "🏆 Vitória de $nomeP1!";
        } elseif ($acertos2 > $acertos1) {
            $vencedorTexto = "🏆 Vitória de $nomeP2!";
        } else {
            $vencedorTexto = "🤝 Empate! Ambos foram incríveis!";
        }
    }
}
?>

<main class="container">
    <section class="hero">
        <span class="badge">Batalha na Floresta</span>
        <h2>Modo Dupla Local</h2>
        <p>Dois aventureiros no mesmo teclado em turnos alternados!</p>

        <?php if ($mensagem !== ''): ?>
            <div role="status" class="feedback-msg <?= ($mensagemTipo === 'acerto') ? 'feedback-sucesso' : 'feedback-erro' ?>">
                <p><strong><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></strong></p>
                <?php if ($explicacao !== ''): ?>
                    <p class="explicacao"><?= htmlspecialchars($explicacao, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!$partidaAtiva): ?>

            <article class="card">
                <h3>Configurar Partida em Dupla</h3>
                <p>O <strong>Jogador 1</strong> é o aluno conectado. O <strong>Jogador 2</strong> joga como convidado local.</p>

                <?php
                $consultaDificuldades = $conexao->query(
                    'SELECT id_dificuldade, nome, xp_por_acerto
                     FROM dificuldade
                     ORDER BY id_dificuldade'
                );
                $dificuldades = $consultaDificuldades->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <form method="POST">
                    <div style="margin-bottom: 16px;">
                        <label>
                            <strong>Jogador 1 (Você - Caçador):</strong><br>
                            <input type="text" value="<?= htmlspecialchars($_SESSION['aluno_nome'] ?? 'Jogador 1', ENT_QUOTES, 'UTF-8') ?>" disabled>
                        </label>
                    </div>

                    <div style="margin-bottom: 16px;">
                        <label for="apelido_jogador2">
                            <strong>Jogador 2 (Convidado - Monstrinho):</strong><br>
                            <input
                                type="text"
                                name="apelido_jogador2"
                                id="apelido_jogador2"
                                placeholder="Nome ou apelido do amigo"
                                maxlength="40"
                                required>
                        </label>
                    </div>

                    <div style="margin-bottom: 16px;">
                        <label for="id_dificuldade">
                            <strong>Dificuldade:</strong><br>
                            <select name="id_dificuldade" id="id_dificuldade" required>
                                <option value="">Selecione uma dificuldade</option>
                                <?php foreach ($dificuldades as $dif): ?>
                                    <option value="<?= (int) $dif['id_dificuldade'] ?>">
                                        <?= htmlspecialchars($dif['nome'], ENT_QUOTES, 'UTF-8') ?> (<?= (int) $dif['xp_por_acerto'] ?> XP por acerto)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>

                    <button type="submit" name="iniciar_dupla" value="1" class="btn">
                        Iniciar Batalha em Dupla
                    </button>
                </form>
            </article>

            <p style="margin-top: 24px;">
                <a href="?pagina=batalha">Voltar aos modos de Batalha</a>
            </p>

        <?php elseif ($fimDeJogo): ?>

            <article class="card resultado-card">
                <h3><?= htmlspecialchars($vencedorTexto, ENT_QUOTES, 'UTF-8') ?></h3>
                <p>A batalha matemática entre os dois aventureiros chegou ao fim!</p>

                <div class="grid" style="margin-top: 20px;">
                    <article class="card">
                        <h4>🏹 <?= htmlspecialchars($nomeP1, ENT_QUOTES, 'UTF-8') ?> (Jogador 1)</h4>
                        <p>Questões: <?= (int) $_SESSION['dupla_p1_questoes'] ?> de 10</p>
                        <p>Acertos: <strong><?= (int) $_SESSION['dupla_p1_acertos'] ?></strong></p>
                        <p>Erros: <?= (int) $_SESSION['dupla_p1_erros'] ?></p>
                        <p>XP conquistado: <strong><?= (int) $_SESSION['dupla_p1_xp'] ?> XP</strong></p>
                    </article>

                    <article class="card">
                        <h4>🐾 <?= htmlspecialchars($nomeP2, ENT_QUOTES, 'UTF-8') ?> (Jogador 2)</h4>
                        <p>Questões: <?= (int) $_SESSION['dupla_p2_questoes'] ?> de 10</p>
                        <p>Acertos: <strong><?= (int) $_SESSION['dupla_p2_acertos'] ?></strong></p>
                        <p>Erros: <?= (int) $_SESSION['dupla_p2_erros'] ?></p>
                        <p>XP conquistado: <strong><?= (int) $_SESSION['dupla_p2_xp'] ?> XP</strong></p>
                    </article>
                </div>

                <div style="margin-top: 24px;">
                    <a href="?pagina=dupla&novo=1" class="btn">Jogar novamente</a>
                    <a href="?pagina=abandonar_dupla" class="btn btn-secondary">Voltar aos modos</a>
                </div>
            </article>

        <?php else: ?>

            <?php
            $turno = (int) ($_SESSION['dupla_turno'] ?? 1);
            $nomeAtual = ($turno === 1) ? $nomeP1 : $nomeP2;
            $papelAtual = ($turno === 1) ? '🏹 Caçador (Jogador 1)' : '🐾 Monstrinho (Jogador 2)';
            ?>

            <!-- Placar em tempo real -->
            <div class="grid" style="margin-bottom: 24px;">
                <article class="card <?= ($turno === 1) ? 'turno-ativo' : '' ?>">
                    <h4>🏹 <?= htmlspecialchars($nomeP1, ENT_QUOTES, 'UTF-8') ?></h4>
                    <p>Questões: <?= (int) $_SESSION['dupla_p1_questoes'] ?>/10</p>
                    <p>Acertos: <strong><?= (int) $_SESSION['dupla_p1_acertos'] ?></strong> | XP: <?= (int) $_SESSION['dupla_p1_xp'] ?></p>
                    <?php if ($turno === 1): ?>
                        <span class="badge">SUA VEZ DE JOGAR</span>
                    <?php endif; ?>
                </article>

                <article class="card <?= ($turno === 2) ? 'turno-ativo' : '' ?>">
                    <h4>🐾 <?= htmlspecialchars($nomeP2, ENT_QUOTES, 'UTF-8') ?></h4>
                    <p>Questões: <?= (int) $_SESSION['dupla_p2_questoes'] ?>/10</p>
                    <p>Acertos: <strong><?= (int) $_SESSION['dupla_p2_acertos'] ?></strong> | XP: <?= (int) $_SESSION['dupla_p2_xp'] ?></p>
                    <?php if ($turno === 2): ?>
                        <span class="badge">SUA VEZ DE JOGAR</span>
                    <?php endif; ?>
                </article>
            </div>

            <div class="destaque-turno" style="margin-bottom: 16px;">
                <h3>Vez de <?= htmlspecialchars($nomeAtual, ENT_QUOTES, 'UTF-8') ?> (<?= $papelAtual ?>)</h3>
            </div>

            <?php if ($questao): ?>
                <article class="card">
                    <h3><?= htmlspecialchars($questao['enunciado'], ENT_QUOTES, 'UTF-8') ?></h3>

                    <?php if ($alternativas): ?>
                        <form method="POST">
                            <input type="hidden" name="id_questao" value="<?= (int) $questao['id_questao'] ?>">

                            <?php foreach ($alternativas as $alt): ?>
                                <label style="display: block; margin: 8px 0; cursor: pointer;">
                                    <input
                                        type="radio"
                                        name="id_alternativa"
                                        value="<?= (int) $alt['id_alternativa'] ?>"
                                        required>
                                    <?= htmlspecialchars($alt['texto'], ENT_QUOTES, 'UTF-8') ?>
                                </label>
                            <?php endforeach; ?>

                            <div style="margin-top: 16px;">
                                <button type="submit" name="responder_dupla" value="1" class="btn">
                                    Confirmar Resposta de <?= htmlspecialchars($nomeAtual, ENT_QUOTES, 'UTF-8') ?>
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <p>Esta questão não possui alternativas cadastradas.</p>
                    <?php endif; ?>
                </article>
            <?php else: ?>
                <p>Não há mais questões disponíveis para esta rodada.</p>
            <?php endif; ?>

            <p style="margin-top: 24px;">
                <a href="?pagina=abandonar_dupla" class="btn btn-secondary">
                    Abandonar partida em dupla e voltar aos modos
                </a>
            </p>

        <?php endif; ?>
    </section>
</main>
