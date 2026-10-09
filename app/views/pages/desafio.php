
<?php
require_once __DIR__ . '/../../config/conexao.php';

// Verifica se o aluno está conectado.
if (empty($_SESSION['aluno_id'])) {
    header('Location: ?pagina=login');
    exit;
}

// Inicializa os dados do desafio.
if (!isset($_SESSION['desafio_inicio'])) {
    $_SESSION['desafio_inicio'] = time();
    $_SESSION['desafio_xp'] = 0;
    $_SESSION['desafio_acertos'] = 0;
    $_SESSION['desafio_erros'] = 0;
}

// Calcula o tempo restante.
$tempoRestante = max(
    0,
    180 - (time() - $_SESSION['desafio_inicio'])
);

$mensagem = '';
$questao = null;
$alternativas = [];

// Verifica se o aluno respondeu.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tempoRestante > 0) {
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

    if ($idQuestao && $idAlternativa) {
        // Confere se a alternativa pertence à questão.
        $consulta = $conexao->prepare(
            'SELECT correta
             FROM alternativa
             WHERE id_alternativa = :alternativa
               AND id_questao = :questao
             LIMIT 1'
        );

        $consulta->execute([
            'alternativa' => $idAlternativa,
            'questao' => $idQuestao
        ]);

        $resposta = $consulta->fetch(PDO::FETCH_ASSOC);

        if ($resposta) {
            if ((int) $resposta['correta'] === 1) {
                // Busca a dificuldade da questão.
                $consultaXP = $conexao->prepare(
                    'SELECT d.xp_por_acerto
                     FROM dificuldade d
                     WHERE d.id_dificuldade = (
                         SELECT q.id_dificuldade
                         FROM questao q
                         WHERE q.id_questao = :questao
                           AND q.id_jogo = 1
                           AND q.ativo = 1
                     )
                     LIMIT 1'
                );

                $consultaXP->execute(['questao' => $idQuestao]);
                $xpQuestao = $consultaXP->fetchColumn();

                if ($xpQuestao !== false) {
                    $_SESSION['desafio_xp'] += (int) $xpQuestao;
                    $_SESSION['desafio_acertos']++;
                    $mensagem = 'Resposta correta! Você ganhou '
                        . (int) $xpQuestao . ' XP!';
                }
            } else {
                $_SESSION['desafio_erros']++;
                $mensagem = 'Não foi dessa vez! Continue tentando.';
            }
        }
    }

    // Atualiza o tempo após processar a resposta.
    $tempoRestante = max(
        0,
        180 - (time() - $_SESSION['desafio_inicio'])
    );
}

// Busca uma questão ativa da Batalha na Floresta.
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

    // Busca as alternativas separadamente.
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

        <p>
            Tempo restante:
            <strong id="cronometro">03:00</strong>
        </p>

        <p>
            XP: <strong><?= (int) $_SESSION['desafio_xp'] ?></strong>
            |
            Acertos: <strong><?= (int) $_SESSION['desafio_acertos'] ?></strong>
            |
            Erros: <strong><?= (int) $_SESSION['desafio_erros'] ?></strong>
        </p>

        <?php if ($mensagem !== ''): ?>
            <p role="status">
                <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <?php if ($tempoRestante <= 0): ?>

            <h3>Tempo esgotado!</h3>

            <p>
                Você conseguiu
                <?= (int) $_SESSION['desafio_acertos'] ?> acertos
                e <?= (int) $_SESSION['desafio_erros'] ?> erros.
            </p>

            <p>
                XP conquistado:
                <?= (int) $_SESSION['desafio_xp'] ?>
            </p>

            <p>
                <a href="?pagina=batalha">Voltar à Batalha</a>
            </p>

        <?php elseif ($questao): ?>

            <div class="card">
                <h3>
                    <?= htmlspecialchars(
                        $questao['enunciado'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </h3>

                <?php if (count($alternativas) > 0): ?>

                    <form method="POST" id="formResposta">
                        <input
                            type="hidden"
                            name="id_questao"
                            value="<?= (int) $questao['id_questao'] ?>"
                        >

                        <?php foreach ($alternativas as $alternativa): ?>
                            <label>
                                <input
                                    type="radio"
                                    name="id_alternativa"
                                    value="<?= (int) $alternativa['id_alternativa'] ?>"
                                    required
                                >

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

                    <p>Esta questão ainda não possui alternativas.</p>

                <?php endif; ?>
            </div>

        <?php else: ?>

            <p>Não foi encontrada uma questão ativa para este jogo.</p>

        <?php endif; ?>
    </section>
</main>

<script>
    let segundos = <?= (int) $tempoRestante ?>;
    const cronometro = document.getElementById('cronometro');
    const formulario = document.getElementById('formResposta');

    function atualizarCronometro() {
        const minutos = Math.floor(segundos / 60);
        const resto = segundos % 60;

        cronometro.textContent =
            String(minutos).padStart(2, '0') + ':' +
            String(resto).padStart(2, '0');

        if (segundos <= 0) {
            if (formulario) {
                formulario.querySelectorAll('input, button')
                    .forEach(elemento => elemento.disabled = true);
            }

            cronometro.textContent = '00:00';
            window.location.reload();
            return;
        }

        segundos--;
    }

    atualizarCronometro();
    setInterval(atualizarCronometro, 1000);
</script>
