<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StorePengajuanMagangRequest extends FormRequest
{
    /**
     * Authorization is handled by the `mahasiswa` auth guard on the route.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Preserve the controller's original behavior: on validation failure,
     * redirect back with the errors + input AND flash the generic
     * "Terdapat kesalahan..." error message.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Terdapat kesalahan dalam pengisian form. Silakan periksa kembali.')
        );
    }

    /**
     * Validation rules for a pengajuan magang submission.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cv' => 'required|file|mimes:pdf|max:2048',
            'transkrip_nilai' => 'required|file|mimes:pdf|max:2048',
            'portfolio' => 'nullable|file|mimes:pdf|max:2048',
        ];
    }

    /**
     * Custom validation messages (kept verbatim from the controller).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cv.required' => 'CV wajib diupload.',
            'cv.file' => 'CV harus berupa file.',
            'cv.mimes' => 'CV harus berupa file PDF.',
            'cv.max' => 'Ukuran file CV maksimal 2 MB.',
            'transkrip_nilai.required' => 'Transkrip nilai wajib diupload.',
            'transkrip_nilai.file' => 'Transkrip nilai harus berupa file.',
            'transkrip_nilai.mimes' => 'Transkrip nilai harus berupa file PDF.',
            'transkrip_nilai.max' => 'Ukuran file transkrip maksimal 2 MB.',
            'portfolio.file' => 'Portofolio harus berupa file.',
            'portfolio.mimes' => 'Portofolio harus berupa file PDF.',
            'portfolio.max' => 'Ukuran file portofolio maksimal 2 MB.',
        ];
    }
}
