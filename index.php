<?php
require_once __DIR__ . "/config/config.php";
require_once __DIR__ . "/includes/auth.php";
exigirLogin();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Painel de Qualidade — Sinergia Agro</title>
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
</head>
<body class="painel">
  <header class="cabecalho">
    <div class="marca">
      <img src="assets/img/logo.png" alt="Sinergia Agro" class="logo">
      <div>
        <h1>Painel de Qualidade</h1>
        <p>Gestão à vista &middot; Serra Negra/SP</p>
      </div>
    </div>
    <div class="area-topo-direita">
      <div class="info-topo">
        <div id="relogio" class="relogio">--:--:--</div>
        <div id="clima" class="clima">Carregando clima...</div>
      </div>
      <div class="caixa-noticias-cabecalho">
        <h2 class="titulo-cartao">Últimas notícias</h2>
        <div id="noticias-lista" class="noticias-lista"></div>
      </div>
    </div>
  </header>

  <main class="conteudo">
    <section class="cartao cartao-status">
      <h2 class="titulo-cartao">Status da qualidade</h2>
      <p id="mes-ano-piramide" class="mes-ano-piramide"></p>
      <div id="piramide" class="piramide"></div>
      <div class="legenda-status">
        <span><i class="bolinha bolinha-ok"></i> Sem problema</span>
        <span><i class="bolinha bolinha-atencao"></i> Resolvido</span>
        <span><i class="bolinha bolinha-grave"></i> Grave</span>
      </div>
      <h2 class="titulo-cartao" style="margin-top:16px;">Desvios de qualidade</h2>
      <div id="lista-desvios" class="lista-desvios"></div>
    </section>

    <section class="cartao cartao-indicador"><canvas id="grafico-indicador-0"></canvas></section>
    <section class="cartao cartao-indicador"><canvas id="grafico-indicador-1"></canvas></section>
    <section class="cartao cartao-mural" id="mural">
      <h2 class="titulo-cartao">Mural</h2>
      <div id="mural-item" class="mural-item">Carregando comunicados...</div>
    </section>

    <section class="cartao cartao-indicador"><canvas id="grafico-indicador-2"></canvas></section>
    <section class="cartao cartao-producao">
      <h2 class="titulo-cartao">Em formulação</h2>
      <div id="producao-conteudo">Carregando...</div>
    </section>
  </main>

  <script src="assets/js/dashboard.js"></script>
</body>
</html>
