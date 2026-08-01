<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:5000'],
            'conversation_id' => ['nullable', 'integer', 'exists:conversations,id'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*.mime_type' => ['required_with:attachments', 'string', 'in:image/jpeg,image/png,image/gif,image/webp,image/bmp,image/tiff,application/pdf,text/plain,text/csv'],
            'attachments.*.data' => ['required_with:attachments', 'string'],
            'attachments.*.name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Message is required.',
            'message.max' => 'Message must not exceed 5000 characters.',
            'attachments.max' => 'You may attach up to 5 files per message.',
            'attachments.*.mime_type.in' => 'File type not allowed.',
        ];
    }
}
