<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;

class CepController extends Controller
{
    public function service($cep)
    {
        $cep = preg_replace('/\D/', '', $cep); // só números mesmo
        $url = "https://viacep.com.br/ws/{$cep}/json/";

        // $response = Http::get($url);
        $response = Http::withOptions(['verify' => false])->get($url);

        if ($response->successful()) {
            return response()->json($response->json());
        }

        return response()->json(['error' => 'CEP não encontrado'], 404);
    }
}
