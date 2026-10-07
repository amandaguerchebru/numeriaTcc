<?php

$host = "localhost";
$porta = "3307";
$banco = "numeria";
$usuario = "root";
$senha = "";

try {

    $conexao = new PDO(
        "mysql:host=$host;port=$porta;dbname=$banco;charset=utf8mb4",
        $usuario,
        $senha
    );

    // Faz o PDO mostrar erros de banco de forma clara
    $conexao->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

} catch (PDOException $e) {

    die("Erro na conexão com o banco: " . $e->getMessage());

}