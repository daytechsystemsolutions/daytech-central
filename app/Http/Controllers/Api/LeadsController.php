<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Marca;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** O site conta à Central que chegou um lead vindo do Instagram. Só a origem: o lead em si vai para o CRM. */
class LeadsController extends Controller
{
    public function receber(Request $request, Marca $marca): JsonResponse
    {
        $dados = $request->validate([
            'fonte' => 'required|string|max:40',
            'meio' => 'nullable|string|max:40',
            'campanha' => 'nullable|string|max:100',
            'conteudo' => 'nullable|string|max:100',
        ]);
        $marca->leads()->create($dados + ['recebido_em' => now()]);

        return response()->json(['ok' => true], 201);
    }
}
