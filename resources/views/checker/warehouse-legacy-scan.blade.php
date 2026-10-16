@extends('checker.scan')
@push('scripts')
<script>
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
@endpush
