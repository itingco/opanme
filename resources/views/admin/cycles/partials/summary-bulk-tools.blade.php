@if(in_array($cycle->status, [\App\Models\StockOpnameCycle::STATUS_OPEN, \App\Models\StockOpnameCycle::STATUS_CLOSED], true))
<details class="summary-bulk-details" id="summaryBulkOverride" @if($errors->has('bulk_data') || $errors->has('bulk_file')) open @endif>
    <summary class="btn">Override Massal</summary>
    <div class="summary-bulk-body">
        <h3>Override Qty secara massal</h3>
        <p>Upload Excel <strong>.xlsx</strong> atau paste 3 kolom: <strong>Item Code, Qty Override, Remarks</strong>. Maksimal <strong>2.000 barang</strong> dalam satu batch.</p>
        @if($errors->has('bulk_data'))<div class="alert danger">{{ $errors->first('bulk_data') }}</div>@endif
        @if($errors->has('bulk_file'))<div class="alert danger">{{ $errors->first('bulk_file') }}</div>@endif
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.cycles.summary.bulk-override', $cycle) }}" onsubmit="return confirm('Simpan override massal? Data lama untuk item sama akan diperbarui.');">
            @csrf
                <fieldset style="border:1px solid #cbd5e1;border-radius:8px;padding:12px">
                    <legend><strong>Metode Override</strong></legend>
                    <label style="display:flex;align-items:center;gap:8px"><input type="radio" name="override_mode" value="add" checked required> <span><strong>Tambah Qty</strong> — jumlahkan dengan override sebelumnya</span></label>
                    <label style="display:flex;align-items:center;gap:8px"><input type="radio" name="override_mode" value="replace"> <span><strong>Ganti Qty</strong> — timpa override sebelumnya</span></label>
                    <small>Contoh: override lama 10, input 5 = 15 jika memilih Tambah. Scan fisik asli tidak berubah.</small>
                </fieldset>
            <label><strong>Warehouse tujuan</strong>
                <select name="bulk_warehouse_id" required>
                    <option value="">Pilih warehouse</option>
                    @foreach($cycle->warehouses as $wh)
                        <option value="{{ $wh->id }}" @selected((int)old('bulk_warehouse_id', $warehouseId) === (int)$wh->id)>{{ $wh->warehouse_code }} - {{ $wh->warehouse_name }}</option>
                    @endforeach
                </select>
            </label>
            <label><strong>Upload Excel (.xlsx)</strong>
                <input type="file" name="bulk_file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" id="summary-bulk-file">
            </label>
            <small>Kolom A: Item Code; B: Qty Override; C: Remarks. Format Item Code sebagai Text agar 00072 tetap 00072. Bisa juga QR <code>00072~5</code> pada kolom A dan Remarks pada kolom C.</small>
            <div class="summary-bulk-separator">ATAU</div>
            <label><strong>Paste data Excel</strong>
                <textarea name="bulk_data" rows="5" placeholder="ItemCode&#9;Qty Override&#9;Remarks&#10;00072&#9;2&#9;Penyesuaian stok&#10;00092&#9;11&#9;Hasil recount">{{ old('bulk_data') }}</textarea>
            </label>
            <small>Batas 2.000 item per penyimpanan. Seluruh batch divalidasi sebelum disimpan. Jika satu kode invalid, tidak ada yang disimpan.</small>
            <button class="btn primary" type="submit">Simpan Semua Override</button>
        </form>
    </div>
</details>
@endif
<style>
.summary-bulk-details{position:relative;display:inline-block}.summary-bulk-details>summary{cursor:pointer;list-style:none;white-space:nowrap}.summary-bulk-details>summary::-webkit-details-marker{display:none}.summary-bulk-body{position:absolute;z-index:30;right:0;top:calc(100% + 9px);width:min(570px,85vw);padding:20px;background:#fff;border:1px solid #d9e2ee;border-radius:12px;box-shadow:0 14px 34px #142e4633}.summary-bulk-body h3{margin:0 0 8px}.summary-bulk-body p{font-size:13px;line-height:1.5}.summary-bulk-body form{display:grid;gap:12px}.summary-bulk-body label{display:grid;gap:6px}.summary-bulk-body select,.summary-bulk-body textarea,.summary-bulk-body input[type=file]{width:100%;padding:10px;border:1px solid #ccd7e6;border-radius:7px;font:inherit}.summary-bulk-body textarea{font-family:monospace;font-size:13px;white-space:pre;overflow:auto}.summary-bulk-separator{text-align:center;font-weight:700;font-size:12px;color:#68768b}
</style>
