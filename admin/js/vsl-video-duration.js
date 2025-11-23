/**
 * Script para obter a duração do vídeo do YouTube usando a YouTube IFrame API
 */
(function ($) {
  "use strict";

  // Variáveis globais
  let player = null;
  let ytApiLoaded = false;

  // Função chamada quando a API do YouTube é carregada
  window.onYouTubeIframeAPIReady = function () {
    ytApiLoaded = true;
    console.log("YouTube API carregada com sucesso");

    // Verificar se já temos um valor de URL do YouTube nos metadados
    checkExistingVideoUrl();
  };

  // Função para verificar se já existe uma URL do YouTube e processar
  function checkExistingVideoUrl() {
    const youtubeUrl = $("#vsl_youtube_url").val();
    if (youtubeUrl) {
      processYouTubeUrl(youtubeUrl);
    }
  }

  // Função para processar a URL do YouTube e extrair o ID
  function processYouTubeUrl(url) {
    if (!url) return;

    const videoId = extractYoutubeId(url);
    if (!videoId) return;

    console.log("ID do vídeo:", videoId);
    createYouTubePlayer(videoId);
  }

  // Função para extrair o ID do vídeo do YouTube da URL
  function extractYoutubeId(url) {
    const pattern =
      /(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/;
    const matches = url.match(pattern);

    return matches ? matches[1] : null;
  }

  // Função para criar o player do YouTube (oculto)
  function createYouTubePlayer(videoId) {
    // Verificar se o container já existe, caso contrário, criar
    if ($("#yt-player-container").length === 0) {
      $("body").append(
        '<div id="yt-player-container" style="position:absolute; left:-9999px;"></div>'
      );
    }

    // Limpar o container
    $("#yt-player-container").empty();

    // Adicionar o elemento para o player
    $("#yt-player-container").html('<div id="yt-player-hidden"></div>');

    // Destruir player anterior se existir
    if (player) {
      player.destroy();
      player = null;
    }

    // Criar novo player
    if (ytApiLoaded) {
      player = new YT.Player("yt-player-hidden", {
        height: "1",
        width: "1",
        videoId: videoId,
        events: {
          onReady: onPlayerReady,
          onError: onPlayerError,
        },
      });
    } else {
      console.log("YouTube API ainda não está carregada. Aguardando...");
      setTimeout(function () {
        createYouTubePlayer(videoId);
      }, 1000);
    }
  }

  // Quando o player estiver pronto
  function onPlayerReady(event) {
    const duration = event.target.getDuration();
    const postId = $("#post_ID").val();

    console.log("Duração obtida:", duration, "segundos");

    // Enviar a duração via AJAX
    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "vsl_update_video_duration",
        post_id: postId,
        duration: duration,
        nonce: vsl_duration_nonce,
      },
      success: function (response) {
        console.log("Duração do vídeo atualizada:", response);
        $("#vsl-video-duration").text(formatDuration(duration));
        $("#vsl-video-duration-container").show();
      },
      error: function (xhr, status, error) {
        console.error("Erro ao atualizar duração:", error);
      },
    });
  }

  // Quando ocorrer um erro no player
  function onPlayerError(event) {
    console.error("Erro no player do YouTube:", event.data);
    $("#vsl-video-duration-container").hide();
  }

  // Formatar a duração para exibição (HH:MM:SS)
  function formatDuration(seconds) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);

    let formattedTime = "";

    if (hours > 0) {
      formattedTime += hours + ":";
      formattedTime += (minutes < 10 ? "0" : "") + minutes + ":";
    } else {
      formattedTime += minutes + ":";
    }

    formattedTime += (secs < 10 ? "0" : "") + secs;

    return formattedTime;
  }

  // Inicializar quando o documento estiver pronto
  $(document).ready(function () {
    // Carregar a API do YouTube
    const tag = document.createElement("script");
    tag.src = "https://www.youtube.com/iframe_api";
    const firstScriptTag = document.getElementsByTagName("script")[0];
    firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

    // Adicionar evento para quando a URL for alterada
    $("#vsl_youtube_url").on("change input", function () {
      const url = $(this).val();
      processYouTubeUrl(url);
    });
  });
})(jQuery);
