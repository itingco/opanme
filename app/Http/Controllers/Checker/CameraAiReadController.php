<?php

namespace App\Http\Controllers\Checker;

use App\Http\Controllers\Controller;
use App\Models\CheckerAssignment;
use App\Models\ScanSession;
use App\Models\StockOpnameCycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class CameraAiReadController extends Controller
{
    public function __invoke(Request $request, ScanSession $session): JsonResponse
    {
        $data = $request->validate(['image' => ['required', 'string', 'max:8500000']]);
        $session->loadMissing('cycle');
        abort_unless((int)$session->checker_id === (int)$request->user()->id && ! $session->ended_at && $session->cycle->status === StockOpnameCycle::STATUS_OPEN, 403);
        abort_unless(CheckerAssignment::query()->where('cycle_id', $session->cycle_id)
            ->where('warehouse_id', $session->warehouse_id)->where('checker_id', $request->user()->id)->exists(), 403);
        if (! preg_match('#^data:image/jpeg;base64,([A-Za-z0-9+/=]+)$#D', $data['image'], $m)) {
            throw ValidationException::withMessages(['image' => 'Foto kamera tidak valid.']);
        }
        $bytes = base64_decode($m[1], true);
        if ($bytes === false || strlen($bytes) > 5 * 1024 * 1024 || ! @getimagesizefromstring($bytes)) {
            throw ValidationException::withMessages(['image' => 'Gambar tidak valid atau terlalu besar.']);
        }
        $key = trim((string)config('opanme_camera_ai.key', ''));
        if ($key === '') {
            return response()->json(['ok' => false, 'message' => 'AI belum dikonfigurasi. Atur OPENAI_API_KEY di .env server.'], 503);
        }
        $model = (string)config('opanme_camera_ai.model', 'gpt-4.1-mini');
        $response = Http::withToken($key)->acceptJson()->timeout(22)->connectTimeout(5)
            ->post('https://api.openai.com/v1/responses', [
                'model' => $model,
                'store' => false,
                'max_output_tokens' => 100,
                'input' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'input_text', 'text' => 'Transcribe exactly ONE barcode/QR value visible in this photo. Include ~QTY suffix if present. Do not guess missing characters. Reply with only the barcode characters or UNCLEAR. Do not add explanations.'],
                        ['type' => 'input_image', 'image_url' => $data['image'], 'detail' => 'high'],
                    ],
                ]],
            ]);
        if (! $response->successful()) {
            return response()->json(['ok' => false, 'message' => 'AI gagal mengolah gambar. Coba lagi atau input kode barang.'], 502);
        }
        $output = collect($response->json('output', []))
            ->flatMap(fn ($item) => $item['content'] ?? [])
            ->filter(fn ($piece) => ($piece['type'] ?? '') === 'output_text')
            ->pluck('text')->implode(' ');
        $value = trim($output, " \n\r\t\"'`");
        // Reject explanations, multiple lines, or potentially invented strings.
        if ($value === '' || strtoupper($value) === 'UNCLEAR' || ! preg_match('/^[A-Za-z0-9._~\/-]{1,150}$/D', $value)) {
            return response()->json(['ok' => false, 'message' => 'AI tidak yakin dengan nilai barcode. Silakan ulangi foto atau input kode barang.'], 422);
        }
        if (preg_match('~^https?://~i', $value)) {
            return response()->json(['ok' => false, 'message' => 'QR website diabaikan.'], 422);
        }
        // This is a candidate only. Frontend MUST get explicit confirmation and call ScanService.
        return response()->json(['ok' => true, 'value' => $value, 'requires_confirmation' => true]);
    }
}
