@extends('warehouse_sampling.admin.show')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/warehouse-sampling-enhanced.css') }}?v=20261016">
@endpush
@push('scripts')
<script>
window.wsBulkValidationUrl = @json(route('warehouse.admin.items.validate-bulk', $period));
window.wsPeriodIsDraft = @json($period->isDraft());
</script>
<script src="{{ asset('assets/js/warehouse-sampling-enhanced.js') }}?v=20261016" defer></script>
@endpush
