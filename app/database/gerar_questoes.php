<?php

require_once "../config/conexao.php";

echo "<h1>Gerador de questões - Numéria</h1>";


// ========================================
// CONFIGURAÇÃO
// ========================================

// Quantidade de questões por dificuldade
$quantidadePorDificuldade =2.5;


// ========================================
// FUNÇÃO PARA CRIAR UMA QUESTÃO
// ========================================

function criarQuestao(
    $conexao,
    $jogo,
    $conteudo,
    $dificuldade,
    $numero1,
    $numero2,
    $operacao
) {

    // Calcula a resposta correta
    if ($operacao == "+") {

        $respostaCorreta = $numero1 + $numero2;

    } else {

        $respostaCorreta = $numero1 - $numero2;

    }


    // Cria o enunciado
    $enunciado = "Quanto é $numero1 $operacao $numero2?";


    // Cria a explicação
    $explicacao =
        "Ao realizar $numero1 $operacao $numero2, obtemos $respostaCorreta.";


    // ========================================
    // INSERE A QUESTÃO
    // ========================================

    $sql = "INSERT INTO questao (
                id_jogo,
                id_conteudo,
                id_dificuldade,
                enunciado,
                explicacao_curta,
                mecanica
            )
            VALUES (
                :jogo,
                :conteudo,
                :dificuldade,
                :enunciado,
                :explicacao,
                NULL
            )";

    $stmt = $conexao->prepare($sql);

    $stmt->execute([
        ":jogo" => $jogo,
        ":conteudo" => $conteudo,
        ":dificuldade" => $dificuldade,
        ":enunciado" => $enunciado,
        ":explicacao" => $explicacao
    ]);


    // Pega o ID da questão criada
    $idQuestao = $conexao->lastInsertId();


    // ========================================
    // CRIA AS ALTERNATIVAS
    // ========================================

    $alternativas = [
        $respostaCorreta,
        $respostaCorreta + 1,
        $respostaCorreta - 1,
        $respostaCorreta + 2
    ];


    // Embaralha as alternativas
    shuffle($alternativas);


    // ========================================
    // SALVA AS ALTERNATIVAS
    // ========================================

    foreach ($alternativas as $alternativa) {

        $correta = ($alternativa == $respostaCorreta) ? 1 : 0;


        $sqlAlternativa = "INSERT INTO alternativa (
                                id_questao,
                                texto,
                                correta
                            )
                            VALUES (
                                :questao,
                                :texto,
                                :correta
                            )";


        $stmtAlternativa = $conexao->prepare($sqlAlternativa);


        $stmtAlternativa->execute([
            ":questao" => $idQuestao,
            ":texto" => $alternativa,
            ":correta" => $correta
        ]);
    }


    echo "<p>✔ $enunciado → $respostaCorreta</p>";
}


// ========================================
// GERAÇÃO DAS QUESTÕES
// ========================================


// ----------------------------------------
// ADIÇÃO
// ----------------------------------------

echo "<h2>➕ Adição</h2>";


// FÁCIL
for ($i = 0; $i < $quantidadePorDificuldade; $i++) {

    $numero1 = rand(1, 20);
    $numero2 = rand(1, 20);

    criarQuestao(
        $conexao,
        1, // Batalha na Floresta
        1, // Adição
        1, // Fácil
        $numero1,
        $numero2,
        "+"
    );
}


// MÉDIO
for ($i = 0; $i < $quantidadePorDificuldade; $i++) {

    $numero1 = rand(10, 100);
    $numero2 = rand(10, 100);

    criarQuestao(
        $conexao,
        1,
        1,
        2, // Médio
        $numero1,
        $numero2,
        "+"
    );
}


// DIFÍCIL
for ($i = 0; $i < $quantidadePorDificuldade; $i++) {

    $numero1 = rand(100, 1000);
    $numero2 = rand(100, 1000);

    criarQuestao(
        $conexao,
        1,
        1,
        3, // Difícil
        $numero1,
        $numero2,
        "+"
    );
}


// ----------------------------------------
// SUBTRAÇÃO
// ----------------------------------------

echo "<h2>➖ Subtração</h2>";


// FÁCIL
for ($i = 0; $i < $quantidadePorDificuldade; $i++) {

    $numero1 = rand(5, 20);
    $numero2 = rand(1, $numero1);

    criarQuestao(
        $conexao,
        1,
        2, // Subtração
        1, // Fácil
        $numero1,
        $numero2,
        "-"
    );
}


// MÉDIO
for ($i = 0; $i < $quantidadePorDificuldade; $i++) {

    $numero1 = rand(20, 100);
    $numero2 = rand(10, $numero1);

    criarQuestao(
        $conexao,
        1,
        2,
        2, // Médio
        $numero1,
        $numero2,
        "-"
    );
}


// DIFÍCIL
for ($i = 0; $i < $quantidadePorDificuldade; $i++) {

    $numero1 = rand(100, 1000);
    $numero2 = rand(50, $numero1);

    criarQuestao(
        $conexao,
        1,
        2,
        3, // Difícil
        $numero1,
        $numero2,
        "-"
    );
}


echo "<h2>🎉 Geração concluída!</h2>";