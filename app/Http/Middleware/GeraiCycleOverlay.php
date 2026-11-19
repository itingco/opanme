<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GeraiCycleOverlay
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (!$response instanceof \Illuminate\Http\Response || $response->headers->get('Content-Type') && !str_contains($response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }
        if (!$request->user() || !$response->isSuccessful()) return $response;
        $html = $response->getContent();
        if (!is_string($html) || !str_contains($html, '</body>')) return $response;
        if ($request->routeIs('admin.cycles.index')) {
            $script = '<script src="'.e(asset('assets/js/stock-cycle-cancel.js')).'?v=20261119b" data-options-url="'.e(route('admin.cycles.cancel-options')).'" data-cancel-prefix="'.e(url('/admin/cycle-cancel')).'"></script>';
            $html = str_replace('</body>', $script.'</body>', $html);
        }
        if ($request->routeIs('gerai.checker.scan')) {
            $html = str_replace('</body>', '<script src="'.e(asset('assets/js/gerai-cycle-overlay.js')).'?v=20261119"></script></body>', $html);
        }
        if ($request->routeIs('gerai.admin.home')) {
            $link = '<div class="panel" style="padding:14px;margin-bottom:16px"><a class="btn" href="'.e(route('cycle-audit.my-cycles')).'">Batalkan Cycle yang Saya Buat</a></div>';
            $html = str_replace('<div class="page-heading">', $link.'<div class="page-heading">', $html);
        }
        if ($request->routeIs('admin.sampling.index','gerai.admin.sampling.index')) {
            $link = '<div class="panel" style="padding:14px;margin-bottom:16px"><strong>Riwayat Cycle Selesai</strong> · <a class="btn" href="'.e(route('cycle-audit.index')).'">Lihat Sampling CLOSED dan Opname FINALIZED</a></div>';
            $html = str_replace('<main class="page-wrap', '<main class="page-wrap', $html);
            $html = str_replace('<div class="page-heading">', $link.'<div class="page-heading">', $html);
        }
        $response->setContent($html);
        return $response;
    }
}
