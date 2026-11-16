<section class="panel" style="margin-top:16px" id="manual-photo-count-panel">
  <h2>Barang tanpa Barcode — Input Manual + Foto</h2>
  <p>Masukkan kode barang yang ada di snapshot warehouse cycle ini. Qty wajib dalam satuan terkecil; setiap input wajib melampirkan foto barang sebagai bukti.</p>
  <form id="manual-photo-count-form" enctype="multipart/form-data" action="{{ (request()->routeIs('warehouse.legacy.*') ? route('warehouse.legacy.manual-photo.store', $session) : route('checker.manual-photo.store', $session)) }}" style="display:grid;gap:12px">
    @csrf
    <label>Kode Barang <input name="item_code" maxlength="100" autocomplete="off" required placeholder="Contoh: AAC1408"></label>
    <label>Qty fisik (smallest UOM) <input name="qty" type="number" min="0.0001" step="0.0001" required placeholder="Contoh: 5"></label>
    <label>Foto barang (wajib, JPG/PNG/WebP, maks 5 MB) <input name="photo" type="file" accept="image/jpeg,image/png,image/webp" capture="environment" required></label>
    <img id="manual-photo-preview" alt="Preview bukti foto" hidden style="max-height:220px;object-fit:contain;border-radius:8px;max-width:100%">
    <p id="manual-photo-count-status" role="status" aria-live="polite"></p>
    <button class="btn primary" type="submit">Simpan Qty + Foto</button>
  </form>
</section>
<script>
(() => {
 const form = document.getElementById('manual-photo-count-form');
 if (!form) return;
 const status = document.getElementById('manual-photo-count-status');
 const button = form.querySelector('button[type=submit]');
 const picker = form.querySelector('[name=photo]');
 const preview = document.getElementById('manual-photo-preview');
 let photoUrl = null;
 picker.addEventListener('change', () => {
   if(photoUrl) URL.revokeObjectURL(photoUrl);
   photoUrl = picker.files[0] ? URL.createObjectURL(picker.files[0]) : null;
   preview.hidden = !photoUrl;
   if(photoUrl) preview.src = photoUrl;
 });
 form.addEventListener('submit', async e => {
   e.preventDefault();
   if(button.disabled) return;
   button.disabled = true;
   status.textContent = 'Menyimpan qty dan foto...';
   try {
     const res = await fetch(form.action, {method:'POST',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},body:new FormData(form)});
     const data = await res.json();
     if (!res.ok || !data.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Gagal menyimpan');
     status.textContent = 'Berhasil: ' + data.item_code + ' · Qty ' + data.qty + '. Foto tersimpan.';
     form.reset(); preview.hidden = true;
     if(photoUrl) URL.revokeObjectURL(photoUrl); photoUrl = null;
   } catch(err) { status.textContent = 'Gagal: ' + (err.message || 'Terjadi kesalahan'); }
   finally { button.disabled = false; }
 });
})();
</script>
