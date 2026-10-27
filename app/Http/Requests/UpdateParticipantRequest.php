<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateParticipantRequest extends FormRequest
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
        $participant = $this->route('participant');

        return ['name' => ['required', 'string', 'max:120'], 'username' => ['required', 'string', 'max:225', Rule::unique('participants', 'username')->ignore($participant->id)], 'social_media' => ['nullable', 'string', 'max:80'], 'priority' => ['required', 'integer', 'min:0', 'max:4294967295'], 'is_active' => ['required', 'boolean']];
    }
}
