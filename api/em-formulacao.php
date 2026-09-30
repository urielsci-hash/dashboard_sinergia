<?php
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../includes/auth.php";
exigirLogin();
header("Content-Type: application/json; charset=utf-8");

$pdo = getConexao();
$hoje = date("Y-m-d");

$stmt = $pdo->prepare("SELECT * FROM producao_diaria WHERE data = ?");
$stmt->execute([$hoje]);
$producao = $stmt->fetch() ?: null;

$tanques = [];
if ($producao) {
    $stmt = $pdo->prepare("SELECT tanque, produto, valor_litros FROM producao_tanques WHERE producao_diaria_id = ?");
    $stmt->execute([$producao["id"]]);
    $tanques = $stmt->fetchAll();
}

echo json_encode([
    "producao" => $producao,
    "producao_tanques" => $tanques,
], JSON_UNESCAPED_UNICODE);
