<?php
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../includes/auth.php";
exigirLogin();
header("Content-Type: application/json; charset=utf-8");

$pdo = getConexao();
$mesAtual = date("Y-m");

// Status "atual" de cada dia = registro mais recente daquele dia (histórico preservado no banco).
$stmt = $pdo->prepare("
    SELECT s1.data, s1.status
    FROM status_qualidade_dia s1
    INNER JOIN (
        SELECT data, MAX(criado_em) AS max_criado
        FROM status_qualidade_dia
        WHERE data LIKE ?
        GROUP BY data
    ) s2 ON s1.data = s2.data AND s1.criado_em = s2.max_criado
");
$stmt->execute([$mesAtual . "-%"]);
$statusPorDia = [];
foreach ($stmt->fetchAll() as $linha) {
    $statusPorDia[(int) date("j", strtotime($linha["data"]))] = $linha["status"];
}

// Desvios do mês, em ordem cronológica (para a rotação seguir sempre a mesma sequência),
// já trazendo junto a observação mais recente lançada para aquele mesmo dia.
$stmt = $pdo->prepare("
    SELECT d.data, d.descricao_desvio, d.acao_tomada, d.como_evitar,
        (SELECT s.observacao FROM status_qualidade_dia s WHERE s.data = d.data ORDER BY s.criado_em DESC LIMIT 1) AS observacao
    FROM desvios_qualidade d
    WHERE d.data LIKE ?
    ORDER BY d.data ASC, d.id ASC
");
$stmt->execute([$mesAtual . "-%"]);
$desvios = $stmt->fetchAll();

echo json_encode([
    "status_dias" => $statusPorDia,
    "desvios" => $desvios,
], JSON_UNESCAPED_UNICODE);
