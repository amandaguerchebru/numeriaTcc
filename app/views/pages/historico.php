<?php
require_once __DIR__ . '/../../config/conexao.php';

if (!isset($_SESSION['aluno_id'])) {
    header('Location: ?pagina=login');
    exit;
}

$idAluno = (int) $_SESSION['aluno_id'];

// Busca as partidas do aluno que já foram finalizadas.
$consulta = $conexao->prepare(
    "SELECT
        id_participante,
        id_partida,
        xp_total,
        acertos,
        erros
     FROM partida_participante
     WHERE id_aluno = :aluno
       AND id_partida IN (
           SELECT id_partida
           FROM partida
           WHERE status = 'finalizada'
       )
     ORDER BY id_partida DESC"
);

$consulta->execute(['aluno' => $idAluno]);
$partidas = $consulta->fetchAll(PDO::FETCH_ASSOC);


$partidas = $consulta->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// DESEMPENHO DO ALUNO POR CONTEÚDO
// ==========================================

// Busca os participantes das partidas finalizadas
// que pertencem ao aluno conectado.
$consultaParticipantes = $conexao->prepare(
    "SELECT id_participante
     FROM partida_participante
     WHERE id_aluno = :aluno
       AND id_partida IN (
           SELECT id_partida
           FROM partida
           WHERE status = 'finalizada'
       )"
);

$consultaParticipantes->execute(['aluno' => $idAluno]);
$participantes = $consultaParticipantes->fetchAll(PDO::FETCH_COLUMN);

// Inicializa os resultados.
$desempenho = [];

if (!empty($participantes)) {
    // Monta os marcadores para consultar os participantes
    // sem concatenar valores fornecidos pelo usuário.
    $marcadores = implode(
        ', ',
        array_fill(0, count($participantes), '?')
    );

    // Busca as respostas desses participantes.
    $consultaRespostas = $conexao->prepare(
        "SELECT id_questao, acertou
         FROM resposta
         WHERE id_participante IN ($marcadores)"
    );

    $consultaRespostas->execute(
        array_map('intval', $participantes)
    );

    $respostas = $consultaRespostas->fetchAll(PDO::FETCH_ASSOC);

    // Identifica o conteúdo de cada questão.
    $consultaConteudo = $conexao->prepare(
        "SELECT id_conteudo
         FROM questao
         WHERE id_questao = :questao
         LIMIT 1"
    );

    $consultaNomeConteudo = $conexao->prepare(
        "SELECT nome
         FROM conteudo
         WHERE id_conteudo = :conteudo
         LIMIT 1"
    );

    foreach ($respostas as $resposta) {
        $consultaConteudo->execute([
            'questao' => (int) $resposta['id_questao']
        ]);

        $idConteudo = $consultaConteudo->fetchColumn();

        if ($idConteudo === false) {
            continue;
        }

        $consultaNomeConteudo->execute([
            'conteudo' => (int) $idConteudo
        ]);

        $nomeConteudo = $consultaNomeConteudo->fetchColumn();

        if ($nomeConteudo === false) {
            continue;
        }

        if (!isset($desempenho[$idConteudo])) {
            $desempenho[$idConteudo] = [
                'nome' => $nomeConteudo,
                'acertos' => 0,
                'erros' => 0
            ];
        }

        if ((int) $resposta['acertou'] === 1) {
            $desempenho[$idConteudo]['acertos']++;
        } else {
            $desempenho[$idConteudo]['erros']++;
        }
    }
}
?>

<main>
    <div class="container">
        <section class="hero">
            <span class="badge">Sua jornada</span>
            <h2>Histórico de partidas</h2>
            <p>
                Veja suas aventuras anteriores e acompanhe
                o quanto você evoluiu no Numéria.
            </p>
        </section>

        <p style="margin-top: 24px;">
            <a href="?pagina=home">Voltar ao início</a>
        </p>


        <section id="desempenho" style="margin-top: 40px;">
            <h2>Meu desempenho por conteúdo</h2>
            <p>
                Descubra como está seu progresso em cada conteúdo
                da aventura matemática.
            </p>

            <?php if (empty($desempenho)): ?>
                <article class="card">
                    <p>
                        Ainda não há respostas suficientes para
                        apresentar seu desempenho.
                    </p>
                </article>
            <?php else: ?>

                <div class="grid">
                    <?php foreach ($desempenho as $item): ?>
                        <?php
                        $total = $item['acertos'] + $item['erros'];

                        $percentual = $total > 0
                            ? round(($item['acertos'] / $total) * 100)
                            : 0;
                        ?>

                        <article class="card">
                            <h3>
                                <?= htmlspecialchars(
                                    $item['nome'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </h3>

                            <p>
                                <strong>Acertos:</strong>
                                <?= $item['acertos'] ?>
                            </p>

                            <p>
                                <strong>Erros:</strong>
                                <?= $item['erros'] ?>
                            </p>

                            <p>
                                <strong>Aproveitamento:</strong>
                                <?= $percentual ?>%
                            </p>

                            <progress
                                value="<?= $percentual ?>"
                                max="100"
                                aria-label="Aproveitamento em <?= htmlspecialchars(
                                                                    $item['nome'],
                                                                    ENT_QUOTES,
                                                                    'UTF-8'
                                                                ) ?>"
                                style="width: 100%;"></progress>
                        </article>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>
        </section>

        <section style="margin-top: 32px;">
            <?php if (empty($partidas)): ?>
                <article class="card">
                    <h3>Nenhuma partida finalizada ainda</h3>
                    <p>
                        Jogue uma partida e volte aqui para
                        conferir seus resultados!
                    </p>
                    <a href="?pagina=batalha">Ir para os jogos</a>
                </article>
            <?php else: ?>

                <div class="grid">
                    <?php foreach ($partidas as $partida): ?>

                        <?php
                        // Busca os dados da partida atual.
                        $consultaDetalhes = $conexao->prepare(
                            'SELECT id_jogo, data_inicio, data_fim
                             FROM partida
                             WHERE id_partida = :partida
                             LIMIT 1'
                        );

                        $consultaDetalhes->execute([
                            'partida' => (int) $partida['id_partida']
                        ]);

                        $detalhes = $consultaDetalhes->fetch(
                            PDO::FETCH_ASSOC
                        );

                        if (!$detalhes) {
                            continue;
                        }

                        // Busca o nome do jogo.
                        $consultaJogo = $conexao->prepare(
                            'SELECT nome
                             FROM jogo
                             WHERE id_jogo = :jogo
                             LIMIT 1'
                        );

                        $consultaJogo->execute([
                            'jogo' => (int) $detalhes['id_jogo']
                        ]);

                        $nomeJogo = $consultaJogo->fetchColumn();
                        ?>

                        <article class="card">
                            <h3>
                                <?= htmlspecialchars(
                                    $nomeJogo ?: 'Jogo',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </h3>

                            <p>
                                <strong>Data:</strong>
                                <?= date(
                                    'd/m/Y H:i',
                                    strtotime($detalhes['data_inicio'])
                                ) ?>
                            </p>

                            <p>
                                <strong>XP conquistado:</strong>
                                <?= (int) $partida['xp_total'] ?> XP
                            </p>

                            <p>
                                <strong>Acertos:</strong>
                                <?= (int) $partida['acertos'] ?>
                            </p>

                            <p>
                                <strong>Erros:</strong>
                                <?= (int) $partida['erros'] ?>
                            </p>

                            <p>
                                <strong>Status:</strong> Finalizada
                            </p>
                        </article>

                    <?php endforeach; ?>
                </div>

            <?php endif; ?>
        </section>

    </div>
</main>