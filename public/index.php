
<?php

session_start();

require __DIR__ . '/../app/config/app.php';

$pagina = $_GET['pagina'] ?? 'home';

$paginasPermitidas = [
    'home',
    'cadastro',
    'login',
    'sair',
    'batalha',
    'pocoes',
    'desafio',
    'finalizar_desafio',
    'historico',
    'guardiao',
    'dupla',
    'abandonar_guardiao',
    'abandonar_dupla',
    'abandonar_pocoes'
];

// VALIDAR A ROTA SOLICITADA
if (!in_array($pagina, $paginasPermitidas, true)) {
    http_response_code(404);
    $pagina = 'home';
}

// PROTEGER ACESSO ÀS PÁGINAS DOS JOGOS
if (
    in_array(
        $pagina,
        [
            'batalha',
            'pocoes',
            'desafio',
            'guardiao',
            'dupla',
            'historico'
        ],
        true
    )
    && !isset($_SESSION['aluno_id'])
) {
    header('Location: ?pagina=login');
    exit;
}



// ENCERRAR SESSÃO
if ($pagina === 'sair') {
    $_SESSION = [];
    session_destroy();

    header('Location: ?pagina=home');
    exit;
}

// FINALIZAR DESAFIO PELO CRONÔMETRO
if ($pagina === 'finalizar_desafio') {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Método não permitido.');
    }

    if (
        empty($_SESSION['aluno_id']) ||
        empty($_SESSION['desafio_partida_id']) ||
        empty($_SESSION['desafio_participante_id']) ||
        !isset($_SESSION['desafio_inicio'])
    ) {
        http_response_code(403);
        exit('Partida não encontrada.');
    }

    // Confirma que os 180 segundos já passaram.
    if (time() - (int) $_SESSION['desafio_inicio'] < 180) {
        http_response_code(400);
        exit('O tempo da partida ainda não terminou.');
    }

    require_once __DIR__ . '/../app/config/conexao.php';

    $consultaFim = $conexao->prepare(
        "UPDATE partida
         SET status = 'finalizada',
             data_fim = NOW()
         WHERE id_partida = :partida
           AND status = 'em_andamento'
           AND EXISTS (
               SELECT 1
               FROM partida_participante
               WHERE partida_participante.id_partida = partida.id_partida
                 AND partida_participante.id_participante = :participante
                 AND partida_participante.id_aluno = :aluno
           )"
    );

    $consultaFim->execute([
        'partida' => (int) $_SESSION['desafio_partida_id'],
        'participante' => (int) $_SESSION['desafio_participante_id'],
        'aluno' => (int) $_SESSION['aluno_id']
    ]);

    if ($consultaFim->rowCount() > 0) {
        exit('Partida finalizada.');
    }

    // Se já estiver finalizada, não precisa atualizá-la novamente.
    $consultaStatus = $conexao->prepare(
        "SELECT status
         FROM partida
         WHERE id_partida = :partida"
    );

    $consultaStatus->execute([
        'partida' => (int) $_SESSION['desafio_partida_id']
    ]);

    if ($consultaStatus->fetchColumn() === 'finalizada') {
        exit('Partida já finalizada.');
    }

    http_response_code(403);
    exit('Não foi possível finalizar esta partida.');
}

// ABANDONAR CONTRA O GUARDIÃO
if ($pagina === 'abandonar_guardiao') {
    if (!empty($_SESSION['aluno_id']) && !empty($_SESSION['guardiao_partida_id'])) {
        require_once __DIR__ . '/../app/config/conexao.php';

        $idPartida = (int) $_SESSION['guardiao_partida_id'];
        $idAluno = (int) $_SESSION['aluno_id'];

        $consultaAbandono = $conexao->prepare(
            "UPDATE partida
             SET status = 'abandonada',
                 data_fim = NOW()
             WHERE id_partida = :partida
               AND status = 'em_andamento'
               AND EXISTS (
                   SELECT 1
                   FROM partida_participante
                   WHERE partida_participante.id_partida = partida.id_partida
                     AND partida_participante.id_aluno = :aluno
               )"
        );

        $consultaAbandono->execute([
            'partida' => $idPartida,
            'aluno' => $idAluno
        ]);
    }

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

    header('Location: ?pagina=batalha');
    exit;
}

// ABANDONAR DUPLA LOCAL
if ($pagina === 'abandonar_dupla') {
    if (!empty($_SESSION['aluno_id']) && !empty($_SESSION['dupla_partida_id'])) {
        require_once __DIR__ . '/../app/config/conexao.php';

        $idPartida = (int) $_SESSION['dupla_partida_id'];
        $idAluno = (int) $_SESSION['aluno_id'];

        $consultaAbandono = $conexao->prepare(
            "UPDATE partida
             SET status = 'abandonada',
                 data_fim = NOW()
             WHERE id_partida = :partida
               AND status = 'em_andamento'
               AND EXISTS (
                   SELECT 1
                   FROM partida_participante
                   WHERE partida_participante.id_partida = partida.id_partida
                     AND partida_participante.id_aluno = :aluno
               )"
        );

        $consultaAbandono->execute([
            'partida' => $idPartida,
            'aluno' => $idAluno
        ]);
    }

    unset(
        $_SESSION['dupla_partida_id'],
        $_SESSION['dupla_participante1_id'],
        $_SESSION['dupla_participante2_id'],
        $_SESSION['dupla_jogador2_nome'],
        $_SESSION['dupla_turno_atual'],
        $_SESSION['dupla_rodada'],
        $_SESSION['dupla_dificuldade'],
        $_SESSION['dupla_questao_atual'],
        $_SESSION['dupla_acertos_p1'],
        $_SESSION['dupla_erros_p1'],
        $_SESSION['dupla_xp_p1'],
        $_SESSION['dupla_acertos_p2'],
        $_SESSION['dupla_erros_p2'],
        $_SESSION['dupla_xp_p2']
    );

    header('Location: ?pagina=batalha');
    exit;
}

// ABANDONAR POÇÕES MÁGICAS
if ($pagina === 'abandonar_pocoes') {
    if (!empty($_SESSION['aluno_id']) && !empty($_SESSION['pocoes_partida_id'])) {
        require_once __DIR__ . '/../app/config/conexao.php';

        $idPartida = (int) $_SESSION['pocoes_partida_id'];
        $idAluno = (int) $_SESSION['aluno_id'];

        $consultaAbandono = $conexao->prepare(
            "UPDATE partida
             SET status = 'abandonada',
                 data_fim = NOW()
             WHERE id_partida = :partida
               AND status = 'em_andamento'
               AND EXISTS (
                   SELECT 1
                   FROM partida_participante
                   WHERE partida_participante.id_partida = partida.id_partida
                     AND partida_participante.id_aluno = :aluno
               )"
        );

        $consultaAbandono->execute([
            'partida' => $idPartida,
            'aluno' => $idAluno
        ]);
    }

    unset(
        $_SESSION['pocoes_partida_id'],
        $_SESSION['pocoes_participante_id'],
        $_SESSION['pocoes_dificuldade'],
        $_SESSION['pocoes_questoes'],
        $_SESSION['pocoes_xp'],
        $_SESSION['pocoes_acertos'],
        $_SESSION['pocoes_erros']
    );

    header('Location: ?pagina=home#jogos');
    exit;
}

// PROCESSAR FORMULÁRIOS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($pagina === 'cadastro') {
        require __DIR__ . '/../app/controllers/CadastroController.php';
    }

    if ($pagina === 'login') {
        require __DIR__ . '/../app/controllers/loginController.php';
    }
}

// EXIBIR PÁGINA
include __DIR__ . '/../app/views/layout/header.php';

include __DIR__ . '/../app/views/pages/' . $pagina . '.php';

include __DIR__ . '/../app/views/layout/footer.php';
