#!/usr/bin/env python3
"""Apply changes to an EXISTING checkout of itingco/opanme. Generates changed-files-only ZIP.
Usage: python APPLY_PATCH.py C:\\xampp82\\htdocs\\opanme
"""
from pathlib import Path
import sys, shutil, zipfile

root=Path(sys.argv[1]).resolve() if len(sys.argv)>1 else Path.cwd()
if not (root/'artisan').exists() or not (root/'app/Services/SampleCheckingService.php').exists():
    sys.exit('ERROR: Parameter harus folder root project Laravel opanme (berisi artisan).')
out=Path(__file__).resolve().parent
backup=out/'BACKUP_ORIGINAL'
changed=[]
def put(path, content):
    dst=root/path
    if dst.exists():
        old=dst.read_text(encoding='utf-8')
        if old==content: return
        bk=backup/path;bk.parent.mkdir(parents=True,exist_ok=True)
        if not bk.exists(): shutil.copy2(dst,bk)
    dst.parent.mkdir(parents=True,exist_ok=True)
    dst.write_text(content,encoding='utf-8')
    changed.append(path)
def modify(path, pairs):
    src=(root/path).read_text(encoding='utf-8')
    for before,after in pairs:
        if before not in src:
            raise RuntimeError(f'Anchor tidak ditemukan di {path}: {before[:75]}')
        src=src.replace(before,after,1)
    put(path,src)

modify('app/Services/SampleCheckingService.php',[
('use App\\Models\\SampleCycle;', 'use App\\Models\\SampleCycle;\nuse App\\Models\\UomRatio;'),
("        $systemQty = (string) ($stockRow['smallest_on_hand'] ?? '0');", """        $systemQty = (string) ($stockRow['smallest_on_hand'] ?? '0');
        $base = $this->catalog->findItemByCode($cycle->source_database, (string)$item['item_code']);
        $smallestUom = (string)($base['uom_code'] ?? $item['uom_code']);
        $ratio = 1.0;
        if ((int)($item['uom_level'] ?? 1) > 1) {
            $ratioRow = UomRatio::query()->where('item_code', (string)$item['item_code'])
                ->where('uom_code', (string)$item['uom_code'])->first();
            if (!$ratioRow || !is_numeric($ratioRow->ratio) || (float)$ratioRow->ratio <= 0) {
                throw ValidationException::withMessages(['barcode' => 'Ratio UOM barcode belum ada di Master Ratio.']);
            }
            $ratio = (float)$ratioRow->ratio;
        }"""),
("            'uom_code' => (string) $item['uom_code'],\n            'system_qty'", "            'uom_code' => (string) $item['uom_code'],\n            'ratio' => $ratio,\n            'smallest_uom_code' => $smallestUom,\n            'system_qty'"),
("            'uom_code' => $payload['uom_code'],\n            'system_qty'", "            'uom_code' => $payload['uom_code'],\n            'ratio' => $ratio,\n            'smallest_uom_code' => $smallestUom,\n            'system_qty'"),
])
p='app/Services/SampleCheckingService.php';s=(root/p).read_text()
a=s.index("        $systemQty = round((float) ($payload['system_qty'] ?? 0), 4);",s.index('public function confirm'))
b=s.index('        return DB::transaction(',a)
s=s[:a]+'''        $systemQty = round((float) ($payload['system_qty'] ?? 0), 4);
        $ratio = (float)($payload['ratio'] ?? 1);
        if (!is_finite($ratio) || $ratio <= 0 || $physicalQty === null || $physicalQty === ''
            || !is_numeric($physicalQty) || !is_finite((float)$physicalQty) || (float)$physicalQty < 0) {
            throw ValidationException::withMessages(['physical_qty' => 'Qty tidak valid.']);
        }
        $physical = round((float)$physicalQty * $ratio, 4);
        if (!is_finite($physical) || $physical > 1000000000000) {
            throw ValidationException::withMessages(['physical_qty' => 'Qty terlalu besar.']);
        }
        $result = abs($physical - $systemQty) <= 0.00005
            ? SampleCheck::RESULT_MATCH : SampleCheck::RESULT_MISMATCH;

'''+s[b:]
put(p,s)

modify('resources/views/gerai/scan.blade.php',[
('<div class="result-actions" id="sample-validation-actions">','<div class="result-actions" id="sample-validation-actions" hidden>'),
('<form id="sample-mismatch-form" class="mismatch-form sampling-mismatch-form" hidden>','<form id="sample-mismatch-form" class="mismatch-form sampling-mismatch-form">'),
('<strong>Masukkan stok fisik sebenarnya</strong>','<strong>Qty Fisik (bisa diedit setelah scan)</strong>'),
('Qty Fisik Sebenarnya\n                        <input','Qty Fisik - <span id="sample-input-uom">UOM</span>\n                        <input'),
('name="physical_qty"\n                               type="number"','name="physical_qty"\n                               value="1"\n                               type="number"'),
('<div class="sample-qty-difference"','<small id="sample-conversion-note">Konversi ke satuan terkecil otomatis.</small>\n                    <div class="sample-qty-difference"'),
('<button class="btn" type="button" id="sample-mismatch-cancel">Kembali</button>',''),
])
p='resources/views/gerai/scan.blade.php';put(p,(root/p).read_text().replace('?v=20260930camera2','?v=20261119qty'))

p='public/assets/js/sampling.js';s=(root/p).read_text()
for before,after in [
('const difference = Number(raw) - Number(active.system_qty || 0);','const difference = Number(raw) * Number(active.ratio || 1) - Number(active.system_qty || 0);'),
("document.getElementById('sample-uom').textContent=`UOM scan: ${data.uom_code}`;", "document.getElementById('sample-uom').textContent=`Stok sistem dalam ${data.smallest_uom_code || data.uom_code}`;\n        document.getElementById('sample-input-uom').textContent=data.uom_code;\n        document.getElementById('sample-conversion-note').textContent=`1 ${data.uom_code} = ${fmt(data.ratio || 1)} ${data.smallest_uom_code || data.uom_code}`;"),
('        showDecisionActions();\n        resultBox.hidden = false;', '        showMismatchForm();\n        resultBox.hidden = false;'),
("            const d=await post(confirmUrl,{token:active.token,result,physical_qty});", "            const d=await post(confirmUrl,{token:active.token,result,physical_qty});\n            result = d.result;"),
]:
    if before not in s:raise RuntimeError('JS anchor missing: '+before[:60])
    s=s.replace(before,after,1)
s=s.replace("if (qtyInput) qtyInput.value = '';", "if (qtyInput) qtyInput.value = '1';",2)
s=s.replace("if (result === 'MISMATCH' && (physical_qty === null || physical_qty === '' || !Number.isFinite(Number(physical_qty)) || Number(physical_qty) < 0))", "if (physical_qty === null || physical_qty === '' || !Number.isFinite(Number(physical_qty)) || Number(physical_qty) < 0)")
put(p,s)

modify('resources/views/admin/sampling/index.blade.php',[("@section('content')", "@section('content')\n@if(app('router')->has('cycle-audit.index'))\n<div class=\"panel\" style=\"margin-bottom:16px\"><strong>View Sampling & Cycle Finalisasi</strong><p>Riwayat CLOSED dan FINALIZED tersedia berdasarkan urutan waktu. Akses hanya baca.</p><a class=\"btn\" href=\"{{ route('cycle-audit.index') }}\">Lihat Riwayat Cycle Final</a></div>\n@endif")])

modify('app/Models/SampleCycle.php',[("    public const STATUS_CLOSED = 'CLOSED';", "    public const STATUS_CLOSED = 'CLOSED';\n    public const STATUS_CANCELLED = 'BATAL';")])

cancel='''    /** Only the creator may cancel a Gerai cycle. Never remove scan history. */
    public function cancel(Request $request, SampleCycle $sampleCycle): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isAdminGerai(), 403);
        DB::transaction(function () use ($request, $sampleCycle) {
            $cycle = SampleCycle::query()->lockForUpdate()->findOrFail($sampleCycle->id);
            abort_unless($cycle->cycle_type === SampleCycle::TYPE_GERAI, 404);
            abort_unless((int)$cycle->created_by === (int)$request->user()->id, 403);
            if (!in_array($cycle->status, [SampleCycle::STATUS_OPEN, SampleCycle::STATUS_CLOSED], true)) {
                throw ValidationException::withMessages(['cycle'=>'Cycle tidak dapat dibatalkan.']);
            }
            $cycle->update([
                'status' => SampleCycle::STATUS_CANCELLED,
                'closed_at' => $cycle->closed_at ?? now(),
                'notes' => trim((string)$cycle->notes.' [BATAL '.now()->format('Y-m-d H:i:s').' oleh '.$request->user()->username.']'),
            ]);
        });
        return redirect()->route('gerai.admin.home')->with('success','Cycle dibatalkan. Riwayat scan tetap tersimpan.');
    }

'''
p='app/Http/Controllers/Gerai/SamplingController.php';s=(root/p).read_text();anchor='    private function adminAssignments(User $user, ErpCatalogService $erp): array';assert anchor in s;s=s.replace(anchor,cancel+anchor,1);put(p,s)

modify('resources/views/gerai/home.blade.php',[
('<th class="num">Item Dicek</th></tr>','<th class="num">Item Dicek</th><th>Aksi</th></tr>'),
('<td class="num">{{ number_format($cycle->checks_count) }}</td>', '''<td class="num">{{ number_format($cycle->checks_count) }}</td>
            <td>@if((int)$cycle->created_by === (int)auth()->id() && in_array($cycle->status,['OPEN','CLOSED']))
              <form method="POST" action="{{ route('gerai.admin.cancel',$cycle) }}" onsubmit="return confirm('Batalkan cycle? History scan tetap tersimpan.')">@csrf @method('PATCH')<button class="btn small" type="submit">Batalkan</button></form>
            @endif</td>'''),
('<tr><td colspan="7" class="empty">Belum ada cycle Gerai.</td></tr>','<tr><td colspan="8" class="empty">Belum ada cycle Gerai.</td></tr>'),
])

p='routes/web.php';s=(root/p).read_text()
a="        Route::post('/cycles', [SamplingController::class, 'create'])->name('create');";assert a in s
s=s.replace(a,a+"\n        Route::patch('/cycles/{sampleCycle}/cancel', [SamplingController::class, 'cancel'])->name('cancel');",1)
needle="Route::middleware(['auth', 'role:CHECKER_GERAI,GERAI'])";assert needle in s
routes='''Route::middleware(['auth', 'role:ADMIN,ADMIN_GERAI,CYCLE_AUDITOR'])
    ->prefix('cycle-audit')->name('cycle-audit.')->group(function () {
        Route::get('/', [\\App\\Http\\Controllers\\Gerai\\CycleAuditController::class, 'index'])->name('index');
        Route::get('/gerai/{sampleCycle}', [\\App\\Http\\Controllers\\Gerai\\CycleAuditController::class, 'gerai'])->name('gerai');
        Route::get('/final/{cycle}', [\\App\\Http\\Controllers\\Gerai\\CycleAuditController::class, 'final'])->name('final');
    });

'''
s=s.replace(needle,routes+needle,1);put(p,s)

modify('config/menu_permissions.php',[
("        'gerai_reports' =>", "        'gerai_audit' => ['label' => 'Audit Cycle Read Only', 'route' => 'cycle-audit.index', 'match' => ['cycle-audit.*']],\n        'gerai_reports' =>"),
("        'CHECKER_GUDANG',\n    ],", "        'CHECKER_GUDANG',\n        'CYCLE_AUDITOR',\n    ],"),
("'gerai_admin','gerai_reports','warehouse_admin'", "'gerai_admin','gerai_reports','gerai_audit','warehouse_admin'"),
("'ADMIN_GERAI' => ['gerai_admin','gerai_reports'],", "'ADMIN_GERAI' => ['gerai_admin','gerai_reports','gerai_audit'],\n        'CYCLE_AUDITOR' => ['gerai_audit'],"),
])
modify('app/Http/Controllers/Admin/UserController.php',[("        User::ROLE_CHECKER_GUDANG,\n    ];", "        User::ROLE_CHECKER_GUDANG,\n        'CYCLE_AUDITOR',\n    ];")])
modify('resources/views/admin/users/index.blade.php',[("        'CHECKER_GUDANG' => 'CHECKER GUDANG',", "        'CHECKER_GUDANG' => 'CHECKER GUDANG',\n        'CYCLE_AUDITOR' => 'AUDITOR CYCLE (READ ONLY)',")])
modify('resources/views/layouts/app.blade.php',[("        'CHECKER_GUDANG' => 'CHECKER GUDANG',", "        'CHECKER_GUDANG' => 'CHECKER GUDANG',\n        'CYCLE_AUDITOR' => 'AUDITOR CYCLE',"),("'gerai_reports'=>'≡',", "'gerai_reports'=>'≡','gerai_audit'=>'◉',")])

put('app/Http/Controllers/Gerai/CycleAuditController.php',r'''<?php
namespace App\Http\Controllers\Gerai;

use App\Http\Controllers\Controller;
use App\Models\SampleCycle;
use App\Models\StockOpnameCycle;
use App\Services\CycleSummaryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CycleAuditController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $gerai = SampleCycle::query()->where('cycle_type', SampleCycle::TYPE_GERAI)
            ->where('status', SampleCycle::STATUS_CLOSED);
        $final = StockOpnameCycle::query()->where('status', StockOpnameCycle::STATUS_FINALIZED);
        if ($user->isAdminGerai()) {
            $pairs = $user->warehouseAssignments()->get(['source_database','erp_warehouse_id']);
            $gerai->where(function ($q) use ($pairs) {
                if ($pairs->isEmpty()) $q->whereRaw('1=0');
                foreach ($pairs as $p) $q->orWhere(fn($one) => $one
                    ->where('source_database',$p->source_database)->where('erp_warehouse_id',$p->erp_warehouse_id));
            });
            $final->where(function ($q) use ($pairs) {
                if ($pairs->isEmpty()) $q->whereRaw('1=0');
                foreach ($pairs as $p) $q->orWhere(fn($one) => $one
                    ->where('source_database',$p->source_database)
                    ->whereHas('warehouses', fn($w) => $w->where('erp_warehouse_id',$p->erp_warehouse_id)));
            });
        }
        $a = $gerai->withCount(['checks as selisih_count'=>fn($q)=>$q->where('result','MISMATCH')])
            ->orderByDesc('closed_at')->limit(150)->get()->map(fn($c)=>[
            'type'=>'gerai','id'=>$c->id,'date'=>$c->closed_at,'number'=>$c->cycle_no,
            'database'=>$c->source_database,'warehouse'=>$c->warehouse_code,'variance'=>$c->selisih_count]);
        $b = $final->orderByDesc('finalized_at')->limit(150)->get()->map(fn($c)=>[
            'type'=>'final','id'=>$c->id,'date'=>$c->finalized_at,'number'=>$c->cycle_no,
            'database'=>$c->source_database,'warehouse'=>'Multi Gudang','variance'=>null]);
        $history = $a->concat($b)->sortByDesc('date')->take(150)->values();
        return view('gerai.cycle-audit',compact('history'));
    }
    public function gerai(Request $request, SampleCycle $sampleCycle): View
    {
        abort_unless($sampleCycle->cycle_type === SampleCycle::TYPE_GERAI && $sampleCycle->status === SampleCycle::STATUS_CLOSED,404);
        if ($request->user()->isAdminGerai()) abort_unless($request->user()->hasWarehouseAssignment($sampleCycle->source_database,(int)$sampleCycle->erp_warehouse_id),403);
        $rows = $sampleCycle->checks()->where('result','MISMATCH')->orderBy('scanned_at')->paginate(60);
        return view('gerai.cycle-audit-detail',['kind'=>'gerai','number'=>$sampleCycle->cycle_no,'rows'=>$rows]);
    }
    public function final(Request $request, StockOpnameCycle $cycle, CycleSummaryService $summary)
    {
        abort_unless($cycle->status === StockOpnameCycle::STATUS_FINALIZED,404);
        $warehouseId = $request->integer('warehouse_id') ?: null;
        if ($warehouseId) abort_unless($cycle->warehouses()->whereKey($warehouseId)->exists(),404);
        if ($request->user()->isAdminGerai()) {
            $ids = $request->user()->warehouseAssignments()->where('source_database',$cycle->source_database)->pluck('erp_warehouse_id')->all();
            $allowed = $cycle->warehouses()->whereIn('erp_warehouse_id',$ids)->pluck('id')->all();
            abort_if(!$allowed,403);
            if (!$warehouseId) return redirect()->route('cycle-audit.final',['cycle'=>$cycle->id,'warehouse_id'=>$allowed[0]]);
            abort_unless(in_array($warehouseId,$allowed),403);
        }
        $rows = $summary->query($cycle,$warehouseId,true)->paginate(60)->withQueryString();
        return view('gerai.cycle-audit-detail',['kind'=>'final','number'=>$cycle->cycle_no,'rows'=>$rows]);
    }
}
''')
put('resources/views/gerai/cycle-audit.blade.php',r'''@extends('layouts.app')
@section('title','Audit Cycle Read Only')
@section('content')
<div class="page-heading"><div><h1>Riwayat Sampling & Cycle Final</h1><p>Hanya sampling CLOSED dan cycle opname FINALIZED. Urut waktu terbaru.</p></div></div>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>Waktu</th><th>Jenis</th><th>Cycle</th><th>Database</th><th>Gudang</th><th>Selisih</th><th>Detail</th></tr></thead><tbody>
@forelse($history as $r)
<tr><td>{{ $r['date']?->format('d/m/Y H:i:s') }}</td><td>{{ $r['type']==='gerai' ? 'Sampling CLOSED' : 'Opname FINALIZED' }}</td><td>{{ $r['number'] }}</td><td>{{ $r['database'] }}</td><td>{{ $r['warehouse'] }}</td><td>{{ $r['variance'] ?? '-' }}</td><td><a class="btn small" href="{{ $r['type']==='gerai' ? route('cycle-audit.gerai',$r['id']) : route('cycle-audit.final',$r['id']) }}">Lihat Selisih</a></td></tr>
@empty <tr><td colspan="7">Tidak ada cycle yang selesai.</td></tr> @endforelse
</tbody></table></div></section>
@endsection
''')
put('resources/views/gerai/cycle-audit-detail.blade.php',r'''@extends('layouts.app')
@section('title','Detail Audit Selisih')
@section('content')
<div class="page-heading"><div><h1>Selisih {{ $number }}</h1><p>Read only. Tidak tersedia override atau perubahan data.</p></div><a class="btn" href="{{ route('cycle-audit.index') }}">Kembali</a></div>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>Waktu / Gudang</th><th>Kode</th><th>Nama Barang</th><th>Stok Sistem</th><th>Fisik Final</th><th>Selisih</th></tr></thead><tbody>
@forelse($rows as $r)
@if($kind === 'gerai')
<tr><td>{{ $r->scanned_at?->format('d/m/Y H:i:s') }} · {{ $r->warehouse_code }}</td><td>{{ $r->item_code }}</td><td>{{ $r->item_name }}</td><td>{{ number_format((float)$r->system_qty,4) }}</td><td>{{ number_format((float)$r->physical_qty,4) }}</td><td>{{ number_format((float)$r->physical_qty-(float)$r->system_qty,4) }}</td></tr>
@else
<tr><td>{{ $r->warehouse_code }}</td><td>{{ $r->item_code }}</td><td>{{ $r->item_name }}</td><td>{{ number_format((float)$r->opening_system_qty,4) }}</td><td>{{ number_format((float)$r->final_physical_qty,4) }}</td><td>{{ number_format((float)$r->variance,4) }}</td></tr>
@endif
@empty <tr><td colspan="6">Tidak ada item selisih.</td></tr> @endforelse
</tbody></table></div>{{ $rows->links() }}</section>
@endsection
''')
with zipfile.ZipFile(out/'CHANGED_FILES_ONLY.zip','w',zipfile.ZIP_DEFLATED) as z:
    for p in changed:z.write(root/p,p)
(out/'FILE_YANG_BERUBAH.txt').write_text('\n'.join(changed)+'\n',encoding='utf-8')
print('SUCCESS:', len(changed),'files. ZIP:',out/'CHANGED_FILES_ONLY.zip')
