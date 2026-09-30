<?php
require_once __DIR__ . "/../includes/auth.php";
exigirDepartamento(["Qualidade"]);
require_once __DIR__ . "/../config/database.php";

$pdo = getConexao();
$mensagem = null;

$nomesMeses = [1=>"Janeiro",2=>"Fevereiro",3=>"Março",4=>"Abril",5=>"Maio",6=>"Junho",7=>"Julho",8=>"Agosto",9=>"Setembro",10=>"Outubro",11=>"Novembro",12=>"Dezembro"];

$anoAtualServidor = (int) date("Y");
$anoMinimo = $anoAtualServidor - 5;
$anoMaximo = max(2050, $anoAtualServidor + 25);

$anoSelecionado = (int) ($_GET["ano"] ?? $anoAtualServidor);
$mesSelecionadoNum = (int) ($_GET["mes_num"] ?? date("n"));
$diaPreSelecionado = $_GET["dia"] ?? date("Y-m-d");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["salvar_status"])) {
        $data = $_POST["data"];
        $status = $_POST["status"];
        $observacao = $_POST["observacao"] !== "" ? $_POST["observacao"] : null;

        // Sempre insere uma linha nova (nunca sobrescreve): preserva o histórico de observações.
        $stmt = $pdo->prepare("INSERT INTO status_qualidade_dia (data, status, observacao, usuario_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$data, $status, $observacao, $_SESSION["usuario_id"]]);

        if ($status === "grave") {
            $stmt = $pdo->prepare("INSERT INTO desvios_qualidade (data, descricao_desvio, acao_tomada, como_evitar, usuario_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$data, $_POST["desvio"], $_POST["acao_tomada"], $_POST["como_evitar"], $_SESSION["usuario_id"]]);
        }

        registrarLog("qualidade", "Lançou o status de qualidade de um dia do mês");
        $mensagem = "Status do dia salvo.";
        $anoSelecionado = (int) substr($data, 0, 4);
        $mesSelecionadoNum = (int) substr($data, 5, 2);
    } elseif (isset($_POST["atualizar_status_historico"])) {
        // Correção de um lançamento específico do histórico (ex.: erro de digitação).
        $observacao = $_POST["observacao"] !== "" ? $_POST["observacao"] : null;
        $stmt = $pdo->prepare("UPDATE status_qualidade_dia SET status = ?, observacao = ? WHERE id = ?");
        $stmt->execute([$_POST["status"], $observacao, $_POST["id"]]);
        registrarLog("qualidade", "Editou um lançamento do histórico de status");
        $mensagem = "Lançamento do histórico atualizado.";
    } elseif (isset($_POST["excluir_status_historico"])) {
        $pdo->prepare("DELETE FROM status_qualidade_dia WHERE id = ?")->execute([$_POST["id"]]);
        registrarLog("qualidade", "Excluiu um lançamento do histórico de status");
        $mensagem = "Lançamento do histórico excluído.";
    } elseif (isset($_POST["atualizar_desvio"])) {
        $stmt = $pdo->prepare("UPDATE desvios_qualidade SET descricao_desvio = ?, acao_tomada = ?, como_evitar = ? WHERE id = ?");
        $stmt->execute([$_POST["descricao_desvio"], $_POST["acao_tomada"], $_POST["como_evitar"], $_POST["id"]]);

        // A observação mora no lançamento de status do mesmo dia (não no desvio) — se o campo veio
        // preenchido no formulário de edição, atualiza o registro de status mais recente daquele dia.
        if (isset($_POST["observacao_do_dia"]) && $_POST["status_id_do_dia"] !== "") {
            $observacaoDia = $_POST["observacao_do_dia"] !== "" ? $_POST["observacao_do_dia"] : null;
            $pdo->prepare("UPDATE status_qualidade_dia SET observacao = ? WHERE id = ?")
                ->execute([$observacaoDia, $_POST["status_id_do_dia"]]);
        }

        registrarLog("qualidade", "Editou um desvio de qualidade");
        $mensagem = "Desvio atualizado.";
    } elseif (isset($_POST["excluir_desvio"])) {
        $pdo->prepare("DELETE FROM desvios_qualidade WHERE id = ?")->execute([$_POST["id"]]);
        registrarLog("qualidade", "Excluiu um desvio de qualidade");
        $mensagem = "Desvio excluído.";
    }
}

$mesSelecionado = sprintf("%04d-%02d", $anoSelecionado, $mesSelecionadoNum);
$diasNoMes = (int) date("t", strtotime($mesSelecionado . "-01"));

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
$stmt->execute([$mesSelecionado . "-%"]);
$statusPorDia = [];
foreach ($stmt->fetchAll() as $linha) {
    $statusPorDia[(int) date("j", strtotime($linha["data"]))] = $linha["status"];
}

$stmt = $pdo->prepare("SELECT sq.*, u.nome AS usuario_nome FROM status_qualidade_dia sq JOIN usuarios u ON u.id = sq.usuario_id WHERE sq.data LIKE ? ORDER BY sq.data DESC, sq.criado_em DESC");
$stmt->execute([$mesSelecionado . "-%"]);
$historicoStatus = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM desvios_qualidade WHERE data LIKE ? ORDER BY data DESC");
$stmt->execute([$mesSelecionado . "-%"]);
$desvios = $stmt->fetchAll();

$historicoEmEdicao = null;
if (!empty($_GET["editar_status"])) {
    $stmt = $pdo->prepare("SELECT * FROM status_qualidade_dia WHERE id = ?");
    $stmt->execute([$_GET["editar_status"]]);
    $historicoEmEdicao = $stmt->fetch();
}

$desvioEmEdicao = null;
$statusDoDiaDoDesvio = null;
if (!empty($_GET["editar_desvio"])) {
    $stmt = $pdo->prepare("SELECT * FROM desvios_qualidade WHERE id = ?");
    $stmt->execute([$_GET["editar_desvio"]]);
    $desvioEmEdicao = $stmt->fetch();

    if ($desvioEmEdicao) {
        $stmt = $pdo->prepare("SELECT * FROM status_qualidade_dia WHERE data = ? ORDER BY criado_em DESC LIMIT 1");
        $stmt->execute([$desvioEmEdicao["data"]]);
        $statusDoDiaDoDesvio = $stmt->fetch();
    }
}

$tituloPagina = "Status da qualidade";
require_once __DIR__ . "/../includes/layout_admin_topo.php";
?>
<h1>Status da qualidade</h1>
<?php if ($mensagem): ?><p style="color:var(--verde-ok)"><?= htmlspecialchars($mensagem) ?></p><?php endif; ?>

<form method="get" style="margin-bottom:16px;display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap;">
  <div>
    <label>Mês</label><br>
    <select name="mes_num" onchange="this.form.submit()">
      <?php for ($m = 1; $m <= 12; $m++): ?>
        <option value="<?= $m ?>" <?= $m === $mesSelecionadoNum ? "selected" : "" ?>><?= $nomesMeses[$m] ?></option>
      <?php endfor; ?>
    </select>
  </div>
  <div>
    <label>Ano</label><br>
    <select name="ano" onchange="this.form.submit()">
      <?php for ($a = $anoMinimo; $a <= $anoMaximo; $a++): ?>
        <option value="<?= $a ?>" <?= $a === $anoSelecionado ? "selected" : "" ?>><?= $a ?></option>
      <?php endfor; ?>
    </select>
  </div>
</form>

<div class="piramide-admin">
  <?php for ($dia = 1; $dia <= $diasNoMes; $dia++): $status = $statusPorDia[$dia] ?? "vazio"; $classe = $status === "vazio" ? "" : " status-dia-" . $status; ?>
    <span class="dia-piramide<?= $classe ?>"><?= $dia ?></span>
  <?php endfor; ?>
</div>
<p style="font-size:12px;color:#777;">Em branco = ainda sem registro &middot; Verde = sem problema &middot; Amarelo = resolvido com ação imediata &middot; Vermelho = problema grave</p>

<form class="formulario" method="post" id="form-status" style="margin-top:24px;">
  <label>Dia</label>
  <input type="date" name="data" value="<?= htmlspecialchars($diaPreSelecionado) ?>" required>
  <label>Status</label>
  <select name="status" id="campo-status" onchange="alternarCamposStatus()">
    <option value="ok">Sem problema de qualidade</option>
    <option value="atencao">Problema resolvido com ação imediata</option>
    <option value="grave">Problema grave</option>
  </select>
  <div id="bloco-observacao">
    <label>Observação (opcional)</label>
    <textarea name="observacao"></textarea>
  </div>
  <div id="bloco-desvio" style="display:none;">
    <label>Desvio</label>
    <textarea name="desvio"></textarea>
    <label>Ação tomada</label>
    <textarea name="acao_tomada"></textarea>
    <label>Como evitar reincidência</label>
    <textarea name="como_evitar"></textarea>
  </div>
  <button type="submit" name="salvar_status" value="1">Salvar</button>
</form>

<script>
function alternarCamposStatus() {
  var status = document.getElementById("campo-status").value;
  document.getElementById("bloco-desvio").style.display = status === "grave" ? "block" : "none";
}
</script>

<h2 style="margin-top:32px;">Histórico do mês</h2>
<p style="font-size:12px;color:#777;">Cada lançamento fica registrado — nada é sobrescrito, mesmo que o mesmo dia seja atualizado mais de uma vez. Use Editar só para corrigir um erro de digitação.</p>

<?php if ($historicoEmEdicao): ?>
<form class="formulario" method="post" style="margin-bottom:16px;">
  <input type="hidden" name="id" value="<?= $historicoEmEdicao["id"] ?>">
  <label>Lançamento de <?= date("d/m/Y H:i", strtotime($historicoEmEdicao["criado_em"])) ?> (<?= date("d/m/Y", strtotime($historicoEmEdicao["data"])) ?>)</label>
  <select name="status">
    <option value="ok" <?= $historicoEmEdicao["status"] === "ok" ? "selected" : "" ?>>Sem problema de qualidade</option>
    <option value="atencao" <?= $historicoEmEdicao["status"] === "atencao" ? "selected" : "" ?>>Problema resolvido com ação imediata</option>
    <option value="grave" <?= $historicoEmEdicao["status"] === "grave" ? "selected" : "" ?>>Problema grave</option>
  </select>
  <label>Observação</label>
  <textarea name="observacao"><?= htmlspecialchars($historicoEmEdicao["observacao"] ?? "") ?></textarea>
  <button type="submit" name="atualizar_status_historico" value="1">Salvar edição</button>
</form>
<?php endif; ?>

<table class="tabela-simples">
  <tr><th>Data</th><th>Status</th><th>Observação</th><th>Lançado por</th><th>Quando</th><th>Ações</th></tr>
  <?php foreach ($historicoStatus as $h): ?>
  <tr>
    <td><?= date("d/m/Y", strtotime($h["data"])) ?></td>
    <td><span class="status-pill status-<?= $h["status"] === "ok" ? "encerrado" : ($h["status"] === "atencao" ? "em_andamento" : "aberto") ?>"><?= $h["status"] ?></span></td>
    <td><?= $h["observacao"] ? htmlspecialchars($h["observacao"]) : "—" ?></td>
    <td><?= htmlspecialchars($h["usuario_nome"]) ?></td>
    <td><?= date("d/m/Y H:i", strtotime($h["criado_em"])) ?></td>
    <td class="acoes-linha">
      <a href="?editar_status=<?= $h["id"] ?>&mes_num=<?= $mesSelecionadoNum ?>&ano=<?= $anoSelecionado ?>">Editar</a>
      <form method="post" onsubmit="return confirm(&quot;Excluir este lançamento do histórico? Essa ação não pode ser desfeita.&quot;);" style="display:inline;">
        <input type="hidden" name="id" value="<?= $h["id"] ?>">
        <button type="submit" name="excluir_status_historico" value="1" class="botao-link-perigo">Excluir</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$historicoStatus): ?><tr><td colspan="6">Nenhum registro neste mês.</td></tr><?php endif; ?>
</table>

<h2 style="margin-top:32px;">Desvios registrados no mês</h2>

<?php if ($desvioEmEdicao): ?>
<form class="formulario" method="post" style="margin-bottom:16px;">
  <input type="hidden" name="id" value="<?= $desvioEmEdicao["id"] ?>">
  <?php if ($statusDoDiaDoDesvio): ?>
    <input type="hidden" name="status_id_do_dia" value="<?= $statusDoDiaDoDesvio["id"] ?>">
  <?php else: ?>
    <input type="hidden" name="status_id_do_dia" value="">
  <?php endif; ?>
  <label>Desvio (<?= date("d/m/Y", strtotime($desvioEmEdicao["data"])) ?>)</label>
  <textarea name="descricao_desvio"><?= htmlspecialchars($desvioEmEdicao["descricao_desvio"]) ?></textarea>
  <label>Ação tomada</label>
  <textarea name="acao_tomada"><?= htmlspecialchars($desvioEmEdicao["acao_tomada"]) ?></textarea>
  <label>Como evitar reincidência</label>
  <textarea name="como_evitar"><?= htmlspecialchars($desvioEmEdicao["como_evitar"]) ?></textarea>
  <label>Observação do dia<?= $statusDoDiaDoDesvio ? "" : " (nenhum lançamento de status encontrado para essa data — preencher aqui não vai salvar em lugar nenhum)" ?></label>
  <textarea name="observacao_do_dia" <?= $statusDoDiaDoDesvio ? "" : "disabled" ?>><?= $statusDoDiaDoDesvio ? htmlspecialchars($statusDoDiaDoDesvio["observacao"] ?? "") : "" ?></textarea>
  <button type="submit" name="atualizar_desvio" value="1">Salvar edição</button>
</form>
<?php endif; ?>

<table class="tabela-simples">
  <tr><th>Data</th><th>Desvio</th><th>Ação tomada</th><th>Como evitar reincidência</th><th>Ações</th></tr>
  <?php foreach ($desvios as $d): ?>
  <tr>
    <td><?= date("d/m/Y", strtotime($d["data"])) ?></td>
    <td><?= htmlspecialchars($d["descricao_desvio"]) ?></td>
    <td><?= htmlspecialchars($d["acao_tomada"]) ?></td>
    <td><?= htmlspecialchars($d["como_evitar"]) ?></td>
    <td class="acoes-linha">
      <a href="?editar_desvio=<?= $d["id"] ?>&mes_num=<?= $mesSelecionadoNum ?>&ano=<?= $anoSelecionado ?>">Editar</a>
      <form method="post" onsubmit="return confirm(&quot;Excluir este desvio? Essa ação não pode ser desfeita.&quot;);" style="display:inline;">
        <input type="hidden" name="id" value="<?= $d["id"] ?>">
        <button type="submit" name="excluir_desvio" value="1" class="botao-link-perigo">Excluir</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$desvios): ?><tr><td colspan="5">Nenhum desvio neste mês.</td></tr><?php endif; ?>
</table>
<?php require_once __DIR__ . "/../includes/layout_admin_rodape.php"; ?>
