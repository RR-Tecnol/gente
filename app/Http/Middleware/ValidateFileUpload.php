<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ValidateFileUpload
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $allowedMimes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // docx
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // xlsx
        ];

        $suspiciousExtensions = ['php', 'asp', 'js', 'sh'];

        foreach ($request->allFiles() as $file) {
            // Verificar tamanho máximo 10MB via $f->getSize()
            if ($file->getSize() > 10 * 1024 * 1024) {
                return response()->json(['error' => 'Arquivo excede o tamanho máximo de 10MB.'], 422);
            }

            // Verificar MIME real via mime_content_type()
            $mime = mime_content_type($file->getRealPath());
            if (!in_array($mime, $allowedMimes)) {
                return response()->json(['error' => 'Formato não permitido. MIME detectado: ' . $mime], 422);
            }

            // Verificar extensão dupla suspeita
            $originalName = strtolower($file->getClientOriginalName());
            $parts = explode('.', $originalName);
            if (count($parts) > 2) {
                foreach ($suspiciousExtensions as $suspicious) {
                    if (in_array($suspicious, $parts)) {
                        $lastExt = end($parts);
                        if ($suspicious !== $lastExt) {
                            return response()->json(['error' => 'Arquivo com extensão dupla suspeita.'], 422);
                        }
                    }
                }
            }
        }

        return $next($request);
    }
}
