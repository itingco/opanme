<?php

namespace App\Http\Controllers\Checker;

use App\Http\Controllers\Controller;
use App\Models\CheckerAssignment;
use App\Models\ScanSession;
use App\Models\StockOpnameCycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LegacyPhotoReadController extends Controller
{
    public function __invoke(Request $request, ScanSession $session): JsonResponse
    {
        $session->loadMissing('cycle');
        abort_unless(
            (int) $session->checker_id === (int) $request->user()->id
            && $session->ended_at === null
            && $session->cycle->status === StockOpnameCycle::STATUS_OPEN
            && CheckerAssignment::query()->where('cycle_id', $session->cycle_id)
                ->where('warehouse_id', $session->warehouse_id)
                ->where('checker_id', $request->user()->id)->exists(),
            403,
            'Sesi checker tidak aktif.'
        );

        $data = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:3072'],
        ]);

        $apiKey = trim((string) config('services.openai.key', env('OPENAI_API_KEY', '')));
        if ($apiKey === '') {
            return response()->json([
                'ok' => false,
                'message' => 'OCR/AI belum aktif. Isi OPENAI_API_KEY pada .env; barcode decoder lokal tetap bisa dipakai.',
            ], 503);
        }

        $photo = $data['photo'];
        $mime = $photo->getMimeType();
        $mime = in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) ? $mime : 'image/jpeg';
        $bytes = file_get_contents($photo->getRealPath());
        if ($bytes === false) {
            throw ValidationException::withMessages(['photo' => 'Foto gagal dibaca.']);
        }

        try {
            $result = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(18)
                ->connectTimeout(5)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.vision_model', env('OPENAI_VISION_MODEL', 'gpt-4.1-mini')),
                    'temperature' => 0,
                    'max_tokens' => 180,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [[
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => 'Baca ONLY kode barcode/QR atau angka/huruf yang tercetak tepat di bawah barcode dalam foto stok opname. Format QR dapat KODE~QTY. Jangan menebak karakter yang tidak terlihat. Jawab JSON hanya {"code":"...","confidence":"high|medium|low"}; jika tidak bisa dibaca gunakan code kosong. Jangan melakukan interpretasi jumlah kecuali tertulis eksplisit setelah ~.'],
                            ['type' => 'image_url', 'image_url' => [
                                'url' => 'data:'.$mime.';base64,'.base64_encode($bytes),
                                'detail' => 'high',
                            ]],
                        ],
                    ]],
                ]);
        } catch (\Throwable $exception) {
            Log::warning('Legacy photo read request failed', ['type' => get_class($exception)]);
            return response()->json(['ok' => false, 'message' => 'Layanan AI tidak merespons. Coba foto ulang atau input barcode manual.'], 503);
        }

        if (! $result->successful()) {
            Log::warning('Legacy photo read API unavailable', ['status' => $result->status()]);
            return response()->json(['ok' => false, 'message' => 'Layanan AI tidak tersedia (HTTP '.$result->status().').'], 503);
        }

        $raw = $result->json('choices.0.message.content');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $code = is_array($decoded) ? trim((string) ($decoded['code'] ?? '')) : '';
        // Never accept a sentence or an invented/extraneous payload as a scan code.
        if ($code === '' || strlen($code) > 150 || preg_match('/\s/u', $code)) {
            return response()->json(['ok' => false, 'message' => 'AI tidak bisa membaca kode dengan yakin. Foto ulang atau isi secara manual.'], 422);
        }

        return response()->json([
            'ok' => true,
            'code' => $code,
            'confidence' => in_array($decoded['confidence'] ?? '', ['high', 'medium', 'low'], true)
                ? $decoded['confidence'] : 'low',
            'needs_confirmation' => true,
            'source' => 'ai',
        ]);
    }
}
