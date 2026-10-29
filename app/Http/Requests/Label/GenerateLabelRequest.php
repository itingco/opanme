<?php

namespace App\Http\Requests\Label;

use Illuminate\Foundation\Http\FormRequest;

class GenerateLabelRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'item_code' => trim((string) $this->input('item_code')),
            'label_size' => $this->input('label_size', '10x10'),
        ]);
    }

    public function rules(): array
    {
        return [
            'item_code' => ['required', 'string', 'max:100'],
            'barcode_id' => ['nullable', 'integer', 'min:1'],
            'qty' => ['required', 'integer', 'min:1', 'max:999999'],
            'label_size' => ['required', 'in:10x10,4x3'],
        ];
    }

    public function messages(): array
    {
        return [
            'item_code.required' => 'Pilih kode barang terlebih dahulu.',
            'barcode_id.integer' => 'Pilihan satuan/barcode tidak valid.',
            'qty.required' => 'Qty wajib diisi.',
            'qty.integer' => 'Qty harus angka bulat.',
            'qty.min' => 'Qty minimal 1.',
            'qty.max' => 'Qty maksimal 999999.',
            'label_size.in' => 'Pilih ukuran label 10x10 atau 4x3.',
        ];
    }
}
