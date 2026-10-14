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
$resultado = null;

// ============================================
// 1. REINICIAR / LIMPAR SE SOLICITADO
// ============================================
if (isset($_GET['novo'])) {
    unset(
        $_SESSION['pocoes_partida_id'],
        $_SESSION['pocoes_participante_id'],
        $_SESSION['pocoes_dificuldade'],
        $_SESSION['pocoes_questoes'],
        $_SESSION['pocoes_xp'],
        $_SESSION['pocoes_acertos'],
        $_SESSION['pocoes_erros'],
        $_SESSION['pocoes_feedback']
    );
    header('Location: ?pagina=pocoes');
    exit;
}

// ============================================
// 2. VALIDAR SE PARTIDA ATIVA CONTINUA EM ANDAMENTO NO BANCO
// ============================================
if (!empty($_SESSION['pocoes_partida_id'])) {
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
        'partida' => (int) $_SESSION['pocoes_partida_id'],
        'aluno' => $idAluno
    ]);
    $statusAtual = $consultaPartidaValida->fetchColumn();

    if ($statusAtual !== 'em_andamento') {
        unset(
            $_SESSION['pocoes_partida_id'],
            $_SESSION['pocoes_participante_id'],
            $_SESSION['pocoes_dificuldade'],
            $_SESSION['pocoes_questoes'],
            $_SESSION['pocoes_xp'],
            $_SESSION['pocoes_acertos'],
            $_SESSION['pocoes_erros'],
            $_SESSION['pocoes_feedback']
        );
    }
}

// ============================================
// 3. INICIAR UMA NOVA PARTIDA DE POÇÕES
// ============================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['iniciar_pocoes'])
) {
    $idDificuldade = filter_input(
        INPUT_POST,
        'id_dificuldade',
        FILTER_VALIDATE_INT
    );

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
        // Jogo 2: Poções Mágicas, Modo 1: Desafio / Individual
        $consultaPartida = $conexao->prepare(
            "INSERT INTO partida (id_jogo, id_modo_jogo, tempo_segundos, status)
             VALUES (2, 1, 0, 'em_andamento')"
        );
        $consultaPartida->execute();
        $idPartida = (int) $conexao->lastInsertId();

        $nomeAluno = $_SESSION['aluno_nome'] ?? 'Aventureiro';

        $consultaParticipante = $conexao->prepare(
            'INSERT INTO partida_participante (id_partida, id_aluno, apelido, xp_total, acertos, erros)
             VALUES (:partida, :aluno, :apelido, 0, 0, 0)'
        );
        $consultaParticipante->execute([
            'partida' => $idPartida,
            'aluno' => $idAluno,
            'apelido' => $nomeAluno
        ]);
        $idParticipante = (int) $conexao->lastInsertId();

        $_SESSION['pocoes_partida_id'] = $idPartida;
        $_SESSION['pocoes_participante_id'] = $idParticipante;
        $_SESSION['pocoes_dificuldade'] = (int) $dificuldade['id_dificuldade'];
        $_SESSION['pocoes_questoes'] = 0;
        $_SESSION['pocoes_xp'] = 0;
        $_SESSION['pocoes_acertos'] = 0;
        $_SESSION['pocoes_erros'] = 0;
        unset($_SESSION['pocoes_feedback']);

        header('Location: ?pagina=pocoes');
        exit;
    }
}

// ============================================
// 4. ESTADO DA PARTIDA
// ============================================
$partidaAtiva = !empty($_SESSION['pocoes_partida_id']);
$fimDeJogo = false;

if ($partidaAtiva) {
    $idPartida = (int) $_SESSION['pocoes_partida_id'];
    $idParticipante = (int) $_SESSION['pocoes_participante_id'];
    $idDificuldade = (int) $_SESSION['pocoes_dificuldade'];
    $totalQuestoes = (int) $_SESSION['pocoes_questoes'];

    $fimDeJogo = ($totalQuestoes >= 10);

    // ============================================
    // 5. PROCESSAR RESPOSTA
    // ============================================
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['responder_pocao']) &&
        !$fimDeJogo
    ) {
        $idQuestao = filter_input(INPUT_POST, 'id_questao', FILTER_VALIDATE_INT);
        $idAlternativa = filter_input(INPUT_POST, 'id_alternativa', FILTER_VALIDATE_INT);

        // Confirma que a questão pertence ao jogo de Poções (2) e à dificuldade escolhida
        $consultaQuestao = $conexao->prepare(
            'SELECT id_questao, explicacao_curta
             FROM questao
             WHERE id_questao = :questao
               AND id_jogo = 2
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
            // Verifica se alternativa é correta
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

                // Registra resposta
                $consultaRegistro = $conexao->prepare(
                    'INSERT INTO resposta (id_participante, id_questao, id_alternativa, acertou, xp_ganho)
                     VALUES (:participante, :questao, :alternativa, :acertou, :xp)'
                );
                $consultaRegistro->execute([
                    'participante' => $idParticipante,
                    'questao' => $idQuestao,
                    'alternativa' => $idAlternativa,
                    'acertou' => $acertou ? 1 : 0,
                    'xp' => $xpGanho
                ]);

                // Atualiza estatísticas do participante
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

                // Atualiza sessão
                $_SESSION['pocoes_questoes']++;
                if ($acertou) {
                    $_SESSION['pocoes_acertos']++;
                    $_SESSION['pocoes_xp'] += $xpGanho;
                    $feedbackMsg = "✨ Poção preparada com perfeição! Você conquistou $xpGanho XP!";
                    $feedbackTipo = 'acerto';
                } else {
                    $_SESSION['pocoes_erros']++;
                    $feedbackMsg = "🧪 A mistura não deu certo desta vez. Mas tudo bem errar! O aprendizado é uma receita que se aprimora a cada tentativa.";
                    $feedbackTipo = 'erro';
                }

                $_SESSION['pocoes_feedback'] = [
                    'tipo' => $feedbackTipo,
                    'texto' => $feedbackMsg,
                    'explicacao' => $dadosQuestao['explicacao_curta'] ?? ''
                ];

                $novoTotalQuestoes = (int) $_SESSION['pocoes_questoes'];
                if ($novoTotalQuestoes >= 10) {
                    $consultaFim = $conexao->prepare(
                        "UPDATE partida
                         SET status = 'finalizada',
                             data_fim = NOW()
                         WHERE id_partida = :partida
                           AND status = 'em_andamento'"
                    );
                    $consultaFim->execute(['partida' => $idPartida]);
                }

                header('Location: ?pagina=pocoes');
                exit;
            }
        }
    }

    $totalQuestoes = (int) $_SESSION['pocoes_questoes'];
    $fimDeJogo = ($totalQuestoes >= 10);

    if (!empty($_SESSION['pocoes_feedback'])) {
        $mensagem = $_SESSION['pocoes_feedback']['texto'];
        $mensagemTipo = $_SESSION['pocoes_feedback']['tipo'];
        $explicacao = $_SESSION['pocoes_feedback']['explicacao'];
        unset($_SESSION['pocoes_feedback']);
    }

    $questao = null;
    $alternativas = [];

    if (!$fimDeJogo) {
        // Busca questão de frações sem repetir as já respondidas nesta partida
        $consultaQ = $conexao->prepare(
            'SELECT id_questao, enunciado, mecanica
             FROM questao
             WHERE id_jogo = 2
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
        $consultaQ->execute([
            'dificuldade' => $idDificuldade,
            'participante' => $idParticipante
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
            // Acabaram as questões desta dificuldade
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
}
?>

<main class="container">
    <section class="hero">
        <span class="badge">Laboratório Mágico de Numéria</span>
        <h2>Poções Mágicas</h2>
        <p>Aprenda frações preparando receitas mágicas com o mascote <strong>Tucano Bicó</strong>!</p>

        <?php if ($mensagem !== ''): ?>
            <div role="status" class="feedback-msg <?= ($mensagemTipo === 'acerto') ? 'feedback-sucesso' : 'feedback-erro' ?>">
                <div class="bico-feedback">
                    <span class="bico-icone" aria-hidden="true"><?= ($mensagemTipo === 'acerto') ? '🌟' : '🦉' ?></span>
                    <div>
                        <p><strong><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></strong></p>
                        <?php if ($explicacao !== ''): ?>
                            <p class="explicacao">
                                <strong>Dica do Bicó:</strong> <?= htmlspecialchars($explicacao, ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$partidaAtiva): ?>

            <article class="card">
                <h3>Escolha o nível das suas Poções</h3>
                <p>
                    No laboratório do Reino de Numéria, cada receita exige frações de ingredientes mágicos
                    como essência lunar, pó de fada e lágrimas de dragão.
                </p>

                <?php
                $consultaDifs = $conexao->query(
                    'SELECT id_dificuldade, nome, xp_por_acerto
                     FROM dificuldade
                     ORDER BY id_dificuldade'
                );
                $dificuldades = $consultaDifs->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <form method="POST">
                    <div style="margin-bottom: 16px;">
                        <label for="id_dificuldade">
                            <strong>Dificuldade da receita:</strong><br>
                            <select name="id_dificuldade" id="id_dificuldade" required>
                                <option value="">Selecione uma dificuldade</option>
                                <?php foreach ($dificuldades as $dif): ?>
                                    <option value="<?= (int) $dif['id_dificuldade'] ?>">
                                        <?= htmlspecialchars($dif['nome'], ENT_QUOTES, 'UTF-8') ?> — <?= (int) $dif['xp_por_acerto'] ?> XP por poção correta
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>

                    <button type="submit" name="iniciar_pocoes" value="1" class="btn">
                        Entrar no Laboratório
                    </button>
                </form>
            </article>

            <p style="margin-top: 24px;">
                <a href="?pagina=home#jogos">Voltar ao catálogo de jogos</a>
            </p>

        <?php elseif ($fimDeJogo): ?>

            <article class="card resultado-card">
                <h3>🎉 Jornada de Poções Concluída!</h3>
                <p>O Tucano Bicó agradece sua ajuda na preparação das poções mágicas!</p>

                <div class="grid" style="margin-top: 20px;">
                    <article class="card">
                        <h4>Poções Preparadas</h4>
                        <p class="destaque-numero"><?= (int) $_SESSION['pocoes_questoes'] ?> de 10</p>
                    </article>

                    <article class="card">
                        <h4>Poções Mágicas de Sucesso</h4>
                        <p class="destaque-numero">✅ <?= (int) $_SESSION['pocoes_acertos'] ?></p>
                    </article>

                    <article class="card">
                        <h4>Receitas a Aprimorar</h4>
                        <p class="destaque-numero">⚠️ <?= (int) $_SESSION['pocoes_erros'] ?></p>
                    </article>

                    <article class="card">
                        <h4>XP Total Conquistado</h4>
                        <p class="destaque-numero">⭐ <?= (int) $_SESSION['pocoes_xp'] ?> XP</p>
                    </article>
                </div>

                <div style="margin-top: 24px;">
                    <a href="?pagina=pocoes&novo=1" class="btn">Preparar mais poções</a>
                    <a href="?pagina=abandonar_pocoes" class="btn btn-secondary">Voltar aos jogos</a>
                </div>
            </article>

        <?php else: ?>

            <div class="painel-status" style="margin-bottom: 20px;">
                <p>
                    Receita: <strong><?= $totalQuestoes + 1 ?></strong> de 10 |
                    Poções Corretas: <strong><?= (int) $_SESSION['pocoes_acertos'] ?></strong> |
                    XP Acumulado: <strong><?= (int) $_SESSION['pocoes_xp'] ?> XP</strong>
                </p>
                <progress value="<?= $totalQuestoes ?>" max="10" style="width: 100%;" aria-label="Progresso da partida"></progress>
            </div>

            <?php if ($questao): ?>
                <article class="card">
                    <span class="badge">Receita Mágica #<?= $totalQuestoes + 1 ?></span>
                    <h3 style="margin-top: 12px;"><?= htmlspecialchars($questao['enunciado'], ENT_QUOTES, 'UTF-8') ?></h3>

                    <?php if ($alternativas): ?>
                        <form method="POST" style="margin-top: 16px;">
                            <input type="hidden" name="id_questao" value="<?= (int) $questao['id_questao'] ?>">

                            <fieldset style="border: none; padding: 0;">
                                <legend class="sr-only">Escolha a fração correta para a poção</legend>
                                <?php foreach ($alternativas as $alt): ?>
                                    <label style="display: block; margin: 10px 0; padding: 10px; background: rgba(255,255,255,0.05); border-radius: 6px; cursor: pointer;">
                                        <input
                                            type="radio"
                                            name="id_alternativa"
                                            value="<?= (int) $alt['id_alternativa'] ?>"
                                            required>
                                        <strong><?= htmlspecialchars($alt['texto'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>

                            <div style="margin-top: 16px;">
                                <button type="submit" name="responder_pocao" value="1" class="btn">
                                    Misturar Ingredientes e Preparar Poção
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <p>Esta receita não possui ingredientes cadastrados.</p>
                    <?php endif; ?>
                </article>
            <?php else: ?>
                <p>Não há mais receitas disponíveis para esta dificuldade no momento.</p>
            <?php endif; ?>

            <p style="margin-top: 24px;">
                <a href="?pagina=abandonar_pocoes" class="btn btn-secondary">
                    Guardar frascos e voltar aos jogos
                </a>
            </p>

        <?php endif; ?>
    </section>
</main>
