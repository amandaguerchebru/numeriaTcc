<?php

require_once __DIR__ . '/../../config/conexao.php';

// ==================================================
// 1. VERIFICAR SE O ALUNO ESTÁ CONECTADO
// ==================================================

if (empty($_SESSION['aluno_id'])) {
    header('Location: ?pagina=login');
    exit;
}


// ==================================================
// 2. INICIAR OU REINICIAR A PARTIDA
// ==================================================

if (
    !isset($_SESSION['desafio_inicio']) ||
    !isset($_SESSION['desafio_partida_id']) ||
    isset($_GET['novo'])
) {
    // Busca o modo Desafio no banco.
    $consultaModo = $conexao->prepare(
        'SELECT id_modo_jogo
         FROM modo_jogo
         WHERE nome = :nome
         LIMIT 1'
    );

    $consultaModo->execute([
        'nome' => 'Desafio'
    ]);

    $idModo = $consultaModo->fetchColumn();

    if ($idModo === false) {
        exit('Erro: o modo Desafio não foi encontrado no banco de dados.');
    }

    // Cria a partida.
    $consultaPartida = $conexao->prepare(
        'INSERT INTO partida
            (id_jogo, id_modo_jogo, tempo_segundos, status)
         VALUES
            (:jogo, :modo, :tempo, :status)'
    );

    $consultaPartida->execute([
        'jogo' => 1,
        'modo' => (int) $idModo,
        'tempo' => 180,
        'status' => 'em_andamento'
    ]);

    $idPartida = (int) $conexao->lastInsertId();

    // Registra o aluno participante.
    $consultaParticipante = $conexao->prepare(
        'INSERT INTO partida_participante
            (id_partida, id_aluno, xp_total, acertos, erros)
         VALUES
            (:partida, :aluno, 0, 0, 0)'
    );

    $consultaParticipante->execute([
        'partida' => $idPartida,
        'aluno' => (int) $_SESSION['aluno_id']
    ]);

    // Guarda os identificadores para usar durante o jogo.
    $_SESSION['desafio_partida_id'] = $idPartida;
    $_SESSION['desafio_participante_id'] =
        (int) $conexao->lastInsertId();

    // Reinicia os dados da sessão.
    $_SESSION['desafio_inicio'] = time();
    $_SESSION['desafio_xp'] = 0;
    $_SESSION['desafio_acertos'] = 0;
    $_SESSION['desafio_erros'] = 0;

    unset($_SESSION['desafio_questao_atual']);

    // Evita que o parâmetro novo reinicie a partida novamente.
    if (isset($_GET['novo'])) {
        header('Location: ?pagina=desafio');
        exit;
    }
}

// Garante que os contadores existam.
$_SESSION['desafio_xp'] = (int) ($_SESSION['desafio_xp'] ?? 0);
$_SESSION['desafio_acertos'] = (int) ($_SESSION['desafio_acertos'] ?? 0);
$_SESSION['desafio_erros'] = (int) ($_SESSION['desafio_erros'] ?? 0);

// ==================================================
// 3. CALCULAR O TEMPO RESTANTE
// ==================================================

$tempoRestante = max(
    0,
    180 - (time() - $_SESSION['desafio_inicio'])
);

$mensagem = '';
$questao = null;
$alternativas = [];

// ==================================================
// 3.1. FINALIZAR A PARTIDA NO BANCO
// ==================================================

if ($tempoRestante <= 0 && !empty($_SESSION['desafio_partida_id'])) {

    $consultaFim = $conexao->prepare(
        "UPDATE partida
         SET status = 'finalizada',
             data_fim = NOW()
         WHERE id_partida = :partida
           AND status = 'em_andamento'"
    );

    $consultaFim->execute([
        'partida' => (int) $_SESSION['desafio_partida_id']
    ]);
}


 // ==================================================
// 4. PROCESSAR A RESPOSTA
// ==================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $tempoRestante > 0
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

    $idParticipante = (int) (
        $_SESSION['desafio_participante_id'] ?? 0
    );

    $idPartida = (int) (
        $_SESSION['desafio_partida_id'] ?? 0
    );

    if ($idQuestao && $idAlternativa && $idParticipante && $idPartida) {

        // Confirma que a alternativa pertence a uma questão
        // ativa da Batalha na Floresta.
        $consulta = $conexao->prepare(
            'SELECT correta
             FROM alternativa
             WHERE id_alternativa = :alternativa
               AND id_questao = :questao
               AND id_questao IN (
                   SELECT id_questao
                   FROM questao
                   WHERE id_jogo = 1
                     AND ativo = 1
               )
             LIMIT 1'
        );

        $consulta->execute([
            'alternativa' => $idAlternativa,
            'questao' => $idQuestao
        ]);

        $respostaSelecionada = $consulta->fetch(PDO::FETCH_ASSOC);

        if ($respostaSelecionada) {

            // Confirma que a partida pertence ao aluno
            // e continua em andamento.
            $consultaValida = $conexao->prepare(
                "SELECT id_participante
                 FROM partida_participante
                 WHERE id_participante = :participante
                   AND id_partida = :partida
                   AND id_aluno = :aluno
                   AND EXISTS (
                       SELECT 1
                       FROM partida
                       WHERE partida.id_partida = :partida_verificacao
                         AND partida.status = 'em_andamento'
                   )
                 LIMIT 1"
            );

            $consultaValida->execute([
                'participante' => $idParticipante,
                'partida' => $idPartida,
                'aluno' => (int) $_SESSION['aluno_id'],
                'partida_verificacao' => $idPartida
            ]);

            if ($consultaValida->fetchColumn()) {

                $acertou = (int) $respostaSelecionada['correta'] === 1;
                $xpQuestao = 0;

                // Calcula o XP somente se a resposta estiver correta.
                if ($acertou) {
                    $consultaXP = $conexao->prepare(
                        'SELECT xp_por_acerto
                         FROM dificuldade
                         WHERE id_dificuldade = (
                             SELECT id_dificuldade
                             FROM questao
                             WHERE id_questao = :questao
                               AND id_jogo = 1
                               AND ativo = 1
                         )
                         LIMIT 1'
                    );

                    $consultaXP->execute([
                        'questao' => $idQuestao
                    ]);

                    $resultadoXP = $consultaXP->fetchColumn();

                    if ($resultadoXP !== false) {
                        $xpQuestao = (int) $resultadoXP;
                    } else {
                        $acertou = false;
                    }
                }

                // Registra a resposta no banco.
                $consultaRegistro = $conexao->prepare(
                    'INSERT INTO resposta
                        (
                            id_participante,
                            id_questao,
                            id_alternativa,
                            acertou,
                            xp_ganho
                        )
                     VALUES
                        (
                            :participante,
                            :questao,
                            :alternativa,
                            :acertou,
                            :xp
                        )'
                );

                $consultaRegistro->execute([
                    'participante' => $idParticipante,
                    'questao' => $idQuestao,
                    'alternativa' => $idAlternativa,
                    'acertou' => $acertou ? 1 : 0,
                    'xp' => $xpQuestao
                ]);

                // Atualiza os totais do participante.
                if ($acertou) {
                    $consultaAtualiza = $conexao->prepare(
                        'UPDATE partida_participante
                         SET xp_total = xp_total + :xp,
                             acertos = acertos + 1
                         WHERE id_participante = :participante'
                    );

                    $consultaAtualiza->execute([
                        'xp' => $xpQuestao,
                        'participante' => $idParticipante
                    ]);

                    $_SESSION['desafio_xp'] += $xpQuestao;
                    $_SESSION['desafio_acertos']++;

                    $mensagem = 'Resposta correta! Você ganhou '
                        . $xpQuestao . ' XP!';

                } else {
                    $consultaAtualiza = $conexao->prepare(
                        'UPDATE partida_participante
                         SET erros = erros + 1
                         WHERE id_participante = :participante'
                    );

                    $consultaAtualiza->execute([
                        'participante' => $idParticipante
                    ]);

                    $_SESSION['desafio_erros']++;

                    $mensagem = 'Não foi dessa vez! Continue tentando.';
                }

            } else {
                $mensagem = 'Esta partida não está mais disponível.';
            }
        }
    }

    // Recalcula o tempo sem reiniciar a partida.
    $tempoRestante = max(
        0,
        180 - (time() - $_SESSION['desafio_inicio'])
    );
}

// ==================================================
// 5. BUSCAR UMA QUESTÃO E SUAS ALTERNATIVAS
// ==================================================

if ($tempoRestante > 0) {

    $consulta = $conexao->prepare(
        'SELECT id_questao, enunciado, explicacao_curta
         FROM questao
         WHERE id_jogo = 1
           AND ativo = 1
         ORDER BY RAND()
         LIMIT 1'
    );

    $consulta->execute();

    $questao = $consulta->fetch(PDO::FETCH_ASSOC);

    if ($questao) {

        $consultaAlternativas = $conexao->prepare(
            'SELECT id_alternativa, texto
             FROM alternativa
             WHERE id_questao = :questao
             ORDER BY id_alternativa'
        );

        $consultaAlternativas->execute([
            'questao' => $questao['id_questao']
        ]);

        $alternativas = $consultaAlternativas->fetchAll(
            PDO::FETCH_ASSOC
        );
    }
}

?>

<main class="container">
    <section class="hero">

        <span class="badge">Batalha na Floresta</span>

        <h2>Desafio Matemático</h2>

        <!-- CRONÔMETRO -->
        <p>
            Tempo restante:
            <strong id="cronometro">
                00:00
            </strong>
        </p>

        <!-- PONTUAÇÃO -->
        <p>
            XP:
            <strong id="pontuacaoXP">
                <?= (int) $_SESSION['desafio_xp'] ?>
            </strong>

            |

            Acertos:
            <strong id="pontuacaoAcertos">
                <?= (int) $_SESSION['desafio_acertos'] ?>
            </strong>

            |

            Erros:
            <strong id="pontuacaoErros">
                <?= (int) $_SESSION['desafio_erros'] ?>
            </strong>
        </p>

        <!-- FEEDBACK DA RESPOSTA -->
        <?php if ($mensagem !== ''): ?>
            <p role="status">
                <?= htmlspecialchars(
                    $mensagem,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>
        <?php endif; ?>


        <!-- ======================================
             ÁREA FINAL DO DESAFIO
             ====================================== -->

        <div
            id="areaFim"
            <?= $tempoRestante > 0 ? 'hidden' : '' ?>>

            <h3>Tempo esgotado!</h3>

            <p>
                Você conseguiu
                <strong>
                    <?= (int) $_SESSION['desafio_acertos'] ?>
                </strong>
                acertos e
                <strong>
                    <?= (int) $_SESSION['desafio_erros'] ?>
                </strong>
                erros.
            </p>

            <p>
                XP conquistado:
                <strong>
                    <?= (int) $_SESSION['desafio_xp'] ?>
                </strong>
            </p>

            <a href="?pagina=batalha" class="btn">
                Voltar à Batalha
            </a>

        </div>


        <!-- ======================================
             ÁREA DA QUESTÃO
             ====================================== -->

        <div
            id="areaQuestao"
            <?= $tempoRestante <= 0 ? 'hidden' : '' ?>>

            <?php if ($questao): ?>

                <div class="card">

                    <h3>
                        <?= htmlspecialchars(
                            $questao['enunciado'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </h3>

                    <?php if (count($alternativas) > 0): ?>

                        <form
                            method="POST"
                            id="formResposta">

                            <input
                                type="hidden"
                                name="id_questao"
                                value="<?= (int) $questao['id_questao'] ?>">

                            <?php foreach ($alternativas as $alternativa): ?>

                                <label>
                                    <input
                                        type="radio"
                                        name="id_alternativa"
                                        value="<?= (int) $alternativa['id_alternativa'] ?>"
                                        required>

                                    <?= htmlspecialchars(
                                        $alternativa['texto'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </label>

                                <br>

                            <?php endforeach; ?>

                            <button type="submit">
                                Responder
                            </button>

                        </form>

                    <?php else: ?>

                        <p>
                            Esta questão ainda não possui alternativas.
                        </p>

                    <?php endif; ?>

                </div>

            <?php else: ?>

                <p>
                    Não foi encontrada uma questão ativa
                    para este jogo.
                </p>

            <?php endif; ?>

        </div>

    </section>
</main>


<!-- ==========================================
     JAVASCRIPT DO CRONÔMETRO
     ========================================== -->

<script>
    document.addEventListener('DOMContentLoaded', function() {

        let segundos = <?= (int) $tempoRestante ?>;

        const cronometro = document.getElementById('cronometro');
        const formulario = document.getElementById('formResposta');
        const areaQuestao = document.getElementById('areaQuestao');
        const areaFim = document.getElementById('areaFim');

        let intervaloCronometro = null;

        // Exibe o tempo no formato MM:SS.
        function atualizarExibicao() {
            const minutos = Math.floor(segundos / 60);
            const resto = segundos % 60;

            cronometro.textContent =
                String(minutos).padStart(2, '0') + ':' +
                String(resto).padStart(2, '0');
        }

        // Finaliza a partida sem recarregar a página.
        // Finaliza a partida na tela e solicita a atualização no banco.
        function finalizarDesafio() {

            if (intervaloCronometro !== null) {
                clearInterval(intervaloCronometro);
                intervaloCronometro = null;
            }

            segundos = 0;
            cronometro.textContent = '00:00';

            if (formulario) {
                formulario.querySelectorAll('input, button')
                    .forEach(function(elemento) {
                        elemento.disabled = true;
                    });
            }

            areaQuestao.hidden = true;
            areaFim.hidden = false;

            // Avisa o PHP que o cronômetro terminou.
            fetch('?pagina=finalizar_desafio', {
                    method: 'POST',
                    credentials: 'same-origin'
                })
                .then(function(resposta) {
                    if (!resposta.ok) {
                        throw new Error('Não foi possível finalizar no banco.');
                    }

                    return resposta.text();
                })
                .then(function(mensagem) {
                    console.log(mensagem);
                })
                .catch(function(erro) {
                    console.error(erro);
                });
        }

        // Mostra o tempo inicial.
        atualizarExibicao();

        // Se o tempo já acabou ao carregar a página,
        // mostra imediatamente a tela final.
        if (segundos <= 0) {
            finalizarDesafio();
            return;
        }

        // Atualiza o cronômetro a cada segundo.
        intervaloCronometro = setInterval(function() {

            segundos--;

            if (segundos <= 0) {
                finalizarDesafio();
                return;
            }

            atualizarExibicao();

        }, 1000);

    });
</script>