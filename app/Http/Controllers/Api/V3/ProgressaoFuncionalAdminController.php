<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use App\Services\Progressao\ProgressaoFuncionalListagemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgressaoFuncionalAdminController extends Controller
{
    public function __construct(
        private ProgressaoFuncionalListagemService $listagem
    ) {}

    public function indexTodos(Request $request): JsonResponse
    {
        try {
            $page = (int) $request->query('page', 1);
            $perPage = (int) $request->query('per_page', 50);
            $busca = $request->query('busca');
            $setorRaw = $request->query('setor_id');
            $setorId = $setorRaw !== null && $setorRaw !== '' ? (int) $setorRaw : null;

            $data = $this->listagem->paginateTodos(
                $page,
                $perPage,
                is_string($busca) ? $busca : null,
                $setorId
            );

            return response()->json($data);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    }

    public function indexElegiveis(Request $request): JsonResponse
    {
        try {
            $page = (int) $request->query('page', 1);
            $perPage = (int) $request->query('per_page', 50);
            $busca = $request->query('busca');
            $setorRaw = $request->query('setor_id');
            $setorId = $setorRaw !== null && $setorRaw !== '' ? (int) $setorRaw : null;

            $data = $this->listagem->paginateElegiveis(
                $page,
                $perPage,
                is_string($busca) ? $busca : null,
                $setorId
            );

            return response()->json($data);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    }

    public function impacto(Request $request): JsonResponse
    {
        try {
            $payload = $this->listagem->impactoAgregado();
            $detPage = (int) $request->query('detalhes_page', 0);
            $detPer = (int) $request->query('detalhes_per_page', 0);
            if ($detPage > 0 && $detPer > 0) {
                $det = $this->listagem->impactoDetalhesPagina($detPage, $detPer);
                $payload['detalhes'] = $det['detalhes'];
                $payload['detalhes_meta'] = $det['meta'];
            }

            return response()->json($payload);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    }

    public function impactoDetalhes(Request $request): JsonResponse
    {
        try {
            $page = (int) $request->query('page', 1);
            $perPage = (int) $request->query('per_page', 50);
            $data = $this->listagem->impactoDetalhesPagina($page, $perPage);

            return response()->json($data);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    }
}
