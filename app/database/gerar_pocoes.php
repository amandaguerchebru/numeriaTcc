<?php

require_once "../config/conexao.php";

echo "<h1>🧪 Gerador de questões - Poções Mágicas</h1>";


// ========================================
// CONFIGURAÇÃO
// ========================================

$quantidadePorDificuldade = 5;


// ========================================
// FUNÇÃO PARA VERIFICAR SE JÁ EXISTEM
// QUESTÕES DE UMA MECÂNICA
// ========================================

function mecanicaJaExiste($conexao, $mecanica)
{
    $sql = "SELECT COUNT(*)
            FROM questao
            WHERE mecanica = :mecanica";

    $stmt = $conexao->prepare($sql);

    $stmt->execute([
        ":mecanica" => $mecanica
    ]);

    return $stmt->fetchColumn() > 0;
}


// ========================================
// FUNÇÃO PARA CRIAR ALTERNATIVAS DE
// IDENTIFICAÇÃO
// ========================================

function gerarAlternativasFracao($numerador, $denominador)
{
    $correta = "$numerador/$denominador";

    $alternativas = [$correta];

    while (count($alternativas) < 4) {

        $novoDenominador = rand(2, 20);
        $novoNumerador = rand(1, $novoDenominador - 1);

        $novaFracao = "$novoNumerador/$novoDenominador";

        if (!in_array($novaFracao, $alternativas)) {
            $alternativas[] = $novaFracao;
        }
    }

    shuffle($alternativas);

    return $alternativas;
}


// ========================================
// FUNÇÃO PARA CRIAR QUESTÃO DE
// IDENTIFICAÇÃO DE FRAÇÃO
// ========================================

function criarQuestaoFracao(
    $conexao,
    $dificuldade,
    $numerador,
    $denominador
) {

    $respostaCorreta = "$numerador/$denominador";


    // ========================================
    // ENUNCIADO
    // ========================================

    $enunciado =
        "Uma poção foi dividida em $denominador partes iguais. "
        . "$numerador partes receberam essência lunar. "
        . "Qual fração da poção recebeu essência?";


    // ========================================
    // EXPLICAÇÃO
    // ========================================

    $explicacao =
        "O numerador $numerador representa as partes que receberam essência "
        . "e o denominador $denominador representa o total de partes iguais. "
        . "Por isso, a fração correta é $respostaCorreta.";


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
                2,
                3,
                :dificuldade,
                :enunciado,
                :explicacao,
                'identificacao_fracao'
            )";

    $stmt = $conexao->prepare($sql);

    $stmt->execute([
        ":dificuldade" => $dificuldade,
        ":enunciado" => $enunciado,
        ":explicacao" => $explicacao
    ]);


    $idQuestao = $conexao->lastInsertId();


    // ========================================
    // CRIA AS ALTERNATIVAS
    // ========================================

    $alternativas = gerarAlternativasFracao(
        $numerador,
        $denominador
    );


    foreach ($alternativas as $alternativa) {

        $correta =
            ($alternativa == $respostaCorreta) ? 1 : 0;


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

        $stmtAlternativa =
            $conexao->prepare($sqlAlternativa);

        $stmtAlternativa->execute([
            ":questao" => $idQuestao,
            ":texto" => $alternativa,
            ":correta" => $correta
        ]);
    }


    echo "<p>✔ $enunciado → $respostaCorreta</p>";
}


// ========================================
// FUNÇÃO PARA CRIAR QUESTÃO DE
// COMPARAÇÃO DE FRAÇÕES
// ========================================

function criarComparacaoFracao(
    $conexao,
    $dificuldade,
    $numerador1,
    $denominador1,
    $numerador2,
    $denominador2
) {

    // Multiplicação cruzada
    $produto1 = $numerador1 * $denominador2;
    $produto2 = $numerador2 * $denominador1;

    $fracao1 = "$numerador1/$denominador1";
    $fracao2 = "$numerador2/$denominador2";


    // ========================================
    // DESCOBRE QUAL É A MAIOR PROPORÇÃO
    // ========================================

    if ($produto1 > $produto2) {

        $nomePocaoMaior = "Poção da Lua";
        $respostaCorreta = "$nomePocaoMaior ($fracao1)";

    } else {

        $nomePocaoMaior = "Poção da Folha";
        $respostaCorreta = "$nomePocaoMaior ($fracao2)";
    }


    // ========================================
    // ENUNCIADO
    // ========================================

    $enunciado =
        "A Poção da Lua contém $fracao1 de essência e "
        . "a Poção da Folha contém $fracao2. "
        . "Qual delas possui a maior proporção de essência?";


    // ========================================
    // EXPLICAÇÃO
    // ========================================

    $explicacao =
        "Para comparar as frações, fazemos a multiplicação cruzada: "
        . "$numerador1 × $denominador2 = $produto1 e "
        . "$numerador2 × $denominador1 = $produto2. "
        . "Como $produto1 "
        . ($produto1 > $produto2 ? "é maior" : "é menor")
        . " que $produto2, a $nomePocaoMaior possui a maior proporção de essência.";


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
                2,
                3,
                :dificuldade,
                :enunciado,
                :explicacao,
                'comparacao_fracao'
            )";

    $stmt = $conexao->prepare($sql);

    $stmt->execute([
        ":dificuldade" => $dificuldade,
        ":enunciado" => $enunciado,
        ":explicacao" => $explicacao
    ]);


    $idQuestao = $conexao->lastInsertId();


    // ========================================
    // ALTERNATIVAS
    // ========================================

    $respostaErrada =
        ($nomePocaoMaior == "Poção da Lua")
        ? "Poção da Folha ($fracao2)"
        : "Poção da Lua ($fracao1)";


    $alternativas = [
        $respostaCorreta,
        $respostaErrada,
        "As duas têm a mesma proporção.",
        "Não é possível comparar as frações."
    ];


    shuffle($alternativas);


    // ========================================
    // SALVA AS ALTERNATIVAS
    // ========================================

    foreach ($alternativas as $alternativa) {

        $correta =
            ($alternativa == $respostaCorreta) ? 1 : 0;


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

        $stmtAlternativa =
            $conexao->prepare($sqlAlternativa);

        $stmtAlternativa->execute([
            ":questao" => $idQuestao,
            ":texto" => $alternativa,
            ":correta" => $correta
        ]);
    }


    echo "<p>✔ $enunciado → $respostaCorreta</p>";
}


// ==================================================
// 1️⃣ IDENTIFICAÇÃO DE FRAÇÕES
// ==================================================

echo "<h2>🔎 Identificação de frações</h2>";


if (mecanicaJaExiste($conexao, "identificacao_fracao")) {

    echo "<p>⚠️ As questões de identificação já foram geradas.</p>";

} else {

    // ----------------------------------------
    // FÁCIL
    // ----------------------------------------

    echo "<h3>🟢 Fácil</h3>";

    for ($i = 0; $i < $quantidadePorDificuldade; $i++) {

        $denominador = rand(2, 10);
        $numerador = rand(1, $denominador - 1);

        criarQuestaoFracao(
            $conexao,
            1,
            $numerador,
            $denominador
        );
    }


    // ----------------------------------------
    // MÉDIO
    // ----------------------------------------

    echo "<h3>🟡 Médio</h3>";

    for ($i = 0; $i < $quantidadePorDificuldade; $i++) {

        $denominador = rand(4, 15);
        $numerador = rand(2, $denominador - 1);

        criarQuestaoFracao(
            $conexao,
            2,
            $numerador,
            $denominador
        );
    }


    // ----------------------------------------
    // DIFÍCIL
    // ----------------------------------------

    echo "<h3>🔴 Difícil</h3>";

    for ($i = 0; $i < $quantidadePorDificuldade; $i++) {

        $denominador = rand(5, 20);
        $numerador = rand(3, $denominador - 1);

        criarQuestaoFracao(
            $conexao,
            3,
            $numerador,
            $denominador
        );
    }
}


// ==================================================
// 2️⃣ COMPARAÇÃO DE FRAÇÕES
// ==================================================

echo "<h2>⚖️ Comparação de frações</h2>";


if (mecanicaJaExiste($conexao, "comparacao_fracao")) {

    echo "<p>⚠️ As questões de comparação já foram geradas.</p>";

} else {

    // ----------------------------------------
    // FÁCIL
    // ----------------------------------------

    echo "<h3>🟢 Fácil</h3>";

    for ($i = 0; $i < $quantidadePorDificuldade; $i++) {

        $denominador = rand(3, 10);

        $numerador1 = rand(1, $denominador - 1);

        do {
            $numerador2 = rand(1, $denominador - 1);
        } while ($numerador1 == $numerador2);


        criarComparacaoFracao(
            $conexao,
            1,
            $numerador1,
            $denominador,
            $numerador2,
            $denominador
        );
    }


    // ----------------------------------------
    // MÉDIO
    // ----------------------------------------

    echo "<h3>🟡 Médio</h3>";

    for ($i = 0; $i < $quantidadePorDificuldade; $i++) {

        do {

            $denominador1 = rand(3, 12);
            $numerador1 = rand(1, $denominador1 - 1);

            $denominador2 = rand(3, 12);
            $numerador2 = rand(1, $denominador2 - 1);

        } while (
            $numerador1 * $denominador2
            ==
            $numerador2 * $denominador1
        );


        criarComparacaoFracao(
            $conexao,
            2,
            $numerador1,
            $denominador1,
            $numerador2,
            $denominador2
        );
    }


    // ----------------------------------------
    // DIFÍCIL
    // ----------------------------------------

    echo "<h3>🔴 Difícil</h3>";

    for ($i = 0; $i < $quantidadePorDificuldade; $i++) {

        do {

            $denominador1 = rand(4, 20);
            $numerador1 = rand(2, $denominador1 - 1);

            $denominador2 = rand(4, 20);
            $numerador2 = rand(2, $denominador2 - 1);

        } while (
            $numerador1 * $denominador2
            ==
            $numerador2 * $denominador1
        );


        criarComparacaoFracao(
            $conexao,
            3,
            $numerador1,
            $denominador1,
            $numerador2,
            $denominador2
        );
    }
}


// ========================================
// FINAL
// ========================================

echo "<h2>🎉 Geração concluída!</h2>";

echo "<p>
As questões já existentes não serão duplicadas ao atualizar a página.
</p>";