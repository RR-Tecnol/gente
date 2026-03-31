<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LayoutConsignatariaController extends Controller
{
    public function index($id)
    {
        return response()->json(['ok' => true]);
    }

    public function store(Request $request, $id)
    {
        return response()->json(['ok' => true]);
    }

    public function update(Request $request, $id, $lid)
    {
        return response()->json(['ok' => true]);
    }
}
