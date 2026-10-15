<?php

namespace App\Http\Requests\Label;

use Illuminate\Foundation\Http\FormRequest;

class GenerateLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'item_code' => trim((string) $this->input('item_code')),
            // Pertahankan format 10x10 untuk pemanggil lama yang belum mengirim label_size.
            'label_size' => $this->input('label_size', '10x10'),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'item_code' => ['required', 'string', 'max:100'],
            'qty' => ['required', 'integer', 'min:1', 'max:999999'],
            'label_size' => ['required', 'in:10x10,4x3'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'item_code.required' => 'Kode barang wajib diisi.',
            'item_code.max' => 'Kode barang maksimal 100 karakter.',
            'qty.required' => 'Qty wajib diisi.',
            'qty.integer' => 'Qty harus berupa angka bulat.',
            'qty.min' => 'Qty minimal 1.',
            'qty.max' => 'Qty maksimal 999999.',
            'label_size.required' => 'Ukuran label wajib dipilih.',
            'label_size.in' => 'Pilih ukuran label 10 × 10 cm atau 4 × 3 cm.',
        ];
    }
}
