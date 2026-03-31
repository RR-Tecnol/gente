<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ConsignatariaController extends Controller
{
    public function index()
    {
        return response()->json(['ok' => true]);
    }

    public function store(Request $request)
    {
        return response()->json(['ok' => true]);
    }

    public function show($id)
    {
        return response()->json(['ok' => true]);
    }

    public function update(Request $request, $id)
    {
        return response()->json(['ok' => true]);
    }

    public function toggleAtiva($id)
    {
        return response()->json(['ok' => true]);
    }
}
