<?php

namespace App\Services\Instagram;

use RuntimeException;

/** Falha ao falar com a API do Instagram. A mensagem é segura para mostrar na tela (sem token). */
class InstagramErro extends RuntimeException {}
