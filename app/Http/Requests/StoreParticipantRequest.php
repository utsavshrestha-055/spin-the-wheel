<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreParticipantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:120'], 'username' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:participants,username'], 'social_media' => ['nullable', 'string', 'max:80'], 'priority' => ['required', 'integer', 'min:0', 'max:4294967295']];
    }

    public function messages(): array
    {
        return ['username.regex' => 'The username field may only contain letters, numbers, dots, dashes, and underscores.'];
    }
}
