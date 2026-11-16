@extends('checker.scan')
@push('styles')
<style>
.legacy-photo-action{margin:12px 0;display:flex;flex-wrap:wrap;gap:8px}
.legacy-photo-dialog[hidden]{display:none!important}
.legacy-photo-dialog{position:fixed;inset:0;z-index:1200;background:rgba(10,20,35,.76);display:flex;align-items:center;justify-content:center;padding:16px}
.legacy-photo-card{background:#fff;color:#18212e;border-radius:16px;padding:18px;width:min(100%,460px);max-height:92vh;overflow:auto;box-shadow:0 15px 40px rgba(0,0,0,.2)}
.legacy-photo-card img{width:100%;height:auto;max-height:220px;object-fit:contain;background:#111;border-radius:8px;margin:9px 0}
.legacy-photo-card label{display:block;margin:10px 0 5px;font-weight:600}
.legacy-photo-card input[type=text]{width:100%;padding:12px;border:1px solid #b5bdc8;border-radius:8px;font-size:18px}
.legacy-photo-status{font-size:13px;min-height:22px;margin:10px 0;overflow-wrap:anywhere}
.legacy-photo-buttons{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.legacy-photo-buttons .btn{flex:1;min-width:110px}
</style>
@endpush
@push('scripts')
<script>
// Replace scan endpoints before the shared app.js DOMContentLoaded handler runs.
window.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('[data-scanner]');
    if (root) {
        root.dataset.scanUrl = @json(route('warehouse.legacy.scan.store', $session));
        root.dataset.nonSystemUrl = @json(route('warehouse.legacy.scan.non-system', $session));
    }
    const relocate = document.querySelector('.change-location form');
    if (relocate) relocate.action = @json(route('warehouse.legacy.session.start', [$session->cycle, $session->warehouse->id]));
}, true);
</script>
<script src="{{ asset('assets/js/legacy-photo-scan.js') }}?v=20261116-v8" defer></script>
<script>
window.LEGACY_PHOTO_AI_URL = @json(route('warehouse.legacy.photo.read', $session));
</script>
@endpush

@section('content')
    @parent
    @include('checker.partials_manual_count')
@endsection
