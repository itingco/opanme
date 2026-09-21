<?php

namespace App\Http\Controllers;

use App\Models\GoodTransferCheckHeader;
use App\Models\WarehouseCheckHeader;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $documentType = $request->input('document_type');
        $company = $request->filled('company')
            ? strtoupper((string) $request->company)
            : null;
        $status = $request->filled('status')
            ? strtoupper((string) $request->status)
            : null;

        $keyword = trim((string) (
            $request->input('document')
            ?? $request->input('invoice')
            ?? $request->input('mutation')
            ?? ''
        ));

        $combined = collect();

        if ($documentType !== 'good_transfer') {
            $query = WarehouseCheckHeader::query()
                ->with(['picker', 'checker'])
                ->withCount('scanErrors');

            if ($keyword !== '') {
                $query->where('invoice_number', 'like', '%' . $keyword . '%');
            }

            if ($company) {
                $query->where('company', $company);
            }

            if ($status) {
                $query->where('status', $status);
            }

            $invoiceRows = $query->get()->map(function (WarehouseCheckHeader $row) {
                return (object) [
                    'id' => $row->id,
                    'document_type' => 'invoice',
                    'document_type_label' => 'Invoice',
                    'document_number' => $row->invoice_number,
                    'company' => $row->company,
                    'context' => $row->customer_name ?: '-',
                    'picker_name' => optional($row->picker)->name ?: '-',
                    'checker_name' => optional($row->checker)->name ?: '-',
                    'started_at' => $row->started_at,
                    'completed_at' => $row->completed_at,
                    'scan_errors_count' => $row->scan_errors_count,
                    'status' => $row->status,
                ];
            });

            $combined = $combined->concat($invoiceRows);
        }

        if ($documentType !== 'invoice') {
            $query = GoodTransferCheckHeader::query()
                ->with(['picker', 'checker'])
                ->withCount('scanErrors');

            if ($keyword !== '') {
                $query->where('mutation_number', 'like', '%' . $keyword . '%');
            }

            if ($company) {
                $query->where('company', $company);
            }

            if ($status) {
                $query->where('status', $status);
            }

            $transferRows = $query->get()->map(function (GoodTransferCheckHeader $row) {
                $warehouseContext = trim(
                    ($row->source_warehouse_name ?: '-')
                    . ' → '
                    . ($row->destination_warehouse_name ?: '-')
                );

                return (object) [
                    'id' => $row->id,
                    'document_type' => 'good_transfer',
                    'document_type_label' => 'Good Transfer',
                    'document_number' => $row->mutation_number,
                    'company' => $row->company,
                    'context' => $warehouseContext,
                    'picker_name' => optional($row->picker)->name ?: '-',
                    'checker_name' => optional($row->checker)->name ?: '-',
                    'started_at' => $row->started_at,
                    'completed_at' => $row->completed_at,
                    'scan_errors_count' => $row->scan_errors_count,
                    'status' => $row->status,
                ];
            });

            $combined = $combined->concat($transferRows);
        }

        $combined = $combined
            ->sortByDesc(fn ($row) => $row->started_at?->getTimestamp() ?? 0)
            ->values();

        $perPage = 25;
        $page = LengthAwarePaginator::resolveCurrentPage();

        $rows = new LengthAwarePaginator(
            $combined->forPage($page, $perPage)->values(),
            $combined->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return view('dashboard', compact('rows'));
    }
}
